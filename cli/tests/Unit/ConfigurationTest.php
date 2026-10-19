<?php

declare(strict_types=1);

use Gcego\Cli\CliConfig;
use Gcego\Cli\CliManager;
use Gcego\Cli\Contracts\DiagnosticCheck;
use Gcego\Cli\Contracts\Generator;
use Gcego\Cli\Diagnostics\DiagnosticResult;
use Gcego\Cli\Exceptions\ExtensionException;
use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Generation\GenerationPlan;

it('has safe defaults and validates its configuration', function (): void {
    $config = app(CliConfig::class);

    expect($config->enabled)->toBeTrue()->and($config->generateTests)->toBeFalse()->and($config->limit)->toBe(200)
        ->and(CliConfig::fromArray([])->paths)->toBe([])
        ->and(fn () => CliConfig::fromArray(['paths' => ['modules' => 'x']]))->toThrow(InvalidArgumentException::class, 'not a known kind')
        ->and(fn () => CliConfig::fromArray(['paths' => ['page' => '../outside']]))->toThrow(InvalidArgumentException::class, 'without ".."')
        ->and(fn () => CliConfig::fromArray(['inspection' => ['limit' => 0]]))->toThrow(InvalidArgumentException::class, 'between 1 and 10000')
        ->and(fn () => CliConfig::fromArray(['generators' => ['custom' => [stdClass::class]]]))->toThrow(InvalidArgumentException::class, 'does not implement')
        ->and(fn () => CliConfig::fromArray(['diagnostics' => ['enabled' => 'yes']]))->toThrow(InvalidArgumentException::class, 'true or false');
});

final class ReportCardGenerator implements Generator
{
    public function __construct(private string $name = 'report-card') {}

    public function id(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return 'Report cards';
    }

    public function requires(): array
    {
        return [];
    }

    public function options(): array
    {
        return ['term' => 'Term'];
    }

    public function plan(GenerationContext $context): GenerationPlan
    {
        return new GenerationPlan($this->id(), []);
    }
}

final class QueueCheck implements DiagnosticCheck
{
    public function id(): string
    {
        return 'acme.queue';
    }

    public function label(): string
    {
        return 'Queue workers';
    }

    public function usesDatabase(): bool
    {
        return false;
    }

    public function run(): DiagnosticResult
    {
        return DiagnosticResult::pass($this->id(), $this->label(), 'Workers are running.');
    }
}

it('registers custom generators and checks, refusing collisions', function (): void {
    $cli = app(CliManager::class);
    $cli->generator(ReportCardGenerator::class);
    $cli->generator(ReportCardGenerator::class);   // the same class again is fine
    $cli->check(QueueCheck::class);

    expect($cli->resolve('report-card'))->toBeInstanceOf(ReportCardGenerator::class)->and($cli->isCustom('report-card'))->toBeTrue()
        ->and(array_keys($cli->generators()))->toContain('resource', 'report-card')
        ->and(fn () => $cli->generator(new ReportCardGenerator('resource')))->toThrow(ExtensionException::class, 'built-in generator')
        ->and(fn () => $cli->generator(new ReportCardGenerator('Bad Id')))->toThrow(ExtensionException::class, 'invalid id')
        ->and(fn () => $cli->generator(stdClass::class))->toThrow(ExtensionException::class, 'does not implement')
        ->and(fn () => $cli->check(new class implements DiagnosticCheck
        {
            public function id(): string
            {
                return 'definitions.valid';
            }

            public function label(): string
            {
                return 'x';
            }

            public function usesDatabase(): bool
            {
                return false;
            }

            public function run(): DiagnosticResult
            {
                return DiagnosticResult::pass('x', 'x', 'x');
            }
        }))->toThrow(ExtensionException::class, 'already taken');

    [$code, $output] = gcego('gcego:make', ['--list' => true]);
    expect($code)->toBe(0)->and($output)->toContain('report-card')->toContain('(custom)');
});
