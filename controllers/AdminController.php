<?php
declare(strict_types=1);

namespace AM\Controllers;

use AM\Auth;
use AM\Crypto;
use AM\Database;
use AM\Helper;
use AM\Mailer;
use AM\Session;

class AdminController
{
    public static function dashboard(): void
    {
        Auth::requireAdmin();
        $totalUsers = (int) (Database::fetch('SELECT COUNT(*) AS c FROM users')['c'] ?? 0);
        $todayUsers = (int) (Database::fetch(
            'SELECT COUNT(*) AS c FROM users WHERE DATE(created_at) = CURDATE()'
        )['c'] ?? 0);
        $totalProviders = (int) (Database::fetch('SELECT COUNT(*) AS c FROM api_providers')['c'] ?? 0);
        $totalModels    = (int) (Database::fetch('SELECT COUNT(*) AS c FROM models')['c'] ?? 0);
        $smtpActive = Database::fetch('SELECT * FROM smtp_configs WHERE is_active = 1 LIMIT 1');

        Helper::view('admin/dashboard', [
            'stats' => [
                'total_users'     => $totalUsers,
                'today_users'     => $todayUsers,
                'total_providers' => $totalProviders,
                'total_models'    => $totalModels,
            ],
            'smtp_active' => $smtpActive,
        ]);
    }

    public static function users(): void
    {
        Auth::requireAdmin();
        $users = Database::fetchAll(
            'SELECT id, email, is_admin, status, email_verified_at, last_login_at, last_login_ip, created_at
             FROM users ORDER BY id DESC'
        );
        Helper::view('admin/users', [
            'users'  => $users,
            'notice' => Session::flash('admin_notice'),
        ]);
    }

    public static function toggleUser(array $params): void
    {
        $admin = Auth::requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        if ($id === (int) $admin['id']) {
            Helper::json(['ok' => false, 'message' => '不能禁用自己'], 400);
        }
        $u = Database::fetch('SELECT id, status FROM users WHERE id = :id', ['id' => $id]);
        if (!$u) {
            Helper::json(['ok' => false, 'message' => '用户不存在'], 404);
        }
        $new = (int) $u['status'] === 1 ? 0 : 1;
        Database::update('users', ['status' => $new], 'id = :id', ['id' => $id]);
        Helper::json(['ok' => true, 'status' => $new]);
    }

    public static function deleteUser(array $params): void
    {
        $admin = Auth::requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        if ($id === (int) $admin['id']) {
            Helper::json(['ok' => false, 'message' => '不能删除自己'], 400);
        }
        Database::delete('users', 'id = :id', ['id' => $id]);
        Helper::json(['ok' => true]);
    }

    public static function smtp(): void
    {
        Auth::requireAdmin();
        $cfg = Database::fetch('SELECT * FROM smtp_configs ORDER BY id DESC LIMIT 1');
        Helper::view('admin/smtp', [
            'cfg'    => $cfg,
            'notice' => Session::flash('admin_notice'),
            'error'  => Session::flash('admin_error'),
        ]);
    }

    public static function saveSmtp(): void
    {
        Auth::requireAdmin();
        $host        = trim((string) Helper::input('host', ''));
        $port        = (int) (Helper::input('port', '465') ?? 465);
        $encryption  = (string) Helper::input('encryption', 'ssl');
        $username    = trim((string) Helper::input('username', ''));
        $password    = (string) ($_POST['password'] ?? '');
        $fromAddress = trim((string) Helper::input('from_address', ''));
        $fromName    = trim((string) Helper::input('from_name', '')) ?: 'API Manager';
        $isActive    = !empty($_POST['is_active']) ? 1 : 0;

        if (!$host || !$username || !$fromAddress) {
            Session::flash('admin_error', '请填写必填项');
            Helper::redirect('/admin/smtp');
        }
        if (!in_array($encryption, ['ssl', 'tls', 'none'], true)) {
            $encryption = 'ssl';
        }

        $appKey = $GLOBALS['AM_CONFIG']['app']['app_key'];
        $existing = Database::fetch('SELECT * FROM smtp_configs ORDER BY id DESC LIMIT 1');

        // 密码留空表示不修改
        $passwordEnc = null;
        if ($password !== '') {
            $passwordEnc = Crypto::encrypt($password, $appKey);
        } elseif ($existing) {
            $passwordEnc = $existing['password_encrypted'];
        } else {
            Session::flash('admin_error', '首次配置必须填写密码');
            Helper::redirect('/admin/smtp');
        }

        $data = [
            'host'               => $host,
            'port'               => $port,
            'encryption'         => $encryption,
            'username'           => $username,
            'password_encrypted' => $passwordEnc,
            'from_address'       => $fromAddress,
            'from_name'          => $fromName,
            'is_active'          => $isActive,
        ];

        if ($existing) {
            Database::update('smtp_configs', $data, 'id = :id', ['id' => $existing['id']]);
        } else {
            Database::insert('smtp_configs', $data);
        }
        Session::flash('admin_notice', 'SMTP 配置已保存');
        Helper::redirect('/admin/smtp');
    }

    public static function testSmtp(): void
    {
        Auth::requireAdmin();
        $to = trim((string) Helper::input('to_email', ''));
        if (!Helper::validateEmail($to)) {
            Helper::json(['ok' => false, 'message' => '请输入有效的接收邮箱'], 400);
        }
        try {
            $mailer = Mailer::fromActiveConfig();
            if (!$mailer) {
                Helper::json(['ok' => false, 'message' => '请先保存并启用 SMTP 配置'], 400);
            }
            $site = Helper::siteName();
            $mailer->send(
                $to,
                "【{$site}】SMTP 测试邮件",
                '<h2>SMTP 测试成功 ✅</h2><p>这是一封来自 ' . htmlspecialchars($site) . ' 的测试邮件，证明 SMTP 配置正确。</p>'
            );
            Helper::json(['ok' => true, 'message' => '测试邮件已发送']);
        } catch (\Throwable $e) {
            Helper::json(['ok' => false, 'message' => '发送失败：' . $e->getMessage()], 500);
        }
    }

    public static function settings(): void
    {
        Auth::requireAdmin();
        Helper::view('admin/settings', [
            'site_name'                  => Helper::setting('site_name', 'API Manager'),
            'allow_registration'         => Helper::setting('allow_registration', '1'),
            'require_email_verification' => Helper::setting('require_email_verification', '1'),
            'notice'                     => Session::flash('admin_notice'),
        ]);
    }

    public static function saveSettings(): void
    {
        Auth::requireAdmin();
        $name = trim((string) Helper::input('site_name', 'API Manager')) ?: 'API Manager';
        Helper::setSetting('site_name', $name);
        Helper::setSetting('allow_registration', !empty($_POST['allow_registration']) ? '1' : '0');
        Helper::setSetting('require_email_verification', !empty($_POST['require_email_verification']) ? '1' : '0');
        Session::flash('admin_notice', '设置已保存');
        Helper::redirect('/admin/settings');
    }
}
