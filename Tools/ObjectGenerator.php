<?php

namespace Redking\ParseBundle\Tools;

use Doctrine\Common\Inflector\Inflector;
use Redking\ParseBundle\Mapping\ClassMetadata;
use Redking\ParseBundle\Types\Type;

/**
 * Generic class used to generate PHP5 document classes from ClassMetadata instances.
 *
 *     [php]
 *     $classes = $dm->getClassMetadataFactory()->getAllMetadata();
 *
 *     $generator = new \Doctrine\ODM\MongoDB\Tools\DocumentGenerator();
 *     $generator->setGenerateAnnotations(true);
 *     $generator->setGenerateStubMethods(true);
 *     $generator->setregenerateObjectIfExists(false);
 *     $generator->setupdateObjectIfExists(true);
 *     $generator->generate($classes, '/path/to/generate/documents');
 *
 * @since   1.0
 *
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 */
class ObjectGenerator
{
    /**
     * @var bool
     */
    private $backupExisting = true;

    /** The extension to use for written php files */
    private $extension = '.php';

    /** Whether or not the current ClassMetadata instance is new or old */
    private $isNew = true;

    private $staticReflection = array();

    /** Number of spaces to use for indention in generated code */
    private $numSpaces = 4;

    /** The actual spaces to use for indention */
    private $spaces = '    ';

    /** The class all generated documents should extend */
    private $classToExtend;

    /** Interfaces the generated object have to implement */
    private $interfacesToImplement = [];

    /** Whether or not to generate annotations */
    private $generateAnnotations = false;

    /** Whether or not to generate stub methods */
    private $generateObjectStubMethods = false;

    /** Whether or not to update the document class if it exists already */
    private $updateObjectIfExists = false;

    /** Whether or not to re-generate document class if it exists already */
    private $regenerateObjectIfExists = false;

    /** Attribute used in the __toString method */
    private $toStringField = null;

    protected $typeAlias = array(
        Type::DATE => '\DateTime',
        Type::GEOPOINT => '\Parse\ParseGeoPoint',
        Type::FILE => '\Symfony\Component\HttpFoundation\File\File',
        Type::TOBJECT => 'array',
        Type::HASH => 'array',
    );

    private static $classTemplate =
    '<?php

<namespace>

<imports>

<documentAnnotation>
<documentClassName>
{
<documentBody>
}';

    private static $getMethodTemplate =
    '/**
 * <description>
 *
 * @return <variableType>$<variableName>
 */
public function <methodName>()
{
<spaces>return $this-><fieldName>;
}';

    private static $setMethodTemplate =
    '/**
 * <description>
 *
 * @param <variableType>$<variableName>
 * @return self
 */
public function <methodName>(<methodTypeHint>$<variableName><variableDefault>)
{
<spaces>$this-><fieldName> = $<variableName>;
<spaces>return $this;
}';

    private static $addMethodTemplate =
    '/**
 * <description>
 *
 * @param <variableType>$<variableName>
 */
public function <methodName>(<methodTypeHint>$<variableName>)
{
<spaces>$this-><fieldName>[] = $<variableName>;
}';

    private static $removeMethodTemplate =
    '/**
 * <description>
 *
 * @param <variableType>$<variableName>
 */
public function <methodName>(<methodTypeHint>$<variableName>)
{
<spaces>$this-><fieldName>->removeElement($<variableName>);
}';

    private static $lifecycleCallbackMethodTemplate =
    '<comment>
public function <methodName>()
{
<spaces>// Add your code here
}';

    private static $constructorMethodTemplate =
    'public function __construct()
{
<collections>
}
';

    private static $toStringMethodTemplate =
    'public function __toString()
{
<spaces>return <toStringCall>."";
}
';

    /**
     * Generate and write document classes for the given array of ClassMetadata instances.
     *
     * @param array  $metadatas
     * @param string $outputDirectory
     */
    public function generate(array $metadatas, $outputDirectory)
    {
        foreach ($metadatas as $metadata) {
            $this->writeObjectClass($metadata, $outputDirectory);
        }
    }

