<?php
/**
 * 安装向导
 * 步骤：
 *  1. 环境检查
 *  2. 数据库配置
 *  3. 管理员账号
 *  4. 完成
 */
declare(strict_types=1);

session_start();

$root = __DIR__;
$configFile = $root . '/config.php';

// 已安装则禁止重复访问
if (is_file($configFile)) {
    $cfg = require $configFile;
    if (!empty($cfg['installed'])) {
        die('系统已安装。如需重新安装，请删除 config.php 文件。');
    }
}

$step = (int) ($_GET['step'] ?? 1);
$errors = [];

// ---------- 步骤 1：环境检查 ----------
function checkEnv(): array
{
    return [
        'PHP 版本 >= 8.2'        => ['ok' => version_compare(PHP_VERSION, '8.2.0', '>='), 'value' => PHP_VERSION],
        'PDO MySQL 扩展'         => ['ok' => extension_loaded('pdo_mysql'), 'value' => extension_loaded('pdo_mysql') ? '已加载' : '未加载'],
        'OpenSSL 扩展'           => ['ok' => extension_loaded('openssl'),   'value' => extension_loaded('openssl') ? '已加载' : '未加载'],
        'mbstring 扩展'          => ['ok' => extension_loaded('mbstring'),  'value' => extension_loaded('mbstring') ? '已加载' : '未加载'],
        'JSON 扩展'              => ['ok' => extension_loaded('json'),      'value' => extension_loaded('json') ? '已加载' : '未加载'],
        'config.php 可写（项目根目录）' => ['ok' => is_writable(__DIR__),         'value' => is_writable(__DIR__) ? '可写' : '不可写'],
    ];
}

// ---------- 步骤 2：测试数据库连接 ----------
function testDb(array $db): array
{
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], (int) $db['port']);
        $pdo = new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        // 创建数据库
        $pdo->exec(sprintf(
            "CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
            str_replace('`', '', $db['name'])
        ));
        $pdo->exec('USE `' . str_replace('`', '', $db['name']) . '`');
        return ['ok' => true, 'pdo' => $pdo];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

// ---------- 处理 POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        // 保存数据库信息到 session
        $_SESSION['install_db'] = [
            'host'     => trim($_POST['db_host'] ?? '127.0.0.1'),
            'port'     => (int) ($_POST['db_port'] ?? 3306),
            'name'     => trim($_POST['db_name'] ?? 'api_manager'),
            'user'     => trim($_POST['db_user'] ?? 'root'),
            'password' => $_POST['db_password'] ?? '',
        ];
        $r = testDb($_SESSION['install_db']);
        if (!$r['ok']) {
            $errors[] = '数据库连接失败：' . $r['message'];
        } else {
            // 导入 SQL
            $sql = file_get_contents($root . '/sql/install.sql');
            if ($sql === false) {
                $errors[] = '无法读取 sql/install.sql';
            } else {
                try {
                    $r['pdo']->exec($sql);
                    header('Location: install.php?step=3');
                    exit;
                } catch (Throwable $e) {
                    $errors[] = '导入数据表失败：' . $e->getMessage();
                }
            }
        }
    } elseif ($step === 3) {
        $email    = strtolower(trim($_POST['admin_email'] ?? ''));
        $password = $_POST['admin_password'] ?? '';
        $confirm  = $_POST['admin_password_confirm'] ?? '';
        $siteName = trim($_POST['site_name'] ?? 'API Manager');
        $appUrl   = rtrim(trim($_POST['app_url'] ?? ''), '/');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '请输入有效的邮箱';
        }
        if (strlen($password) < 8) {
            $errors[] = '密码长度至少 8 位';
        }
        if ($password !== $confirm) {
            $errors[] = '两次密码不一致';
        }
        if (empty($_SESSION['install_db'])) {
            $errors[] = '请先完成数据库配置（步骤 2）';
        }

        if (!$errors) {
            try {
                $db = $_SESSION['install_db'];
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);
                $pdo = new PDO($dsn, $db['user'], $db['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

                // 生成随机 app_key + 用户密钥
                $appKey   = bin2hex(random_bytes(32));
                $userKey  = bin2hex(random_bytes(32));
                $hash     = password_hash($password, PASSWORD_BCRYPT);

                // 插入管理员用户（直接验证）
                $stmt = $pdo->prepare(
                    'INSERT INTO users (email, password_hash, encryption_key, is_admin, status, email_verified_at)
                     VALUES (:email, :hash, :ek, 1, 1, NOW())'
                );
                $stmt->execute([
                    'email' => $email,
                    'hash'  => $hash,
                    'ek'    => $userKey,
                ]);

                // 站点设置
                $upsert = $pdo->prepare(
                    'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
                );
                $upsert->execute(['k' => 'site_name',                  'v' => $siteName]);
                $upsert->execute(['k' => 'allow_registration',         'v' => '1']);
                $upsert->execute(['k' => 'require_email_verification', 'v' => '1']);

                // 写入 config.php
                $config = [
                    'app' => [
                        'name'     => $siteName,
                        'url'      => $appUrl ?: '',
                        'debug'    => false,
                        'timezone' => 'Asia/Shanghai',
                        'app_key'  => $appKey,
                    ],
                    'database' => $db,
                    'session'  => [
                        'name'              => 'AM_SESSID',
                        'lifetime'          => 7200,
                        'remember_lifetime' => 60 * 60 * 24 * 30,
                    ],
                    'installed' => true,
                ];
                $php = "<?php\nreturn " . var_export($config, true) . ";\n";
                if (file_put_contents($configFile, $php) === false) {
                    throw new RuntimeException('无法写入 config.php，请检查目录权限');
                }

                unset($_SESSION['install_db']);
                header('Location: install.php?step=4');
                exit;
            } catch (Throwable $e) {
                $errors[] = '安装失败：' . $e->getMessage();
            }
        }
    }
}

