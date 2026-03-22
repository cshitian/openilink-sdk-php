<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OpenILink\Client;
use OpenILink\MessageHelper;

$client = new Client('');

echo "正在获取登录二维码...\n";

$result = $client->loginWithQr([
    'on_qrcode' => static function (string $url): void {
        echo "\n请用微信扫描二维码:\n{$url}\n\n";
    },
    'on_scanned' => static function (): void {
        echo "已扫码，请在微信上确认...\n";
    },
    'on_expired' => static function (int $attempt, int $max): void {
        echo "二维码已过期，正在刷新 ({$attempt}/{$max})...\n";
    },
]);

if (!($result['connected'] ?? false)) {
    fwrite(STDERR, "登录未完成: " . ($result['message'] ?? '未知错误') . PHP_EOL);
    exit(1);
}

echo sprintf(
    "登录成功! BotID=%s UserID=%s\n",
    (string) ($result['bot_id'] ?? ''),
    (string) ($result['user_id'] ?? ''),
);

$syncBufFile = __DIR__ . '/sync_buf.dat';
$initialBuf = is_file($syncBufFile) ? (string) file_get_contents($syncBufFile) : '';
$running = true;

if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, static function () use (&$running): void {
        $running = false;
    });
    pcntl_signal(SIGTERM, static function () use (&$running): void {
        $running = false;
    });
}

echo "开始监听消息... (Ctrl+C 退出)\n";

$client->monitor(
    static function (array $message) use ($client): void {
        $text = MessageHelper::extractText($message);
        if ($text === '') {
            return;
        }

        $fromUserId = (string) ($message['from_user_id'] ?? '');
        $contextToken = (string) ($message['context_token'] ?? '');

        echo "[来自 {$fromUserId}]: {$text}\n";

        try {
            $client->sendText($fromUserId, '收到: ' . $text, $contextToken);
        } catch (Throwable $throwable) {
            fwrite(STDERR, '回复失败: ' . $throwable->getMessage() . PHP_EOL);
        }
    },
    [
        'initial_buf' => $initialBuf,
        'on_buf_update' => static function (string $buf) use ($syncBufFile): void {
            file_put_contents($syncBufFile, $buf);
        },
        'should_continue' => static function () use (&$running): bool {
            return $running;
        },
    ],
);

echo "已退出\n";
