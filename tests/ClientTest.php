<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Constants.php';
require_once dirname(__DIR__) . '/src/Exception/APIError.php';
require_once dirname(__DIR__) . '/src/Exception/HTTPError.php';
require_once dirname(__DIR__) . '/src/Exception/NoContextTokenException.php';
require_once dirname(__DIR__) . '/src/Exception/RequestException.php';
require_once dirname(__DIR__) . '/src/Client.php';

use OpenILink\Client;
use OpenILink\Constants;
use OpenILink\Exception\APIError;
use OpenILink\Exception\HTTPError;
use OpenILink\Exception\NoContextTokenException;
use OpenILink\Exception\RequestException;
use ReflectionMethod;

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

function invokePrivate(object $object, string $methodName, mixed ...$args): mixed
{
    $method = new ReflectionMethod($object, $methodName);
    $method->setAccessible(true);

    return $method->invokeArgs($object, $args);
}

$client = new Client('mytoken');
assertSameValue($client->getToken(), 'mytoken', 'Client should preserve the provided token');
assertSameValue($client->getBaseUrl(), Constants::DEFAULT_BASE_URL, 'Client should default the API base URL');
assertSameValue($client->getCdnBaseUrl(), Constants::DEFAULT_CDN_BASE_URL, 'Client should default the CDN base URL');
assertSameValue($client->getBotType(), Constants::DEFAULT_BOT_TYPE, 'Client should default the bot type');
assertSameValue($client->getVersion(), '2.1.6', 'Client should default the channel version');

$custom = new Client('tok', [
    'base_url' => 'https://custom.example.com',
    'cdn_base_url' => 'https://cdn.custom.example.com',
    'bot_type' => '5',
    'version' => '2.0.0',
    'route_tag' => 'route-1',
]);
assertSameValue($custom->getBaseUrl(), 'https://custom.example.com', 'Client should accept a custom API base URL');
assertSameValue($custom->getCdnBaseUrl(), 'https://cdn.custom.example.com', 'Client should accept a custom CDN base URL');
assertSameValue($custom->getBotType(), '5', 'Client should accept a custom bot type');
assertSameValue($custom->getVersion(), '2.0.0', 'Client should accept a custom version');
assertSameValue($custom->getRouteTag(), 'route-1', 'Client should accept a custom route tag');

assertSameValue($client->getContextToken('user1'), null, 'Client should not have a context token before one is cached');
$client->setContextToken('user1', 'tok1');
assertSameValue($client->getContextToken('user1'), 'tok1', 'Client should cache context tokens');
$client->setContextToken('user1', 'tok2');
assertSameValue($client->getContextToken('user1'), 'tok2', 'Client should overwrite cached context tokens');

try {
    (new Client(''))->push('user1', 'hello');
    fwrite(STDERR, "push should reject missing context tokens\n");
    exit(1);
} catch (NoContextTokenException) {
}

$headerClient = new Client('  my-token  ', ['route_tag' => 'route-x']);
$headers = invokePrivate($headerClient, 'buildHeaders', '{"test":true}', ['Content-Type' => 'application/json']);
assertSameValue($headers['Content-Type'] ?? '', 'application/json', 'buildHeaders should preserve explicit content type');
assertSameValue($headers['AuthorizationType'] ?? '', 'ilink_bot_token', 'buildHeaders should set AuthorizationType');
assertSameValue($headers['Authorization'] ?? '', 'Bearer my-token', 'buildHeaders should trim the Authorization token');
assertSameValue($headers['SKRouteTag'] ?? '', 'route-x', 'buildHeaders should include SKRouteTag when configured');
assertSameValue($headers['Content-Length'] ?? '', '13', 'buildHeaders should set Content-Length');
assertTrue(($headers['X-WECHAT-UIN'] ?? '') !== '', 'buildHeaders should set X-WECHAT-UIN');

