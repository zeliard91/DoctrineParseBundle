<?php

namespace Redking\ParseBundle\Tests\Functional;

use Parse\ParseException;
use Parse\ParseSchema;
use Redking\ParseBundle\Command\CreateSchemaDoctrineParseCommand;
use Redking\ParseBundle\Command\DropSchemaDoctrineParseCommand;
use Redking\ParseBundle\Command\UpdateSchemaDoctrineParseCommand;
use Redking\ParseBundle\Registry;
use Redking\ParseBundle\Tests\Models\Blog\IndexedArticle;
use Redking\ParseBundle\Tests\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The doctrine:parse:schema:* commands, run against a real Parse Server.
 */
class SchemaCommandTest extends TestCase
{
    private const COLLECTION = 'blog_indexed_article';

    public function setUp(): void
    {
        parent::setUp();

        $this->dropTestClass();
    }

    public function tearDown(): void
    {
        $this->dropTestClass();

        parent::tearDown();
    }

    private function dropTestClass(): void
    {
        try {
            (new ParseSchema(self::COLLECTION))->purge();
            (new ParseSchema(self::COLLECTION))->delete();
        } catch (ParseException $e) {
            // The class does not exist yet.
        }
    }

    private function tester(string $commandClass): CommandTester
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('getManager')->willReturn($this->om);

        $command = new $commandClass($registry);
        assert($command instanceof Command);

        return new CommandTester($command);
    }

    private function runCommand(string $commandClass, array $input = []): CommandTester
    {
        $tester = $this->tester($commandClass);
        $tester->execute($input + ['--class' => [IndexedArticle::class]]);

        return $tester;
    }

    public function testCreateReportsTheResolvedParseColumnNames()
    {
        $tester = $this->runCommand(CreateSchemaDoctrineParseCommand::class);

        $this->assertSame(0, $tester->getStatusCode());

        $display = $tester->getDisplay();

        // The resolved column names are what makes the output comparable with the
        // indexes Parse actually holds.
        $this->assertStringContainsString('+ index title_author {title: 1, _p_author: 1}', $display);
        $this->assertStringContainsString('+ index hits_-1 {hits: -1}', $display);
        $this->assertStringContainsString('+ field author (Pointer)', $display);
        $this->assertArrayHasKey('title_author', (new ParseSchema(self::COLLECTION))->get()['indexes']);
    }

    public function testDryRunAnnouncesItselfAndWritesNothing()
    {
        $tester = $this->runCommand(CreateSchemaDoctrineParseCommand::class, ['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Dry run', $tester->getDisplay());
        $this->assertStringContainsString('+ index title_1 {title: 1}', $tester->getDisplay());

        $this->expectException(ParseException::class);
        (new ParseSchema(self::COLLECTION))->get();
    }

    public function testNoFieldsSkipsTheFieldCreationAndFailsOnAnUnknownColumn()
    {
        $tester = $this->runCommand(CreateSchemaDoctrineParseCommand::class, ['--no-fields' => true]);

        // Parse refuses an index on a column it does not know about.
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('[FAIL]', $tester->getDisplay());
    }

    public function testUpdateIsIdempotentAndDropRemovesTheIndexes()
    {
        $this->runCommand(CreateSchemaDoctrineParseCommand::class);

        $tester = $this->runCommand(UpdateSchemaDoctrineParseCommand::class);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('0 created, 0 dropped, 3 unchanged', $tester->getDisplay());

        $tester = $this->runCommand(DropSchemaDoctrineParseCommand::class);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('0 created, 3 dropped', $tester->getDisplay());
        $this->assertSame([], (new ParseSchema(self::COLLECTION))->get()['indexes'] ?? []);
    }
}
