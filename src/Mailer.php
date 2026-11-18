<?php
declare(strict_types=1);

namespace AM;

/**
 * 简易 SMTP 客户端 - 不依赖 PHPMailer
 * 支持 SSL/TLS/无加密、AUTH LOGIN
 */
class Mailer
{
    private string $host;
    private int $port;
    private string $encryption; // ssl|tls|none
    private string $username;
    private string $password;
    private string $fromAddress;
    private string $fromName;
    private int $timeout = 15;
    /** @var resource|null */
    private $socket = null;
    /** 用于调试 */
    public array $log = [];

    public function __construct(array $cfg)
    {
        $this->host = $cfg['host'];
        $this->port = (int) $cfg['port'];
        $this->encryption = strtolower($cfg['encryption'] ?? 'ssl');
        $this->username = $cfg['username'];
        $this->password = $cfg['password'];
        $this->fromAddress = $cfg['from_address'];
        $this->fromName = $cfg['from_name'] ?? 'API Manager';
    }

    /**
     * 发送邮件
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $errno = 0;
        $errstr = '';
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
                'allow_self_signed'=> true,
            ],
        ]);
        $this->socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->socket) {
            throw new \RuntimeException("SMTP 连接失败: $errstr ($errno)");
        }
        stream_set_timeout($this->socket, $this->timeout);

        $this->expect(220);
        $this->cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250);

        if ($this->encryption === 'tls') {
            $this->cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($this->socket, true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT |
                STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT |
                STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                throw new \RuntimeException('STARTTLS 升级失败');
            }
            $this->cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250);
        }

        // 认证
        $this->cmd('AUTH LOGIN', 334);
        $this->cmd(base64_encode($this->username), 334);
        $this->cmd(base64_encode($this->password), 235);

        // 信封
        $this->cmd('MAIL FROM:<' . $this->fromAddress . '>', 250);
        $this->cmd('RCPT TO:<' . $to . '>', [250, 251]);

        // 数据
        $this->cmd('DATA', 354);

        $boundary = 'AM-BOUNDARY-' . md5(uniqid('', true));
        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: =?UTF-8?B?' . base64_encode($this->fromName) . '?= <' . $this->fromAddress . '>';
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $message = implode("\r\n", $headers) . "\r\n\r\n";

        $textBody = $textBody ?? strip_tags($htmlBody);
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($textBody)) . "\r\n";

        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $message .= "--$boundary--\r\n";

        // 转义行首的点
        $message = preg_replace('/^\./m', '..', $message);
        $this->write($message . "\r\n.");
        $this->expect(250);

        $this->cmd('QUIT', 221);
        fclose($this->socket);
        $this->socket = null;
        return true;
    }

    private function cmd(string $cmd, int|array $expectCode): string
    {
        $this->write($cmd);
        return $this->expect($expectCode);
    }

    private function write(string $data): void
    {
        $this->log[] = '> ' . $data;
        fwrite($this->socket, $data . "\r\n");
    }

    private function expect(int|array $codes): string
    {
        $codes = (array) $codes;
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            $this->log[] = '< ' . rtrim($line);
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException("SMTP 错误，期望 " . implode('/', $codes) . "，实际：" . trim($response));
        }
        return $response;
    }

    /**
     * 加载激活的 SMTP 配置
     */
    public static function fromActiveConfig(): ?self
    {
        $row = Database::fetch('SELECT * FROM smtp_configs WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
        if (!$row) {
            return null;
        }
        $appKey = $GLOBALS['AM_CONFIG']['app']['app_key'] ?? '';
        $password = Crypto::decrypt($row['password_encrypted'], $appKey);
        if ($password === null) {
            throw new \RuntimeException('SMTP 密码解密失败，请重新配置');
        }
        return new self([
            'host'         => $row['host'],
            'port'         => (int) $row['port'],
            'encryption'   => $row['encryption'],
            'username'     => $row['username'],
            'password'     => $password,
            'from_address' => $row['from_address'],
            'from_name'    => $row['from_name'] ?? Helper::siteName(),
        ]);
    }

    /**
     * 发送邮箱验证码邮件
     */
    public static function sendVerificationCode(string $to, string $code, string $type = 'register'): bool
    {
        $mailer = self::fromActiveConfig();
        if (!$mailer) {
            throw new \RuntimeException('SMTP 未配置，请联系管理员');
        }
        $siteName = Helper::siteName();
        $title = $type === 'register' ? '注册验证码' : '密码重置验证码';
        $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#0f1419;color:#e6edf3;border-radius:12px">'
              . '<h2 style="color:#58a6ff;margin:0 0 16px">' . htmlspecialchars($siteName) . ' - ' . $title . '</h2>'
              . '<p>您好，</p>'
              . '<p>您的验证码是：</p>'
              . '<div style="font-size:32px;font-weight:bold;letter-spacing:8px;background:#1c2128;padding:16px;text-align:center;border-radius:8px;color:#7ee787">'
              . htmlspecialchars($code) . '</div>'
              . '<p style="color:#8b949e">验证码 10 分钟内有效，请勿泄露给他人。</p>'
              . '<p style="color:#8b949e">如果不是您本人操作，请忽略此邮件。</p>'
              . '</div>';
        return $mailer->send($to, "【{$siteName}】{$title}：{$code}", $html);
    }
}
