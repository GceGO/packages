<?php

declare(strict_types=1);

namespace Gcego\Cli\Commands\Make;

use Gcego\Cli\Commands\Concerns\MakeCommand;
use Gcego\Cli\Generators\TestGenerator;

/**
 * php artisan gcego:make-test SubjectResourceTest --type=resource --target=academics.subjects
 */
final class MakeTestCommand extends MakeCommand
{
    protected $description = 'Create a Pest or PHPUnit test using gcego/testing';

    public function __construct()
    {
        $this->signature = 'gcego:make-test {name : The name, e.g. SubjectResourceTest}
        {--type= : component, blueprint, form, table, action, resource, page, module, panel, workflow, settings, analytics, security, tenant}
        {--target= : What the test checks}'.self::Common;

        parent::__construct();
    }

    protected function generator(): string
    {
        return 'test';
    }

    protected function prompts(): array
    {
        return [
            'type' => fn (): ?string => $this->testType(),
            'target' => function (): ?string {
                $answer = $this->ask('What should the test check (see --type)?');

                return is_string($answer) ? $answer : null;
            },
        ];
    }

    private function testType(): ?string
    {
        $answer = $this->choice('Which kind of test?', array_keys(TestGenerator::Types), 'module');

        return is_string($answer) ? $answer : null;
    }
}