    /**
     * Generated and write document class to disk for the given ClassMetadata instance.
     *
     * @param ClassMetadata $metadata
     * @param string        $outputDirectory
     *
     * @throws \RuntimeException
     */
    public function writeObjectClass(ClassMetadata $metadata, $outputDirectory)
    {
        $path = $outputDirectory.'/'.str_replace('\\', DIRECTORY_SEPARATOR, $metadata->name).$this->extension;
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $this->isNew = !file_exists($path) || (file_exists($path) && $this->regenerateObjectIfExists);

        if (!$this->isNew) {
            $this->parseTokensInObjectFile($path);
        }

        if ($this->backupExisting && file_exists($path)) {
            $backupPath = dirname($path).DIRECTORY_SEPARATOR.basename($path).'~';
            if (!copy($path, $backupPath)) {
                throw new \RuntimeException('Attempt to backup overwritten document file but copy operation failed.');
            }
        }

        // If document doesn't exist or we're re-generating the documents entirely
        if ($this->isNew) {
            file_put_contents($path, $this->generateObjectClass($metadata));

        // If document exists and we're allowed to update the document class
        } elseif (!$this->isNew && $this->updateObjectIfExists) {
            file_put_contents($path, $this->generateUpdatedObjectClass($metadata, $path));
        }
        chmod($path, 0664);
    }

    /**
     * Generate a PHP5 Doctrine 2 document class from the given ClassMetadata instance.
     *
     * @param ClassMetadata $metadata
     *
     * @return string $code
     */
    public function generateObjectClass(ClassMetadata $metadata, $toStringField = null)
    {
        $this->toStringField = $toStringField;

        $placeHolders = array(
            '<namespace>',
            '<imports>',
            '<documentAnnotation>',
            '<documentClassName>',
            '<documentBody>',
        );

        $replacements = array(
            $this->generateObjectNamespace($metadata),
            $this->generateObjectImports($metadata),
            $this->generateObjectDocBlock($metadata),
            $this->generateObjectClassName($metadata),
            $this->generateObjectBody($metadata),
        );

        $code = str_replace($placeHolders, $replacements, self::$classTemplate);

        return str_replace('<spaces>', $this->spaces, $code);
    }

    /**
     * Generate the updated code for the given ClassMetadata and document at path.
     *
     * @param ClassMetadata $metadata
     * @param string        $path
     *
     * @return string $code;
     */
    public function generateUpdatedObjectClass(ClassMetadata $metadata, $path, $toStringField = null)
    {
        $this->toStringField = $toStringField;

        $currentCode = file_get_contents($path);

        $body = $this->generateObjectBody($metadata);
        $body = str_replace('<spaces>', $this->spaces, $body);
        $last = strrpos($currentCode, '}');

        return substr($currentCode, 0, $last).$body.(strlen($body) > 0 ? "\n" : '')."}\n";
    }

    /**
     * Set the number of spaces the exported class should have.
     *
     * @param int $numSpaces
     */
    public function setNumSpaces($numSpaces)
    {
        $this->spaces = str_repeat(' ', $numSpaces);
        $this->numSpaces = $numSpaces;
    }

    /**
     * Set the extension to use when writing php files to disk.
     *
     * @param string $extension
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;
    }

    /**
     * Set the name of the class the generated classes should extend from.
     */
    public function setClassToExtend($classToExtend)
    {
        $this->classToExtend = $classToExtend;
    }

    /**
     * Set whether or not to generate annotations for the document.
     *
     * @param bool $bool
     */
    public function setGenerateAnnotations($bool)
    {
        $this->generateAnnotations = $bool;
    }

    /**
     * Set whether or not to try and update the document if it already exists.
     *
     * @param bool $bool
     */
    public function setupdateObjectIfExists($bool)
    {
        $this->updateObjectIfExists = $bool;
    }

    /**
     * Set whether or not to regenerate the document if it exists.
     *
     * @param bool $bool
     */
    public function setregenerateObjectIfExists($bool)
    {
        $this->regenerateObjectIfExists = $bool;
    }

    /**
     * Set whether or not to generate stub methods for the document.
     *
     * @param bool $bool
     */
    public function setGenerateStubMethods($bool)
    {
        $this->generateObjectStubMethods = $bool;
    }

