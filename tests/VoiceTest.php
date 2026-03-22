<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Voice.php';

use OpenILink\Voice;

function assertSameValue(mixed $actual, mixed $expected, string $message): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual:   " . var_export($actual, true) . "\n");
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

fwrite(STDOUT, "Voice tests passed\n");
