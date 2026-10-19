<?php

declare(strict_types=1);

namespace Gcego\Media\Fields;

use Gcego\Media\Uploads\FileInspector;
use Illuminate\Http\UploadedFile;

/**
 * "report.pdf: The file type is not allowed." (escaped by the view).
 */
final class FileName
{
    public static function prefix(UploadedFile $file, string $message): string
    {
        return FileInspector::displayName($file->getClientOriginalName()).': '.$message;
    }
}
