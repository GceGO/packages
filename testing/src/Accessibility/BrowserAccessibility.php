<?php

declare(strict_types=1);

namespace Gcego\Testing\Accessibility;

use Gcego\Testing\Exceptions\TestEnvironmentException;
use JsonException;

/**
 * axe-core in headless Chromium, through Playwright.
 *
 * Optional: it needs Node plus the "playwright" and "axe-core" npm
 * packages (and Playwright's Chromium). available() says whether they are
 * present; audit() never pretends a page passed when they are missing.
 */
final readonly class BrowserAccessibility
{
    /**
     * @param  string|null  $modules  A node_modules directory; found by walking up from $base when null.
     */
    public function __construct(private string $node = 'node', private ?string $modules = null, private ?string $base = null, private int $timeout = 30000) {}

    public function available(): bool
    {
        return $this->unavailableReason() === null;
    }

    /**
     * Why audits cannot run, or null when they can.
     */
    public function unavailableReason(): ?string
    {
        if (! function_exists('proc_open')) {
            return 'proc_open() is disabled.';
        }

        if ($this->run([$this->node, '--version'])[0] !== 0) {
            return sprintf('Node is not available as "%s".', $this->node);
        }

        $modules = $this->modules();

        if ($modules === null) {
            return 'No node_modules directory with "playwright" and "axe-core" was found.';
        }

        return null;
    }

    /**
     * Audits markup (a fragment or a full document).
     *
     * @param  list<string>  $tags  axe rule tags, e.g. ["wcag2a", "wcag2aa"].
     */
    public function auditHtml(string $html, array $tags = []): AccessibilityResult
    {
        if (stripos($html, '<html') === false) {
            $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Audit</title></head><body><main>'.$html.'</main></body></html>';
        }

        return $this->audit(['html' => $html, 'tags' => $tags]);
    }

    /**
     * Audits a page the browser can reach (http(s):// or file://).
     *
     * @param  list<string>  $tags
     */
    public function auditUrl(string $url, array $tags = []): AccessibilityResult
    {
        return $this->audit(['url' => $url, 'tags' => $tags]);
    }

    public function modules(): ?string
    {
        $candidates = $this->modules !== null ? [$this->modules] : [];

        for ($directory = $this->base ?? (string) getcwd(); $directory !== '' && $directory !== dirname($directory); $directory = dirname($directory)) {
            $candidates[] = $directory.'/node_modules';
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate.'/playwright/package.json') && is_file($candidate.'/axe-core/axe.min.js')) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function audit(array $input): AccessibilityResult
    {
        $reason = $this->unavailableReason();

        if ($reason !== null) {
            throw TestEnvironmentException::because('browser accessibility checks are unavailable. '.$reason);
        }

        $modules = (string) $this->modules();

        try {
            $payload = json_encode([...$input, 'modules' => $modules, 'axe' => $modules.'/axe-core/axe.min.js', 'timeout' => $this->timeout], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw TestEnvironmentException::because('the audit input could not be encoded: '.$exception->getMessage());
        }

        [$status, $output, $errors] = $this->run([$this->node, dirname(__DIR__, 2).'/resources/js/axe-audit.mjs'], $payload);

        if ($status !== 0) {
            throw TestEnvironmentException::because('the browser audit failed: '.trim($errors !== '' ? $errors : $output));
        }

        try {
            $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw TestEnvironmentException::because('the browser audit printed invalid JSON.');
        }

        $violations = [];

        foreach (is_array($decoded) && is_array($decoded['violations'] ?? null) ? $decoded['violations'] : [] as $violation) {
            if (! is_array($violation)) {
                continue;
            }

            foreach (is_array($violation['elements'] ?? null) && $violation['elements'] !== [] ? $violation['elements'] : [''] as $element) {
                $violations[] = new Violation(is_string($violation['rule'] ?? null) ? $violation['rule'] : 'axe', is_string($violation['message'] ?? null) ? $violation['message'] : '', is_string($element) ? $element : '', is_string($violation['impact'] ?? null) ? $violation['impact'] : 'serious');
            }
        }

        return new AccessibilityResult($violations, 'axe');
    }

    /**
     * @param  list<string>  $command
     * @return array{int, string, string}
     */
    private function run(array $command, string $input = ''): array
    {
        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! is_resource($process)) {
            return [127, '', 'Could not start '.$command[0]];
        }

        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output, $errors];
    }
}