    /**
     * Should an existing document be backed up if it already exists?
     */
    public function setBackupExisting($bool)
    {
        $this->backupExisting = $bool;
    }

    /**
     * @param array $interfaces
     */
    public function setInterfacesToImplement(array $interfaces)
    {
        $this->interfacesToImplement = $interfaces;
    }

    /**
     * @param string $interface
     */
    public function addInterfaceToImplement($interface)
    {
        $this->interfacesToImplement[] = $interface;
    }

    /**
     * @return array
     */
    public function getInterfacesToImplement()
    {
        return $this->interfacesToImplement;
    }

    /**
     * @return boolean
     */
    public function hasInterfacesToImplement()
    {
        return count($this->interfacesToImplement) > 0;
    }

    /**
     * @param string $type
     *
     * @return string
     */
    protected function getType($type)
    {
        if (isset($this->typeAlias[$type])) {
            return $this->typeAlias[$type];
        }

        return $type;
    }

    private function generateObjectNamespace(ClassMetadata $metadata)
    {
        if ($this->hasNamespace($metadata)) {
            return 'namespace '.$this->getNamespace($metadata).';';
        }
    }

    private function generateObjectClassName(ClassMetadata $metadata)
    {
        return 'class '.$this->getClassName($metadata).
            ($this->extendsClass() ? ' extends '.$this->getClassToExtendName() : null).
            ($this->hasInterfacesToImplement() ? ' implements '.implode(', ', $this->getInterfacesToImplement()) : null )
        ;
    }

    private function generateObjectBody(ClassMetadata $metadata)
    {
        $fieldMappingProperties = $this->generateObjectFieldMappingProperties($metadata);
        $associationMappingProperties = $this->generateObjectAssociationMappingProperties($metadata);
        $stubMethods = $this->generateObjectStubMethods ? $this->generateObjectStubMethods($metadata) : null;
        $lifecycleCallbackMethods = $this->generateDocumentLifecycleCallbackMethods($metadata);
        $traits = $this->generateTraits($metadata);

        $code = array();

        if ($traits) {
            $code[] = $traits;
        }

        if ($fieldMappingProperties) {
            $code[] = $fieldMappingProperties;
        }

        if ($associationMappingProperties) {
            $code[] = $associationMappingProperties;
        }

        $code[] = $this->generateObjectConstructor($metadata);

        if (null !== $this->toStringField) {
            $code[] = $this->generateToStringMethod($metadata);
        }

        if ($stubMethods) {
            $code[] = $stubMethods;
        }

        if ($lifecycleCallbackMethods) {
            $code[] = "\n".$lifecycleCallbackMethods;
        }

        return implode("\n", $code);
    }

    private function generateTraits(ClassMetadata $metadata)
    {
        $existing_traits = array_keys($this->getTraits($metadata));

        $traits = [];
        if (!in_array('Redking\\ParseBundle\\ObjectTrait', $existing_traits) && !in_array('Redking\\ParseBundle\\ACLTrait', $existing_traits)) {
            $traits[] = $this->spaces.'use \Redking\ParseBundle\ACLTrait;'."\n";
        }

        return implode("\n\n", $traits);
    }

    private function generateObjectConstructor(ClassMetadata $metadata)
    {
        if ($this->hasMethod('__construct', $metadata)) {
            return '';
        }

        $collections = array();
        foreach ($metadata->fieldMappings as $mapping) {
            if ($mapping['type'] === ClassMetadata::MANY) {
                $collections[] = '$this->'.$mapping['fieldName'].' = new \Doctrine\Common\Collections\ArrayCollection();';
            }
        }
        if ($collections) {
            return $this->prefixCodeWithSpaces(str_replace('<collections>', $this->spaces.implode("\n".$this->spaces, $collections), self::$constructorMethodTemplate));
        }

        return '';
    }

