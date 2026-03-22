<?php

declare(strict_types=1);

namespace OpenILink;

use RuntimeException;

final class Cdn
{
    public const UPLOAD_MAX_RETRIES = 3;

    public static function encryptAesEcb(string $plaintext, string $key): string
    {
        $ciphertext = openssl_encrypt($plaintext, 'aes-128-ecb', $key, OPENSSL_RAW_DATA);
        if ($ciphertext === false) {
            throw new RuntimeException('ilink: aes encrypt failed');
        }

        return $ciphertext;
    }

    public static function decryptAesEcb(string $ciphertext, string $key): string
    {
        $plaintext = openssl_decrypt($ciphertext, 'aes-128-ecb', $key, OPENSSL_RAW_DATA);
        if ($plaintext === false) {
            throw new RuntimeException('ilink: aes decrypt failed');
        }

        return $plaintext;
    }

    public static function aesEcbPaddedSize(int $plaintextSize): int
    {
        return (int) (ceil(($plaintextSize + 1) / 16) * 16);
    }

    public static function buildDownloadUrl(string $cdnBaseUrl, string $encryptedQueryParam): string
    {
        return $cdnBaseUrl . '/download?encrypted_query_param=' . rawurlencode($encryptedQueryParam);
    }

    public static function buildUploadUrl(string $cdnBaseUrl, string $uploadParam, string $fileKey): string
    {
        return $cdnBaseUrl
            . '/upload?encrypted_query_param=' . rawurlencode($uploadParam)
            . '&filekey=' . rawurlencode($fileKey);
    }

    public static function parseAESKey(string $aesKeyBase64): string
    {
        $decoded = self::decodeBase64Flexible($aesKeyBase64);

        if (strlen($decoded) === 16) {
            return $decoded;
        }

        if (strlen($decoded) === 32 && ctype_xdigit($decoded)) {
            $raw = hex2bin($decoded);
            if ($raw === false) {
                throw new RuntimeException('ilink: decode hex aes_key failed');
            }

            return $raw;
        }

        throw new RuntimeException(
            sprintf('ilink: aes_key must decode to 16 raw bytes or 32-char hex, got %d bytes', strlen($decoded)),
        );
    }

    public static function mediaAesKeyHex(string $hexKey): string
    {
        return base64_encode($hexKey);
    }

    private static function decodeBase64Flexible(string $value): string
    {
        $candidates = [
            $value,
            strtr($value, '-_', '+/'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = self::normalizeBase64($candidate);
            $decoded = base64_decode($normalized, true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        throw new RuntimeException(sprintf('ilink: invalid base64: %s', $value));
    }

    private static function normalizeBase64(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding === 0) {
            return $value;
        }

        return $value . str_repeat('=', 4 - $padding);
    }
}
