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
      serverUrl:
        serverUrl ||
        (typeof EXTENSION_CONFIG !== 'undefined' ? EXTENSION_CONFIG.serverUrl : '') ||
        '',
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

async function apiLogin(serverUrl, email, password) {
  const base = normalizeBaseUrl(serverUrl);
  if (!base) throw new ApiError('Enter the server URL.', 'NO_SERVER');

  let res;
  try {
    res = await fetch(`${base}/api/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email, password }),
    });
  } catch {
    throw new ApiError('Unable to connect to the server.', 'NETWORK');
  }

  if (res.status === 401) throw new ApiError('Invalid email or password.', 'AUTH', 401);
  const data = await parseResponse(res);
  if (!res.ok) throw httpError(res, data);
  return data;
}

async function apiFetch(path, { method = 'GET', body, query, headers = {} } = {}) {
  const { serverUrl, token } = await Auth.getSession();
  const base = normalizeBaseUrl(serverUrl);
  if (!base) throw new ApiError('Configure the server URL first.', 'NO_SERVER');
  if (!token) throw new ApiError('You are not signed in.', 'AUTH', 401);

  const url = new URL(base + path);
  for (const [key, value] of Object.entries(query || {})) {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, value);
    }
  }

  const isForm = typeof FormData !== 'undefined' && body instanceof FormData;
  const requestHeaders = {
    Accept: 'application/json',
    Authorization: `Bearer ${token}`,
    ...headers,
  };
  let requestMethod = method;
  let requestBody = body;

  if (isForm) {
    // Laravel's multipart method spoofing keeps file updates compatible with
    // the same PUT/PATCH resource endpoint used by JSON clients.
    if (['PUT', 'PATCH', 'DELETE'].includes(method) && !body.has('_method')) {
      body.append('_method', method);
      requestMethod = 'POST';
    }
  } else if (body !== undefined) {
    requestHeaders['Content-Type'] = 'application/json';
    requestBody = JSON.stringify(body);
  }

  let res;
  try {
    res = await fetch(url, {
      method: requestMethod,
      headers: requestHeaders,
      body: requestBody,
    });
  } catch {
    throw new ApiError('Unable to connect to the server.', 'NETWORK');
  }

  if (res.status === 401) {
    await Auth.clear();
    throw new ApiError('Your session expired. Sign in again.', 'AUTH', 401);
  }

  const data = await parseResponse(res);
  if (!res.ok) throw httpError(res, data);
  return data;
}

async function parseResponse(res) {
  const contentType = res.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    try {
      return await res.json();
    } catch {
      return null;
    }
  }

  try {
    return await res.text();
  } catch {
    return null;
  }
}

function httpError(res, data) {
  const errors = data && data.errors ? Object.values(data.errors).flat().join(' ') : '';
  return new ApiError(
    (data && data.message) || errors || `Request failed (${res.status}).`,
    'HTTP',
    res.status,
  );
}

async function apiDownload(path, filename) {
  const { serverUrl, token } = await Auth.getSession();
  const base = normalizeBaseUrl(serverUrl);
  if (!base) throw new ApiError('Configure the server URL first.', 'NO_SERVER');
  if (!token) throw new ApiError('You are not signed in.', 'AUTH', 401);

  let res;
  try {
    res = await fetch(base + path, {
      headers: { Accept: '*/*', Authorization: `Bearer ${token}` },
    });
  } catch {
    throw new ApiError('Unable to connect to the server.', 'NETWORK');
  }

  if (res.status === 401) {
    await Auth.clear();
    throw new ApiError('Your session expired. Sign in again.', 'AUTH', 401);
  }
  if (!res.ok) throw httpError(res, await parseResponse(res));

  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename || 'download';
  link.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

async function apiLogout() {
  try {
    await apiFetch('/api/auth/logout', { method: 'POST' });
  } catch {
    // Local session cleanup is still required when the server is unavailable.
  }
  await Auth.clear();
}

async function fetchMe() {
  return apiFetch('/api/auth/me');
}

async function fetchAll(path, query = {}) {
  const first = await apiFetch(path, { query: { ...query, page: 1 } });
  const items = [...(first.data || [])];
  const lastPage = (first.meta && first.meta.last_page) || 1;
  for (let page = 2; page <= lastPage; page += 1) {
    const response = await apiFetch(path, { query: { ...query, page } });
    items.push(...(response.data || []));
  }
  return items;
}

async function fetchAllProjects() {
  return fetchAll('/api/projects');
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

async function copyText(value) {
  await navigator.clipboard.writeText(value);
}
