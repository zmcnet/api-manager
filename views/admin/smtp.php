<?php
use AM\Csrf;
use AM\Helper;
$title = 'SMTP 配置';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <div>
    <h1 class="page-title">📧 SMTP 配置</h1>
    <p class="page-subtitle">用于发送注册验证、密码重置等系统邮件</p>
  </div>
</div>
<div class="admin-tabs">
  <a href="/admin" class="tab">仪表盘</a>
  <a href="/admin/users" class="tab">用户管理</a>
  <a href="/admin/smtp" class="tab active">SMTP 配置</a>
  <a href="/admin/settings" class="tab">系统设置</a>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-error"><?= Helper::e($error) ?></div>
<?php endif; ?>
<?php if (!empty($notice)): ?>
  <div class="alert alert-success"><?= Helper::e($notice) ?></div>
<?php endif; ?>

<div class="card">
  <form method="POST" action="/admin/smtp" class="form">
    <?= Csrf::field() ?>

    <div class="grid-2">
      <div class="field">
        <label>SMTP 主机 <span class="req">*</span></label>
        <input type="text" name="host" required value="<?= Helper::e($cfg['host'] ?? '') ?>" placeholder="smtp.example.com">
      </div>
      <div class="field">
        <label>端口 <span class="req">*</span></label>
        <input type="number" name="port" required value="<?= Helper::e((string) ($cfg['port'] ?? 465)) ?>">
      </div>
    </div>

    <div class="grid-2">
      <div class="field">
        <label>加密方式</label>
        <select name="encryption">
          <?php foreach (['ssl' => 'SSL', 'tls' => 'TLS (STARTTLS)', 'none' => '无'] as $v => $label): ?>
            <option value="<?= $v ?>" <?= ($cfg['encryption'] ?? 'ssl') === $v ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>启用此配置</label>
        <label class="check"><input type="checkbox" name="is_active" value="1" <?= !empty($cfg['is_active']) ? 'checked' : '' ?>> 作为活动 SMTP 使用</label>
      </div>
    </div>

    <div class="grid-2">
      <div class="field">
        <label>用户名 <span class="req">*</span></label>
        <input type="text" name="username" required value="<?= Helper::e($cfg['username'] ?? '') ?>">
      </div>
      <div class="field">
        <label>密码 <?= $cfg ? '<span class="hint">留空表示不修改</span>' : '<span class="req">*</span>' ?></label>
        <input type="password" name="password" <?= $cfg ? '' : 'required' ?>>
      </div>
    </div>

    <div class="grid-2">
      <div class="field">
        <label>发件邮箱 <span class="req">*</span></label>
        <input type="email" name="from_address" required value="<?= Helper::e($cfg['from_address'] ?? '') ?>">
      </div>
      <div class="field">
        <label>发件人名称</label>
        <input type="text" name="from_name" value="<?= Helper::e($cfg['from_name'] ?? Helper::siteName()) ?>">
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">💾 保存配置</button>
    </div>
  </form>
</div>

<div class="card" style="margin-top: 24px;">
  <h3 class="section-title">测试发送</h3>
  <p class="muted">向以下邮箱发送一封测试邮件，验证 SMTP 配置是否正常。请先保存并启用配置。</p>
  <div class="input-group" style="max-width: 500px;">
    <input type="email" id="testEmail" placeholder="receiver@example.com">
    <button type="button" class="btn btn-secondary" id="testSmtpBtn">发送测试邮件</button>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
