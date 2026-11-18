<?php
declare(strict_types=1);

namespace AM;

/**
 * 通用工具函数
 */
class Helper
{
    /** HTML 转义 */
    public static function e(?string $str): string
    {
        return htmlspecialchars((string) $str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** 仅获取请求 input（GET/POST）并 trim */
    public static function input(string $key, ?string $default = null): ?string
    {
        $val = $_POST[$key] ?? $_GET[$key] ?? null;
        if ($val === null) {
            // 尝试 JSON body
            if (isset($GLOBALS['AM_JSON_BODY']) && is_array($GLOBALS['AM_JSON_BODY'])) {
                $val = $GLOBALS['AM_JSON_BODY'][$key] ?? null;
            }
        }
        if ($val === null) {
            return $default;
        }
        if (is_array($val)) {
            return $default;
        }
        return trim((string) $val);
    }

    public static function jsonInput(): array
    {
        if (isset($GLOBALS['AM_JSON_BODY']) && is_array($GLOBALS['AM_JSON_BODY'])) {
            return $GLOBALS['AM_JSON_BODY'];
        }
        $raw = file_get_contents('php://input') ?: '';
        if (!$raw) {
            return [];
        }
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    public static function isAjax(): bool
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            return true;
        }
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    public static function clientIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', $_SERVER[$k])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }

    public static function setting(string $key, ?string $default = null): ?string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                foreach (Database::fetchAll('SELECT setting_key, setting_value FROM settings') as $row) {
                    $cache[$row['setting_key']] = $row['setting_value'];
                }
            } catch (\Throwable) {
                // 表不存在等情况
            }
        }
        return $cache[$key] ?? $default;
    }

    public static function setSetting(string $key, ?string $value): void
    {
        Database::query(
            "INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            ['k' => $key, 'v' => $value]
        );
    }

    public static function url(string $path = ''): string
    {
        $base = rtrim($GLOBALS['AM_CONFIG']['app']['url'] ?? '', '/');
        return $base . '/' . ltrim($path, '/');
    }

    public static function siteName(): string
    {
        return self::setting('site_name', $GLOBALS['AM_CONFIG']['app']['name'] ?? 'API Manager') ?? 'API Manager';
    }

    public static function validateEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /** 渲染视图 */
    public static function view(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $file = AM_ROOT . 'views' . AM_DS . str_replace('/', AM_DS, $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $view);
        }
        require $file;
    }

    /** 渲染分部模板（用于 layout 嵌入） */
    public static function partial(string $view, array $data = []): void
    {
        self::view($view, $data);
    }
}
