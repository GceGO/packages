<?php

declare(strict_types=1);

namespace Gcego\Actions\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;

/**
 * The text of an action's confirmation dialog (it also turns confirmation on):
 *
 *     new Confirmation('Delete this subject?', 'Its topics and lessons are deleted too.', 'Delete subject')
 */
final readonly class Confirmation implements ConfigurationNode
{
    public function __construct(
        public ?string $title = null,
        public ?string $message = null,
        public ?string $confirmLabel = null,
        public ?string $cancelLabel = null,
    ) {}

    /**
     * @return array{title: string|null, message: string|null, confirm: string|null, cancel: string|null}
     */
    public function normalize(): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'confirm' => $this->confirmLabel, 'cancel' => $this->cancelLabel];
    }
}
