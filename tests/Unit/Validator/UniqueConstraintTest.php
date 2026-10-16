<?php

declare(strict_types=1);

namespace Redking\ParseBundle\Tests\Unit\Validator;

use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Redking\ParseBundle\Validator\Constraints\Unique;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;

class UniqueConstraintTest extends TestCase
{
    public function testYamlMappingBuildsTheConstraintFromNamedArguments(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'unique').'.yaml';
        file_put_contents($file, sprintf(
            "%s:\n    constraints:\n        - %s: { fields: [email, accessCode], groups: [registration] }\n",
            UniqueConstraintSubject::class,
            Unique::class,
        ));

        try {
            $metadata = new ClassMetadata(UniqueConstraintSubject::class);
            (new YamlFileLoader($file))->loadClassMetadata($metadata);
        } finally {
            unlink($file);
        }

        $constraints = $metadata->getConstraints();
        $this->assertCount(1, $constraints);
        $this->assertInstanceOf(Unique::class, $constraints[0]);
        $this->assertSame(['email', 'accessCode'], $constraints[0]->fields);
        $this->assertSame(['registration'], $constraints[0]->groups);
    }

    public function testDefaultsTargetTheParseValidator(): void
    {
        $constraint = new Unique(fields: 'email');

        $this->assertSame('email', $constraint->fields);
        $this->assertSame('doctrine_parse.unique', $constraint->service);
        $this->assertSame('findByWithoutManaging', $constraint->repositoryMethod);
        $this->assertSame(['Default'], $constraint->groups);
    }

    public function testGroupsAndPayloadKeepTheirPlace(): void
    {
        $constraint = new Unique(fields: ['name'], groups: ['edit'], payload: 'severity');

        $this->assertSame(['edit'], $constraint->groups);
        $this->assertSame('severity', $constraint->payload);
    }

    #[IgnoreDeprecations]
    public function testLegacyOptionsArrayStillWorks(): void
    {
        $constraint = new Unique(['fields' => ['name'], 'message' => 'Taken.']);

        $this->assertSame(['name'], $constraint->fields);
        $this->assertSame('Taken.', $constraint->message);
        $this->assertSame('doctrine_parse.unique', $constraint->service);
    }
}

class UniqueConstraintSubject
{
}
