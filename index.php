<?php
/**
 * 前端控制器（路由分发入口）
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use AM\Router;
use AM\Auth;
use AM\Csrf;
use AM\Helper;
use AM\Controllers\AuthController;
use AM\Controllers\HomeController;
use AM\Controllers\ProviderController;
use AM\Controllers\ModelController;
use AM\Controllers\AdminController;

// 全局 CSRF 校验（写操作）
Csrf::verify();

$router = new Router();

// 首页
$router->get('/', function () {
    if (Auth::check()) {
        Helper::redirect('/dashboard');
    }
    Helper::redirect('/login');
});

// ----- 认证 -----
$router->get ('/login',           [AuthController::class, 'showLogin']);
$router->post('/login',           [AuthController::class, 'login']);
$router->get ('/register',        [AuthController::class, 'showRegister']);
$router->post('/register',        [AuthController::class, 'register']);
$router->post('/register/code',   [AuthController::class, 'sendRegisterCode']);
$router->get ('/logout',          [AuthController::class, 'logout']);
$router->post('/logout',          [AuthController::class, 'logout']);
$router->get ('/forgot',          [AuthController::class, 'showForgot']);
$router->post('/forgot/code',     [AuthController::class, 'sendResetCode']);
$router->post('/forgot',          [AuthController::class, 'resetPassword']);

// ----- 用户主面板 -----
$router->get ('/dashboard',                         [HomeController::class, 'dashboard']);
$router->get ('/providers/new',                     [ProviderController::class, 'create']);
$router->get ('/providers/{id}/edit',               [ProviderController::class, 'edit']);
$router->post('/api/providers',                     [ProviderController::class, 'store']);
$router->post('/api/providers/{id}',                [ProviderController::class, 'update']);
$router->post('/api/providers/{id}/delete',         [ProviderController::class, 'destroy']);
$router->post('/api/providers/{id}/api-key',        [ProviderController::class, 'getApiKey']);
$router->post('/api/providers/{id}/json',           [ProviderController::class, 'fullJson']);
$router->post('/api/providers/sort',                [ProviderController::class, 'sort']);
$router->post('/api/providers/from-preset',         [ProviderController::class, 'fromPreset']);
$router->get ('/api/presets',                       [ProviderController::class, 'listPresets']);

// ----- 模型 CRUD -----
$router->post('/api/providers/{pid}/models',        [ModelController::class, 'store']);
$router->post('/api/models/{id}',                   [ModelController::class, 'update']);
$router->post('/api/models/{id}/delete',            [ModelController::class, 'destroy']);
$router->post('/api/providers/{pid}/models/sort',   [ModelController::class, 'sort']);

// ----- 管理员 -----
$router->get ('/admin',                       [AdminController::class, 'dashboard']);
$router->get ('/admin/users',                 [AdminController::class, 'users']);
$router->post('/admin/users/{id}/toggle',     [AdminController::class, 'toggleUser']);
$router->post('/admin/users/{id}/delete',     [AdminController::class, 'deleteUser']);
$router->get ('/admin/smtp',                  [AdminController::class, 'smtp']);
$router->post('/admin/smtp',                  [AdminController::class, 'saveSmtp']);
$router->post('/admin/smtp/test',             [AdminController::class, 'testSmtp']);
$router->get ('/admin/settings',              [AdminController::class, 'settings']);
$router->post('/admin/settings',              [AdminController::class, 'saveSettings']);

// 分发请求
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
