<?php

declare(strict_types=1);

namespace Gcego\Media\Uploads;

use finfo;

/**
 * What a file really is, from its bytes (finfo), never from the browser:
 * its content type, the extension gcego/media stores it with, and whether
 * its claimed name is consistent and safe.
 */
final class FileInspector
{
    /** Content type => extensions it may be named with (the first is used for storage). */
    public const array Extensions = [
        'image/jpeg' => ['jpg', 'jpeg', 'jpe'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/svg+xml' => ['svg'],
        'application/pdf' => ['pdf'],
        'text/plain' => ['txt', 'text', 'log', 'md', 'csv'],
        'text/csv' => ['csv', 'txt'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'application/vnd.oasis.opendocument.text' => ['odt'],
        'application/msword' => ['doc'],
        'application/vnd.ms-excel' => ['xls'],
        'application/zip' => ['zip'],
        'audio/mpeg' => ['mp3'],
        'audio/ogg' => ['ogg', 'oga'],
        'audio/wav' => ['wav'],
        'audio/x-wav' => ['wav'],
        'audio/mp4' => ['m4a', 'mp4'],
        'video/mp4' => ['mp4', 'm4v'],
        'video/webm' => ['webm'],
        'video/quicktime' => ['mov'],
    ];

    /** Never accepted when gcego-media.security.deny_executable_uploads (by name or by content). */
    public const array DangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'inc',
        'exe', 'com', 'bat', 'cmd', 'msi', 'dll', 'so', 'dylib', 'scr', 'cpl', 'jar', 'class', 'app', 'apk', 'deb', 'rpm',
        'sh', 'bash', 'zsh', 'ps1', 'psm1', 'vbs', 'vbe', 'wsf', 'hta', 'pl', 'py', 'pyc', 'rb', 'cgi', 'asp', 'aspx', 'jsp', 'jspx',
        'js', 'mjs', 'cjs', 'html', 'htm', 'xhtml', 'shtml', 'xml', 'xsl', 'svgz', 'swf', 'htaccess', 'user.ini',
    ];

    public const array DangerousTypes = [
        'application/x-php', 'text/x-php', 'application/x-httpd-php', 'application/x-executable', 'application/x-sharedlib',
        'application/x-mach-binary', 'application/x-dosexec', 'application/x-msdownload', 'application/vnd.microsoft.portable-executable',
        'application/x-sh', 'text/x-shellscript', 'text/x-python', 'text/x-perl', 'application/javascript', 'text/javascript',
        'text/html', 'application/xhtml+xml', 'application/java-archive', 'application/x-java-applet', 'application/x-shockwave-flash',
        'application/xml', 'text/xml',
    ];

    public function detectType(string $path): string
    {
        $type = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $type = is_string($type) ? strtolower($type) : 'application/octet-stream';

        // libmagic reports "text/plain" for CSV and some SVG; keep the stricter view.
        if ($type === 'image/svg' || ($type === 'text/plain' && self::looksLikeSvg($path))) {
            return 'image/svg+xml';
        }

        if ($type === 'text/html' || ($type === 'text/plain' && self::looksLikeHtml($path))) {
            return 'text/html';
        }

        return $type;
    }

    /**
     * The extension a file of this type is stored with.
     */
    public function storageExtension(string $type): string
    {
        return self::Extensions[$type][0] ?? 'bin';
    }

    /**
     * Whether the claimed name's extension fits the detected type.
     */
    public function extensionMatches(string $name, string $type): bool
    {
        $extension = self::extension($name);

        return $extension !== '' && in_array($extension, self::Extensions[$type] ?? [], true);
    }

    public function isDangerous(string $name, string $type): bool
    {
        if (in_array($type, self::DangerousTypes, true)) {
            return true;
        }

        $parts = explode('.', strtolower(basename($name)));
        array_shift($parts);

        // "invoice.php.jpg" and ".htaccess" style names are refused, not only the last extension.
        foreach ($parts as $part) {
            if (in_array($part, self::DangerousExtensions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Width, height and image type of a decodable raster image, or null.
     *
     * @return array{width: int, height: int, type: string}|null
     */
    public function imageSize(string $path): ?array
    {
        $size = @getimagesize($path);

        if (! is_array($size) || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        return ['width' => $size[0], 'height' => $size[1], 'type' => $size['mime']];
    }

    public static function extension(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1 ? $extension : '';
    }

    /**
     * A display-safe original name: no directories, control or bidi
     * characters, quotes or slashes; at most 180 characters.
     */
    public static function displayName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = (string) preg_replace('/[\x00-\x1F\x7F"\'<>:|?*\/\\\\]|\p{Cf}/u', '', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name), " .\t");

        if ($name === '' || preg_match('//u', $name) !== 1) {
            return 'file';
        }

        return mb_substr($name, 0, 180);
    }

    private static function looksLikeSvg(string $path): bool
    {
        $head = (string) @file_get_contents($path, false, null, 0, 4096);

        return preg_match('/<svg[\s>]/i', $head) === 1;
    }

    private static function looksLikeHtml(string $path): bool
    {
        $head = (string) @file_get_contents($path, false, null, 0, 4096);

        return preg_match('/<(?:!doctype\s+html|html|script|iframe|body)[\s>]/i', $head) === 1;
    }
}
