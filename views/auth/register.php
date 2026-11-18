<?php
use AM\Csrf;
use AM\Helper;
$title = '注册';
require __DIR__ . '/../layouts/header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <h2 class="auth-title">注册新账号</h2>
    <p class="auth-subtitle">创建你的 AI API 管理空间</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= Helper::e($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($notice)): ?>
      <div class="alert alert-success"><?= Helper::e($notice) ?></div>
    <?php endif; ?>

    <form method="POST" action="/register" class="form" id="registerForm">
      <?= Csrf::field() ?>
      <div class="field">
        <label>邮箱</label>
        <input type="email" name="email" id="reg_email" required>
      </div>

      <?php if (!empty($requireVerify)): ?>
      <div class="field">
        <label>邮箱验证码</label>
        <div class="input-group">
          <input type="text" name="code" id="reg_code" maxlength="6" required>
          <button type="button" class="btn btn-secondary" id="sendCodeBtn">发送验证码</button>
        </div>
      </div>
      <?php endif; ?>

      <div class="field">
        <label>密码（≥ 8 位）</label>
        <input type="password" name="password" required minlength="8">
      </div>
      <div class="field">
        <label>确认密码</label>
        <input type="password" name="password_confirm" required minlength="8">
      </div>
      <button type="submit" class="btn btn-primary btn-block">注册</button>
    </form>

    <p class="auth-footer">已有账号？ <a class="link" href="/login">直接登录</a></p>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
