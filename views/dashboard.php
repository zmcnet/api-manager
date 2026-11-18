<?php
use AM\Helper;
use AM\Presets;
$title = '我的 API';
require __DIR__ . '/layouts/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">我的 API 配置</h1>
    <p class="page-subtitle">管理你的 AI 服务凭证，并在不同应用之间一键复制</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-secondary" id="presetBtn">⚡ 预设模板</button>
    <a href="/providers/new" class="btn btn-primary">+ 新增 API 供应商</a>
  </div>
</div>

<?php if (empty($providers)): ?>
  <div class="empty-state">
    <div class="empty-icon">📭</div>
    <h3>还没有任何 API 配置</h3>
    <p>从添加你的第一个供应商开始，或选择一个预设模板快速创建。</p>
    <div class="empty-actions">
      <button class="btn btn-secondary" onclick="document.getElementById('presetBtn').click()">从预设模板开始</button>
      <a class="btn btn-primary" href="/providers/new">手动添加</a>
    </div>
  </div>
<?php else: ?>
  <div class="grid" id="providerGrid">
    <?php foreach ($providers as $p): ?>
      <?php $models = $modelsByProvider[(int) $p['id']] ?? []; ?>
      <div class="card provider-card" data-id="<?= (int) $p['id'] ?>" draggable="true">
        <div class="card-head">
          <div class="card-title">
            <span class="card-icon">🔗</span>
            <span><?= Helper::e($p['name']) ?></span>
          </div>
          <div class="card-actions">
            <a class="icon-btn" href="/providers/<?= (int) $p['id'] ?>/edit" title="编辑">✏️</a>
            <button class="icon-btn js-delete" data-id="<?= (int) $p['id'] ?>" title="删除">🗑️</button>
          </div>
        </div>

        <div class="kv">
          <span class="kv-label">Base URL</span>
          <span class="kv-value mono" title="<?= Helper::e($p['base_url']) ?>">
            <?= Helper::e($p['base_url']) ?>
          </span>
          <button class="icon-btn-mini js-copy" data-copy="<?= Helper::e($p['base_url']) ?>" title="复制 Base URL">📋</button>
        </div>
        <div class="kv">
          <span class="kv-label">格式后缀</span>
          <span class="kv-value mono"><?= Helper::e($p['api_format']) ?></span>
          <button class="icon-btn-mini js-copy"
                  data-copy="<?= Helper::e($p['api_format']) ?>" title="复制 API 格式">📋F</button>
          <button class="icon-btn-mini js-copy primary"
                  data-copy="<?= Helper::e(rtrim($p['base_url'], '/') . '/' . ltrim($p['api_format'], '/')) ?>"
                  title="复制完整 URL">📋U</button>
        </div>

        <div class="card-divider">📦 模型 (<?= count($models) ?>)</div>
        <div class="model-tags">
          <?php foreach ($models as $m): ?>
            <span class="tag" title="<?= Helper::e($m['model_id']) ?> · <?= Helper::e((string) $m['context_window']) ?>">
              <?= Helper::e($m['nickname']) ?>
              <span class="tag-id"><?= Helper::e($m['model_id']) ?></span>
            </span>
          <?php endforeach; ?>
          <a class="tag tag-add" href="/providers/<?= (int) $p['id'] ?>/edit">+ 添加模型</a>
        </div>

        <?php if (!empty($p['notes'])): ?>
          <div class="card-notes">📝 <?= Helper::e($p['notes']) ?></div>
        <?php endif; ?>

        <div class="card-foot">
          <button class="btn btn-secondary btn-sm js-copy-key" data-id="<?= (int) $p['id'] ?>">📋 复制 Key</button>
          <button class="btn btn-secondary btn-sm js-copy-json" data-id="<?= (int) $p['id'] ?>">📋 复制 JSON</button>
          <?php if (!empty($p['website_url'])): ?>
            <a class="btn btn-ghost btn-sm" href="<?= Helper::e($p['website_url']) ?>" target="_blank" rel="noopener">🌐 官网</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- 预设模板对话框 -->
<div class="modal" id="presetModal" hidden>
  <div class="modal-backdrop" data-close></div>
  <div class="modal-dialog">
    <div class="modal-head">
      <h3>选择预设模板</h3>
      <button class="icon-btn" data-close>✕</button>
    </div>
    <div class="modal-body">
      <p class="muted">选择一个内置模板，填入你的 API Key 即可。</p>
      <div class="preset-list">
        <?php foreach (Presets::all() as $preset): ?>
          <div class="preset-item">
            <div class="preset-info">
              <div class="preset-name"><?= Helper::e($preset['name']) ?></div>
              <div class="preset-meta mono"><?= Helper::e($preset['base_url']) ?> · <?= Helper::e($preset['api_format']) ?></div>
              <div class="preset-models">
                <?php foreach ($preset['models'] as $m): ?>
                  <span class="tag tag-sm"><?= Helper::e($m['nickname']) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
            <button class="btn btn-primary btn-sm js-use-preset"
                    data-preset="<?= Helper::e($preset['id']) ?>"
                    data-name="<?= Helper::e($preset['name']) ?>">
              使用
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>
