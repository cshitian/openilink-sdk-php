<?php

declare(strict_types=1);

namespace OpenILink\Exception;

use RuntimeException;

final class NoContextTokenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('ilink: no cached context token; user must send a message first');
    }
}