$uploadHeaders = invokePrivate($headerClient, 'buildUploadHeaders', 'binary-data');
assertSameValue($uploadHeaders['Content-Type'] ?? '', 'application/octet-stream', 'buildUploadHeaders should set the upload content type');
assertSameValue($uploadHeaders['Content-Length'] ?? '', '11', 'buildUploadHeaders should set Content-Length');
assertTrue(!array_key_exists('AuthorizationType', $uploadHeaders), 'buildUploadHeaders should omit API auth headers');
assertTrue(!array_key_exists('Authorization', $uploadHeaders), 'buildUploadHeaders should omit bearer auth');
assertTrue(!array_key_exists('X-WECHAT-UIN', $uploadHeaders), 'buildUploadHeaders should omit X-WECHAT-UIN');
assertTrue(!array_key_exists('SKRouteTag', $uploadHeaders), 'buildUploadHeaders should omit SKRouteTag');

$baseInfo = invokePrivate(new Client('', ['version' => '3.0.0']), 'buildBaseInfo');
assertSameValue($baseInfo['channel_version'] ?? '', '3.0.0', 'buildBaseInfo should expose the configured version');

$outboundMessage = invokePrivate(
    $client,
    'buildOutgoingMessage',
    'user-1',
    'client-1',
    'ctx-1',
    [
        [
            'type' => Constants::ITEM_TYPE_TEXT,
            'text_item' => ['text' => 'hello'],
        ],
    ],
);
assertTrue(array_key_exists('from_user_id', $outboundMessage), 'buildOutgoingMessage should always include from_user_id');
assertSameValue($outboundMessage['from_user_id'] ?? null, '', 'buildOutgoingMessage should send an explicit empty from_user_id');
assertSameValue($outboundMessage['to_user_id'] ?? '', 'user-1', 'buildOutgoingMessage should preserve the recipient');
assertSameValue($outboundMessage['client_id'] ?? '', 'client-1', 'buildOutgoingMessage should preserve the client id');
assertSameValue($outboundMessage['context_token'] ?? '', 'ctx-1', 'buildOutgoingMessage should preserve the context token');

$requests = [];
$transportClient = new Client('tok', [
    'base_url' => 'https://api.example.com',
    'route_tag' => 'route-x',
    'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs) use (&$requests): array {
        $requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout_ms' => $timeoutMs,
        ];

        return [
            'status_code' => 200,
            'body' => json_encode([
                'ret' => 0,
                'msgs' => [['message_id' => 1]],
                'get_updates_buf' => 'cursor-1',
                'sync_buf' => 'sync-1',
                'longpolling_timeout_ms' => 40000,
            ], JSON_THROW_ON_ERROR),
            'headers' => ['Content-Type' => 'application/json'],
        ];
    },
]);

