<?php

declare(strict_types=1);

namespace Gcego\Testing\Accessibility;

final readonly class Violation
{
    public function __construct(public string $rule, public string $message, public string $element = '', public string $impact = 'serious') {}

    /**
     * @return array{rule: string, message: string, element: string, impact: string}
     */
    public function toArray(): array
    {
        return ['rule' => $this->rule, 'message' => $this->message, 'element' => $this->element, 'impact' => $this->impact];
    }

    public function format(): string
    {
        return sprintf('[%s] %s%s', $this->rule, $this->message, $this->element !== '' ? ' '.$this->element : '');
    }
}
