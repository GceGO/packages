<?php

declare(strict_types=1);

namespace Gcego\Forms;

/**
 * Server-side event of a standalone FormHost submission (a blueprint without
 * Create/Edit actions): validated, authorized data for trusted listeners.
 * It is never sent to the browser.
 */
final readonly class FormSubmitted
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $definition,
        public string $form,
        public array $data,
        public string|int|null $userKey,
    ) {}
}
