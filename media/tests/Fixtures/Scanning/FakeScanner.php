<?php

declare(strict_types=1);

namespace Gcego\Media\Tests\Fixtures\Scanning;

use Gcego\Media\Contracts\MalwareScanner;
use Gcego\Media\Scanning\ScanResult;
use RuntimeException;

/**
 * Flags files containing the EICAR marker; fails on "SCANNER-DOWN".
 */
final class FakeScanner implements MalwareScanner
{
    public function scan(string $path, string $mime): ScanResult
    {
        $contents = (string) file_get_contents($path);

        if (str_contains($contents, 'SCANNER-DOWN')) {
            throw new RuntimeException('Scanner unreachable.');
        }

        return str_contains($contents, 'EICAR') ? ScanResult::infected('EICAR-Test-File') : ScanResult::clean();
    }
}
