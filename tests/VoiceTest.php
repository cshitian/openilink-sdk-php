<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Voice.php';
require_once dirname(__DIR__) . '/src/Cdn.php';
require_once dirname(__DIR__) . '/src/Constants.php';
require_once dirname(__DIR__) . '/src/Client.php';

use OpenILink\Client;
use OpenILink\Cdn;
use OpenILink\Voice;

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

$pcm = str_repeat("\x00", 480);
$wav = Voice::buildWav($pcm, 24000, 1, 16);

assertSameValue(strlen($wav), 44 + strlen($pcm), 'buildWav should prepend a 44-byte WAV header');
assertSameValue(substr($wav, 0, 4), 'RIFF', 'buildWav should include a RIFF marker');
assertSameValue(unpack('Vvalue', substr($wav, 4, 4))['value'], 36 + strlen($pcm), 'buildWav should set the RIFF chunk size');
assertSameValue(substr($wav, 8, 4), 'WAVE', 'buildWav should include a WAVE marker');
assertSameValue(substr($wav, 12, 4), 'fmt ', 'buildWav should include a fmt chunk');
assertSameValue(unpack('Vvalue', substr($wav, 16, 4))['value'], 16, 'buildWav should set PCM fmt chunk size');
assertSameValue(unpack('vvalue', substr($wav, 20, 2))['value'], 1, 'buildWav should set PCM format');
assertSameValue(unpack('vvalue', substr($wav, 22, 2))['value'], 1, 'buildWav should set the channel count');
assertSameValue(unpack('Vvalue', substr($wav, 24, 4))['value'], 24000, 'buildWav should set the sample rate');
assertSameValue(unpack('Vvalue', substr($wav, 28, 4))['value'], 48000, 'buildWav should set the byte rate');
assertSameValue(unpack('vvalue', substr($wav, 32, 2))['value'], 2, 'buildWav should set the block align');
assertSameValue(unpack('vvalue', substr($wav, 34, 2))['value'], 16, 'buildWav should set the bit depth');
assertSameValue(substr($wav, 36, 4), 'data', 'buildWav should include a data chunk');
assertSameValue(unpack('Vvalue', substr($wav, 40, 4))['value'], strlen($pcm), 'buildWav should set the data size');
assertSameValue(substr($wav, 44), $pcm, 'buildWav should preserve PCM payload');

$stereoPcm = str_repeat("\x01", 960);
$stereoWav = Voice::buildWav($stereoPcm, 24000, 2, 16);
assertSameValue(unpack('vvalue', substr($stereoWav, 22, 2))['value'], 2, 'buildWav should support stereo output');
assertSameValue(unpack('Vvalue', substr($stereoWav, 28, 4))['value'], 96000, 'buildWav should update byte rate for stereo output');
assertSameValue(unpack('vvalue', substr($stereoWav, 32, 2))['value'], 4, 'buildWav should update block align for stereo output');

try {
    (new Client('token'))->downloadVoice(['media' => ['encrypt_query_param' => 'x', 'aes_key' => 'y']]);
    fwrite(STDERR, "downloadVoice should reject missing silk decoders\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertSameValue(
        $exception->getMessage(),
        'ilink: no SILK decoder configured; use config["silk_decoder"] or setSilkDecoder()',
        'downloadVoice should explain how to configure a silk decoder',
    );
}

try {
    (new Client('token', ['silk_decoder' => static fn (string $silkData, int $sampleRate): string => '']))->downloadVoice(null);
    fwrite(STDERR, "downloadVoice should reject nil voice items\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertSameValue($exception->getMessage(), 'ilink: voice item or media is nil', 'downloadVoice should reject nil voice items');
}

try {
    (new Client('token', ['silk_decoder' => static fn (string $silkData, int $sampleRate): string => '']))->downloadVoice([]);
    fwrite(STDERR, "downloadVoice should reject missing voice media\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertSameValue($exception->getMessage(), 'ilink: voice item or media is nil', 'downloadVoice should reject missing voice media');
}

$voiceKey = '0123456789abcdef';
$voiceKeyBase64 = base64_encode($voiceKey);

try {
    (new Client('token', [
        'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs): array {
            return [
                'status_code' => 500,
                'body' => 'cdn failed',
                'headers' => [],
            ];
        },
        'silk_decoder' => static fn (string $silkData, int $sampleRate): string => '',
    ]))->downloadVoice([
        'media' => [
            'encrypt_query_param' => 'download-param',
            'aes_key' => $voiceKeyBase64,
        ],
    ]);
    fwrite(STDERR, "downloadVoice should wrap download failures\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertSameValue($exception->getMessage(), 'ilink: download voice: ilink: http 500: cdn failed', 'downloadVoice should label download failures');
    assertTrue($exception->getPrevious() !== null, 'downloadVoice should preserve the download exception');
}

$ciphertext = Cdn::encryptAesEcb('silk', $voiceKey);
$decodeError = new RuntimeException('decoder failed');

try {
    (new Client('token', [
        'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs) use ($ciphertext): array {
            return [
                'status_code' => 200,
                'body' => $ciphertext,
                'headers' => [],
            ];
        },
        'silk_decoder' => static function (string $silkData, int $sampleRate) use ($decodeError): string {
            throw $decodeError;
        },
    ]))->downloadVoice([
        'media' => [
            'encrypt_query_param' => 'download-param',
            'aes_key' => $voiceKeyBase64,
        ],
    ]);
    fwrite(STDERR, "downloadVoice should wrap decode failures\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertSameValue($exception->getMessage(), 'ilink: decode voice: decoder failed', 'downloadVoice should label decoder failures');
    assertSameValue($exception->getPrevious(), $decodeError, 'downloadVoice should preserve the decoder exception');
}

$seenSampleRate = 0;
$wav = (new Client('token', [
    'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs) use ($ciphertext): array {
        return [
            'status_code' => 200,
            'body' => $ciphertext,
            'headers' => [],
        ];
    },
    'silk_decoder' => static function (string $silkData, int $sampleRate) use (&$seenSampleRate): string {
        $seenSampleRate = $sampleRate;

        return "\x01\x02\x03\x04";
    },
]))->downloadVoice([
    'media' => [
        'encrypt_query_param' => 'download-param',
        'aes_key' => $voiceKeyBase64,
    ],
    'sample_rate' => 16000,
]);

assertSameValue($seenSampleRate, 16000, 'downloadVoice should pass sample_rate to the decoder');
assertSameValue(unpack('Vvalue', substr($wav, 24, 4))['value'], 16000, 'downloadVoice should build WAV output with the voice sample_rate');

fwrite(STDOUT, "Voice tests passed\n");
