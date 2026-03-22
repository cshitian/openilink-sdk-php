<?php

declare(strict_types=1);

namespace OpenILink;

final class Constants
{
    public const DEFAULT_BASE_URL = 'https://ilinkai.weixin.qq.com';
    public const DEFAULT_CDN_BASE_URL = 'https://novac2c.cdn.weixin.qq.com/c2c';
    public const DEFAULT_BOT_TYPE = '3';

    public const MESSAGE_TYPE_NONE = 0;
    public const MESSAGE_TYPE_USER = 1;
    public const MESSAGE_TYPE_BOT = 2;

    public const ITEM_TYPE_NONE = 0;
    public const ITEM_TYPE_TEXT = 1;
    public const ITEM_TYPE_IMAGE = 2;
    public const ITEM_TYPE_VOICE = 3;
    public const ITEM_TYPE_FILE = 4;
    public const ITEM_TYPE_VIDEO = 5;

    public const MESSAGE_STATE_NEW = 0;
    public const MESSAGE_STATE_GENERATING = 1;
    public const MESSAGE_STATE_FINISH = 2;

    public const TYPING = 1;
    public const CANCEL_TYPING = 2;

    public const MEDIA_IMAGE = 1;
    public const MEDIA_VIDEO = 2;
    public const MEDIA_FILE = 3;
    public const MEDIA_VOICE = 4;

    public const SESSION_EXPIRED_ERR_CODE = -14;
}