    /**
     * @todo this won't work if there is a namespace in brackets and a class outside of it.
     *
     * @param string $path
     */
    private function parseTokensInObjectFile($path)
    {
        $tokens = token_get_all(file_get_contents($path));
        $lastSeenNamespace = '';
        $lastSeenClass = false;

        for ($i = 0; $i < count($tokens); ++$i) {
            $token = $tokens[$i];
            if ($token[0] == T_NAMESPACE) {
                $peek = $i;
                $lastSeenNamespace = '';
                while (isset($tokens[++$peek])) {
                    if (';' == $tokens[$peek]) {
                        break;
                    } elseif (is_array($tokens[$peek]) && in_array($tokens[$peek][0], array(T_STRING, T_NS_SEPARATOR))) {
                        $lastSeenNamespace .= $tokens[$peek][1];
                    }
                }
            } elseif ($token[0] == T_CLASS) {
                $lastSeenClass = $lastSeenNamespace.'\\'.$tokens[$i + 2][1];
                $this->staticReflection[$lastSeenClass]['properties'] = array();
                $this->staticReflection[$lastSeenClass]['methods'] = array();
            } elseif ($token[0] == T_FUNCTION) {
                if ($tokens[$i + 2][0] == T_STRING) {
                    $this->staticReflection[$lastSeenClass]['methods'][] = $tokens[$i + 2][1];
                } elseif ($tokens[$i + 2][0] == '&' && $tokens[$i + 3][0] == T_STRING) {
                    $this->staticReflection[$lastSeenClass]['methods'][] = $tokens[$i + 3][1];
                }
            } elseif (in_array($token[0], array(T_VAR, T_PUBLIC, T_PRIVATE, T_PROTECTED)) && $tokens[$i + 2][0] != T_FUNCTION) {
                $this->staticReflection[$lastSeenClass]['properties'][] = substr($tokens[$i + 2][1], 1);
            }
        }
    }

    private function hasProperty($property, ClassMetadata $metadata)
    {
        if ($this->extendsClass() || class_exists($metadata->name)) {
            // don't generate property if its already on the base class.
            $reflClass = new \ReflectionClass($this->getClassToExtend() ?: $metadata->name);

            if ($reflClass->hasProperty($property)) {
                return true;
            }
        }

        foreach ($this->getTraits($metadata) as $trait) {
            if ($trait->hasProperty($property)) {
                return true;
            }
        }

        return
            isset($this->staticReflection[$metadata->name]) &&
            in_array($property, $this->staticReflection[$metadata->name]['properties'])
        ;
    }

    private function hasMethod($method, ClassMetadata $metadata)
    {
        if ($this->extendsClass() || class_exists($metadata->name)) {
            // don't generate method if its already on the base class.
            $reflClass = new \ReflectionClass($this->getClassToExtend() ?: $metadata->name);

            if ($reflClass->hasMethod($method)) {
                return true;
            }
        }

        foreach ($this->getTraits($metadata) as $trait) {
            if ($trait->hasMethod($method)) {
                return true;
            }
        }

        return
            isset($this->staticReflection[$metadata->name]) &&
            in_array($method, $this->staticReflection[$metadata->name]['methods'])
        ;
    }

    private function hasNamespace(ClassMetadata $metadata)
    {
        return strpos($metadata->name, '\\') ? true : false;
    }

    private function extendsClass()
    {
        return $this->classToExtend ? true : false;
    }

    private function getClassToExtend()
    {
        return $this->classToExtend;
    }

    private function getClassToExtendName()
    {
        $refl = new \ReflectionClass($this->getClassToExtend());

        return '\\'.$refl->getName();
    }

    private function getClassName(ClassMetadata $metadata)
    {
        return ($pos = strrpos($metadata->name, '\\'))
            ? substr($metadata->name, $pos + 1, strlen($metadata->name)) : $metadata->name;
    }

    private function getNamespace(ClassMetadata $metadata)
    {
        return substr($metadata->name, 0, strrpos($metadata->name, '\\'));
    }

    /**
     * @param ClassMetadata $metadata
     *
     * @return array
     */
    protected function getTraits(ClassMetadata $metadata)
    {
        if (PHP_VERSION_ID >= 50400 && ($metadata->reflClass !== null || class_exists($metadata->name))) {
            $reflClass = $metadata->reflClass === null ? new \ReflectionClass($metadata->name) : $metadata->reflClass;
            $traits = array();
            while ($reflClass !== false) {
                $traits = array_merge($traits, $reflClass->getTraits());
                $reflClass = $reflClass->getParentClass();
            }

            return $traits;
        }

        return array();
    }

