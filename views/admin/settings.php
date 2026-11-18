<?php
use AM\Csrf;
use AM\Helper;
$title = '系统设置';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <div>
    <h1 class="page-title">⚙ 系统设置</h1>
  </div>
</div>
<div class="admin-tabs">
  <a href="/admin" class="tab">仪表盘</a>
  <a href="/admin/users" class="tab">用户管理</a>
  <a href="/admin/smtp" class="tab">SMTP 配置</a>
  <a href="/admin/settings" class="tab active">系统设置</a>
</div>

<?php if (!empty($notice)): ?>
  <div class="alert alert-success"><?= Helper::e($notice) ?></div>
<?php endif; ?>

<div class="card">
  <form method="POST" action="/admin/settings" class="form">
    <?= Csrf::field() ?>
    <div class="field">
      <label>站点名称</label>
      <input type="text" name="site_name" value="<?= Helper::e($site_name ?? '') ?>" required>
    </div>
    <div class="field">
      <label class="check">
        <input type="checkbox" name="allow_registration" value="1" <?= ($allow_registration ?? '1') === '1' ? 'checked' : '' ?>>
        开放新用户注册
      </label>
    </div>
    <div class="field">
      <label class="check">
        <input type="checkbox" name="require_email_verification" value="1" <?= ($require_email_verification ?? '1') === '1' ? 'checked' : '' ?>>
        注册时要求邮箱验证（需先配置 SMTP）
      </label>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">💾 保存设置</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
