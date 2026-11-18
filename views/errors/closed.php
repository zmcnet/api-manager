<?php
use AM\Helper;
$title = '功能已关闭';
require __DIR__ . '/../layouts/header.php';
?>
<div class="empty-state" style="margin-top: 60px;">
  <div class="empty-icon">🚫</div>
  <h3><?= Helper::e($message ?? '功能已关闭') ?></h3>
  <div class="empty-actions">
    <a class="btn btn-primary" href="/login">返回登录</a>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
