// popup 与 dashboard 共用的 API 客户端与工具函数。
// 只使用 chrome.* 命名空间的 Promise 形式 API。

class ApiError extends Error {
  constructor(message, code, status) {
    super(message);
    this.code = code;
    this.status = status;
  }
}

const Auth = {
  async getSession() {
    const { serverUrl, token, admin } = await chrome.storage.local.get([
      'serverUrl',
      'token',
      'admin',
    ]);
    return {
      serverUrl: serverUrl || (typeof EXTENSION_CONFIG !== 'undefined' ? EXTENSION_CONFIG.serverUrl : '') || '',
      token: token || '',
      admin: admin || null,
    };
  },

  async saveSession({ serverUrl, token, admin }) {
    await chrome.storage.local.set({ serverUrl, token, admin });
  },

  async saveServerUrl(serverUrl) {
    await chrome.storage.local.set({ serverUrl });
  },

  async clear() {
    await chrome.storage.local.remove(['token', 'admin']);
  },
};

function normalizeBaseUrl(serverUrl) {
  return (serverUrl || '').trim().replace(/\/+$/, '');
}

// 登录：401 → 邮箱或密码错误；网络异常 → 无法连接服务器
async function apiLogin(serverUrl, email, password) {
  const base = normalizeBaseUrl(serverUrl);
  if (!base) throw new ApiError('请填写服务器地址', 'NO_SERVER');
  let res;
  try {
    res = await fetch(`${base}/api/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email, password }),
    });
  } catch {
    throw new ApiError('无法连接服务器', 'NETWORK');
  }
  if (res.status === 401) throw new ApiError('邮箱或密码错误', 'AUTH', 401);
  let data = null;
  try {
    data = await res.json();
  } catch {
    data = null;
  }
  if (!res.ok) throw new ApiError((data && data.message) || `登录失败 (${res.status})`, 'HTTP', res.status);
  return data; // { token, admin }
}

// 已认证请求：自动带 token；401 时清掉本地 token 并抛出 AUTH 错误
async function apiFetch(path, { method = 'GET', body, query } = {}) {
  const { serverUrl, token } = await Auth.getSession();
  const base = normalizeBaseUrl(serverUrl);
  if (!base) throw new ApiError('请先在登录页配置服务器地址', 'NO_SERVER');
  if (!token) throw new ApiError('未登录', 'AUTH', 401);

  const url = new URL(base + path);
  for (const [key, value] of Object.entries(query || {})) {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, value);
    }
  }

  let res;
  try {
    res = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new ApiError('无法连接服务器', 'NETWORK');
  }

  if (res.status === 401) {
    await Auth.clear();
    throw new ApiError('登录已过期，请重新登录', 'AUTH', 401);
  }

  let data = null;
  try {
    data = await res.json();
  } catch {
    data = null;
  }
  if (!res.ok) {
    const message =
      (data && data.message) ||
      (data && data.errors && Object.values(data.errors).flat().join('；')) ||
      `请求失败 (${res.status})`;
    throw new ApiError(message, 'HTTP', res.status);
  }
  return data;
}

async function apiLogout() {
  try {
    await apiFetch('/api/auth/logout', { method: 'POST' });
  } catch {
    // 即使接口失败也要清理本地会话
  }
  await Auth.clear();
}

async function fetchMe() {
  return apiFetch('/api/auth/me');
}

// 拉取全部项目（用于下拉框），逐页请求直到最后一页
async function fetchAllProjects() {
  const first = await apiFetch('/api/projects', { query: { page: 1 } });
  const items = [...(first.data || [])];
  const lastPage = (first.meta && first.meta.last_page) || 1;
  for (let page = 2; page <= lastPage; page++) {
    const res = await apiFetch('/api/projects', { query: { page } });
    items.push(...(res.data || []));
  }
  return items;
}

function debounce(fn, delay) {
  let timer = null;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

async function copyText(text) {
  await navigator.clipboard.writeText(text);
}