    private function generateObjectImports(ClassMetadata $metadata)
    {
        if ($this->generateAnnotations) {
            return 'use Redking\\ParseBundle\\Mapping\\Annotations as ORM;';
        }
    }

    private function generateObjectDocBlock(ClassMetadata $metadata)
    {
        $lines = array();
        $lines[] = '/**';
        $lines[] = ' * '.$metadata->name;

        if ($this->generateAnnotations) {
            $lines[] = ' *';

            if ($metadata->isMappedSuperclass) {
                $lines[] = ' * @ORM\\MappedSuperclass';
            } else {
                $lines[] = ' * @ORM\\ParseObject';
            }

            $document = array();
            if (!$metadata->isMappedSuperclass && !$metadata->isEmbeddedDocument) {
                if ($metadata->collection) {
                    $document[] = ' *     collection="'.$metadata->collection.'"';
                }
                if ($metadata->customRepositoryClassName) {
                    $document[] = ' *     repositoryClass="'.$metadata->customRepositoryClassName.'"';
                }
            }

            if ($document) {
                $lines[count($lines) - 1] .= '(';
                $lines[] = implode(",\n", $document);
                $lines[] = ' * )';
            }

            if (!empty($metadata->lifecycleCallbacks)) {
                $lines[] = ' * @ORM\HasLifecycleCallbacks';
            }

            $methods = array(
                'generateInheritanceAnnotation',
                'generateDiscriminatorFieldAnnotation',
                'generateDiscriminatorMapAnnotation',
                'generateDefaultDiscriminatorValueAnnotation',
            );

            foreach ($methods as $method) {
                if ($code = $this->$method($metadata)) {
                    $lines[] = ' * '.$code;
                }
            }
        }

        $lines[] = ' */';

        return implode("\n", $lines);
    }

    private function generateInheritanceAnnotation(ClassMetadata $metadata)
    {
        if ($metadata->inheritanceType !== ClassMetadata::INHERITANCE_TYPE_NONE) {
            return '@ORM\\InheritanceType("'.$this->getInheritanceTypeString($metadata->inheritanceType).'")';
        }
    }

    private function generateDiscriminatorFieldAnnotation(ClassMetadata $metadata)
    {
        if ($metadata->inheritanceType === ClassMetadata::INHERITANCE_TYPE_SINGLE_COLLECTION) {
            return '@ORM\\DiscriminatorField(name="'.$metadata->discriminatorField.'")';
        }
    }

    private function generateDiscriminatorMapAnnotation(ClassMetadata $metadata)
    {
        if ($metadata->inheritanceType === ClassMetadata::INHERITANCE_TYPE_SINGLE_COLLECTION) {
            $inheritanceClassMap = array();

            foreach ($metadata->discriminatorMap as $type => $class) {
                $inheritanceClassMap[] .= '"'.$type.'" = "'.$class.'"';
            }

            return '@ORM\\DiscriminatorMap({'.implode(', ', $inheritanceClassMap).'})';
        }
    }

    private function generateDefaultDiscriminatorValueAnnotation(ClassMetadata $metadata)
    {
        if ($metadata->inheritanceType === ClassMetadata::INHERITANCE_TYPE_SINGLE_COLLECTION && isset($metadata->defaultDiscriminatorValue)) {
            return '@ORM\\DefaultDiscriminatorValue("'.$metadata->defaultDiscriminatorValue.'")';
        }
    }

