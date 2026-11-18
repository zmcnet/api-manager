<?php
use AM\Helper;
$title = '后台仪表盘';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <div>
    <h1 class="page-title">📊 管理后台</h1>
    <p class="page-subtitle">系统概览与全局配置</p>
  </div>
</div>

<div class="admin-tabs">
  <a href="/admin" class="tab active">仪表盘</a>
  <a href="/admin/users" class="tab">用户管理</a>
  <a href="/admin/smtp" class="tab">SMTP 配置</a>
  <a href="/admin/settings" class="tab">系统设置</a>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">总用户数</div>
    <div class="stat-value"><?= (int) $stats['total_users'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">今日注册</div>
    <div class="stat-value"><?= (int) $stats['today_users'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">API 供应商</div>
    <div class="stat-value"><?= (int) $stats['total_providers'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">模型条目</div>
    <div class="stat-value"><?= (int) $stats['total_models'] ?></div>
  </div>
</div>

<div class="card" style="margin-top: 24px;">
  <h3 class="section-title">系统状态</h3>
  <table class="data-table">
    <tr><td>PHP 版本</td><td class="mono"><?= PHP_VERSION ?></td></tr>
    <tr><td>SMTP 状态</td>
      <td>
        <?php if ($smtp_active): ?>
          <span class="badge badge-success">已启用</span>
          <span class="muted">（<?= Helper::e($smtp_active['host']) ?>:<?= (int) $smtp_active['port'] ?>）</span>
        <?php else: ?>
          <span class="badge badge-warn">未配置</span>
          <a href="/admin/smtp" class="link">立即配置 →</a>
        <?php endif; ?>
      </td>
    </tr>
    <tr><td>数据库</td><td class="mono"><?= Helper::e($GLOBALS['AM_CONFIG']['database']['host']) ?> · <?= Helper::e($GLOBALS['AM_CONFIG']['database']['name']) ?></td></tr>
    <tr><td>注册开关</td><td>
      <?= Helper::setting('allow_registration', '1') === '1'
          ? '<span class="badge badge-success">开放</span>'
          : '<span class="badge badge-warn">已关闭</span>' ?>
    </td></tr>
  </table>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
