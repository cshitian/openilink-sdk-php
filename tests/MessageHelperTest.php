<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Constants.php';
require_once dirname(__DIR__) . '/src/MessageHelper.php';

use OpenILink\Constants;
use OpenILink\MessageHelper;

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

$textMessage = [
    'item_list' => [
        [
            'type' => Constants::ITEM_TYPE_TEXT,
            'text_item' => ['text' => 'hello'],
        ],
    ],
];

assertSameValue(MessageHelper::extractText($textMessage), 'hello', 'extractText should return plain text content');

$quotedTextMessage = [
    'item_list' => [
        [
            'type' => Constants::ITEM_TYPE_TEXT,
            'text_item' => ['text' => 'reply body'],
            'ref_msg' => [
                'title' => '原消息',
                'message_item' => [
                    'type' => Constants::ITEM_TYPE_TEXT,
                    'text_item' => ['text' => 'quoted body'],
                ],
            ],
        ],
    ],
];

assertSameValue(
    MessageHelper::extractText($quotedTextMessage),
    "[引用: 原消息 | quoted body]\nreply body",
    'extractText should prepend quoted text context',
);

$quotedMediaMessage = [
    'item_list' => [
        [
            'type' => Constants::ITEM_TYPE_TEXT,
            'text_item' => ['text' => 'reply body'],
            'ref_msg' => [
                'title' => '图片',
                'message_item' => [
                    'type' => Constants::ITEM_TYPE_IMAGE,
                    'image_item' => ['url' => 'https://example.invalid/image.jpg'],
                ],
            ],
        ],
    ],
];

assertSameValue(
    MessageHelper::extractText($quotedMediaMessage),
    'reply body',
    'extractText should not prepend media references',
);

$voiceOnlyMessage = [
    'item_list' => [
        [
            'type' => Constants::ITEM_TYPE_VOICE,
            'voice_item' => ['text' => 'voice transcript'],
        ],
    ],
];

assertSameValue(
    MessageHelper::extractText($voiceOnlyMessage),
    'voice transcript',
    'extractText should fall back to voice transcription',
);

assertSameValue(
    MessageHelper::extractText([
        'item_list' => [
            [
                'type' => Constants::ITEM_TYPE_IMAGE,
                'image_item' => ['url' => 'https://example.invalid/image.jpg'],
            ],
        ],
    ]),
    '',
    'extractText should return empty string when no text exists',
);

assertSameValue(MessageHelper::extractText(['item_list' => []]), '', 'extractText should return empty string when no text exists');

assertSameValue(
    MessageHelper::extractText([
        'item_list' => [
            [
                'type' => Constants::ITEM_TYPE_VOICE,
                'voice_item' => [],
            ],
        ],
    ]),
    '',
    'extractText should ignore voice items without transcription text',
);

assertSameValue(
    MessageHelper::extractText([
        'item_list' => [
            [
                'type' => Constants::ITEM_TYPE_VOICE,
                'voice_item' => ['text' => 'voice transcript'],
            ],
            [
                'type' => Constants::ITEM_TYPE_TEXT,
                'text_item' => ['text' => 'preferred text'],
            ],
        ],
    ]),
    'preferred text',
    'extractText should prioritize text items over voice items',
);

assertTrue(
    MessageHelper::isMediaItem(['type' => Constants::ITEM_TYPE_IMAGE]),
    'isMediaItem should treat images as media',
);
assertTrue(
    !MessageHelper::isMediaItem(['type' => Constants::ITEM_TYPE_TEXT]),
    'isMediaItem should reject text items',
);
assertTrue(
    MessageHelper::isMediaItem(['type' => Constants::ITEM_TYPE_VOICE]),
    'isMediaItem should treat voice as media',
);

fwrite(STDOUT, "MessageHelper tests passed\n");
