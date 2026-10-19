<?php

declare(strict_types=1);

namespace Gcego\Cli\Generators;

use Gcego\Cli\Generation\GenerationContext;
use Gcego\Cli\Stubs\StubValue;

/**
 * gcego:make-workflow ContentApproval [--model=]: a gcego/workflows
 * approval workflow (draft → submitted → approved | rejected). Submitting
 * needs the subject policy's "update", deciding its "approve" ability, so no
 * transition is open to everyone.
 */
final class WorkflowGenerator extends AbstractGenerator
{
    public function id(): string
    {
        return 'workflow';
    }

    public function description(): string
    {
        return 'An approval workflow (gcego/workflows) with policy-authorized transitions';
    }

    public function requires(): array
    {
        return ['gcego/workflows'];
    }

    public function options(): array
    {
        return ['model' => 'The Eloquent subject (its policy authorizes transitions)', 'title' => 'Title'];
    }

    protected function files(GenerationContext $context): array
    {
        $name = $this->kit->names->slug($context->name);

        $this->assertAvailable('workflow:'.$name, $this->kit->paths->directory('workflow').'/'.$name.'.php');

        $imports = ['Gcego\\Workflows\\States\\State', 'Gcego\\Workflows\\Transitions\\Transition', 'Gcego\\Workflows\\Workflow'];
        $subject = '';
        $model = $this->model($context, false, '');

        if ($model !== null) {
            $imports[] = $model;
            $subject = sprintf("\n\n    subject: %s::class,", class_basename($model));
            $this->next(sprintf('Add update() and approve() to %s\'s policy: they decide who submits and who decides.', class_basename($model)));
        } else {
            $this->next('Run it on a model whose policy has update() and approve(): without them every transition is refused.');
        }

        return [$this->file($context, $this->kit->paths->directory('workflow').'/'.$name.'.php', 'workflow', [
            'imports' => $this->imports($imports),
            'name' => StubValue::string($name),
            'title' => StubValue::string($context->string('title') ?? $this->kit->names->title($context->name)),
            'subject' => StubValue::code($subject),
        ], 'Workflow "'.$name.'"')];
    }

    protected function test(GenerationContext $context): array
    {
        return ['workflow', $this->kit->names->slug($context->name)];
    }
}
