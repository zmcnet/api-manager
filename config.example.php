<?php
/**
 * API Manager - 主配置文件
 * 安装向导会自动生成 config.php，请勿直接修改本示例文件。
 * 兼容：PHP 8.2 / MySQL 5.7 / Nginx 1.26
 */

return [
    // 站点配置
    'app' => [
        'name'         => 'API Manager',
        'url'          => 'http://localhost',
        'debug'        => false,
        'timezone'     => 'Asia/Shanghai',
        // 应用密钥（用于加密）— 安装时随机生成
        'app_key'      => 'CHANGE_ME_TO_A_RANDOM_64_CHAR_HEX_STRING',
    ],

    // 数据库配置（MySQL 5.7）
    'database' => [
        'host'         => '127.0.0.1',
        'port'         => 3306,
        'name'         => 'api_manager',
        'user'         => 'root',
        'password'     => '',
        'charset'      => 'utf8mb4',
    ],

    // 会话配置
    'session' => [
        'name'         => 'AM_SESSID',
        'lifetime'     => 7200,
        'remember_lifetime' => 60 * 60 * 24 * 30, // 30 天
    ],

    // 是否已安装（安装完成后由 install.php 写入 true）
    'installed' => false,
];
