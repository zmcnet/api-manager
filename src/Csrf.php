<?php
declare(strict_types=1);

namespace AM;

/**
 * CSRF Token 管理
 */
class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    public static function check(?string $token): bool
    {
        $stored = Session::get(self::KEY);
        return is_string($stored) && is_string($token) && hash_equals($stored, $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * 验证 POST/JSON 请求中的 CSRF token，失败时直接 403
     */
    public static function verify(): void
    {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return;
        }
        $token = $_POST['_csrf']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? null;

        // JSON body
        if (!$token) {
            $raw = file_get_contents('php://input') ?: '';
            if ($raw && ($json = json_decode($raw, true)) && is_array($json)) {
                $token = $json['_csrf'] ?? null;
                $GLOBALS['AM_JSON_BODY'] = $json;
            }
        }

        if (!self::check(is_string($token) ? $token : null)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'CSRF token 无效或已过期']);
            exit;
        }
    }
}
