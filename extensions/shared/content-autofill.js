// 内容脚本：向 background 索取一次性凭据并自动填写登录表单。
// 凭据用完即弃，不写回 storage，不自动提交表单。

(async () => {
  let response;
  try {
    response = await chrome.runtime.sendMessage({
      type: 'getPendingAutofill',
      hostname: location.hostname,
    });
  } catch {
    return; // background 不可用（如扩展刚重载），直接放弃
  }
  if (!response || !response.ok) return;

  const { username, password } = response;
  const RETRY_TIMEOUT = 10000; // 最多重试约 10 秒

  function isVisible(el) {
    if (!(el instanceof HTMLElement)) return false;
    const style = window.getComputedStyle(el);
    if (style.display === 'none' || style.visibility === 'hidden') return false;
    const rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
  }

  function isUserInputCandidate(el) {
    const type = (el.getAttribute('type') || 'text').toLowerCase();
    return ['text', 'email', 'tel'].includes(type);
  }

  function findPasswordField() {
    const fields = Array.from(document.querySelectorAll('input[type="password"]'));
    return fields.find(isVisible) || null;
  }

  function findUsernameField(passwordField) {
    const all = Array.from(document.querySelectorAll('input')).filter(
      (el) => isUserInputCandidate(el) && isVisible(el)
    );
    // 文档顺序在密码框之前的候选
    const before = all.filter(
      (el) => el !== passwordField &&
        (el.compareDocumentPosition(passwordField) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0
    );
    // 优先同一 form 内
    const inForm = passwordField.form
      ? before.filter((el) => el.form === passwordField.form)
      : [];
    const pool = inForm.length > 0 ? inForm : before;
    if (pool.length === 0) return null;

    // 优先 name/id/autocomplete 含 username|email|user|login|account 的候选
    const keyword = /username|email|user|login|account/i;
    const named = pool.filter((el) =>
      keyword.test(`${el.name || ''} ${el.id || ''} ${el.autocomplete || ''}`)
    );
    if (named.length > 0) return named[named.length - 1];
    // 否则取密码框之前最近的一个
    return pool[pool.length - 1];
  }

  function setNativeValue(el, value) {
    const descriptor = Object.getOwnPropertyDescriptor(
      window.HTMLInputElement.prototype,
      'value'
    );
    descriptor.set.call(el, value);
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function showToast() {
    const host = document.createElement('div');
    host.style.position = 'fixed';
    host.style.top = '12px';
    host.style.right = '12px';
    host.style.zIndex = '2147483647';
    const shadow = host.attachShadow({ mode: 'open' });
    const bar = document.createElement('div');
    bar.textContent = '已自动填写账号密码';
    bar.style.cssText = [
      'background:#2563eb',
      'color:#fff',
      'font:13px/1.4 -apple-system,"Segoe UI","Microsoft YaHei",sans-serif',
      'padding:8px 14px',
      'border-radius:8px',
      'box-shadow:0 4px 12px rgba(0,0,0,.25)',
      'opacity:1',
      'transition:opacity .3s',
    ].join(';');
    shadow.appendChild(bar);
    document.documentElement.appendChild(host);
    setTimeout(() => {
      bar.style.opacity = '0';
      setTimeout(() => host.remove(), 350);
    }, 3000);
  }

  function tryFill() {
    const passwordField = findPasswordField();
    if (!passwordField) return false;
    const usernameField = findUsernameField(passwordField);
    if (usernameField) setNativeValue(usernameField, username);
    setNativeValue(passwordField, password);
    showToast();
    return true;
  }

  if (tryFill()) return;

  // 找不到元素时：MutationObserver + 轮询重试，直到超时
  const deadline = Date.now() + RETRY_TIMEOUT;
  const observer = new MutationObserver(() => {
    if (tryFill()) cleanup();
  });
  const timer = setInterval(() => {
    if (Date.now() > deadline || tryFill()) cleanup();
  }, 500);

  function cleanup() {
    observer.disconnect();
    clearInterval(timer);
  }

  observer.observe(document.documentElement, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['style', 'class', 'type'],
  });
})();
