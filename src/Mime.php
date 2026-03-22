<?php

declare(strict_types=1);

namespace OpenILink;

final class Mime
{
    /**
     * @var array<string, string>
     */
    private const EXT_TO_MIME = [
        '.pdf' => 'application/pdf',
        '.doc' => 'application/msword',
        '.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        '.xls' => 'application/vnd.ms-excel',
        '.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        '.ppt' => 'application/vnd.ms-powerpoint',
        '.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        '.txt' => 'text/plain',
        '.csv' => 'text/csv',
        '.zip' => 'application/zip',
        '.tar' => 'application/x-tar',
        '.gz' => 'application/gzip',
        '.mp3' => 'audio/mpeg',
        '.ogg' => 'audio/ogg',
        '.wav' => 'audio/wav',
        '.mp4' => 'video/mp4',
        '.mov' => 'video/quicktime',
        '.webm' => 'video/webm',
        '.mkv' => 'video/x-matroska',
        '.avi' => 'video/x-msvideo',
        '.png' => 'image/png',
        '.jpg' => 'image/jpeg',
        '.jpeg' => 'image/jpeg',
        '.gif' => 'image/gif',
        '.webp' => 'image/webp',
        '.bmp' => 'image/bmp',
    ];

    /**
     * @var array<string, string>
     */
    private const MIME_TO_EXT = [
        'application/pdf' => '.pdf',
        'application/msword' => '.doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
        'application/vnd.ms-excel' => '.xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
        'application/vnd.ms-powerpoint' => '.ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => '.pptx',
        'text/plain' => '.txt',
        'text/csv' => '.csv',
        'application/zip' => '.zip',
        'application/x-tar' => '.tar',
        'application/gzip' => '.gz',
        'audio/mpeg' => '.mp3',
        'audio/ogg' => '.ogg',
        'audio/wav' => '.wav',
        'video/mp4' => '.mp4',
        'video/quicktime' => '.mov',
        'video/webm' => '.webm',
        'video/x-matroska' => '.mkv',
        'video/x-msvideo' => '.avi',
        'image/png' => '.png',
        'image/jpeg' => '.jpg',
        'image/gif' => '.gif',
        'image/webp' => '.webp',
        'image/bmp' => '.bmp',
    ];

    public static function mimeFromFilename(string $fileName): string
    {
        $extension = strtolower(strrchr($fileName, '.') ?: '');

        return self::EXT_TO_MIME[$extension] ?? 'application/octet-stream';
    }

    public static function extensionFromMime(string $mime): string
    {
        $normalized = trim(explode(';', $mime, 2)[0]);

        return self::MIME_TO_EXT[$normalized] ?? '.bin';
    }

    public static function isImageMime(string $mime): bool
    {
        return str_starts_with($mime, 'image/');
    }

    public static function isVideoMime(string $mime): bool
    {
        return str_starts_with($mime, 'video/');
    }
}