    private function generateObjectStubMethods(ClassMetadata $metadata)
    {
        $methods = array();

        foreach ($metadata->fieldMappings as $fieldMapping) {
            if (isset($fieldMapping['id'])) {
                if ($code = $code = $this->generateObjectStubMethod($metadata, 'get', $fieldMapping['fieldName'], $fieldMapping['type'])) {
                    $methods[] = $code;
                }
            } elseif (!isset($fieldMapping['association'])) {
                if ($code = $code = $this->generateObjectStubMethod($metadata, 'set', $fieldMapping['fieldName'], $fieldMapping['type'])) {
                    $methods[] = $code;
                }
                if ($code = $code = $this->generateObjectStubMethod($metadata, 'get', $fieldMapping['fieldName'], $fieldMapping['type'])) {
                    $methods[] = $code;
                }
            } elseif ($fieldMapping['type'] === ClassMetadata::ONE) {
                $nullable = $this->isAssociationNullable($fieldMapping) ? 'null' : null;
                if ($code = $this->generateObjectStubMethod($metadata, 'set', $fieldMapping['fieldName'], isset($fieldMapping['targetDocument']) ? $fieldMapping['targetDocument'] : null, $nullable)) {
                    $methods[] = $code;
                }
                if ($code = $this->generateObjectStubMethod($metadata, 'get', $fieldMapping['fieldName'], isset($fieldMapping['targetDocument']) ? $fieldMapping['targetDocument'] : null)) {
                    $methods[] = $code;
                }
            } elseif ($fieldMapping['type'] === ClassMetadata::MANY) {
                if ($code = $this->generateObjectStubMethod($metadata, 'add', $fieldMapping['fieldName'], isset($fieldMapping['targetDocument']) ? $fieldMapping['targetDocument'] : null)) {
                    $methods[] = $code;
                }
                if ($code = $this->generateObjectStubMethod($metadata, 'remove', $fieldMapping['fieldName'], isset($fieldMapping['targetDocument']) ? $fieldMapping['targetDocument'] : null)) {
                    $methods[] = $code;
                }
                if ($code = $this->generateObjectStubMethod($metadata, 'get', $fieldMapping['fieldName'], '\Doctrine\Common\Collections\Collection')) {
                    $methods[] = $code;
                }
            }
        }

        return implode("\n\n", $methods);
    }

    /**
     * @param array $fieldMapping
     *
     * @return bool
     */
    protected function isAssociationNullable($fieldMapping)
    {
        return isset($fieldMapping['nullable']) && $fieldMapping['nullable'];
    }

    private function generateDocumentLifecycleCallbackMethods(ClassMetadata $metadata)
    {
        if (empty($metadata->lifecycleCallbacks)) {
            return '';
        }

        $methods = array();

        foreach ($metadata->lifecycleCallbacks as $event => $callbacks) {
            foreach ($callbacks as $callback) {
                if ($code = $this->generateLifecycleCallbackMethod($event, $callback, $metadata)) {
                    $methods[] = $code;
                }
            }
        }

        return implode("\n\n", $methods);
    }

    private function generateObjectAssociationMappingProperties(ClassMetadata $metadata)
    {
        $lines = array();

        foreach ($metadata->fieldMappings as $fieldMapping) {
            if ($this->hasProperty($fieldMapping['fieldName'], $metadata) ||
                $metadata->isInheritedField($fieldMapping['fieldName'])) {
                continue;
            }
            if (!isset($fieldMapping['association'])) {
                continue;
            }

            $lines[] = $this->generateAssociationMappingPropertyDocBlock($fieldMapping, $metadata);
            $lines[] = $this->spaces.'protected $'.$fieldMapping['fieldName']
                .($fieldMapping['type'] === ClassMetadata::MANY ? ' = array()' : null).";\n";
        }

        return implode("\n", $lines);
    }

    private function generateObjectFieldMappingProperties(ClassMetadata $metadata)
    {
        $lines = array();

        foreach ($metadata->fieldMappings as $fieldMapping) {
            if ($this->hasProperty($fieldMapping['fieldName'], $metadata) ||
                $metadata->isInheritedField($fieldMapping['fieldName'])) {
                continue;
            }
            if (isset($fieldMapping['association']) && $fieldMapping['association']) {
                continue;
            }

            $lines[] = $this->generateFieldMappingPropertyDocBlock($fieldMapping, $metadata);
            $lines[] = $this->spaces.'protected $'.$fieldMapping['fieldName']
                .(isset($fieldMapping['default']) ? ' = '.var_export($fieldMapping['default'], true) : null).";\n";
        }

        return implode("\n", $lines);
    }

