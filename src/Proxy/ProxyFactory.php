<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Proxy;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Redking\ParseBundle\Configuration;
use Redking\ParseBundle\Exception\ParseObjectNotFoundException;
use Redking\ParseBundle\ObjectManager;
use Redking\ParseBundle\Persisters\ObjectPersister;
use Redking\ParseBundle\UnitOfWork;
use ReflectionClass;
use Symfony\Component\VarExporter\ProxyHelper;

use function chmod;
use function class_exists;
use function dirname;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function property_exists;
use function rename;
use function sprintf;
use function str_replace;
use function tempnam;

/**
 * Creates lazy ghost proxies for managed objects.
 *
 * Two backends are supported:
 *  - symfony/var-exporter ghost classes generated via {@see ProxyHelper::generateLazyGhost()}
 *    (default for PHP 8.1+).
 *  - PHP 8.4 native lazy ghosts via {@see ReflectionClass::newLazyGhost()} when
 *    {@see Configuration::isLazyGhostObjectEnabled()} is true.
 */
final class ProxyFactory
{
    private const MARKER = '__CG__';

    private UnitOfWork $uow;

    /** @var array<class-string, class-string> */
    private array $proxyClassNames = [];

    public function __construct(
        private ObjectManager $om,
        private ?string $proxyDir,
        private string $proxyNs,
        private int $autoGenerate = Configuration::AUTOGENERATE_FILE_NOT_EXISTS,
        private bool $useLazyGhostObject = false,
    ) {
        $this->uow = $om->getUnitOfWork();
    }

    /**
     * Returns a lazy ghost for the given class, with the identifier eagerly set.
     *
     * @param array<string, mixed> $identifier
     */
    public function getProxy(string $className, array $identifier): object
    {
        $classMetadata   = $this->om->getClassMetadata($className);
        $objectPersister = $this->uow->getObjectPersister($className);

        $initializer = $this->createInitializer($classMetadata, $objectPersister);

        $idField     = $classMetadata->identifier;
        $idReflField = $classMetadata->reflFields[$idField] ?? null;

        // Mark the identifier property as already-initialized so reading it does
        // not trigger the initializer (and writing it below does not either).
        $skippedProperties = [];
        if ($idReflField !== null) {
            $skippedProperties[self::propertyArrayKey($idReflField)] = true;
        }

        $proxy = $this->createLazyGhost($classMetadata, $initializer, $skippedProperties);

        if ($idReflField !== null && isset($identifier[$idField])) {
            if ($this->useLazyGhostObject && method_exists($idReflField, 'setRawValueWithoutLazyInitialization')) {
                $idReflField->setRawValueWithoutLazyInitialization($proxy, $identifier[$idField]);
            } else {
                $idReflField->setValue($proxy, $identifier[$idField]);
            }
        }

        return $proxy;
    }

    /**
     * Creates a lazy proxy for an inverse ReferenceOne (mappedBy) association.
     * The query is deferred until the first property access on the proxy.
     */
    public function getLazyReferenceOneProxy(
        string $targetClass,
        string $mappedBy,
        object $owner,
        \ReflectionProperty $ownerField,
    ): object {
        $classMetadata   = $this->om->getClassMetadata($targetClass);
        $objectPersister = $this->uow->getObjectPersister($targetClass);

        $initializer = static function (object $proxy) use ($objectPersister, $classMetadata, $mappedBy, $owner, $ownerField): void {
            $loadedObject = $objectPersister->loadReference($mappedBy, $owner);

            if (null !== $loadedObject) {
                foreach ($classMetadata->reflFields as $refProp) {
                    $refProp->setAccessible(true);
                    $refProp->setValue($proxy, $refProp->getValue($loadedObject));
                }
            } else {
                $ownerField->setValue($owner, null);
            }
        };

        return $this->createLazyGhost($classMetadata, $initializer, []);
    }

