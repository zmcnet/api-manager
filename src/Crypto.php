<?php
declare(strict_types=1);

namespace AM;

/**
 * AES-256-CBC 加密 / HMAC 校验
 *
 * 存储格式（base64）： base64(iv . ciphertext . hmac)
 * - iv: 16 bytes
 * - hmac: sha256(iv . ciphertext, key)
 *
 * 每个用户拥有独立 encryption_key（hex 32 bytes -> 64 chars）
 * 应用密钥 app_key 用于加密 SMTP 密码等系统级数据
 */
class Crypto
{
    public const CIPHER = 'aes-256-cbc';

    /**
     * 生成新的随机密钥（hex 编码，32 字节 = 64 hex chars）
     */
    public static function newKeyHex(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * 加密
     * @param string $plain 明文
     * @param string $keyHex hex 密钥（64 chars，对应 32 字节）
     * @return string base64 编码的密文（含 IV+HMAC）
     */
    public static function encrypt(string $plain, string $keyHex): string
    {
        $key = self::keyBytes($keyHex);
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed');
        }
        $hmac = hash_hmac('sha256', $iv . $cipher, $key, true);
        return base64_encode($iv . $cipher . $hmac);
    }

    /**
     * 解密
     * @return string|null 解密失败返回 null
     */
    public static function decrypt(string $payload, string $keyHex): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false) {
            return null;
        }
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($raw) < $ivLen + 32 + 1) {
            return null;
        }
        $key = self::keyBytes($keyHex);
        $iv = substr($raw, 0, $ivLen);
        $hmac = substr($raw, -32);
        $cipher = substr($raw, $ivLen, -32);
        $expected = hash_hmac('sha256', $iv . $cipher, $key, true);
        if (!hash_equals($expected, $hmac)) {
            return null;
        }
        $plain = openssl_decrypt($cipher, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $plain === false ? null : $plain;
    }

    private static function keyBytes(string $keyHex): string
    {
        if (strlen($keyHex) !== 64 || !ctype_xdigit($keyHex)) {
            throw new \InvalidArgumentException('Invalid hex key (must be 64 hex chars)');
        }
        return hex2bin($keyHex);
    }
}
