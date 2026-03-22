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

foreach ([
    ['photo.jpg', 'image/jpeg'],
    ['photo.JPEG', 'image/jpeg'],
    ['image.png', 'image/png'],
    ['image.gif', 'image/gif'],
    ['image.webp', 'image/webp'],
    ['image.bmp', 'image/bmp'],
    ['video.mp4', 'video/mp4'],
    ['video.mov', 'video/quicktime'],
    ['video.webm', 'video/webm'],
    ['video.mkv', 'video/x-matroska'],
    ['video.avi', 'video/x-msvideo'],
    ['doc.pdf', 'application/pdf'],
    ['doc.doc', 'application/msword'],
    ['doc.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['sheet.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['audio.mp3', 'audio/mpeg'],
    ['audio.wav', 'audio/wav'],
    ['archive.zip', 'application/zip'],
    ['data.csv', 'text/csv'],
    ['notes.txt', 'text/plain'],
    ['unknown.xyz', 'application/octet-stream'],
    ['noext', 'application/octet-stream'],
    ['', 'application/octet-stream'],
] as [$fileName, $expectedMime]) {
    assertSameValue(
        Mime::mimeFromFilename($fileName),
        $expectedMime,
        sprintf('mimeFromFilename should resolve %s', $fileName === '' ? 'empty filenames' : $fileName),
    );
}

foreach ([
    ['image/jpeg', '.jpg'],
    ['image/jpg', '.jpg'],
    ['image/png', '.png'],
    ['video/mp4', '.mp4'],
    ['application/pdf', '.pdf'],
    ['text/plain', '.txt'],
    ['text/plain; charset=utf-8', '.txt'],
    ['unknown/type', '.bin'],
    ['', '.bin'],
] as [$mime, $expectedExtension]) {
    assertSameValue(
        Mime::extensionFromMime($mime),
        $expectedExtension,
        sprintf('extensionFromMime should resolve %s', $mime === '' ? 'empty MIME types' : $mime),
    );
}

assertTrue(Mime::isImageMime('image/png'), 'isImageMime should detect image MIME types');
assertTrue(Mime::isImageMime('image/jpeg'), 'isImageMime should detect jpeg MIME types');
assertTrue(!Mime::isImageMime('video/mp4'), 'isImageMime should reject non-image MIME types');
assertTrue(!Mime::isImageMime('application/pdf'), 'isImageMime should reject documents');
assertTrue(Mime::isVideoMime('video/mp4'), 'isVideoMime should detect video MIME types');
assertTrue(Mime::isVideoMime('video/webm'), 'isVideoMime should detect webm MIME types');
assertTrue(!Mime::isVideoMime('application/pdf'), 'isVideoMime should reject non-video MIME types');
assertTrue(!Mime::isVideoMime('image/png'), 'isVideoMime should reject images');

fwrite(STDOUT, "Mime tests passed\n");