    private function generateObjectStubMethod(ClassMetadata $metadata, $type, $fieldName, $typeHint = null, $defaultValue = null)
    {
        // Add/remove methods should use the singular form of the field name
        $formattedFieldName = in_array($type, array('add', 'remove'))
            ? Inflector::singularize($fieldName)
            : $fieldName;

        $methodName = $type.Inflector::classify($formattedFieldName);
        $variableName = Inflector::camelize($formattedFieldName);

        if ($this->hasMethod($methodName, $metadata)) {
            return;
        }

        $methodTypeHint = null;
        $types          = Type::getTypesMap();
        $variableType   = $typeHint ? $this->getType($typeHint) . ' ' : null;

        if ($typeHint && ! isset($types[$typeHint])) {
            $variableType   =  '\\' . ltrim($variableType, '\\');
            $methodTypeHint =  '\\' . $typeHint . ' ';
        }

        $replacements = array(
            '<description>' => ucfirst($type) . ' ' . $fieldName,
            '<methodTypeHint>' => $methodTypeHint,
            '<variableType>' => $variableType,
            '<variableName>' => $variableName,
            '<methodName>' => $methodName,
            '<fieldName>' => $fieldName,
            '<variableDefault>' => ($defaultValue !== null) ? (' = '.$defaultValue) : '',
        );

        $templateVar = sprintf('%sMethodTemplate', $type);

        $method = str_replace(
            array_keys($replacements),
            array_values($replacements),
            self::$$templateVar
        );

        return $this->prefixCodeWithSpaces($method);
    }

    private function generateToStringMethod(ClassMetadata $metadata)
    {
        if ($this->hasMethod('__toString', $metadata)) {
            return;
        }

        $replacements = array(
            '<toStringCall>' => $this->toStringField,
        );

        $method = str_replace(
            array_keys($replacements),
            array_values($replacements),
            self::$toStringMethodTemplate
        );

        return $this->prefixCodeWithSpaces($method);
    }

    private function generateLifecycleCallbackMethod($name, $methodName, ClassMetadata $metadata)
    {
        if ($this->hasMethod($methodName, $metadata)) {
            return;
        }

        $replacements = array(
            '<comment>' => $this->generateAnnotations ? '/** @ORM\\'.ucfirst($name).' */' : '',
            '<methodName>' => $methodName,
        );

        $method = str_replace(
            array_keys($replacements),
            array_values($replacements),
            self::$lifecycleCallbackMethodTemplate
        );

        return $this->prefixCodeWithSpaces($method);
    }

    private function generateAssociationMappingPropertyDocBlock(array $fieldMapping, ClassMetadata $metadata)
    {
        $lines = array();
        $lines[] = $this->spaces.'/**';
        $lines[] = $this->spaces.' * @var '.(isset($fieldMapping['targetDocument']) ? $fieldMapping['targetDocument'] : 'object');

        if ($this->generateAnnotations) {
            $lines[] = $this->spaces.' *';

            $type = null;
            switch ($fieldMapping['association']) {
                case ClassMetadata::REFERENCE_ONE:
                    $type = 'ReferenceOne';
                    break;
                case ClassMetadata::REFERENCE_MANY:
                    $type = 'ReferenceMany';
                    break;
            }
            $typeOptions = array();

            if (isset($fieldMapping['targetDocument'])) {
                $typeOptions[] = 'targetDocument="'.$fieldMapping['targetDocument'].'"';
            }

            if (isset($fieldMapping['cascade']) && $fieldMapping['cascade']) {
                $cascades = array();

                if ($fieldMapping['isCascadePersist']) {
                    $cascades[] = '"persist"';
                }
                if ($fieldMapping['isCascadeRemove']) {
                    $cascades[] = '"remove"';
                }
                if ($fieldMapping['isCascadeDetach']) {
                    $cascades[] = '"detach"';
                }
                if ($fieldMapping['isCascadeMerge']) {
                    $cascades[] = '"merge"';
                }
                if ($fieldMapping['isCascadeRefresh']) {
                    $cascades[] = '"refresh"';
                }

                $typeOptions[] = 'cascade={'.implode(',', $cascades).'}';
            }

            if (isset($fieldMapping['implementation'])) {
                $typeOptions[] = 'implementation="'.$fieldMapping['implementation'].'"';
            }

            if (isset($fieldMapping['inversedBy'])) {
                $typeOptions[] = 'inversedBy="'.$fieldMapping['inversedBy'].'"';
            }
            if (isset($fieldMapping['mappedBy'])) {
                $typeOptions[] = 'mappedBy="'.$fieldMapping['mappedBy'].'"';
            }
            if (isset($fieldMapping['orphanRemoval']) && $fieldMapping['orphanRemoval']) {
                $typeOptions[] = 'orphanRemoval=true';
            }

            $lines[] = $this->spaces.' * @ORM\\'.$type.'('.implode(', ', $typeOptions).')';
        }

        $lines[] = $this->spaces.' */';

        return implode("\n", $lines);
    }

