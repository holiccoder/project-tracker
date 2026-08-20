// 后台脚本：响应 content script 的凭据索取请求。
// 注意：service worker 环境，不要使用任何 DOM API。

chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  if (!message || message.type !== 'getPendingAutofill') {
    return false;
  }

  (async () => {
    try {
      const { pendingAutofill } = await chrome.storage.local.get('pendingAutofill');
      if (!pendingAutofill) {
        sendResponse({ ok: false, reason: 'empty' });
        return;
      }

      // 过期（5 分钟）即作废
      if (typeof pendingAutofill.expiresAt !== 'number' || Date.now() > pendingAutofill.expiresAt) {
        await chrome.storage.local.remove('pendingAutofill');
        sendResponse({ ok: false, reason: 'expired' });
        return;
      }

      // 校验目标站点与登录地址同源（hostname 一致）
      let expectedHostname = null;
      try {
        expectedHostname = new URL(pendingAutofill.login_url).hostname;
      } catch {
        expectedHostname = null;
      }
      if (!expectedHostname) {
        await chrome.storage.local.remove('pendingAutofill');
        sendResponse({ ok: false, reason: 'invalid_url' });
        return;
      }
      if (expectedHostname !== message.hostname) {
        sendResponse({ ok: false, reason: 'hostname_mismatch' });
        return;
      }

      // 一次性消费：立即删除后再返回凭据
      await chrome.storage.local.remove('pendingAutofill');
      sendResponse({
        ok: true,
        username: pendingAutofill.username,
        password: pendingAutofill.password,
      });
    } catch (err) {
      sendResponse({ ok: false, reason: 'error' });
    }
  })();

  // 返回 true 表示异步调用 sendResponse
  return true;
});
