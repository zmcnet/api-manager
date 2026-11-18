/* =================================================================
   API Manager - 前端交互脚本
   - 复制到剪贴板（含降级方案）
   - 卡片 / 模型行 拖拽排序
   - 表单 AJAX 提交
   - 验证码倒计时
   ================================================================= */
(function () {
  'use strict';

  // ---------- 全局工具 ----------
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const CSRF = csrfMeta ? csrfMeta.getAttribute('content') : '';

  const toastEl = document.getElementById('toast');
  let toastTimer = null;
  function toast(msg, type) {
    if (!toastEl) { alert(msg); return; }
    toastEl.textContent = msg;
    toastEl.className = 'toast show' + (type ? ' ' + type : '');
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastEl.className = 'toast'; }, 2200);
  }

  function copyText(text) {
    if (!text) return Promise.reject(new Error('空内容'));
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    // 降级方案
    return new Promise((resolve, reject) => {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.focus(); ta.select();
      try {
        document.execCommand('copy') ? resolve() : reject();
      } catch (e) { reject(e); }
      document.body.removeChild(ta);
    });
  }

  function api(url, options) {
    options = options || {};
    options.headers = Object.assign({
      'X-CSRF-Token': CSRF,
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
    }, options.headers || {});
    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(Object.assign({ _csrf: CSRF }, options.body));
    }
    options.method = options.method || 'POST';
    return fetch(url, options).then(async (r) => {
      let data;
      try { data = await r.json(); } catch { data = {}; }
      if (!r.ok && data.redirect) { window.location.href = data.redirect; return data; }
      return data;
    });
  }

  // ---------- 复制按钮（事件委托） ----------
  document.addEventListener('click', function (e) {
    // 直接复制
    const copyBtn = e.target.closest('.js-copy');
    if (copyBtn) {
      const text = copyBtn.getAttribute('data-copy') || '';
      copyText(text).then(
        () => toast('已复制到剪贴板', 'success'),
        () => toast('复制失败', 'error')
      );
      return;
    }

    // 复制 API Key（需向后端请求解密）
    const keyBtn = e.target.closest('.js-copy-key');
    if (keyBtn) {
      const id = keyBtn.getAttribute('data-id');
      api('/api/providers/' + id + '/api-key').then((r) => {
        if (r && r.ok) {
          copyText(r.api_key).then(
            () => toast('API Key 已复制', 'success'),
            () => toast('复制失败', 'error')
          );
        } else {
          toast((r && r.message) || '获取失败', 'error');
        }
      });
      return;
    }

    // 复制完整 JSON
    const jsonBtn = e.target.closest('.js-copy-json');
    if (jsonBtn) {
      const id = jsonBtn.getAttribute('data-id');
      api('/api/providers/' + id + '/json').then((r) => {
        if (r && r.ok) {
          const text = JSON.stringify(r.data, null, 2);
          copyText(text).then(
            () => toast('完整配置 JSON 已复制', 'success'),
            () => toast('复制失败', 'error')
          );
        } else {
          toast((r && r.message) || '获取失败', 'error');
        }
      });
      return;
    }

    // 删除供应商
    const delBtn = e.target.closest('.js-delete');
    if (delBtn) {
      if (!confirm('确定要删除此 API 供应商及其所有模型吗？此操作不可恢复。')) return;
      const id = delBtn.getAttribute('data-id');
      api('/api/providers/' + id + '/delete').then((r) => {
        if (r && r.ok) {
          const card = delBtn.closest('.provider-card');
          if (card) card.remove();
          toast('已删除', 'success');
        } else {
          toast((r && r.message) || '删除失败', 'error');
        }
      });
      return;
    }

    // 关闭弹窗
    if (e.target.closest('[data-close]')) {
      const modal = e.target.closest('.modal');
      if (modal) modal.hidden = true;
    }

    // 打开预设弹窗
    if (e.target.closest('#presetBtn')) {
      const modal = document.getElementById('presetModal');
      if (modal) modal.hidden = false;
    }

    // 使用预设
    const usePreset = e.target.closest('.js-use-preset');
    if (usePreset) {
      const presetId = usePreset.getAttribute('data-preset');
      const name = usePreset.getAttribute('data-name');
      const apiKey = prompt('请输入「' + name + '」的 API Key（可留空稍后填）：', '') || '';
      api('/api/providers/from-preset', {
        body: { preset_id: presetId, api_key: apiKey },
      }).then((r) => {
        if (r && r.ok) {
          toast('已添加，正在跳转编辑...', 'success');
          setTimeout(() => { window.location.href = r.redirect; }, 600);
        } else {
          toast((r && r.message) || '添加失败', 'error');
        }
      });
      return;
    }

    // 后台用户禁用/启用
    const tg = e.target.closest('.js-toggle-user');
    if (tg) {
      const id = tg.getAttribute('data-id');
      api('/admin/users/' + id + '/toggle').then((r) => {
        if (r && r.ok) { toast('已更新', 'success'); setTimeout(() => location.reload(), 500); }
        else toast((r && r.message) || '操作失败', 'error');
      });
      return;
    }
    const dbtn = e.target.closest('.js-delete-user');
    if (dbtn) {
      const email = dbtn.getAttribute('data-email');
      if (!confirm('确定要删除用户 ' + email + ' 吗？该用户的所有 API 配置也会被一并删除！')) return;
      const id = dbtn.getAttribute('data-id');
      api('/admin/users/' + id + '/delete').then((r) => {
        if (r && r.ok) { toast('已删除', 'success'); setTimeout(() => location.reload(), 500); }
        else toast((r && r.message) || '删除失败', 'error');
      });
      return;
    }
  });

  // ---------- 拖拽排序：供应商卡片 ----------
  initDragSort('#providerGrid', '.provider-card', function (ids) {
    api('/api/providers/sort', { body: { ids: ids } });
  });

  function initDragSort(containerSel, itemSel, onSort) {
    const container = document.querySelector(containerSel);
    if (!container) return;
    let dragging = null;

    container.addEventListener('dragstart', (e) => {
      const t = e.target.closest(itemSel);
      if (!t) return;
      dragging = t;
      t.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', ''); } catch {}
    });
    container.addEventListener('dragend', () => {
      if (dragging) {
        dragging.classList.remove('dragging');
        const ids = Array.from(container.querySelectorAll(itemSel)).map((el) => el.getAttribute('data-id'));
        if (onSort) onSort(ids);
        dragging = null;
      }
    });
    container.addEventListener('dragover', (e) => {
      if (!dragging) return;
      e.preventDefault();
      const target = e.target.closest(itemSel);
      if (!target || target === dragging) return;
      const rect = target.getBoundingClientRect();
      const after = (e.clientY - rect.top) > rect.height / 2;
      if (after) target.parentNode.insertBefore(dragging, target.nextSibling);
      else target.parentNode.insertBefore(dragging, target);
    });
  }

  // ---------- 供应商表单页 ----------
  const providerForm = document.getElementById('providerForm');
  if (providerForm) initProviderForm(providerForm);

  function initProviderForm(form) {
    const baseInput = form.querySelector('[name="base_url"]');
    const fmtInput  = form.querySelector('[name="api_format"]');
    const preview   = document.getElementById('urlPreview');

    function updatePreview() {
      const b = (baseInput.value || '').replace(/\/+$/, '');
      const f = (fmtInput.value || '').replace(/^\/+/, '');
      preview.textContent = b && f ? (b + '/' + f) : '—';
    }
    if (baseInput && fmtInput && preview) {
      baseInput.addEventListener('input', updatePreview);
      fmtInput.addEventListener('input',  updatePreview);
      updatePreview();
    }

    // 显示 / 隐藏 API Key
    const toggleKey = document.getElementById('toggleKey');
    const keyInput  = document.getElementById('apiKeyInput');
    if (toggleKey && keyInput) {
      toggleKey.addEventListener('click', () => {
        if (keyInput.type === 'password') { keyInput.type = 'text'; toggleKey.textContent = '🙈 隐藏'; }
        else                              { keyInput.type = 'password'; toggleKey.textContent = '👁 显示'; }
      });
    }

    // 添加模型行
    const list = document.getElementById('modelList');
    const addBtn = document.getElementById('addModelBtn');
    if (addBtn && list) {
      addBtn.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'model-row';
        row.draggable = true;
        row.innerHTML =
          '<span class="drag-handle" title="拖拽排序">⋮⋮</span>' +
          '<input type="text" name="models[][nickname]" placeholder="昵称（如 GPT-4o 主力）" required>' +
          '<input type="text" name="models[][model_id]" placeholder="模型 ID（如 gpt-4o）" required>' +
          '<input type="text" name="models[][context_window]" placeholder="128000">' +
          '<button type="button" class="icon-btn js-remove-model" title="删除">🗑️</button>';
        list.appendChild(row);
        row.querySelector('input').focus();
      });
      list.addEventListener('click', (e) => {
        if (e.target.closest('.js-remove-model')) {
          e.target.closest('.model-row').remove();
        }
      });
      // 模型行拖拽排序
      let dragging = null;
      list.addEventListener('dragstart', (e) => {
        const t = e.target.closest('.model-row');
        if (!t) return;
        dragging = t; t.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', ''); } catch {}
      });
      list.addEventListener('dragend', () => {
        if (dragging) { dragging.classList.remove('dragging'); dragging = null; }
      });
      list.addEventListener('dragover', (e) => {
        if (!dragging) return;
        e.preventDefault();
        const target = e.target.closest('.model-row');
        if (!target || target === dragging) return;
        const rect = target.getBoundingClientRect();
        const after = (e.clientY - rect.top) > rect.height / 2;
        if (after) target.parentNode.insertBefore(dragging, target.nextSibling);
        else target.parentNode.insertBefore(dragging, target);
      });
    }

    // 提交（AJAX）
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const isEdit = form.getAttribute('data-edit') === '1';
      const id = form.getAttribute('data-id');
      const url = isEdit ? '/api/providers/' + id : '/api/providers';

      // 收集模型（结构化）
      const models = [];
      list.querySelectorAll('.model-row').forEach((row) => {
        const inputs = row.querySelectorAll('input');
        models.push({
          nickname:       inputs[0].value.trim(),
          model_id:       inputs[1].value.trim(),
          context_window: inputs[2].value.trim(),
        });
      });

      const payload = {
        name:        form.name.value.trim(),
        base_url:    form.base_url.value.trim(),
        api_format:  form.api_format.value.trim(),
        api_key:     form.api_key.value,
        website_url: form.website_url.value.trim(),
        notes:       form.notes.value.trim(),
        models:      models,
      };

      api(url, { body: payload }).then((r) => {
        if (r && r.ok) {
          toast(isEdit ? '已更新' : '已创建', 'success');
          setTimeout(() => { window.location.href = r.redirect || '/dashboard'; }, 500);
        } else {
          toast((r && r.message) || '保存失败', 'error');
        }
      }).catch(() => toast('网络错误', 'error'));
    });
  }

  // ---------- 注册：发送验证码 ----------
  initSendCode('sendCodeBtn',     'reg_email',    '/register/code');
  initSendCode('sendResetCodeBtn', 'forgot_email', '/forgot/code');

  function initSendCode(btnId, emailId, url) {
    const btn = document.getElementById(btnId);
    const email = document.getElementById(emailId);
    if (!btn || !email) return;
    btn.addEventListener('click', () => {
      const v = email.value.trim();
      if (!v) { toast('请先输入邮箱', 'error'); email.focus(); return; }
      btn.disabled = true;
      btn.textContent = '发送中...';
      api(url, { body: { email: v } }).then((r) => {
        if (r && r.ok) {
          toast(r.message || '验证码已发送', 'success');
          countdown(btn, 60);
        } else {
          toast((r && r.message) || '发送失败', 'error');
          btn.disabled = false;
          btn.textContent = '发送验证码';
        }
      }).catch(() => {
        toast('网络错误', 'error');
        btn.disabled = false;
        btn.textContent = '发送验证码';
      });
    });
  }
  function countdown(btn, sec) {
    btn.disabled = true;
    btn.textContent = sec + ' 秒后重发';
    const t = setInterval(() => {
      sec--;
      if (sec <= 0) {
        clearInterval(t);
        btn.disabled = false;
        btn.textContent = '发送验证码';
      } else {
        btn.textContent = sec + ' 秒后重发';
      }
    }, 1000);
  }

  // ---------- 后台 SMTP 测试 ----------
  const testBtn = document.getElementById('testSmtpBtn');
  if (testBtn) {
    testBtn.addEventListener('click', () => {
      const to = document.getElementById('testEmail').value.trim();
      if (!to) { toast('请输入接收邮箱', 'error'); return; }
      testBtn.disabled = true;
      testBtn.textContent = '发送中...';
      api('/admin/smtp/test', { body: { to_email: to } }).then((r) => {
        toast((r && r.message) || (r && r.ok ? '已发送' : '失败'), r && r.ok ? 'success' : 'error');
      }).finally(() => {
        testBtn.disabled = false;
        testBtn.textContent = '发送测试邮件';
      });
    });
  }
})();
