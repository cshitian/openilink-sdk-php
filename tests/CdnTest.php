<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Cdn.php';

use OpenILink\Cdn;
use RuntimeException;

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

$key = implode('', array_map(static fn (int $value): string => chr($value), range(0, 15)));

foreach ([
    '',
    'hello',
    str_repeat('x', 16),
    str_repeat('y', 32),
    str_repeat('z', 37),
    str_repeat('a', 4096),
] as $plaintext) {
    $ciphertext = Cdn::encryptAesEcb($plaintext, $key);
    assertSameValue(strlen($ciphertext) % 16, 0, 'encryptAesEcb should return block-aligned ciphertext');
    assertSameValue(Cdn::decryptAesEcb($ciphertext, $key), $plaintext, 'decryptAesEcb should round-trip plaintext');
}

foreach ([
    0 => 16,
    1 => 16,
    15 => 16,
    16 => 32,
    31 => 32,
    32 => 48,
    100 => 112,
] as $input => $expected) {
    assertSameValue(Cdn::aesEcbPaddedSize($input), $expected, sprintf('aesEcbPaddedSize should resolve %d', $input));
}

assertSameValue(
    Cdn::buildDownloadUrl('https://cdn.example.com/c2c', 'abc=123&foo'),
    'https://cdn.example.com/c2c/download?encrypted_query_param=abc%3D123%26foo',
    'buildDownloadUrl should encode encrypted query parameters',
);

assertSameValue(
    Cdn::buildUploadUrl('https://cdn.example.com/c2c', 'param=1', 'key123'),
    'https://cdn.example.com/c2c/upload?encrypted_query_param=param%3D1&filekey=key123',
    'buildUploadUrl should encode query parameters',
);

$rawKey = implode('', array_map(static fn (int $value): string => chr($value), range(1, 16)));
assertSameValue(
    Cdn::parseAESKey(base64_encode($rawKey)),
    $rawKey,
    'parseAESKey should decode raw 16-byte keys',
);

$hexKey = bin2hex($rawKey);
assertSameValue(
    Cdn::parseAESKey(base64_encode($hexKey)),
    $rawKey,
    'parseAESKey should decode base64-encoded hex keys',
);

assertSameValue(
    Cdn::parseAESKey(rtrim(base64_encode($rawKey), '=')),
    $rawKey,
    'parseAESKey should accept unpadded base64',
);

assertSameValue(
    Cdn::mediaAesKeyHex('00112233445566778899aabbccddeeff'),
    base64_encode('00112233445566778899aabbccddeeff'),
    'mediaAesKeyHex should base64-encode the hex key string',
);

try {
    Cdn::parseAESKey('!!!invalid!!!');
    fwrite(STDERR, "parseAESKey should reject invalid base64\n");
    exit(1);
} catch (RuntimeException) {
}

try {
    Cdn::parseAESKey(base64_encode('tooshort'));
    fwrite(STDERR, "parseAESKey should reject invalid key lengths\n");
    exit(1);
} catch (RuntimeException) {
}

fwrite(STDOUT, "Cdn tests passed\n");
