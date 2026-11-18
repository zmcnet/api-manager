<?php
declare(strict_types=1);

namespace AM\Controllers;

use AM\Auth;
use AM\Crypto;
use AM\Database;
use AM\Helper;
use AM\Mailer;
use AM\Session;

class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::check()) {
            Helper::redirect('/dashboard');
        }
        Helper::view('auth/login', [
            'error'  => Session::flash('login_error'),
            'notice' => Session::flash('login_notice'),
        ]);
    }

    public static function login(): void
    {
        $email    = Helper::input('email', '') ?? '';
        $password = (string) ($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);

        if (!$email || !$password) {
            Session::flash('login_error', '请填写邮箱和密码');
            Helper::redirect('/login');
        }
        $r = Auth::attempt($email, $password, $remember);
        if (!$r['ok']) {
            Session::flash('login_error', $r['message']);
            Helper::redirect('/login');
        }
        // 区分管理员
        $u = Auth::user();
        Helper::redirect((int) ($u['is_admin'] ?? 0) === 1 ? '/admin' : '/dashboard');
    }

    public static function showRegister(): void
    {
        if (Auth::check()) {
            Helper::redirect('/dashboard');
        }
        if (Helper::setting('allow_registration', '1') !== '1') {
            Helper::view('errors/closed', ['message' => '注册已关闭']);
            return;
        }
        Helper::view('auth/register', [
            'error'  => Session::flash('register_error'),
            'notice' => Session::flash('register_notice'),
            'requireVerify' => Helper::setting('require_email_verification', '1') === '1',
        ]);
    }

    /** 发送注册验证码（AJAX） */
    public static function sendRegisterCode(): void
    {
        $email = strtolower(trim(Helper::input('email', '') ?? ''));
        if (!Helper::validateEmail($email)) {
            Helper::json(['ok' => false, 'message' => '邮箱格式不正确'], 400);
        }
        $exists = Database::fetch('SELECT id FROM users WHERE email = :e', ['e' => $email]);
        if ($exists) {
            Helper::json(['ok' => false, 'message' => '该邮箱已被注册'], 400);
        }

        // 限频：60 秒内只能请求一次
        $recent = Database::fetch(
            "SELECT id FROM verification_codes
             WHERE email = :e AND type='register' AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)
             ORDER BY id DESC LIMIT 1",
            ['e' => $email]
        );
        if ($recent) {
            Helper::json(['ok' => false, 'message' => '请求太频繁，请 60 秒后重试'], 429);
        }

        $code = (string) random_int(100000, 999999);
        Database::insert('verification_codes', [
            'email'      => $email,
            'code'       => $code,
            'type'       => 'register',
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
        ]);

        try {
            Mailer::sendVerificationCode($email, $code, 'register');
        } catch (\Throwable $e) {
            Helper::json(['ok' => false, 'message' => '邮件发送失败：' . $e->getMessage()], 500);
        }

        Helper::json(['ok' => true, 'message' => '验证码已发送，10 分钟内有效']);
    }

    public static function register(): void
    {
        if (Helper::setting('allow_registration', '1') !== '1') {
            Session::flash('register_error', '注册已关闭');
            Helper::redirect('/register');
        }

        $email    = strtolower(trim(Helper::input('email', '') ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');
        $code     = trim((string) ($_POST['code'] ?? ''));
        $needVerify = Helper::setting('require_email_verification', '1') === '1';

        if (!Helper::validateEmail($email)) {
            Session::flash('register_error', '邮箱格式不正确');
            Helper::redirect('/register');
        }
        if (strlen($password) < 8) {
            Session::flash('register_error', '密码至少 8 位');
            Helper::redirect('/register');
        }
        if ($password !== $confirm) {
            Session::flash('register_error', '两次密码不一致');
            Helper::redirect('/register');
        }
        if (Database::fetch('SELECT id FROM users WHERE email = :e', ['e' => $email])) {
            Session::flash('register_error', '该邮箱已被注册');
            Helper::redirect('/register');
        }

        $verifiedAt = null;
        if ($needVerify) {
            if (!$code) {
                Session::flash('register_error', '请输入邮箱验证码');
                Helper::redirect('/register');
            }
            $vc = Database::fetch(
                "SELECT * FROM verification_codes
                 WHERE email = :e AND code = :c AND type='register' AND used = 0 AND expires_at > NOW()
                 ORDER BY id DESC LIMIT 1",
                ['e' => $email, 'c' => $code]
            );
            if (!$vc) {
                Session::flash('register_error', '验证码无效或已过期');
                Helper::redirect('/register');
            }
            Database::update('verification_codes', ['used' => 1], 'id = :id', ['id' => $vc['id']]);
            $verifiedAt = date('Y-m-d H:i:s');
        }

        $userId = Database::insert('users', [
            'email'             => $email,
            'password_hash'     => password_hash($password, PASSWORD_BCRYPT),
            'encryption_key'    => Crypto::newKeyHex(),
            'email_verified_at' => $verifiedAt,
        ]);

        Auth::login($userId, false);
        Helper::redirect('/dashboard');
    }

    public static function logout(): void
    {
        Auth::logout();
        Helper::redirect('/login');
    }

    public static function showForgot(): void
    {
        Helper::view('auth/forgot', [
            'error'  => Session::flash('forgot_error'),
            'notice' => Session::flash('forgot_notice'),
        ]);
    }

    public static function sendResetCode(): void
    {
        $email = strtolower(trim(Helper::input('email', '') ?? ''));
        if (!Helper::validateEmail($email)) {
            Helper::json(['ok' => false, 'message' => '邮箱格式不正确'], 400);
        }
        $u = Database::fetch('SELECT id FROM users WHERE email = :e', ['e' => $email]);
        if (!$u) {
            // 安全：不暴露用户是否存在
            Helper::json(['ok' => true, 'message' => '若邮箱存在，验证码已发送']);
        }

        $recent = Database::fetch(
            "SELECT id FROM verification_codes
             WHERE email = :e AND type='reset' AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)
             ORDER BY id DESC LIMIT 1",
            ['e' => $email]
        );
        if ($recent) {
            Helper::json(['ok' => false, 'message' => '请求太频繁，请 60 秒后重试'], 429);
        }

        $code = (string) random_int(100000, 999999);
        Database::insert('verification_codes', [
            'email'      => $email,
            'code'       => $code,
            'type'       => 'reset',
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
        ]);

        try {
            Mailer::sendVerificationCode($email, $code, 'reset');
        } catch (\Throwable $e) {
            Helper::json(['ok' => false, 'message' => '邮件发送失败：' . $e->getMessage()], 500);
        }

        Helper::json(['ok' => true, 'message' => '验证码已发送，10 分钟内有效']);
    }

    public static function resetPassword(): void
    {
        $email    = strtolower(trim(Helper::input('email', '') ?? ''));
        $code     = trim((string) ($_POST['code'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8 || $password !== $confirm) {
            Session::flash('forgot_error', '密码至少 8 位且两次输入需一致');
            Helper::redirect('/forgot');
        }
        $vc = Database::fetch(
            "SELECT * FROM verification_codes
             WHERE email = :e AND code = :c AND type='reset' AND used = 0 AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1",
            ['e' => $email, 'c' => $code]
        );
        if (!$vc) {
            Session::flash('forgot_error', '验证码无效或已过期');
            Helper::redirect('/forgot');
        }
        $u = Database::fetch('SELECT id FROM users WHERE email = :e', ['e' => $email]);
        if (!$u) {
            Session::flash('forgot_error', '账户不存在');
            Helper::redirect('/forgot');
        }

        Database::update('users', [
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ], 'id = :id', ['id' => $u['id']]);

        Database::update('verification_codes', ['used' => 1], 'id = :id', ['id' => $vc['id']]);
        Session::flash('login_notice', '密码已重置，请重新登录');
        Helper::redirect('/login');
    }
}
