<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Constants.php';
require_once dirname(__DIR__) . '/src/Exception/APIError.php';
require_once dirname(__DIR__) . '/src/Exception/HTTPError.php';
require_once dirname(__DIR__) . '/src/Exception/NoContextTokenException.php';
require_once dirname(__DIR__) . '/src/Exception/RequestException.php';

use OpenILink\Exception\APIError;
use OpenILink\Exception\HTTPError;
use OpenILink\Exception\NoContextTokenException;
use OpenILink\Exception\RequestException;

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

$apiError = new APIError(-1, -14, 'session expired');
assertTrue($apiError->getMessage() !== '', 'APIError should produce a non-empty error message');

foreach ([
    [new APIError(0, -14, ''), true],
    [new APIError(-14, 0, ''), true],
    [new APIError(-14, -14, ''), true],
    [new APIError(-1, -1, ''), false],
    [new APIError(0, 0, ''), false],
] as [$error, $expected]) {
    assertSameValue($error->isSessionExpired(), $expected, 'APIError should detect session expiry consistently');
}

$httpError = new HTTPError(500, 'internal error', ['x-test' => '1']);
assertTrue($httpError->getMessage() !== '', 'HTTPError should produce a non-empty error message');
assertSameValue($httpError->getStatusCode(), 500, 'HTTPError should expose the status code');
assertSameValue($httpError->getBody(), 'internal error', 'HTTPError should expose the response body');
assertSameValue($httpError->getHeaders(), ['x-test' => '1'], 'HTTPError should expose response headers');

$requestTimeout = new RequestException('timeout', null, null, 28);
assertTrue($requestTimeout->isTimeout(), 'RequestException should treat curl errno 28 as timeout');
assertTrue(!(new RequestException('boom'))->isTimeout(), 'RequestException should not treat unrelated errors as timeout');

$noContextToken = new NoContextTokenException();
assertTrue($noContextToken->getMessage() !== '', 'NoContextTokenException should produce a non-empty error message');

fwrite(STDOUT, "Error tests passed\n");
