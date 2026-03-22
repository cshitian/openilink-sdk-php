<?php

declare(strict_types=1);

namespace OpenILink;

final class MessageHelper
{
    public static function extractText(array $message): string
    {
        foreach (($message['item_list'] ?? []) as $item) {
            if (($item['type'] ?? null) === Constants::ITEM_TYPE_TEXT && isset($item['text_item']['text'])) {
                return (string) $item['text_item']['text'];
            }
        }

        return '';
    }
}
