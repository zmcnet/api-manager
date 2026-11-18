<?php
use AM\Csrf;
use AM\Helper;
$title = '找回密码';
require __DIR__ . '/../layouts/header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <h2 class="auth-title">重置密码</h2>
    <p class="auth-subtitle">通过邮箱验证码重置你的密码</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= Helper::e($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($notice)): ?>
      <div class="alert alert-success"><?= Helper::e($notice) ?></div>
    <?php endif; ?>

    <form method="POST" action="/forgot" class="form" id="forgotForm">
      <?= Csrf::field() ?>
      <div class="field">
        <label>邮箱</label>
        <input type="email" name="email" id="forgot_email" required>
      </div>
      <div class="field">
        <label>验证码</label>
        <div class="input-group">
          <input type="text" name="code" maxlength="6" required>
          <button type="button" class="btn btn-secondary" id="sendResetCodeBtn">发送验证码</button>
        </div>
      </div>
      <div class="field">
        <label>新密码（≥ 8 位）</label>
        <input type="password" name="password" required minlength="8">
      </div>
      <div class="field">
        <label>确认新密码</label>
        <input type="password" name="password_confirm" required minlength="8">
      </div>
      <button type="submit" class="btn btn-primary btn-block">重置密码</button>
    </form>

    <p class="auth-footer"><a class="link" href="/login">← 返回登录</a></p>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
