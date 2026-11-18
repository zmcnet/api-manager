<?php
use AM\Helper;
$title = '页面未找到';
require __DIR__ . '/../layouts/header.php';
?>
<div class="empty-state" style="margin-top: 60px;">
  <div class="empty-icon">🔍</div>
  <h3>404 - 页面未找到</h3>
  <p>请检查访问的链接是否正确。</p>
  <div class="empty-actions">
    <a class="btn btn-primary" href="/dashboard">回到首页</a>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