$updates = $transportClient->getUpdates('cursor-0');
assertSameValue($updates['ret'] ?? null, 0, 'transport should be able to service getUpdates');
assertSameValue($updates['get_updates_buf'] ?? '', 'cursor-1', 'transport responses should decode like cURL responses');
assertSameValue($updates['sync_buf'] ?? '', 'sync-1', 'transport responses should expose sync_buf');
assertSameValue(count($updates['msgs'] ?? []), 1, 'transport responses should preserve message lists');
assertSameValue($updates['raw_response']['status_code'] ?? 0, 200, 'transport responses should expose the raw status code');
assertSameValue($updates['raw_response']['headers']['content-type'] ?? '', 'application/json', 'transport responses should expose normalized raw headers');
assertTrue(str_contains((string) ($updates['raw_response']['body'] ?? ''), '"sync_buf":"sync-1"'), 'transport responses should expose the raw body');
assertSameValue($requests[0]['method'] ?? '', 'POST', 'transport should receive the HTTP method');
assertSameValue($requests[0]['url'] ?? '', 'https://api.example.com/ilink/bot/getupdates', 'transport should receive the resolved URL');
assertSameValue($requests[0]['headers']['Authorization'] ?? '', 'Bearer tok', 'transport should receive the trimmed bearer token');
assertSameValue($requests[0]['headers']['SKRouteTag'] ?? '', 'route-x', 'transport should receive the route tag header');
$transportPayload = json_decode((string) ($requests[0]['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
assertSameValue($transportPayload['get_updates_buf'] ?? '', 'cursor-0', 'transport should receive the serialized request body');
assertSameValue($transportPayload['base_info']['channel_version'] ?? '', '2.1.6', 'transport should receive base_info in serialized requests');
assertSameValue($requests[0]['timeout_ms'] ?? 0, 35000, 'transport should receive the configured timeout');

try {
    (new Client('tok', [
        'base_url' => 'https://api.example.com',
        'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs): array {
            return [
                'status_code' => 301,
                'body' => 'moved',
                'headers' => ['Location' => 'https://api.example.com/redirected'],
            ];
        },
    ]))->getUpdates();
    fwrite(STDERR, "transport-backed getUpdates should reject redirects\n");
    exit(1);
} catch (HTTPError $exception) {
    assertSameValue($exception->getStatusCode(), 301, 'transport-backed requests should still surface HTTPError for 3xx');
}

$waitStatus = (new Client('tok', [
    'base_url' => 'https://api.example.com',
    'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs): array {
        throw new RequestException('timeout', null, null, 28);
    },
]))->pollQRStatus('qr-1');
assertSameValue($waitStatus['status'] ?? '', 'wait', 'pollQRStatus should treat transport timeouts as a normal wait state');

try {
    (new Client('tok', [
        'base_url' => 'https://api.example.com',
        'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs): array {
            return [
                'status_code' => 500,
                'body' => 'qr failed',
                'headers' => [],
            ];
        },
    ]))->fetchQRCode();
    fwrite(STDERR, "fetchQRCode should wrap request failures with Go-style context\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertSameValue($exception->getMessage(), 'ilink: fetch QR code: ilink: http 500: qr failed', 'fetchQRCode should label QR fetch failures');
    assertTrue($exception->getPrevious() instanceof HTTPError, 'fetchQRCode should preserve the underlying HTTP error');
}

try {
    (new Client('tok', [
        'base_url' => 'https://api.example.com',
        'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs): array {
            return [
                'status_code' => 200,
                'body' => '{',
                'headers' => ['Content-Type' => 'application/json'],
            ];
        },
    ]))->fetchQRCode();
    fwrite(STDERR, "fetchQRCode should wrap JSON decode failures with Go-style context\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertTrue(
        str_starts_with($exception->getMessage(), 'ilink: unmarshal QR response: Failed to decode fetchQRCode response:'),
        'fetchQRCode should label QR decode failures',
    );
    assertTrue($exception->getPrevious() instanceof RuntimeException, 'fetchQRCode should preserve the JSON decode exception');
}

$loginPollCount = 0;
$scanned = false;
$qrImage = '';
$loginClient = new Client('', [
    'base_url' => 'https://api.example.com',
    'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs) use (&$loginPollCount): array {
        if ($method !== 'GET') {
            fwrite(STDERR, "login transport should only receive GET requests\n");
            exit(1);
        }

        if (str_contains($url, 'get_bot_qrcode')) {
            return [
                'status_code' => 200,
                'body' => json_encode([
                    'qrcode' => 'qr1',
                    'qrcode_img_content' => 'img1',
                ], JSON_THROW_ON_ERROR),
                'headers' => ['Content-Type' => 'application/json'],
            ];
        }

        if (str_contains($url, 'get_qrcode_status')) {
            $loginPollCount++;

            if ($loginPollCount === 1) {
                return [
                    'status_code' => 200,
                    'body' => json_encode(['status' => 'scaned'], JSON_THROW_ON_ERROR),
                    'headers' => ['Content-Type' => 'application/json'],
                ];
            }

            return [
                'status_code' => 200,
                'body' => json_encode([
                    'status' => 'confirmed',
                    'bot_token' => 'bot-token',
                    'ilink_bot_id' => 'bot-id',
                    'baseurl' => 'https://new-base.example.com',
                    'ilink_user_id' => 'user-id',
                ], JSON_THROW_ON_ERROR),
                'headers' => ['Content-Type' => 'application/json'],
            ];
        }

        fwrite(STDERR, "login transport received an unexpected URL: {$url}\n");
        exit(1);
    },
]);

