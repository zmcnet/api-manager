<?php
use AM\Csrf;
use AM\Helper;
$isEdit = !empty($provider);
$title = $isEdit ? '编辑 API 供应商' : '新增 API 供应商';
require __DIR__ . '/layouts/header.php';
?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $isEdit ? '编辑' : '新增' ?> API 供应商</h1>
    <p class="page-subtitle">填写基础信息，并在下方添加该供应商的模型列表</p>
  </div>
  <div class="page-actions">
    <a href="/dashboard" class="btn btn-ghost">← 返回</a>
  </div>
</div>

<form id="providerForm" class="form provider-form"
      data-id="<?= $isEdit ? (int) $provider['id'] : '' ?>"
      data-edit="<?= $isEdit ? '1' : '0' ?>">

  <div class="card form-card">
    <h3 class="section-title">基础信息</h3>

    <div class="grid-2">
      <div class="field">
        <label>名称 <span class="req">*</span></label>
        <input type="text" name="name" required maxlength="100"
               value="<?= Helper::e($provider['name'] ?? '') ?>"
               placeholder="例如：讯飞星火 / OpenAI">
      </div>
      <div class="field">
        <label>官网链接</label>
        <input type="url" name="website_url"
               value="<?= Helper::e($provider['website_url'] ?? '') ?>"
               placeholder="https://platform.example.com">
      </div>
    </div>

    <div class="field">
      <label>Base URL <span class="req">*</span> <span class="hint">不含 API 路径后缀</span></label>
      <input type="url" name="base_url" required
             value="<?= Helper::e($provider['base_url'] ?? '') ?>"
             placeholder="https://api.example.com/v1">
    </div>

    <div class="field">
      <label>API 格式（路径后缀） <span class="req">*</span> <span class="hint">如 /chat/completions、/v1/messages</span></label>
      <input type="text" name="api_format" required
             value="<?= Helper::e($provider['api_format'] ?? '') ?>"
             placeholder="/chat/completions">
      <div class="preview-line">
        完整 URL 预览：<span class="mono" id="urlPreview">—</span>
      </div>
    </div>

    <div class="field">
      <label>API Key <?php if (!$isEdit): ?><span class="req">*</span><?php endif; ?>
        <?php if ($isEdit): ?><span class="hint">留空表示不修改</span><?php endif; ?>
      </label>
      <div class="input-group">
        <input type="password" name="api_key" id="apiKeyInput"
               <?= $isEdit ? '' : 'required' ?> placeholder="sk-xxxxxxxxxxxx">
        <button type="button" class="btn btn-secondary" id="toggleKey">👁 显示</button>
      </div>
    </div>

    <div class="field">
      <label>备注</label>
      <textarea name="notes" rows="2" placeholder="可选"><?= Helper::e($provider['notes'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="card form-card">
    <div class="row-between">
      <h3 class="section-title" style="margin:0">模型列表</h3>
      <button type="button" class="btn btn-secondary btn-sm" id="addModelBtn">+ 添加模型</button>
    </div>
    <p class="muted" style="margin: 8px 0 12px;">支持拖拽排序，至少添加一个模型</p>

    <div class="model-list" id="modelList">
      <?php foreach ($models as $m): ?>
      <div class="model-row" draggable="true">
        <span class="drag-handle" title="拖拽排序">⋮⋮</span>
        <input type="text" name="models[][nickname]" placeholder="昵称（如 GPT-4o 主力）"
               value="<?= Helper::e($m['nickname']) ?>" required>
        <input type="text" name="models[][model_id]" placeholder="模型 ID（如 gpt-4o）"
               value="<?= Helper::e($m['model_id']) ?>" required>
        <input type="text" name="models[][context_window]" placeholder="128000"
               value="<?= Helper::e((string) $m['context_window']) ?>">
        <button type="button" class="icon-btn js-remove-model" title="删除">🗑️</button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="form-actions">
    <a href="/dashboard" class="btn btn-ghost">取消</a>
    <button type="submit" class="btn btn-primary">💾 保存</button>
  </div>
</form>

<?php require __DIR__ . '/layouts/footer.php'; ?>