    /**
     * Generates proxy class files on disk for the given metadata set.
     *
     * Returns the number of generated files (0 when native lazy objects are
     * enabled, since no codegen is needed).
     *
     * @param ClassMetadata[] $classes
     */
    public function generateProxyClasses(array $classes): int
    {
        if ($this->useLazyGhostObject) {
            return 0;
        }

        if (null === $this->proxyDir) {
            return 0;
        }

        $generated = 0;
        foreach ($classes as $class) {
            if ($this->skipClass($class)) {
                continue;
            }

            $proxyFile = $this->proxyDir . '/' . self::generateProxyFileName($class->getName()) . '.php';

            $this->generateProxyFile($class->getName(), $proxyFile);
            $generated++;
        }

        return $generated;
    }

    /**
     * Creates the initializer closure used for identifier-based proxies.
     */
    private function createInitializer(ClassMetadata $classMetadata, ObjectPersister $objectPersister): \Closure
    {
        return static function (object $proxy) use ($objectPersister, $classMetadata): void {
            $identifier = $classMetadata->getIdentifierValues($proxy);

            $loaded = $objectPersister->load($identifier, $proxy, null, ['doctrine.refresh' => true]);

            if (null === $loaded) {
                throw ParseObjectNotFoundException::objectNotFound($classMetadata->getName(), $identifier);
            }
        };
    }

    /**
     * @param array<string, true> $skippedProperties Lazy properties to mark as already-initialized
     *                                                so they can be read/written without triggering
     *                                                the initializer. Keys use the array-cast format
     *                                                "\0<DeclaringClass>\0<PropertyName>".
     */
    private function createLazyGhost(ClassMetadata $classMetadata, \Closure $initializer, array $skippedProperties): object
    {
        $className = $classMetadata->getName();

        if ($this->useLazyGhostObject) {
            $proxy = (new ReflectionClass($className))->newLazyGhost($initializer);

            // PHP 8.4 native: mark the skipped properties via ReflectionProperty::skipLazyInitialization.
            // Skipped properties are indexed using PHP's array-cast format, but native lazy objects
            // need the property's actual declaring class to instantiate ReflectionProperty.
            if ($skippedProperties !== [] && method_exists(\ReflectionProperty::class, 'skipLazyInitialization')) {
                foreach ($classMetadata->reflFields as $refProp) {
                    if (! $refProp instanceof \ReflectionProperty) {
                        continue;
                    }
                    if (isset($skippedProperties[self::propertyArrayKey($refProp)])) {
                        $refProp->skipLazyInitialization($proxy);
                    }
                }
            }

            return $proxy;
        }

        $proxyClass = $this->getProxyClass($className);

        return $proxyClass::createLazyGhost($initializer, $skippedProperties);
    }

    /**
     * @return class-string
     */
    private function getProxyClass(string $className): string
    {
        if (isset($this->proxyClassNames[$className])) {
            return $this->proxyClassNames[$className];
        }

        $proxyClassName = self::generateProxyClassName($className, $this->proxyNs);

        if (class_exists($proxyClassName, false)) {
            return $this->proxyClassNames[$className] = $proxyClassName;
        }

        if (null !== $this->proxyDir && $this->autoGenerate !== Configuration::AUTOGENERATE_EVAL) {
            $proxyFile = $this->proxyDir . '/' . self::generateProxyFileName($className) . '.php';

            switch ($this->autoGenerate) {
                case Configuration::AUTOGENERATE_NEVER:
                    require $proxyFile;
                    break;

                case Configuration::AUTOGENERATE_FILE_NOT_EXISTS:
                    if (! file_exists($proxyFile)) {
                        $this->generateProxyFile($className, $proxyFile);
                    }
                    require $proxyFile;
                    break;

                case Configuration::AUTOGENERATE_ALWAYS:
                    $this->generateProxyFile($className, $proxyFile);
                    require $proxyFile;
                    break;
            }
        } else {
            eval($this->generateProxyCode($className));
        }

        return $this->proxyClassNames[$className] = $proxyClassName;
    }

    private function generateProxyFile(string $className, string $proxyFile): void
    {
        $code = "<?php\n\n" . $this->generateProxyCode($className);

        $dir = dirname($proxyFile);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $tmpFile = tempnam($dir, 'proxy_');
        if (false === $tmpFile) {
            file_put_contents($proxyFile, $code);

            return;
        }

        file_put_contents($tmpFile, $code);
        @chmod($tmpFile, 0664);
        rename($tmpFile, $proxyFile);
    }

