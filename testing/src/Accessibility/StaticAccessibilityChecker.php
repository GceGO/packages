<?php

declare(strict_types=1);

namespace Gcego\Testing\Accessibility;

use DOMElement;
use Gcego\Testing\Support\Html;

/**
 * Accessibility rules that can be decided from markup alone.
 *
 * These catch common, certain mistakes (an input with no label, a skipped
 * heading level, a reference to a missing id). They do not compute colour
 * contrast, focus order or anything that needs layout or a screen reader;
 * use BrowserAccessibility (axe-core) and manual testing for that.
 */
final class StaticAccessibilityChecker
{
    public const array Rules = ['form-label', 'image-alt', 'button-name', 'link-name', 'heading-order', 'empty-heading', 'table-headers', 'dialog-name', 'duplicate-id', 'aria-reference', 'error-association', 'positive-tabindex', 'aria-hidden-focus', 'landmark-name'];

    private const string Focusable = 'a[@href] | button | input[not(@type="hidden")] | select | textarea | *[@tabindex and not(@tabindex="-1")]';

    private Html $html;

    /**
     * @param  list<string>|null  $rules  Defaults to every rule.
     */
    public function check(string $markup, ?array $rules = null): AccessibilityResult
    {
        $this->html = Html::parse($markup);
        $rules ??= self::Rules;
        $violations = [];

        foreach ($rules as $rule) {
            $method = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $rule))));

            foreach ($this->rule($method) as $violation) {
                $violations[] = $violation;
            }
        }

        return new AccessibilityResult($violations, 'static', array_values($rules));
    }

    /**
     * @return list<Violation>
     */
    private function rule(string $method): array
    {
        return match ($method) {
            'formLabel' => $this->formLabel(),
            'imageAlt' => $this->imageAlt(),
            'buttonName' => $this->buttonName(),
            'linkName' => $this->linkName(),
            'headingOrder' => $this->headingOrder(),
            'emptyHeading' => $this->emptyHeading(),
            'tableHeaders' => $this->tableHeaders(),
            'dialogName' => $this->dialogName(),
            'duplicateId' => $this->duplicateId(),
            'ariaReference' => $this->ariaReference(),
            'errorAssociation' => $this->errorAssociation(),
            'positiveTabindex' => $this->positiveTabindex(),
            'ariaHiddenFocus' => $this->ariaHiddenFocus(),
            'landmarkName' => $this->landmarkName(),
            default => throw new \InvalidArgumentException(sprintf('Unknown accessibility rule "%s".', $method)),
        };
    }

    /**
     * The accessible name, following the order browsers use: aria-labelledby,
     * aria-label, an associated <label>, then content, then title.
     */
    public function accessibleName(DOMElement $element): string
    {
        $labelledBy = trim($element->getAttribute('aria-labelledby'));

        if ($labelledBy !== '') {
            $names = [];

            foreach (preg_split('/\s+/', $labelledBy) ?: [] as $id) {
                $target = $this->html->byId($id);

                if ($target !== null) {
                    $names[] = Html::text($target);
                }
            }

            $name = trim(implode(' ', $names));

            if ($name !== '') {
                return $name;
            }
        }

        if (trim($element->getAttribute('aria-label')) !== '') {
            return trim($element->getAttribute('aria-label'));
        }

        $tag = strtolower($element->tagName);

        if (in_array($tag, ['input', 'select', 'textarea'], true)) {
            $id = $element->getAttribute('id');

            if ($id !== '') {
                foreach ($this->html->select('//label') as $label) {
                    if ($label->getAttribute('for') === $id && Html::text($label) !== '') {
                        return Html::text($label);
                    }
                }
            }

            for ($parent = $element->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
                if (strtolower($parent->tagName) === 'label' && Html::text($parent) !== '') {
                    return Html::text($parent);
                }
            }

            if ($tag === 'input' && in_array(strtolower($element->getAttribute('type')), ['submit', 'reset', 'button'], true) && trim($element->getAttribute('value')) !== '') {
                return trim($element->getAttribute('value'));
            }

            if ($tag === 'input' && strtolower($element->getAttribute('type')) === 'image') {
                return trim($element->getAttribute('alt'));
            }
        } else {
            $text = $this->contentName($element);

            if ($text !== '') {
                return $text;
            }
        }

        return trim($element->getAttribute('title'));
    }

    /**
     * @return list<Violation>
     */
    private function formLabel(): array
    {
        $violations = [];

        foreach ($this->html->select('//input[not(@type="hidden")] | //select | //textarea') as $control) {
            if ($this->isHidden($control) || $this->accessibleName($control) !== '') {
                continue;
            }

            $violations[] = new Violation('form-label', 'Form controls need a label (a <label for>, aria-label or aria-labelledby).', Html::snippet($control), 'critical');
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function imageAlt(): array
    {
        $violations = [];

        foreach ($this->html->select('//img') as $image) {
            if ($image->hasAttribute('alt') || in_array($image->getAttribute('role'), ['presentation', 'none'], true) || $this->isHidden($image)) {
                continue;
            }

            $violations[] = new Violation('image-alt', 'Images need an alt attribute (empty for decorative images).', Html::snippet($image), 'critical');
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function buttonName(): array
    {
        return $this->named('//button | //*[@role="button"]', 'button-name', 'Buttons need an accessible name (text, aria-label or aria-labelledby).');
    }

    /**
     * @return list<Violation>
     */
    private function linkName(): array
    {
        return $this->named('//a[@href]', 'link-name', 'Links need an accessible name (text, aria-label or an image with alt text).');
    }

    /**
     * @return list<Violation>
     */
    private function headingOrder(): array
    {
        $violations = [];
        $previous = null;

        foreach ($this->headings() as [$level, $heading]) {
            if ($previous !== null && $level > $previous + 1) {
                $violations[] = new Violation('heading-order', sprintf('Heading levels should increase by one: h%d follows h%d.', $level, $previous), Html::snippet($heading), 'moderate');
            }

            $previous = $level;
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function emptyHeading(): array
    {
        $violations = [];

        foreach ($this->headings() as [, $heading]) {
            if ($this->accessibleName($heading) === '') {
                $violations[] = new Violation('empty-heading', 'Headings must not be empty.', Html::snippet($heading), 'moderate');
            }
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function tableHeaders(): array
    {
        $violations = [];

        foreach ($this->html->select('//table[not(@role="presentation") and not(@role="none")]') as $table) {
            $headers = $this->html->xpath->query('.//th | .//*[@role="columnheader"] | .//*[@role="rowheader"]', $table);
            $cells = $this->html->xpath->query('.//td', $table);

            if ($headers !== false && $cells !== false && $headers->length === 0 && $cells->length > 0) {
                $violations[] = new Violation('table-headers', 'Data tables need header cells (<th>).', Html::snippet($table));
            }
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function dialogName(): array
    {
        $violations = [];

        foreach ($this->html->select('//dialog | //*[@role="dialog"] | //*[@role="alertdialog"]') as $dialog) {
            if (trim($dialog->getAttribute('aria-label')) === '' && $this->referencedText($dialog->getAttribute('aria-labelledby')) === '') {
                $violations[] = new Violation('dialog-name', 'Dialogs need a name (aria-labelledby pointing at the title, or aria-label).', Html::snippet($dialog));
            }
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function duplicateId(): array
    {
        $seen = [];
        $violations = [];

        foreach ($this->html->select('//*[@id]') as $element) {
            $id = $element->getAttribute('id');

            if (isset($seen[$id])) {
                $violations[] = new Violation('duplicate-id', sprintf('The id "%s" is used more than once.', $id), Html::snippet($element));
            }

            $seen[$id] = true;
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function ariaReference(): array
    {
        $violations = [];

        foreach (['aria-labelledby', 'aria-describedby', 'aria-controls', 'aria-owns', 'aria-errormessage', 'aria-activedescendant'] as $attribute) {
            foreach ($this->html->select('//*[@'.$attribute.']') as $element) {
                foreach (preg_split('/\s+/', trim($element->getAttribute($attribute))) ?: [] as $id) {
                    if ($id !== '' && $this->html->byId($id) === null) {
                        $violations[] = new Violation('aria-reference', sprintf('%s refers to the missing id "%s".', $attribute, $id), Html::snippet($element));
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function errorAssociation(): array
    {
        $violations = [];

        foreach ($this->html->select('//*[@aria-invalid="true"]') as $control) {
            if (trim($control->getAttribute('aria-describedby').$control->getAttribute('aria-errormessage')) === '') {
                $violations[] = new Violation('error-association', 'Invalid fields need their error message linked with aria-describedby or aria-errormessage.', Html::snippet($control));
            }
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function positiveTabindex(): array
    {
        $violations = [];

        foreach ($this->html->select('//*[@tabindex]') as $element) {
            if ((int) $element->getAttribute('tabindex') > 0) {
                $violations[] = new Violation('positive-tabindex', 'A positive tabindex changes the focus order; use 0 or -1.', Html::snippet($element), 'moderate');
            }
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function ariaHiddenFocus(): array
    {
        $violations = [];

        foreach ($this->html->select('//*[@aria-hidden="true"]') as $hidden) {
            $focusable = $this->html->xpath->query('descendant-or-self::'.str_replace(' | ', ' | descendant-or-self::', self::Focusable), $hidden);

            foreach ($focusable ?: [] as $element) {
                if ($element instanceof DOMElement && ! $element->hasAttribute('disabled') && $element->getAttribute('tabindex') !== '-1') {
                    $violations[] = new Violation('aria-hidden-focus', 'Focusable elements must not be inside aria-hidden="true".', Html::snippet($element));
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function landmarkName(): array
    {
        $violations = [];

        foreach (['nav' => '//nav | //*[@role="navigation"]', 'aside' => '//aside | //*[@role="complementary"]'] as $landmark => $query) {
            $elements = $this->html->select($query);

            if (count($elements) < 2) {
                continue;
            }

            $names = [];

            foreach ($elements as $element) {
                $name = trim($element->getAttribute('aria-label')) !== '' ? trim($element->getAttribute('aria-label')) : $this->referencedText($element->getAttribute('aria-labelledby'));

                if ($name === '' || isset($names[$name])) {
                    $violations[] = new Violation('landmark-name', sprintf('When a page has several <%s> landmarks, each needs a distinct aria-label.', $landmark), Html::snippet($element), 'moderate');
                }

                $names[$name] = true;
            }
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function named(string $query, string $rule, string $message): array
    {
        $violations = [];

        foreach ($this->html->select($query) as $element) {
            if (! $this->isHidden($element) && $this->accessibleName($element) === '') {
                $violations[] = new Violation($rule, $message, Html::snippet($element), 'critical');
            }
        }

        return $violations;
    }

    /**
     * @return list<array{int, DOMElement}>
     */
    private function headings(): array
    {
        $headings = [];

        foreach ($this->html->select('//h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //*[@role="heading"]') as $heading) {
            $level = $heading->getAttribute('role') === 'heading' ? (int) ($heading->getAttribute('aria-level') ?: 2) : (int) substr($heading->tagName, 1);
            $headings[] = [$level, $heading];
        }

        return $headings;
    }

    private function contentName(DOMElement $element): string
    {
        $parts = [Html::text($element)];

        foreach ($this->html->xpath->query('.//img[@alt] | .//*[@role="img"][@aria-label]', $element) ?: [] as $image) {
            if ($image instanceof DOMElement) {
                $parts[] = trim($image->getAttribute('alt').' '.$image->getAttribute('aria-label'));
            }
        }

        return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    private function referencedText(string $ids): string
    {
        $names = [];

        foreach (preg_split('/\s+/', trim($ids)) ?: [] as $id) {
            $target = $id === '' ? null : $this->html->byId($id);

            if ($target !== null) {
                $names[] = Html::text($target);
            }
        }

        return trim(implode(' ', $names));
    }

    private function isHidden(DOMElement $element): bool
    {
        for ($node = $element; $node instanceof DOMElement; $node = $node->parentNode) {
            if ($node->getAttribute('aria-hidden') === 'true' || $node->hasAttribute('hidden') || strtolower($node->tagName) === 'template') {
                return true;
            }
        }

        return false;
    }
}