    private function generateFieldMappingPropertyDocBlock(array $fieldMapping, ClassMetadata $metadata)
    {
        $lines = array();
        $lines[] = $this->spaces.'/**';
        $lines[] = $this->spaces.' * @var '.$this->getType($fieldMapping['type']).' $'.$fieldMapping['fieldName'];

        if ($this->generateAnnotations) {
            $lines[] = $this->spaces.' *';

            $field = array();
            if (isset($fieldMapping['id']) && $fieldMapping['id']) {
                if (isset($fieldMapping['strategy'])) {
                    $field[] = 'strategy="'.$this->getIdGeneratorTypeString($metadata->generatorType).'"';
                }
                $lines[] = $this->spaces.' * @ORM\\Id('.implode(', ', $field).')';
            } else {
                if (isset($fieldMapping['name'])) {
                    $field[] = 'name="'.$fieldMapping['name'].'"';
                }

                if (isset($fieldMapping['type'])) {
                    $field[] = 'type="'.$fieldMapping['type'].'"';
                }

                if (isset($fieldMapping['nullable']) && $fieldMapping['nullable'] === true) {
                    $field[] = 'nullable='.var_export($fieldMapping['nullable'], true);
                }
                if (isset($fieldMapping['options'])) {
                    $options = array();
                    foreach ($fieldMapping['options'] as $key => $value) {
                        $options[] = '"'.$key.'" = "'.$value.'"';
                    }
                    $field[] = 'options={'.implode(', ', $options).'}';
                }
                $lines[] = $this->spaces.' * @ORM\\Field('.implode(', ', $field).')';
            }

            if (isset($fieldMapping['version']) && $fieldMapping['version']) {
                $lines[] = $this->spaces.' * @ORM\\Version';
            }
        }

        $lines[] = $this->spaces.' */';

        return implode("\n", $lines);
    }

    private function prefixCodeWithSpaces($code, $num = 1)
    {
        $lines = explode("\n", $code);

        foreach ($lines as $key => $value) {
            $lines[$key] = str_repeat($this->spaces, $num).$lines[$key];
        }

        return implode("\n", $lines);
    }

    private function getInheritanceTypeString($type)
    {
        switch ($type) {
            case ClassMetadata::INHERITANCE_TYPE_NONE:
                return 'NONE';

            case ClassMetadata::INHERITANCE_TYPE_SINGLE_COLLECTION:
                return 'SINGLE_COLLECTION';

            case ClassMetadata::INHERITANCE_TYPE_COLLECTION_PER_CLASS:
                return 'COLLECTION_PER_CLASS';

            default:
                throw new \InvalidArgumentException('Invalid provided InheritanceType: '.$type);
        }
    }

    private function getIdGeneratorTypeString($type)
    {
        switch ($type) {
            case ClassMetadata::GENERATOR_TYPE_AUTO:
                return 'AUTO';

            case ClassMetadata::GENERATOR_TYPE_INCREMENT:
                return 'INCREMENT';

            case ClassMetadata::GENERATOR_TYPE_UUID:
                return 'UUID';

            case ClassMetadata::GENERATOR_TYPE_ALNUM:
                return 'ALNUM';

            case ClassMetadata::GENERATOR_TYPE_CUSTOM:
                return 'CUSTOM';

            case ClassMetadata::GENERATOR_TYPE_NONE:
                return 'NONE';

            default:
                throw new \InvalidArgumentException('Invalid provided IdGeneratorType: '.$type);
        }
    }
}
