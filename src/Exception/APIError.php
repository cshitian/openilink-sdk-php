<?php

declare(strict_types=1);

namespace OpenILink\Exception;

use OpenILink\Constants;
use RuntimeException;

final class APIError extends RuntimeException
{
    public function __construct(
        private readonly int $ret,
        private readonly int $errCode,
        private readonly string $errMsg,
    ) {
        parent::__construct(sprintf('ilink: api error ret=%d errcode=%d errmsg=%s', $ret, $errCode, $errMsg));
    }

    public function getRet(): int
    {
        return $this->ret;
    }

    public function getErrCode(): int
    {
        return $this->errCode;
    }

    public function getErrMsg(): string
    {
        return $this->errMsg;
    }

    public function isSessionExpired(): bool
    {
        return $this->errCode === Constants::SESSION_EXPIRED_ERR_CODE || $this->ret === Constants::SESSION_EXPIRED_ERR_CODE;
    }
}
