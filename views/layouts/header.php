<?php
use AM\Auth;
use AM\Csrf;
use AM\Helper;

$user = Auth::user();
$siteName = Helper::siteName();
$pageTitle = $title ?? $siteName;
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= Helper::e(Csrf::token()) ?>">
<title><?= Helper::e($pageTitle) ?> · <?= Helper::e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<nav class="navbar">
  <div class="nav-inner">
    <a class="brand" href="/dashboard">
      <span class="brand-icon">⚡</span> <?= Helper::e($siteName) ?>
    </a>
    <div class="nav-menu">
      <?php if ($user): ?>
        <a href="/dashboard" class="nav-link">我的 API</a>
        <?php if ((int) ($user['is_admin'] ?? 0) === 1): ?>
          <a href="/admin" class="nav-link">后台</a>
        <?php endif; ?>
        <span class="nav-user">👤 <?= Helper::e($user['email']) ?></span>
        <a href="/logout" class="nav-link nav-logout">退出</a>
      <?php else: ?>
        <a href="/login" class="nav-link">登录</a>
        <a href="/register" class="nav-link nav-cta">注册</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<main class="container">
