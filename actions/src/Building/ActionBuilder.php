<?php

declare(strict_types=1);

namespace Gcego\Actions\Building;

use Closure;
use Gcego\Actions\Configuration\Confirmation;
use Gcego\Actions\Configuration\Handler;
use Gcego\Actions\Configuration\Input;
use Gcego\Actions\Configuration\Lifecycle;
use Gcego\Actions\Configuration\Transaction;
use Gcego\Components\Building\ComponentBuilder;

/**
 * The fluent form of an action:
 *
 *     Create::define()
 *     BulkAction::define('mark-graduated', 'Mark as graduated')
 *         ->ability('update')
 *         ->icon('education.student')
 *         ->confirm('Mark the selected students as graduated?', 'Their status changes to Graduated.', 'Mark as graduated')
 *         ->handler(fn (ActionContext $context) => $context->model()->update([...]))
 *
 * Every action property is available by name (intent(), placement(),
 * order(), transaction(), policy() on bulk actions, and so on). confirm()
 * adds the confirmation dialog; confirmation() is the single-use token setting.
 */
class ActionBuilder extends ComponentBuilder
{
    /**
     * Ask the user to confirm before the action runs.
     */
    public function confirm(?string $title = null, ?string $message = null, ?string $confirmLabel = null, ?string $cancelLabel = null): static
    {
        return $this->replaceConfiguration(new Confirmation($title, $message, $confirmLabel, $cancelLabel));
    }

    /**
     * What the action does: a closure fn (ActionContext $context): mixed, or an ActionHandler class.
     */
    public function handler(Closure|string $handler): static
    {
        return $this->replaceConfiguration(new Handler($handler));
    }

    /**
     * Validate input submitted with the action.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $messages
     */
    public function input(array $rules, array $labels = [], array $messages = []): static
    {
        return $this->replaceConfiguration(new Input($rules, $labels, $messages));
    }

    /**
     * Run the action in a database transaction on the given connection, retrying deadlocks.
     * (transaction() alone turns the Transaction property on.)
     */
    public function transactionUsing(?string $connection = null, int $attempts = 1): static
    {
        return $this->replaceConfiguration(new Transaction(true, $connection, $attempts));
    }

    /**
     * Hooks around the run.
     */
    public function lifecycle(?Closure $prepare = null, ?Closure $beforeExecute = null, ?Closure $afterSuccess = null, ?Closure $afterFailure = null, ?Closure $afterComplete = null): static
    {
        return $this->replaceConfiguration(new Lifecycle($prepare, $beforeExecute, $afterSuccess, $afterFailure, $afterComplete));
    }
}
