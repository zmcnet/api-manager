<?php
use AM\Helper;
$title = '用户管理';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <div>
    <h1 class="page-title">👥 用户管理</h1>
    <p class="page-subtitle">所有 API Key 等敏感数据均为加密存储，后台不可读取</p>
  </div>
</div>
<div class="admin-tabs">
  <a href="/admin" class="tab">仪表盘</a>
  <a href="/admin/users" class="tab active">用户管理</a>
  <a href="/admin/smtp" class="tab">SMTP 配置</a>
  <a href="/admin/settings" class="tab">系统设置</a>
</div>

<?php if (!empty($notice)): ?>
  <div class="alert alert-success"><?= Helper::e($notice) ?></div>
<?php endif; ?>

<div class="card">
  <table class="data-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>邮箱</th>
        <th>角色</th>
        <th>邮箱验证</th>
        <th>状态</th>
        <th>注册时间</th>
        <th>最后登录</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int) $u['id'] ?></td>
        <td class="mono"><?= Helper::e($u['email']) ?></td>
        <td>
          <?= (int) $u['is_admin'] === 1
              ? '<span class="badge badge-info">管理员</span>'
              : '<span class="badge badge-muted">用户</span>' ?>
        </td>
        <td>
          <?= !empty($u['email_verified_at'])
              ? '<span class="badge badge-success">已验证</span>'
              : '<span class="badge badge-warn">未验证</span>' ?>
        </td>
        <td>
          <?= (int) $u['status'] === 1
              ? '<span class="badge badge-success">正常</span>'
              : '<span class="badge badge-error">禁用</span>' ?>
        </td>
        <td class="muted small"><?= Helper::e((string) $u['created_at']) ?></td>
        <td class="muted small">
          <?= $u['last_login_at'] ? Helper::e($u['last_login_at']) : '—' ?>
          <?php if (!empty($u['last_login_ip'])): ?>
            <div class="mono small"><?= Helper::e($u['last_login_ip']) ?></div>
          <?php endif; ?>
        </td>
        <td>
          <button class="btn btn-sm btn-secondary js-toggle-user" data-id="<?= (int) $u['id'] ?>">
            <?= (int) $u['status'] === 1 ? '禁用' : '启用' ?>
          </button>
          <button class="btn btn-sm btn-danger js-delete-user" data-id="<?= (int) $u['id'] ?>" data-email="<?= Helper::e($u['email']) ?>">删除</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
