<?php

declare(strict_types=1);

namespace OpenILink;

use JsonException;
use RuntimeException;
use OpenILink\Exception\NoContextTokenException;
use OpenILink\Exception\RequestException;

final class Client
{
    private const DEFAULT_LONG_POLL_TIMEOUT = 35;
    private const DEFAULT_API_TIMEOUT = 15;
    private const QR_LONG_POLL_TIMEOUT = 35;
    private const DEFAULT_LOGIN_TIMEOUT = 480;
    private const MAX_QR_REFRESH_COUNT = 3;
    private const MAX_CONSECUTIVE_FAILURES = 3;
    private const BACKOFF_DELAY_SECONDS = 30;
    private const RETRY_DELAY_SECONDS = 2;

    private string $baseUrl;
    private string $cdnBaseUrl;
    private string $token;
    private string $botType;
    private string $version;

    /**
     * @var array<string, string>
     */
    private array $contextTokens = [];

    public function __construct(string $token = '', array $config = [])
    {
        $this->baseUrl = (string) ($config['base_url'] ?? Constants::DEFAULT_BASE_URL);
        $this->cdnBaseUrl = (string) ($config['cdn_base_url'] ?? Constants::DEFAULT_CDN_BASE_URL);
        $this->token = $token;
        $this->botType = (string) ($config['bot_type'] ?? Constants::DEFAULT_BOT_TYPE);
        $this->version = (string) ($config['version'] ?? '1.0.0');
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    public function getCdnBaseUrl(): string
    {
        return $this->cdnBaseUrl;
    }

    public function setCdnBaseUrl(string $cdnBaseUrl): void
    {
        $this->cdnBaseUrl = $cdnBaseUrl;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getBotType(): string
    {
        return $this->botType;
    }

    public function setBotType(string $botType): void
    {
        $this->botType = $botType;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getUpdates(string $getUpdatesBuf = ''): array
    {
        $request = [
            'get_updates_buf' => $getUpdatesBuf,
            'base_info' => $this->buildBaseInfo(),
        ];

        try {
            $body = $this->doPost('ilink/bot/getupdates', $request, self::DEFAULT_LONG_POLL_TIMEOUT + 5);
        } catch (RequestException $exception) {
            if ($exception->isTimeout()) {
                return [
                    'ret' => 0,
                    'get_updates_buf' => $getUpdatesBuf,
                ];
            }

            throw $exception;
        }

        return $this->decodeJson($body, 'getUpdates');
    }

    public function sendMessage(array $message): void
    {
        $request = [
            'msg' => $message,
            'base_info' => $this->buildBaseInfo(),
        ];

        $this->doPost('ilink/bot/sendmessage', $request, self::DEFAULT_API_TIMEOUT);
    }

    public function sendText(string $to, string $text, string $contextToken): string
    {
        $clientId = 'sdk-' . $this->nowMillis();

        $message = [
            'to_user_id' => $to,
            'client_id' => $clientId,
            'message_type' => Constants::MESSAGE_TYPE_BOT,
            'message_state' => Constants::MESSAGE_STATE_FINISH,
            'context_token' => $contextToken,
            'item_list' => [
                [
                    'type' => Constants::ITEM_TYPE_TEXT,
                    'text_item' => [
                        'text' => $text,
                    ],
                ],
            ],
        ];

        $this->sendMessage($message);

        return $clientId;
    }

    public function getConfig(string $userId, string $contextToken): array
    {
        $request = [
            'ilink_user_id' => $userId,
            'context_token' => $contextToken,
            'base_info' => $this->buildBaseInfo(),
        ];

        $body = $this->doPost('ilink/bot/getconfig', $request, 10);

        return $this->decodeJson($body, 'getConfig');
    }

    public function sendTyping(string $userId, string $typingTicket, int $status): void
    {
        $request = [
            'ilink_user_id' => $userId,
            'typing_ticket' => $typingTicket,
            'status' => $status,
            'base_info' => $this->buildBaseInfo(),
        ];

        $this->doPost('ilink/bot/sendtyping', $request, 10);
    }

    public function getUploadUrl(array $request): array
    {
        $request['base_info'] = $this->buildBaseInfo();

        $body = $this->doPost('ilink/bot/getuploadurl', $request, self::DEFAULT_API_TIMEOUT);

        return $this->decodeJson($body, 'getUploadUrl');
    }

    public function fetchQRCode(): array
    {
        $query = http_build_query([
            'bot_type' => $this->botType !== '' ? $this->botType : Constants::DEFAULT_BOT_TYPE,
        ]);

        $body = $this->doGet($this->buildUrl('ilink/bot/get_bot_qrcode') . '?' . $query, [], 15);

        return $this->decodeJson($body, 'fetchQRCode');
    }

    public function pollQRStatus(string $qrcode): array
    {
        $query = http_build_query(['qrcode' => $qrcode]);

        try {
            $body = $this->doGet(
                $this->buildUrl('ilink/bot/get_qrcode_status') . '?' . $query,
                ['iLink-App-ClientVersion' => '1'],
                self::QR_LONG_POLL_TIMEOUT + 5,
            );
        } catch (RequestException $exception) {
            if ($exception->isTimeout()) {
                return ['status' => 'wait'];
            }

            throw $exception;
        }

        return $this->decodeJson($body, 'pollQRStatus');
    }

    /**
     * @param array{
     *     on_qrcode?: callable(string): void,
     *     on_scanned?: callable(): void,
     *     on_expired?: callable(int, int): void
     * } $callbacks
     */
    public function loginWithQr(array $callbacks = [], ?int $timeoutSeconds = null): array
    {
        $deadline = time() + ($timeoutSeconds ?? self::DEFAULT_LOGIN_TIMEOUT);
        $qr = $this->fetchQRCode();
        $currentQr = (string) ($qr['qrcode'] ?? '');

        $this->invokeCallback($callbacks['on_qrcode'] ?? null, (string) ($qr['qrcode_img_content'] ?? ''));

        $scannedNotified = false;
        $refreshCount = 1;

        while (time() <= $deadline) {
            $status = $this->pollQRStatus($currentQr);

            switch ((string) ($status['status'] ?? 'wait')) {
                case 'scaned':
                    if (!$scannedNotified) {
                        $scannedNotified = true;
                        $this->invokeCallback($callbacks['on_scanned'] ?? null);
                    }
                    break;

                case 'expired':
                    $refreshCount++;
                    if ($refreshCount > self::MAX_QR_REFRESH_COUNT) {
                        return [
                            'connected' => false,
                            'message' => '登录超时：二维码多次过期。',
                        ];
                    }

                    $this->invokeCallback(
                        $callbacks['on_expired'] ?? null,
                        $refreshCount,
                        self::MAX_QR_REFRESH_COUNT,
                    );

                    $qr = $this->fetchQRCode();
                    $currentQr = (string) ($qr['qrcode'] ?? '');
                    $scannedNotified = false;
                    $this->invokeCallback($callbacks['on_qrcode'] ?? null, (string) ($qr['qrcode_img_content'] ?? ''));
                    break;

                case 'confirmed':
                    $botId = (string) ($status['ilink_bot_id'] ?? '');
                    if ($botId === '') {
                        return [
                            'connected' => false,
                            'message' => '登录失败：服务器未返回 bot ID。',
                        ];
                    }

                    $this->token = (string) ($status['bot_token'] ?? '');
                    if (!empty($status['baseurl'])) {
                        $this->baseUrl = (string) $status['baseurl'];
                    }

                    return [
                        'connected' => true,
                        'bot_token' => (string) ($status['bot_token'] ?? ''),
                        'bot_id' => $botId,
                        'base_url' => (string) ($status['baseurl'] ?? ''),
                        'user_id' => (string) ($status['ilink_user_id'] ?? ''),
                        'message' => '与微信连接成功！',
                    ];
            }

            $this->sleepSeconds(1);
        }

        return [
            'connected' => false,
            'message' => '登录超时，请重试。',
        ];
    }

    /**
     * @param callable(array): void $handler
     * @param array{
     *     initial_buf?: string,
     *     on_buf_update?: callable(string): void,
     *     on_error?: callable(\Throwable): void,
     *     on_session_expired?: callable(): void,
     *     should_continue?: callable(): bool
     * } $options
     */
    public function monitor(callable $handler, array $options = []): void
    {
        $buf = (string) ($options['initial_buf'] ?? '');
        $failures = 0;

        $onError = $options['on_error'] ?? static function (\Throwable $exception): void {
        };

        while ($this->shouldContinue($options['should_continue'] ?? null)) {
            try {
                $response = $this->getUpdates($buf);
            } catch (\Throwable $exception) {
                $failures++;
                $onError(
                    new RuntimeException(
                        sprintf('getUpdates (%d/%d): %s', $failures, self::MAX_CONSECUTIVE_FAILURES, $exception->getMessage()),
                        0,
                        $exception,
                    ),
                );

                if ($failures >= self::MAX_CONSECUTIVE_FAILURES) {
                    $failures = 0;
                    $this->sleepSeconds(self::BACKOFF_DELAY_SECONDS, $options['should_continue'] ?? null);
                } else {
                    $this->sleepSeconds(self::RETRY_DELAY_SECONDS, $options['should_continue'] ?? null);
                }

                continue;
            }

            $ret = (int) ($response['ret'] ?? 0);
            $errCode = (int) ($response['errcode'] ?? 0);

            if ($ret !== 0 || $errCode !== 0) {
                if ($ret === Constants::SESSION_EXPIRED_ERR_CODE || $errCode === Constants::SESSION_EXPIRED_ERR_CODE) {
                    $this->invokeCallback($options['on_session_expired'] ?? null);
                    $onError(new RuntimeException('session expired (errcode -14), pausing 5 min'));
                    $this->sleepSeconds(300, $options['should_continue'] ?? null);
                    continue;
                }

                $failures++;
                $onError(
                    new RuntimeException(
                        sprintf(
                            'getUpdates ret=%d errcode=%d msg=%s (%d/%d)',
                            $ret,
                            $errCode,
                            (string) ($response['errmsg'] ?? ''),
                            $failures,
                            self::MAX_CONSECUTIVE_FAILURES,
                        ),
                    ),
                );

                if ($failures >= self::MAX_CONSECUTIVE_FAILURES) {
                    $failures = 0;
                    $this->sleepSeconds(self::BACKOFF_DELAY_SECONDS, $options['should_continue'] ?? null);
                } else {
                    $this->sleepSeconds(self::RETRY_DELAY_SECONDS, $options['should_continue'] ?? null);
                }

                continue;
            }

            $failures = 0;

            if (!empty($response['get_updates_buf'])) {
                $buf = (string) $response['get_updates_buf'];
                $this->invokeCallback($options['on_buf_update'] ?? null, $buf);
            }

            foreach (($response['msgs'] ?? []) as $message) {
                if (!empty($message['context_token']) && !empty($message['from_user_id'])) {
                    $this->setContextToken((string) $message['from_user_id'], (string) $message['context_token']);
                }

                $handler($message);
            }
        }
    }

    public function setContextToken(string $userId, string $token): void
    {
        $this->contextTokens[$userId] = $token;
    }

    public function getContextToken(string $userId): ?string
    {
        return $this->contextTokens[$userId] ?? null;
    }

    public function push(string $to, string $text): string
    {
        $token = $this->getContextToken($to);
        if ($token === null || $token === '') {
            throw new NoContextTokenException();
        }

        return $this->sendText($to, $text, $token);
    }

    private function buildBaseInfo(): array
    {
        return [
            'channel_version' => $this->version,
        ];
    }

    private function buildUrl(string $endpoint): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    private function doPost(string $endpoint, array $payload, int $timeoutSeconds): string
    {
        try {
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode request body: ' . $exception->getMessage(), 0, $exception);
        }

        $headers = [
            'Content-Type' => 'application/json',
            'AuthorizationType' => 'ilink_bot_token',
            'Content-Length' => (string) strlen($json),
            'X-WECHAT-UIN' => $this->randomWechatUin(),
        ];

        if ($this->token !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return $this->request('POST', $this->buildUrl($endpoint), $headers, $json, $timeoutSeconds);
    }

    private function doGet(string $url, array $headers, int $timeoutSeconds): string
    {
        return $this->request('GET', $url, $headers, null, $timeoutSeconds);
    }

    private function request(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('ext-curl is required.');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $options);

        $responseBody = curl_exec($curl);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        if ($responseBody === false) {
            throw new RequestException(
                'HTTP request failed: ' . $curlError,
                null,
                null,
                $curlErrno,
            );
        }

        $responseBody = (string) $responseBody;

        if ($statusCode >= 400) {
            throw new RequestException(
                sprintf('HTTP %d: %s', $statusCode, $responseBody),
                $statusCode,
                $responseBody,
            );
        }

        return $responseBody;
    }

    private function decodeJson(string $body, string $operation): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                sprintf('Failed to decode %s response: %s', $operation, $exception->getMessage()),
                0,
                $exception,
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('%s response is not a JSON object.', $operation));
        }

        return $decoded;
    }

    private function randomWechatUin(): string
    {
        return base64_encode((string) hexdec(bin2hex(random_bytes(4))));
    }

    private function nowMillis(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private function invokeCallback(?callable $callback, mixed ...$args): void
    {
        if ($callback !== null) {
            $callback(...$args);
        }
    }

    private function shouldContinue(?callable $callback): bool
    {
        if ($callback === null) {
            return true;
        }

        return (bool) $callback();
    }

    private function sleepSeconds(int $seconds, ?callable $shouldContinue = null): void
    {
        $target = microtime(true) + $seconds;

        while (microtime(true) < $target) {
            if ($shouldContinue !== null && !$this->shouldContinue($shouldContinue)) {
                return;
            }

            usleep(250000);
        }
    }
}
