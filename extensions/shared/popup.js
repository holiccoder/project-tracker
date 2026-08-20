// popup 逻辑：登录 / 项目搜索 / 项目账号列表 / 一键打开登录页自动填写

const $ = (id) => document.getElementById(id);

const loginView = $('login-view');
const mainView = $('main-view');
const loginForm = $('login-form');
const loginBtn = $('login-btn');
const loginError = $('login-error');
const listError = $('list-error');
const projectList = $('project-list');
const listStatus = $('list-status');
const searchInput = $('search-input');
const toast = $('toast');

let toastTimer = null;

function showToast(message) {
  toast.textContent = message;
  toast.classList.remove('hidden');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.add('hidden'), 2500);
}

function showLoginView() {
  mainView.classList.add('hidden');
  loginView.classList.remove('hidden');
}

function showMainView(admin) {
  loginView.classList.add('hidden');
  mainView.classList.remove('hidden');
  $('admin-name').textContent = (admin && admin.name) || '';
}

function setLoading(container, text) {
  container.innerHTML = `<div class="loading-row"><span class="spinner"></span>${escapeHtml(text || '加载中…')}</div>`;
}

function showListError(message) {
  if (!message) {
    listError.classList.add('hidden');
    return;
  }
  listError.textContent = message;
  listError.classList.remove('hidden');
}

// ---- 登录 ----

async function init() {
  const { serverUrl, token, admin } = await Auth.getSession();
  $('login-server').value = serverUrl || '';
  if (token) {
    showMainView(admin);
    loadProjects('');
  } else {
    showLoginView();
  }
}

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  loginError.classList.add('hidden');
  const serverUrl = $('login-server').value.trim();
  const email = $('login-email').value.trim();
  const password = $('login-password').value;

  loginBtn.disabled = true;
  loginBtn.textContent = '登录中…';
  try {
    const data = await apiLogin(serverUrl, email, password);
    await Auth.saveSession({
      serverUrl: serverUrl.replace(/\/+$/, ''),
      token: data.token,
      admin: data.admin,
    });
    showMainView(data.admin);
    loadProjects('');
  } catch (err) {
    loginError.textContent = err.message || '登录失败';
    loginError.classList.remove('hidden');
  } finally {
    loginBtn.disabled = false;
    loginBtn.textContent = '登录';
  }
});

// ---- 顶部按钮 ----

$('open-dashboard').addEventListener('click', () => {
  chrome.tabs.create({ url: chrome.runtime.getURL('dashboard.html') });
});

$('logout-btn').addEventListener('click', async () => {
  await apiLogout();
  showLoginView();
});

// ---- 项目列表 ----

async function loadProjects(search) {
  showListError(null);
  setLoading(projectList);
  listStatus.textContent = '';
  try {
    const res = await apiFetch('/api/projects', {
      query: { search: search || undefined },
    });
    renderProjects(res.data || []);
  } catch (err) {
    projectList.innerHTML = '';
    if (err.code === 'AUTH') {
      showLoginView();
      return;
    }
    showListError(err.message || '加载失败');
  }
}

function renderProjects(projects) {
  if (projects.length === 0) {
    projectList.innerHTML = '<div class="empty-row">暂无数据</div>';
    return;
  }
  projectList.innerHTML = '';
  for (const project of projects) {
    const item = document.createElement('div');
    item.className = 'project-item';

    const row = document.createElement('div');
    row.className = 'project-row';
    row.innerHTML =
      `<span class="project-name">${escapeHtml(project.name)}</span>` +
      `<span class="badge badge-status-${escapeHtml(project.status)}">${escapeHtml(project.status_label || project.status)}</span>`;
    row.addEventListener('click', () => toggleAccounts(item, project));

    item.appendChild(row);
    projectList.appendChild(item);
  }
}

// 展开/收起某项目的账号列表
async function toggleAccounts(item, project) {
  const existing = item.querySelector('.account-list');
  if (existing) {
    existing.remove();
    return;
  }

  const container = document.createElement('div');
  container.className = 'account-list';
  item.appendChild(container);
  setLoading(container);

  try {
    const res = await apiFetch('/api/accounts', {
      query: { project_id: project.id },
    });
    renderAccounts(container, res.data || []);
  } catch (err) {
    if (err.code === 'AUTH') {
      showLoginView();
      return;
    }
    container.innerHTML = `<div class="error-bar">${escapeHtml(err.message || '加载失败')}</div>`;
  }
}

function renderAccounts(container, accounts) {
  if (accounts.length === 0) {
    container.innerHTML = '<div class="empty-row">暂无数据</div>';
    return;
  }
  container.innerHTML = '';
  for (const account of accounts) {
    const row = document.createElement('div');
    row.className = 'account-row';

    const info = document.createElement('div');
    info.className = 'account-info';
    info.innerHTML =
      `<div class="account-site">${escapeHtml(account.website_name)}</div>` +
      `<div class="account-user">${escapeHtml(account.username)}</div>` +
      (account.note ? `<div class="account-note">${escapeHtml(account.note)}</div>` : '');
    info.addEventListener('click', () => openLoginPage(account));

    const actions = document.createElement('div');
    actions.className = 'account-actions';

    const copyUserBtn = document.createElement('button');
    copyUserBtn.className = 'btn-sm';
    copyUserBtn.textContent = '复制账号';
    copyUserBtn.title = '复制用户名';
    copyUserBtn.addEventListener('click', async (event) => {
      event.stopPropagation();
      await copyText(account.username);
      showToast('已复制用户名');
    });

    const copyPassBtn = document.createElement('button');
    copyPassBtn.className = 'btn-sm';
    copyPassBtn.textContent = '复制密码';
    copyPassBtn.addEventListener('click', async (event) => {
      event.stopPropagation();
      await copyText(account.password);
      showToast('已复制密码');
    });

    actions.appendChild(copyUserBtn);
    actions.appendChild(copyPassBtn);
    row.appendChild(info);
    row.appendChild(actions);
    container.appendChild(row);
  }
}

// 点击账号：暂存凭据（5 分钟有效）并打开登录页
async function openLoginPage(account) {
  if (!account.login_url) {
    showToast('该账号未配置登录地址');
    return;
  }
  await chrome.storage.local.set({
    pendingAutofill: {
      login_url: account.login_url,
      username: account.username,
      password: account.password,
      expiresAt: Date.now() + 5 * 60 * 1000,
    },
  });
  chrome.tabs.create({ url: account.login_url });
  showToast('已打开登录页，将自动填写');
}

// ---- 搜索（防抖 300ms） ----

searchInput.addEventListener(
  'input',
  debounce(() => loadProjects(searchInput.value.trim()), 300)
);

init();
