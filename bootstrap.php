<?php
/**
 * 应用引导文件 - 加载配置、自动加载、初始化
 */
declare(strict_types=1);

define('AM_ROOT', __DIR__ . DIRECTORY_SEPARATOR);
define('AM_DS', DIRECTORY_SEPARATOR);

// 错误显示由 config 控制
ini_set('display_errors', '0');
error_reporting(E_ALL);

// 自动加载（PSR-4 风格的简化版）
//   AM\Controllers\Foo -> controllers/Foo.php
//   AM\Foo            -> src/Foo.php
spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'AM\\Controllers\\', 15) === 0) {
        $rel = substr($class, 15);
        $file = AM_ROOT . 'controllers' . AM_DS . str_replace('\\', AM_DS, $rel) . '.php';
        if (is_file($file)) { require $file; return; }
        return;
    }
    if (strncmp($class, 'AM\\', 3) === 0) {
        $rel = substr($class, 3);
        $file = AM_ROOT . 'src' . AM_DS . str_replace('\\', AM_DS, $rel) . '.php';
        if (is_file($file)) { require $file; return; }
    }
});

// 安装检查
$configFile = AM_ROOT . 'config.php';
if (!is_file($configFile)) {
    // 跳转到安装向导
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: install.php');
        exit;
    }
    return;
}

$config = require $configFile;
if (empty($config['installed'])) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: install.php');
        exit;
    }
    return;
}

// 时区与错误显示
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Shanghai');
if (!empty($config['app']['debug'])) {
    ini_set('display_errors', '1');
}

// 全局服务容器（极简）
$GLOBALS['AM_CONFIG'] = $config;

// 启动会话
\AM\Session::start($config['session'] ?? []);
