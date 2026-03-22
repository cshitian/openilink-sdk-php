<?php

declare(strict_types=1);

namespace OpenILink;

final class MessageHelper
{
    public static function isMediaItem(array $item): bool
    {
        return in_array(
            $item['type'] ?? null,
            [
                Constants::ITEM_TYPE_IMAGE,
                Constants::ITEM_TYPE_VIDEO,
                Constants::ITEM_TYPE_FILE,
                Constants::ITEM_TYPE_VOICE,
            ],
            true,
        );
    }

    public static function extractText(array $message): string
    {
        foreach (($message['item_list'] ?? []) as $item) {
            if (($item['type'] ?? null) === Constants::ITEM_TYPE_TEXT && isset($item['text_item']['text'])) {
                $text = (string) $item['text_item']['text'];
                $ref = $item['ref_msg'] ?? null;

                if (is_array($ref) && isset($ref['message_item']) && is_array($ref['message_item']) && !self::isMediaItem($ref['message_item'])) {
                    $refBody = (string) ($ref['message_item']['text_item']['text'] ?? '');
                    $title = (string) ($ref['title'] ?? '');

                    if ($title !== '' || $refBody !== '') {
                        $text = sprintf('[引用: %s | %s]' . "\n" . '%s', $title, $refBody, $text);
                    }
                }

                return $text;
            }
        }

        foreach (($message['item_list'] ?? []) as $item) {
            if (($item['type'] ?? null) === Constants::ITEM_TYPE_VOICE && isset($item['voice_item']['text']) && $item['voice_item']['text'] !== '') {
                return (string) $item['voice_item']['text'];
            }
        }

        return '';
    }
}
