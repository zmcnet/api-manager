<?php
declare(strict_types=1);

namespace AM;

/**
 * 用户认证
 */
class Auth
{
    public static function user(): ?array
    {
        $id = Session::get('user_id');
        if (!$id) {
            // 尝试 remember token 自动登录
            self::tryRememberLogin();
            $id = Session::get('user_id');
            if (!$id) {
                return null;
            }
        }
        $user = Database::fetch('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$user || (int) $user['status'] !== 1) {
            self::logout();
            return null;
        }
        return $user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && (int) $u['is_admin'] === 1;
    }

    public static function attempt(string $email, string $password, bool $remember = false): array
    {
        $email = strtolower(trim($email));
        $user = Database::fetch('SELECT * FROM users WHERE email = :e LIMIT 1', ['e' => $email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'message' => '邮箱或密码错误'];
        }
        if ((int) $user['status'] !== 1) {
            return ['ok' => false, 'message' => '账户已被禁用'];
        }
        $requireVerify = Helper::setting('require_email_verification', '1') === '1';
        if ($requireVerify && empty($user['email_verified_at']) && (int) $user['is_admin'] !== 1) {
            return ['ok' => false, 'message' => '邮箱未验证，请先完成邮箱验证'];
        }

        self::login((int) $user['id'], $remember);
        return ['ok' => true];
    }

    public static function login(int $userId, bool $remember = false): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);

        Database::update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => Helper::clientIp(),
        ], 'id = :id', ['id' => $userId]);

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            Database::update('users', ['remember_token' => $token], 'id = :id', ['id' => $userId]);
            $lifetime = (int) ($GLOBALS['AM_CONFIG']['session']['remember_lifetime'] ?? 60 * 60 * 24 * 30);
            setcookie('AM_REMEMBER', $userId . '|' . $token, [
                'expires'  => time() + $lifetime,
                'path'     => '/',
                'httponly' => true,
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function logout(): void
    {
        $uid = Session::get('user_id');
        if ($uid) {
            Database::update('users', ['remember_token' => null], 'id = :id', ['id' => $uid]);
        }
        Session::destroy();
        if (isset($_COOKIE['AM_REMEMBER'])) {
            setcookie('AM_REMEMBER', '', time() - 3600, '/');
        }
    }

    public static function tryRememberLogin(): void
    {
        if (empty($_COOKIE['AM_REMEMBER'])) {
            return;
        }
        $parts = explode('|', $_COOKIE['AM_REMEMBER'], 2);
        if (count($parts) !== 2) {
            return;
        }
        [$id, $token] = $parts;
        if (!ctype_digit($id) || !ctype_xdigit($token)) {
            return;
        }
        $user = Database::fetch(
            'SELECT * FROM users WHERE id = :id AND remember_token = :t AND status = 1 LIMIT 1',
            ['id' => (int) $id, 't' => $token]
        );
        if ($user) {
            Session::set('user_id', (int) $user['id']);
        }
    }

    /** 要求登录，否则跳转登录页 */
    public static function requireLogin(): array
    {
        $u = self::user();
        if (!$u) {
            if (Helper::isAjax()) {
                Helper::json(['ok' => false, 'message' => '未登录', 'redirect' => '/login'], 401);
            }
            Helper::redirect('/login');
        }
        return $u;
    }

    public static function requireAdmin(): array
    {
        $u = self::requireLogin();
        if ((int) $u['is_admin'] !== 1) {
            http_response_code(403);
            echo '<h1>403 Forbidden</h1>';
            exit;
        }
        return $u;
    }
}
