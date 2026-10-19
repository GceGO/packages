<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Testing\Accessibility\BrowserAccessibility;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Support\Html;

/**
 * Assertions about rendered markup: text, elements and attributes.
 *
 * assertSee() matches the escaped text in the raw HTML (as Laravel's
 * TestResponse does); assertSeeText() matches visible text only.
 */
final class HtmlTestable extends Testable
{
    private ?Html $document = null;

    public function __construct(Verifier $verifier, private readonly string $html, private readonly string $label = 'markup', private readonly ?BrowserAccessibility $browser = null)
    {
        parent::__construct($verifier);
    }

    public function html(): string
    {
        return $this->html;
    }

    public function text(): string
    {
        return $this->document()->bodyText();
    }

    public function assertSee(string $value, bool $escape = true): self
    {
        $needle = $escape ? e($value) : $value;

        return $this->check(str_contains($this->html, $needle), sprintf('assertSee(%s)', $this->export($value)), sprintf('Expected the %s to contain [%s].', $this->label, $value), ['contains' => $value], ['text' => $this->text()]);
    }

    public function assertDontSee(string $value, bool $escape = true): self
    {
        $needle = $escape ? e($value) : $value;

        return $this->check(! str_contains($this->html, $needle) && ! str_contains($this->html, $value), sprintf('assertDontSee(%s)', $this->export($value)), sprintf('Expected the %s not to contain [%s].', $this->label, $value), ['contains' => 'no'], ['contains' => 'yes']);
    }

    public function assertSeeText(string $value): self
    {
        return $this->check(str_contains($this->text(), $value), sprintf('assertSeeText(%s)', $this->export($value)), sprintf('Expected the visible text to contain [%s].', $value), ['contains' => $value], ['text' => $this->text()]);
    }

    public function assertDontSeeText(string $value): self
    {
        return $this->check(! str_contains($this->text(), $value), sprintf('assertDontSeeText(%s)', $this->export($value)), sprintf('Expected the visible text not to contain [%s].', $value), ['contains' => 'no'], ['text' => $this->text()]);
    }

    /**
     * @param  list<string>  $values
     */
    public function assertSeeInOrder(array $values): self
    {
        $text = $this->text();
        $offset = 0;
        $passed = true;

        foreach ($values as $value) {
            $position = mb_strpos($text, $value, $offset);

            if ($position === false) {
                $passed = false;
                break;
            }

            $offset = $position + mb_strlen($value);
        }

        return $this->check($passed, 'assertSeeInOrder('.$this->export($values).')', 'Expected the visible text to contain the values in order.', ['order' => $values], ['text' => $text]);
    }

    /**
     * An element matching the selector exists ($count times when given).
     */
    public function assertElement(string $selector, ?int $count = null): self
    {
        $found = count($this->document()->select($selector));

        return $this->check($count === null ? $found > 0 : $found === $count, sprintf('assertElement(%s, %s)', $this->export($selector), $this->export($count)), sprintf('Expected the %s to contain [%s]%s.', $this->label, $selector, $count === null ? '' : " $count time(s)"), ['elements' => $count ?? '≥ 1'], ['elements' => $found]);
    }

    public function assertMissingElement(string $selector): self
    {
        return $this->assertElement($selector, 0);
    }

    public function assertAttribute(string $selector, string $attribute, ?string $value = null): self
    {
        $values = array_map(static fn ($element): ?string => $element->hasAttribute($attribute) ? $element->getAttribute($attribute) : null, $this->document()->select($selector));
        $passed = $value === null ? in_array(true, array_map(static fn (?string $actual): bool => $actual !== null, $values), true) : in_array($value, $values, true);

        return $this->check($passed, sprintf('assertAttribute(%s, %s, %s)', $this->export($selector), $this->export($attribute), $this->export($value)), sprintf('Expected [%s] to have %s="%s".', $selector, $attribute, $value ?? '…'), [$attribute => $value ?? '(present)'], [$attribute => $values]);
    }

    public function accessibility(): AccessibilityTestable
    {
        return new AccessibilityTestable($this->verifier, $this->html, $this->browser);
    }

    /**
     * @param  list<string>  $except
     */
    public function assertAccessible(array $except = []): self
    {
        $this->accessibility()->assertAccessible($except);

        return $this;
    }

    protected function subject(): string
    {
        return 'html';
    }

    protected function context(): array
    {
        return ['Markup' => mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', $this->html)), 0, 200, '…')];
    }

    private function document(): Html
    {
        return $this->document ??= Html::parse($this->html);
    }
}