$loginResult = $loginClient->loginWithQr([
    'on_qrcode' => static function (string $image) use (&$qrImage): void {
        $qrImage = $image;
    },
    'on_scanned' => static function () use (&$scanned): void {
        $scanned = true;
    },
], 5);
assertTrue((bool) ($loginResult['connected'] ?? false), 'loginWithQr should complete when the transport confirms the QR flow');
assertSameValue($loginResult['bot_token'] ?? '', 'bot-token', 'loginWithQr should return the bot token');
assertSameValue($loginResult['bot_id'] ?? '', 'bot-id', 'loginWithQr should return the bot id');
assertSameValue($loginResult['user_id'] ?? '', 'user-id', 'loginWithQr should return the user id');
assertSameValue($qrImage, 'img1', 'loginWithQr should pass QR image content to callbacks');
assertTrue($scanned, 'loginWithQr should invoke the scanned callback once');
assertSameValue($loginClient->getToken(), 'bot-token', 'loginWithQr should update the client token');
assertSameValue($loginClient->getBaseUrl(), 'https://new-base.example.com', 'loginWithQr should update the client base URL');

try {
    (new Client('', [
        'base_url' => 'https://api.example.com',
        'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs): array {
            if (str_contains($url, 'get_bot_qrcode')) {
                return [
                    'status_code' => 200,
                    'body' => json_encode([
                        'qrcode' => 'qr1',
                        'qrcode_img_content' => 'img1',
                    ], JSON_THROW_ON_ERROR),
                    'headers' => ['Content-Type' => 'application/json'],
                ];
            }

            return [
                'status_code' => 500,
                'body' => 'poll failed',
                'headers' => [],
            ];
        },
    ]))->loginWithQr([], 5);
    fwrite(STDERR, "loginWithQr should wrap poll failures with Go-style context\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertSameValue($exception->getMessage(), 'ilink: poll QR status: ilink: http 500: poll failed', 'loginWithQr should label poll failures');
    assertTrue($exception->getPrevious() instanceof HTTPError, 'loginWithQr should preserve the poll HTTP error');
}

try {
    (new Client('', [
        'base_url' => 'https://api.example.com',
        'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs): array {
            if (str_contains($url, 'get_bot_qrcode')) {
                return [
                    'status_code' => 200,
                    'body' => json_encode([
                        'qrcode' => 'qr1',
                        'qrcode_img_content' => 'img1',
                    ], JSON_THROW_ON_ERROR),
                    'headers' => ['Content-Type' => 'application/json'],
                ];
            }

            return [
                'status_code' => 200,
                'body' => '{',
                'headers' => ['Content-Type' => 'application/json'],
            ];
        },
    ]))->loginWithQr([], 5);
    fwrite(STDERR, "loginWithQr should wrap QR decode failures with Go-style context\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertTrue(
        str_starts_with($exception->getMessage(), 'ilink: poll QR status: ilink: unmarshal QR status: Failed to decode pollQRStatus response:'),
        'loginWithQr should label QR status decode failures',
    );
    assertTrue($exception->getPrevious() instanceof RuntimeException, 'loginWithQr should preserve the QR decode exception');
}

