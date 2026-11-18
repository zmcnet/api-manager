<?php
use AM\Csrf;
use AM\Helper;
$title = '登录';
require __DIR__ . '/../layouts/header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <h2 class="auth-title">登录到 <?= Helper::e(Helper::siteName()) ?></h2>
    <p class="auth-subtitle">管理你的 AI API 配置</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= Helper::e($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($notice)): ?>
      <div class="alert alert-success"><?= Helper::e($notice) ?></div>
    <?php endif; ?>

    <form method="POST" action="/login" class="form">
      <?= Csrf::field() ?>
      <div class="field">
        <label>邮箱</label>
        <input type="email" name="email" required autofocus>
      </div>
      <div class="field">
        <label>密码</label>
        <input type="password" name="password" required>
      </div>
      <div class="field-row">
        <label class="check"><input type="checkbox" name="remember" value="1"> 记住我</label>
        <a href="/forgot" class="link">忘记密码？</a>
      </div>
      <button type="submit" class="btn btn-primary btn-block">登录</button>
    </form>

    <p class="auth-footer">还没有账号？ <a class="link" href="/register">立即注册</a></p>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