    private function generateProxyCode(string $className): string
    {
        $proxyClassName = self::generateProxyClassName($className, $this->proxyNs);
        $proxyNamespace = substr($proxyClassName, 0, strrpos($proxyClassName, '\\'));
        $proxyShortName = substr($proxyClassName, strrpos($proxyClassName, '\\') + 1);

        $ghostBody = ProxyHelper::generateLazyGhost(new ReflectionClass($className));
        $ghostBody = self::injectDoctrinePersistenceProxy($ghostBody);

        return sprintf("namespace %s;\n\nclass %s%s", $proxyNamespace, $proxyShortName, $ghostBody);
    }

    /**
     * Adapts the var-exporter ghost body so the generated class also implements
     * \Doctrine\Persistence\Proxy. The Symfony Bridge ManagerRegistry uses this
     * interface as the marker to strip the proxy prefix in getManagerForClass().
     *
     * Also exposes setLazyObjectAsInitialized() as the public __setInitialized()
     * method expected by Doctrine\Persistence\Reflection\RuntimeReflectionProperty,
     * which otherwise silently no-ops when writing to a Proxy whose
     * __isInitialized() returns false.
     */
    private static function injectDoctrinePersistenceProxy(string $ghostBody): string
    {
        $withInterface = str_replace(
            'implements \Symfony\Component\VarExporter\LazyObjectInterface',
            'implements \Symfony\Component\VarExporter\LazyObjectInterface, \Doctrine\Persistence\Proxy',
            $ghostBody,
        );

        $withTraitAlias = str_replace(
            'use \Symfony\Component\VarExporter\LazyGhostTrait;',
            "use \Symfony\Component\VarExporter\LazyGhostTrait {\n"
                . "        setLazyObjectAsInitialized as public __setInitialized;\n"
                . '    }',
            $withInterface,
        );

        $stubs = <<<'PHP'

    public function __load(): void
    {
        $this->initializeLazyObject();
    }

    public function __isInitialized(): bool
    {
        return isset($this->lazyObjectState) && $this->isLazyObjectInitialized();
    }

PHP;

        // Insert the stubs just before the closing brace of the class body
        // (which is followed by a blank line and the opcache.preload hints).
        return str_replace("}\n\n// Help opcache.preload", $stubs . "}\n\n// Help opcache.preload", $withTraitAlias);
    }

    /**
     * Builds the fully-qualified proxy class name in the form
     * "{proxyNs}\\__CG__\\{OriginalFqcn}", which matches the default
     * {@see \Doctrine\Persistence\Mapping\ProxyClassNameResolver} pattern.
     */
    private static function generateProxyClassName(string $className, string $proxyNs): string
    {
        return rtrim($proxyNs, '\\') . '\\' . self::MARKER . '\\' . ltrim($className, '\\');
    }

    private static function generateProxyFileName(string $className): string
    {
        return self::MARKER . str_replace('\\', '_', $className);
    }

    /**
     * Builds the PHP array-cast key for a reflection property.
     * Public properties use the bare name; protected use "\0*\0name";
     * private use "\0DeclaringClass\0name".
     */
    private static function propertyArrayKey(\ReflectionProperty $property): string
    {
        if ($property->isPrivate()) {
            return "\0" . $property->getDeclaringClass()->getName() . "\0" . $property->getName();
        }

        if ($property->isProtected()) {
            return "\0*\0" . $property->getName();
        }

        return $property->getName();
    }

    private function skipClass(ClassMetadata $metadata): bool
    {
        $reflection = $metadata->getReflectionClass();

        if ($reflection->isAbstract() || $reflection->isFinal() || $reflection->isInternal()) {
            return true;
        }

        if (\property_exists($metadata, 'isMappedSuperclass') && $metadata->isMappedSuperclass) {
            return true;
        }

        if (\property_exists($metadata, 'isEmbeddedDocument') && $metadata->isEmbeddedDocument) {
            return true;
        }

        return false;
    }
}