try {
    (new Client('', [
        'base_url' => 'https://api.example.com',
        'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs): array {
            static $refreshAttempt = 0;

            if (str_contains($url, 'get_bot_qrcode')) {
                $refreshAttempt++;

                if ($refreshAttempt === 1) {
                    return [
                        'status_code' => 200,
                        'body' => json_encode([
                            'qrcode' => 'qr1',
                            'qrcode_img_content' => 'img1',
                        ], JSON_THROW_ON_ERROR),
                        'headers' => ['Content-Type' => 'application/json'],
                    ];
                }

                return [
                    'status_code' => 500,
                    'body' => 'refresh failed',
                    'headers' => [],
                ];
            }

            return [
                'status_code' => 200,
                'body' => json_encode(['status' => 'expired'], JSON_THROW_ON_ERROR),
                'headers' => ['Content-Type' => 'application/json'],
            ];
        },
    ]))->loginWithQr([], 5);
    fwrite(STDERR, "loginWithQr should wrap refresh failures with Go-style context\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertSameValue($exception->getMessage(), 'ilink: refresh QR code: ilink: fetch QR code: ilink: http 500: refresh failed', 'loginWithQr should label QR refresh failures');
    assertTrue($exception->getPrevious() instanceof RuntimeException, 'loginWithQr should preserve the wrapped refresh error');
    assertTrue($exception->getPrevious()?->getPrevious() instanceof HTTPError, 'loginWithQr should keep the underlying refresh HTTP error');
}

$monitorRequests = [];
$receivedMessages = [];
$savedBuf = '';
$seenResponses = [];
$keepRunning = true;
$monitorClient = new Client('tok', [
    'base_url' => 'https://api.example.com',
    'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs) use (&$monitorRequests, &$keepRunning): array {
        $monitorRequests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout_ms' => $timeoutMs,
        ];

        $requestIndex = count($monitorRequests);
        if ($requestIndex === 1) {
            return [
                'status_code' => 200,
                'body' => json_encode([
                    'ret' => 0,
                    'msgs' => [
                        [
                            'message_id' => 1,
                            'from_user_id' => 'u1',
                            'context_token' => 'ct1',
                            'item_list' => [['type' => Constants::ITEM_TYPE_TEXT, 'text_item' => ['text' => 'hi']]],
                        ],
                        [
                            'message_id' => 2,
                            'from_user_id' => 'u2',
                            'context_token' => 'ct2',
                        ],
                    ],
                    'get_updates_buf' => 'buf-1',
                    'sync_buf' => 'sync-1',
                    'longpolling_timeout_ms' => 50000,
                ], JSON_THROW_ON_ERROR),
                'headers' => ['Content-Type' => 'application/json'],
            ];
        }

        $keepRunning = false;

        return [
            'status_code' => 200,
            'body' => json_encode([
                'ret' => 0,
                'msgs' => [],
            ], JSON_THROW_ON_ERROR),
            'headers' => ['Content-Type' => 'application/json'],
        ];
    },
]);

$monitorClient->monitor(
    static function (array $message) use (&$receivedMessages): void {
        $receivedMessages[] = $message;
    },
    [
        'on_response' => static function (array $response) use (&$seenResponses): void {
            $seenResponses[] = $response;
        },
        'on_buf_update' => static function (string $buf) use (&$savedBuf): void {
            $savedBuf = $buf;
        },
        'should_continue' => static function () use (&$keepRunning): bool {
            return $keepRunning;
        },
    ],
);

