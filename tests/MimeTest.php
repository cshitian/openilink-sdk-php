<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Mime.php';

use OpenILink\Mime;

function assertSameValue(mixed $actual, mixed $expected, string $message): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

assertSameValue(Mime::mimeFromFilename('photo.jpg'), 'image/jpeg', 'mimeFromFilename should resolve .jpg');
assertSameValue(Mime::mimeFromFilename('photo.JPEG'), 'image/jpeg', 'mimeFromFilename should normalize extension case');
assertSameValue(Mime::mimeFromFilename('archive.unknown'), 'application/octet-stream', 'mimeFromFilename should fall back for unknown extensions');

assertSameValue(Mime::extensionFromMime('image/jpeg'), '.jpg', 'extensionFromMime should resolve image/jpeg');
assertSameValue(Mime::extensionFromMime('image/jpg'), '.jpg', 'extensionFromMime should resolve image/jpg alias');
assertSameValue(Mime::extensionFromMime('text/plain; charset=utf-8'), '.txt', 'extensionFromMime should ignore parameters');
assertSameValue(Mime::extensionFromMime('unknown/type'), '.bin', 'extensionFromMime should fall back for unknown MIME types');

assertTrue(Mime::isImageMime('image/png'), 'isImageMime should detect image MIME types');
assertTrue(!Mime::isImageMime('video/mp4'), 'isImageMime should reject non-image MIME types');
assertTrue(Mime::isVideoMime('video/mp4'), 'isVideoMime should detect video MIME types');
assertTrue(!Mime::isVideoMime('application/pdf'), 'isVideoMime should reject non-video MIME types');

fwrite(STDOUT, "Mime tests passed\n");