$envChecks = $step === 1 ? checkEnv() : [];
$envOk = $step === 1 ? !in_array(false, array_column($envChecks, 'ok'), true) : true;

?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>安装向导 - API Manager</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", sans-serif;
  background: #0d1117; color: #c9d1d9; min-height: 100vh; padding: 40px 20px; }
.container { max-width: 720px; margin: 0 auto; background: #161b22;
  border: 1px solid #30363d; border-radius: 12px; padding: 40px; }
h1 { color: #58a6ff; margin-bottom: 8px; font-size: 26px; }
.subtitle { color: #8b949e; margin-bottom: 32px; }
.steps { display: flex; gap: 12px; margin-bottom: 32px; }
.step { flex: 1; padding: 12px; text-align: center; border: 1px solid #30363d;
  border-radius: 8px; color: #8b949e; font-size: 13px; }
.step.active { background: #1f6feb; color: #fff; border-color: #1f6feb; }
.step.done { background: #238636; color: #fff; border-color: #238636; }
.form-group { margin-bottom: 18px; }
label { display: block; margin-bottom: 6px; font-size: 14px; color: #c9d1d9; font-weight: 500; }
input[type=text], input[type=email], input[type=password], input[type=number] {
  width: 100%; padding: 10px 12px; background: #0d1117; border: 1px solid #30363d;
  border-radius: 6px; color: #c9d1d9; font-size: 14px; }
input:focus { outline: none; border-color: #58a6ff; }
.btn { display: inline-block; padding: 10px 24px; background: #238636; color: #fff;
  border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; }
.btn:hover { background: #2ea043; }
.btn-block { width: 100%; }
.alert { padding: 12px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
.alert-error { background: #4d1316; border: 1px solid #6e1419; color: #ff8a8a; }
.alert-success { background: #0e2b14; border: 1px solid #1a4d24; color: #7ee787; }
table.checks { width: 100%; border-collapse: collapse; }
table.checks td { padding: 10px 12px; border-bottom: 1px solid #21262d; font-size: 14px; }
table.checks td:last-child { text-align: right; font-family: monospace; }
.ok { color: #7ee787; }
.fail { color: #ff7b72; }
.hint { font-size: 12px; color: #8b949e; margin-top: 4px; }
</style>
</head>
<body>
<div class="container">
  <h1>🚀 API Manager 安装向导</h1>
  <p class="subtitle">AI API 配置管理中心 · 首次部署初始化</p>

  <div class="steps">
    <div class="step <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">1. 环境检查</div>
    <div class="step <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">2. 数据库</div>
    <div class="step <?= $step === 3 ? 'active' : ($step > 3 ? 'done' : '') ?>">3. 管理员</div>
    <div class="step <?= $step === 4 ? 'active' : '' ?>">4. 完成</div>
  </div>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error">⚠ <?= htmlspecialchars($err) ?></div>
  <?php endforeach; ?>

  <?php if ($step === 1): ?>
    <h3 style="margin-bottom: 16px;">环境检查</h3>
    <table class="checks">
      <?php foreach ($envChecks as $name => $r): ?>
      <tr>
        <td><?= htmlspecialchars($name) ?></td>
        <td class="<?= $r['ok'] ? 'ok' : 'fail' ?>">
          <?= $r['ok'] ? '✓' : '✗' ?> <?= htmlspecialchars((string)$r['value']) ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div style="margin-top: 24px;">
      <?php if ($envOk): ?>
        <a class="btn btn-block" href="?step=2" style="text-decoration:none;text-align:center">下一步 →</a>
      <?php else: ?>
        <div class="alert alert-error">请先解决以上环境问题</div>
        <a class="btn btn-block" href="?step=1" style="text-decoration:none;text-align:center;background:#30363d">重新检查</a>
      <?php endif; ?>
    </div>

  <?php elseif ($step === 2): ?>
    <h3 style="margin-bottom: 16px;">数据库配置</h3>
    <form method="POST" action="?step=2">
      <div class="form-group">
        <label>主机</label>
        <input type="text" name="db_host" value="<?= htmlspecialchars($_SESSION['install_db']['host'] ?? '127.0.0.1') ?>" required>
      </div>
      <div class="form-group">
        <label>端口</label>
        <input type="number" name="db_port" value="<?= htmlspecialchars((string)($_SESSION['install_db']['port'] ?? 3306)) ?>" required>
      </div>
      <div class="form-group">
        <label>数据库名（不存在会自动创建）</label>
        <input type="text" name="db_name" value="<?= htmlspecialchars($_SESSION['install_db']['name'] ?? 'api_manager') ?>" required>
      </div>
      <div class="form-group">
        <label>用户名</label>
        <input type="text" name="db_user" value="<?= htmlspecialchars($_SESSION['install_db']['user'] ?? 'root') ?>" required>
      </div>
      <div class="form-group">
        <label>密码</label>
        <input type="password" name="db_password" value="">
      </div>
      <button type="submit" class="btn btn-block">连接并安装数据表 →</button>
    </form>

  <?php elseif ($step === 3): ?>
    <h3 style="margin-bottom: 16px;">管理员账号 & 站点信息</h3>
    <form method="POST" action="?step=3">
      <div class="form-group">
        <label>站点名称</label>
        <input type="text" name="site_name" value="API Manager" required>
      </div>
      <div class="form-group">
        <label>站点 URL（可留空，自动识别）</label>
        <input type="text" name="app_url" value="<?= htmlspecialchars((($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?>">
        <div class="hint">例如：https://api.example.com</div>
      </div>
      <div class="form-group">
        <label>管理员邮箱</label>
        <input type="email" name="admin_email" required>
      </div>
      <div class="form-group">
        <label>管理员密码（≥ 8 位）</label>
        <input type="password" name="admin_password" required>
      </div>
      <div class="form-group">
        <label>确认密码</label>
        <input type="password" name="admin_password_confirm" required>
      </div>
      <button type="submit" class="btn btn-block">完成安装 →</button>
    </form>

  <?php elseif ($step === 4): ?>
    <div class="alert alert-success">🎉 安装成功！系统已就绪。</div>
    <p style="margin: 16px 0; line-height: 1.7; color: #8b949e;">
      接下来你可以：
    </p>
    <ul style="margin-left: 20px; line-height: 1.9; color: #c9d1d9;">
      <li>使用管理员账号登录系统</li>
      <li>进入「后台管理 → SMTP 配置」配置邮箱发送服务</li>
      <li>进入「后台管理 → 系统设置」调整开放注册等选项</li>
      <li>开始添加你的 AI API 配置</li>
    </ul>
    <p style="margin-top: 20px; color: #ff7b72; font-size: 13px;">
      ⚠ 安全提醒：请删除项目根目录下的 <code style="background:#21262d;padding:2px 6px;border-radius:4px">install.php</code> 文件，避免被恶意触发。
    </p>
    <a class="btn btn-block" href="/login" style="margin-top: 24px; text-decoration:none; text-align:center; background:#1f6feb;">前往登录 →</a>
  <?php endif; ?>
</div>
</body>
</html>