assertSameValue(count($receivedMessages), 2, 'monitor should dispatch each received message');
assertSameValue(count($seenResponses), 2, 'monitor should surface every successful response');
assertSameValue($seenResponses[0]['sync_buf'] ?? '', 'sync-1', 'monitor should surface sync_buf via on_response');
assertSameValue($savedBuf, 'buf-1', 'monitor should persist the latest get_updates_buf');
assertSameValue($monitorClient->getContextToken('u1'), 'ct1', 'monitor should cache context tokens for the first sender');
assertSameValue($monitorClient->getContextToken('u2'), 'ct2', 'monitor should cache context tokens for the second sender');
assertSameValue($seenResponses[0]['raw_response']['status_code'] ?? 0, 200, 'monitor should preserve raw response metadata');
$firstMonitorPayload = json_decode((string) ($monitorRequests[0]['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
$secondMonitorPayload = json_decode((string) ($monitorRequests[1]['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
assertSameValue($firstMonitorPayload['get_updates_buf'] ?? '', '', 'monitor should start from the initial empty cursor');
assertSameValue($secondMonitorPayload['get_updates_buf'] ?? '', 'buf-1', 'monitor should resume from the updated cursor');
assertSameValue($monitorRequests[0]['timeout_ms'] ?? 0, 35000, 'monitor should use the default long-poll timeout for the first request');
assertSameValue($monitorRequests[1]['timeout_ms'] ?? 0, 50000, 'monitor should reuse the server-provided dynamic timeout');

try {
    (new Client('tok', [
        'base_url' => 'https://api.example.com',
        'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs): array {
            if (str_contains($url, 'getuploadurl')) {
                return [
                    'status_code' => 200,
                    'body' => json_encode([
                        'ret' => 123,
                        'errmsg' => 'upload denied',
                    ], JSON_THROW_ON_ERROR),
                    'headers' => ['Content-Type' => 'application/json'],
                ];
            }

            fwrite(STDERR, "sendMediaFile upload test received unexpected URL: {$url}\n");
            exit(1);
        },
    ]))->sendMediaFile('user-1', 'ctx-1', 'payload', 'photo.jpg');
    fwrite(STDERR, "sendMediaFile should wrap upload failures\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertSameValue($exception->getMessage(), 'ilink: upload media: ilink: api error ret=123 errcode=0 errmsg=upload denied', 'sendMediaFile should label upload failures');
    assertTrue($exception->getPrevious() instanceof APIError, 'sendMediaFile should preserve the upload exception as previous');
}

try {
    $requestCount = 0;
    (new Client('tok', [
        'base_url' => 'https://api.example.com',
        'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs) use (&$requestCount): array {
            $requestCount++;

            if (str_contains($url, 'getuploadurl')) {
                return [
                    'status_code' => 200,
                    'body' => json_encode([
                        'ret' => 0,
                        'upload_param' => 'upload-param',
                    ], JSON_THROW_ON_ERROR),
                    'headers' => ['Content-Type' => 'application/json'],
                ];
            }

            if (str_contains($url, 'novac2c.cdn.weixin.qq.com')) {
                return [
                    'status_code' => 200,
                    'body' => '',
                    'headers' => ['x-encrypted-param' => 'download-param'],
                ];
            }

            return [
                'status_code' => 500,
                'body' => 'caption failed',
                'headers' => ['Content-Type' => 'text/plain'],
            ];
        },
    ]))->sendMediaFile('user-1', 'ctx-1', 'payload', 'photo.jpg', 'hello');
    fwrite(STDERR, "sendMediaFile should wrap caption failures\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertSameValue($exception->getMessage(), 'ilink: send caption: ilink: http 500: caption failed', 'sendMediaFile should label caption failures');
    assertTrue($exception->getPrevious() instanceof HTTPError, 'sendMediaFile should preserve the caption exception as previous');
}

try {
    (new Client('tok', [
        'base_url' => 'https://api.example.com',
        'transport' => static function (string $method, string $url, array $headers, ?string $body, int $timeoutMs): array {
            if (str_contains($url, 'getuploadurl')) {
                return [
                    'status_code' => 200,
                    'body' => json_encode([
                        'ret' => 0,
                        'upload_param' => 'upload-param',
                    ], JSON_THROW_ON_ERROR),
                    'headers' => ['Content-Type' => 'application/json'],
                ];
            }

            if (str_contains($url, 'novac2c.cdn.weixin.qq.com')) {
                return [
                    'status_code' => 200,
                    'body' => '',
                    'headers' => ['x-encrypted-param' => 'download-param'],
                ];
            }

            return [
                'status_code' => 500,
                'body' => 'media failed',
                'headers' => ['Content-Type' => 'text/plain'],
            ];
        },
    ]))->sendMediaFile('user-1', 'ctx-1', 'payload', 'photo.jpg');
    fwrite(STDERR, "sendMediaFile should wrap media send failures\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertSameValue($exception->getMessage(), 'ilink: send media: ilink: http 500: media failed', 'sendMediaFile should label media send failures');
    assertTrue($exception->getPrevious() instanceof HTTPError, 'sendMediaFile should preserve the media exception as previous');
}

fwrite(STDOUT, "Client tests passed\n");
