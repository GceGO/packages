<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Testing\Accessibility\AccessibilityResult;
use Gcego\Testing\Accessibility\BrowserAccessibility;
use Gcego\Testing\Accessibility\StaticAccessibilityChecker;
use Gcego\Testing\Assertions\Verifier;

/**
 * Static accessibility checks on rendered markup, and optionally axe-core.
 *
 * Passing static checks is necessary, not sufficient: they cannot see
 * contrast, focus behaviour or announcements.
 */
final class AccessibilityTestable extends Testable
{
    private ?AccessibilityResult $result = null;

    public function __construct(Verifier $verifier, private readonly string $html, private readonly ?BrowserAccessibility $browser = null)
    {
        parent::__construct($verifier);
    }

    public function result(): AccessibilityResult
    {
        return $this->result ??= (new StaticAccessibilityChecker)->check($this->html);
    }

    /**
     * No static rule is violated, apart from the rules in $except.
     *
     * @param  list<string>  $except
     */
    public function assertAccessible(array $except = []): self
    {
        $result = $this->result()->without($except);

        return $this->check($result->passes(), 'assertAccessible('.$this->export($except).')', 'Expected the markup to pass the static accessibility rules.', ['violations' => 0], ['violations' => $result->formatted()], 'Static rules check markup only; also run browser checks and test with a keyboard and a screen reader.');
    }

    public function assertPasses(string $rule): self
    {
        $violations = $this->result()->violations($rule);

        return $this->check($violations === [], sprintf('assertPasses(%s)', $this->export($rule)), sprintf('Expected the markup to pass the [%s] rule.', $rule), ['violations' => 0], ['violations' => array_map(static fn ($violation): string => $violation->format(), $violations)]);
    }

    public function assertViolates(string $rule): self
    {
        return $this->check($this->result()->violations($rule) !== [], sprintf('assertViolates(%s)', $this->export($rule)), sprintf('Expected the markup to violate the [%s] rule.', $rule), ['violations' => '≥ 1'], ['violations' => 0]);
    }

    public function assertFormLabels(): self
    {
        return $this->assertPasses('form-label');
    }

    public function assertHeadingOrder(): self
    {
        return $this->assertPasses('heading-order');
    }

    public function assertTableHeaders(): self
    {
        return $this->assertPasses('table-headers');
    }

    public function assertButtonNames(): self
    {
        return $this->assertPasses('button-name');
    }

    public function assertDialogNames(): self
    {
        return $this->assertPasses('dialog-name');
    }

    public function assertErrorAssociations(): self
    {
        return $this->assertPasses('error-association');
    }

    /**
     * Runs axe-core in a real browser. Throws TestEnvironmentException when
     * the browser tools are missing; check browserAvailable() to skip.
     *
     * @param  list<string>  $tags
     * @param  list<string>  $except  axe rule ids to ignore.
     */
    public function assertAccessibleInBrowser(array $tags = ['wcag2a', 'wcag2aa'], array $except = []): self
    {
        $result = ($this->browser ?? new BrowserAccessibility)->auditHtml($this->html, $tags)->without($except);

        return $this->check($result->passes(), 'assertAccessibleInBrowser('.$this->export($tags).')', 'Expected axe-core to report no violations.', ['violations' => 0], ['violations' => $result->formatted()]);
    }

    public function browserAvailable(): bool
    {
        return ($this->browser ?? new BrowserAccessibility)->available();
    }

    protected function subject(): string
    {
        return 'accessibility';
    }

    protected function context(): array
    {
        return ['Markup' => mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', $this->html)), 0, 160, '…')];
    }
}
