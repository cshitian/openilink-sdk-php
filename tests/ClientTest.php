<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Constants.php';
require_once dirname(__DIR__) . '/src/Exception/NoContextTokenException.php';
require_once dirname(__DIR__) . '/src/Client.php';

use OpenILink\Client;
use OpenILink\Constants;
use OpenILink\Exception\NoContextTokenException;
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
assertSameValue($client->getVersion(), '1.0.2', 'Client should default the channel version');

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

fwrite(STDOUT, "Client tests passed\n");
