// dashboard 逻辑：登录 / 四个标签页（账号、项目、任务、开发日志）

const $ = (id) => document.getElementById(id);

const loginView = $('login-view');
const appView = $('app-view');
const loginForm = $('login-form');
const loginBtn = $('login-btn');
const loginError = $('login-error');
const errorBar = $('error-bar');
const modalRoot = $('modal-root');
const toast = $('toast');

let me = null; // GET /api/auth/me
let allProjects = []; // 下拉框用的项目列表
let toastTimer = null;

function showToast(message) {
  toast.textContent = message;
  toast.classList.remove('hidden');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.add('hidden'), 2500);
}

function showError(message) {
  if (!message) {
    errorBar.classList.add('hidden');
    return;
  }
  errorBar.textContent = message;
  errorBar.classList.remove('hidden');
}

function handleApiError(err) {
  if (err && err.code === 'AUTH') {
    showLoginView();
    return;
  }
  showError((err && err.message) || '请求失败');
}

function truncate(text, length) {
  const value = String(text ?? '');
  return value.length > length ? value.slice(0, length) + '…' : value;
}

// ---- 登录 / 退出 ----

function showLoginView() {
  appView.classList.add('hidden');
  loginView.classList.remove('hidden');
}

function showAppView() {
  loginView.classList.add('hidden');
  appView.classList.remove('hidden');
}

async function init() {
  const { serverUrl, token } = await Auth.getSession();
  $('login-server').value = serverUrl || '';
  if (!token) {
    showLoginView();
    return;
  }
  showAppView();
  try {
    me = await fetchMe();
    $('admin-name').textContent = me.name || '';
    allProjects = await fetchAllProjects();
    fillProjectSelects();
    bindTabs();
    activateTab('accounts');
  } catch (err) {
    handleApiError(err);
  }
}

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  loginError.classList.add('hidden');
  const serverUrl = $('login-server').value.trim();
  loginBtn.disabled = true;
  loginBtn.textContent = '登录中…';
  try {
    const data = await apiLogin(serverUrl, $('login-email').value.trim(), $('login-password').value);
    await Auth.saveSession({
      serverUrl: serverUrl.replace(/\/+$/, ''),
      token: data.token,
      admin: data.admin,
    });
    loginView.classList.add('hidden');
    await init();
  } catch (err) {
    loginError.textContent = err.message || '登录失败';
    loginError.classList.remove('hidden');
  } finally {
    loginBtn.disabled = false;
    loginBtn.textContent = '登录';
  }
});

$('logout-btn').addEventListener('click', async () => {
  await apiLogout();
  me = null;
  showLoginView();
});

// ---- 标签页切换 ----

const tabInitializers = {
  accounts: loadAccounts,
  projects: loadProjectsTab,
  tasks: loadTasks,
  devlogs: loadDevLogs,
};
const tabLoaded = {};

function bindTabs() {
  document.querySelectorAll('.tab').forEach((tab) => {
    tab.addEventListener('click', () => activateTab(tab.dataset.tab));
  });
}

function activateTab(name) {
  document.querySelectorAll('.tab').forEach((tab) => {
    tab.classList.toggle('active', tab.dataset.tab === name);
  });
  document.querySelectorAll('.tab-panel').forEach((panel) => {
    panel.classList.toggle('hidden', panel.id !== `tab-${name}`);
  });
  showError(null);
  if (!tabLoaded[name]) {
    tabLoaded[name] = true;
    tabInitializers[name]();
  }
}

function fillProjectSelects() {
  for (const id of ['acc-filter-project', 'task-filter-project', 'log-filter-project']) {
    const select = $(id);
    select.innerHTML =
      '<option value="">全部项目</option>' +
      allProjects
        .map((p) => `<option value="${p.id}">${escapeHtml(p.name)}</option>`)
        .join('');
  }
}

// ---- 通用列表加载（分页 + 加载更多） ----

function createListLoader({ tbody, footer, colspan, path, getQuery, renderRow }) {
  const state = { page: 0, lastPage: 1, loading: false };

  async function load(reset) {
    if (state.loading) return;
    if (reset) {
      state.page = 0;
      state.lastPage = 1;
      tbody.innerHTML = '';
    }
    if (state.page >= state.lastPage && state.page > 0) return;

    state.loading = true;
    footer.innerHTML = '<div class="loading-row"><span class="spinner"></span>加载中…</div>';
    try {
      const res = await apiFetch(path, { query: { ...getQuery(), page: state.page + 1 } });
      const items = res.data || [];
      state.page = (res.meta && res.meta.current_page) || state.page + 1;
      state.lastPage = (res.meta && res.meta.last_page) || 1;

      if (reset) tbody.innerHTML = '';
      for (const item of items) tbody.appendChild(renderRow(item));

      if (tbody.children.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="empty-row">暂无数据</td></tr>`;
        footer.innerHTML = '';
      } else if (state.page < state.lastPage) {
        footer.innerHTML = '';
        const moreBtn = document.createElement('button');
        moreBtn.textContent = '加载更多';
        moreBtn.addEventListener('click', () => load(false));
        footer.appendChild(moreBtn);
      } else {
        footer.innerHTML = '';
      }
    } catch (err) {
      footer.innerHTML = '';
      handleApiError(err);
    } finally {
      state.loading = false;
    }
  }

  return { reload: () => load(true), loadMore: () => load(false) };
}

// ---- 模态框 ----

function openModal(title, bodyHtml, onSubmit) {
  const mask = document.createElement('div');
  mask.className = 'modal-mask';
  mask.innerHTML =
    `<div class="modal"><h3>${escapeHtml(title)}</h3>` +
    `<form id="modal-form">${bodyHtml}` +
    '<div class="modal-actions">' +
    '<button type="button" id="modal-cancel">取消</button>' +
    '<button type="submit" id="modal-ok" class="btn-primary">保存</button>' +
    '</div></form></div>';
  modalRoot.appendChild(mask);

  const close = () => mask.remove();
  mask.addEventListener('click', (event) => {
    if (event.target === mask) close();
  });
  mask.querySelector('#modal-cancel').addEventListener('click', close);

  const form = mask.querySelector('#modal-form');
  const okBtn = mask.querySelector('#modal-ok');
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    okBtn.disabled = true;
    okBtn.textContent = '保存中…';
    try {
      await onSubmit(form);
      close();
    } catch (err) {
      if (err && err.code === 'AUTH') {
        close();
        showLoginView();
        return;
      }
      showError((err && err.message) || '保存失败');
    } finally {
      okBtn.disabled = false;
      okBtn.textContent = '保存';
    }
  });

  return { close, form };
}

function generatePassword(length = 16) {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%^&*';
  const values = new Uint32Array(length);
  crypto.getRandomValues(values);
  return Array.from(values, (v) => chars[v % chars.length]).join('');
}

function slugify(text) {
  return String(text || '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

// ==================== 账号管理 ====================

let accountsLoader = null;

function loadAccounts() {
  const searchInput = $('acc-filter-search');
  const projectSelect = $('acc-filter-project');

  accountsLoader = createListLoader({
    tbody: $('acc-tbody'),
    footer: $('acc-footer'),
    colspan: 7,
    path: '/api/accounts',
    getQuery: () => ({
      project_id: projectSelect.value || undefined,
      search: searchInput.value.trim() || undefined,
    }),
    renderRow: renderAccountRow,
  });

  searchInput.addEventListener('input', debounce(() => accountsLoader.reload(), 300));
  projectSelect.addEventListener('change', () => accountsLoader.reload());
  $('acc-new').addEventListener('click', () => openAccountModal(null));

  accountsLoader.reload();
}

function renderAccountRow(account) {
  const tr = document.createElement('tr');
  tr.innerHTML =
    `<td><div class="cell-main">${escapeHtml(account.website_name)}</div></td>` +
    `<td>${escapeHtml(account.project_name || '')}</td>` +
    `<td>${escapeHtml(account.username)}</td>` +
    `<td><span class="password-cell">` +
    `<span class="password-value" data-pwd>******</span>` +
    `<button type="button" class="btn-sm" data-toggle-pwd>显示</button>` +
    `<button type="button" class="btn-sm" data-copy-pwd>复制</button>` +
    `</span></td>` +
    `<td>${account.login_url ? `<a href="${escapeHtml(account.login_url)}" target="_blank" rel="noopener">${escapeHtml(truncate(account.login_url, 40))}</a>` : ''}</td>` +
    `<td><div class="cell-main" title="${escapeHtml(account.note || '')}">${escapeHtml(account.note || '')}</div></td>` +
    `<td><span class="cell-actions">` +
    `<button type="button" class="btn-sm" data-edit>编辑</button>` +
    `<button type="button" class="btn-sm btn-danger" data-delete>删除</button>` +
    `</span></td>`;

  const pwdValue = tr.querySelector('[data-pwd]');
  const toggleBtn = tr.querySelector('[data-toggle-pwd]');
  toggleBtn.addEventListener('click', () => {
    const visible = pwdValue.textContent !== '******';
    pwdValue.textContent = visible ? '******' : account.password;
    toggleBtn.textContent = visible ? '显示' : '隐藏';
  });
  tr.querySelector('[data-copy-pwd]').addEventListener('click', async () => {
    await copyText(account.password);
    showToast('已复制密码');
  });
  tr.querySelector('[data-edit]').addEventListener('click', () => openAccountModal(account));
  tr.querySelector('[data-delete]').addEventListener('click', async () => {
    if (!confirm(`确定删除账号「${account.website_name} / ${account.username}」吗？`)) return;
    try {
      await apiFetch(`/api/accounts/${account.id}`, { method: 'DELETE' });
      showToast('已删除');
      accountsLoader.reload();
    } catch (err) {
      handleApiError(err);
    }
  });
  return tr;
}

function openAccountModal(account) {
  const isEdit = !!account;
  const projectOptions = allProjects
    .map((p) => `<option value="${p.id}" ${isEdit && account.project_id === p.id ? 'selected' : ''}>${escapeHtml(p.name)}</option>`)
    .join('');

  openModal(
    isEdit ? '编辑账号' : '新建账号',
    `<label><span>所属项目</span><select name="project_id" required>${projectOptions}</select></label>` +
      `<label><span>网站名称</span><input type="text" name="website_name" required value="${escapeHtml(isEdit ? account.website_name : '')}"></label>` +
      `<label><span>登录地址</span><input type="url" name="login_url" required placeholder="https://…" value="${escapeHtml(isEdit ? account.login_url : '')}"></label>` +
      `<label><span>用户名</span><input type="text" name="username" required value="${escapeHtml(isEdit ? account.username : '')}"></label>` +
      `<div class="field-row">` +
      `<label><span>密码</span><input type="text" name="password" required value="${escapeHtml(isEdit ? account.password : '')}"></label>` +
      `<button type="button" id="gen-pwd" class="btn-sm">随机生成</button>` +
      `</div>` +
      `<label><span>备注</span><textarea name="note" rows="2">${escapeHtml(isEdit && account.note ? account.note : '')}</textarea></label>`,
    async (form) => {
      const fd = new FormData(form);
      const body = {
        project_id: Number(fd.get('project_id')),
        website_name: fd.get('website_name').trim(),
        login_url: fd.get('login_url').trim(),
        username: fd.get('username').trim(),
        password: fd.get('password'),
        note: fd.get('note').trim() || undefined,
      };
      if (isEdit) {
        await apiFetch(`/api/accounts/${account.id}`, { method: 'PUT', body });
      } else {
        await apiFetch('/api/accounts', { method: 'POST', body });
      }
      showToast(isEdit ? '已保存' : '已创建');
      accountsLoader.reload();
    }
  );

  modalRoot.querySelector('#gen-pwd').addEventListener('click', () => {
    modalRoot.querySelector('input[name="password"]').value = generatePassword();
  });
}

// ==================== 项目管理 ====================

let projectsLoader = null;

function loadProjectsTab() {
  const searchInput = $('proj-filter-search');

  projectsLoader = createListLoader({
    tbody: $('proj-tbody'),
    footer: $('proj-footer'),
    colspan: 6,
    path: '/api/projects',
    getQuery: () => ({ search: searchInput.value.trim() || undefined }),
    renderRow: renderProjectRow,
  });

  searchInput.addEventListener('input', debounce(() => projectsLoader.reload(), 300));
  $('proj-new').addEventListener('click', () => openProjectModal(null));

  projectsLoader.reload();
}

function renderProjectRow(project) {
  const tr = document.createElement('tr');
  tr.innerHTML =
    `<td><div class="cell-main">${escapeHtml(project.name)}</div></td>` +
    `<td>${escapeHtml(project.slug)}</td>` +
    `<td><span class="badge badge-status-${escapeHtml(project.status)}">${escapeHtml(project.status_label || project.status)}</span></td>` +
    `<td>${escapeHtml(project.deadline || '')}</td>` +
    `<td>${escapeHtml(project.created_at ? String(project.created_at).slice(0, 10) : '')}</td>` +
    `<td><span class="cell-actions"><button type="button" class="btn-sm" data-edit>编辑</button></span></td>`;
  tr.querySelector('[data-edit]').addEventListener('click', () => openProjectModal(project));
  return tr;
}

const PROJECT_STATUS_OPTIONS = [
  ['active', '进行中'],
  ['delivered', '已交付'],
  ['paused', '已暂停'],
];

function openProjectModal(project) {
  const isEdit = !!project;
  const statusOptions = PROJECT_STATUS_OPTIONS.map(
    ([value, label]) =>
      `<option value="${value}" ${isEdit && project.status === value ? 'selected' : ''}>${label}</option>`
  ).join('');

  openModal(
    isEdit ? '编辑项目' : '新建项目',
    `<label><span>名称（必填）</span><input type="text" name="name" required value="${escapeHtml(isEdit ? project.name : '')}"></label>` +
      `<label><span>slug（必填，唯一）</span><input type="text" name="slug" required value="${escapeHtml(isEdit ? project.slug : '')}"></label>` +
      `<label><span>状态</span><select name="status">${statusOptions}</select></label>` +
      `<label><span>截止日期</span><input type="date" name="deadline" value="${escapeHtml(isEdit && project.deadline ? project.deadline : '')}"></label>` +
      `<label><span>描述</span><textarea name="description" rows="2">${escapeHtml(isEdit && project.description ? project.description : '')}</textarea></label>` +
      `<label><span>备注</span><textarea name="remark" rows="2">${escapeHtml(isEdit && project.remark ? project.remark : '')}</textarea></label>`,
    async (form) => {
      const fd = new FormData(form);
      const body = {
        name: fd.get('name').trim(),
        slug: fd.get('slug').trim(),
        status: fd.get('status'),
        deadline: fd.get('deadline') || undefined,
        description: fd.get('description').trim() || undefined,
        remark: fd.get('remark').trim() || undefined,
      };
      if (isEdit) {
        await apiFetch(`/api/projects/${project.id}`, { method: 'PUT', body });
      } else {
        body.created_by = me.id;
        await apiFetch('/api/projects', { method: 'POST', body });
      }
      showToast(isEdit ? '已保存' : '已创建');
      allProjects = await fetchAllProjects();
      fillProjectSelects();
      projectsLoader.reload();
    }
  );

  // 新建时根据名称自动生成 slug（用户改过 slug 后停止跟随）
  if (!isEdit) {
    const nameInput = modalRoot.querySelector('input[name="name"]');
    const slugInput = modalRoot.querySelector('input[name="slug"]');
    let slugTouched = false;
    slugInput.addEventListener('input', () => {
      slugTouched = true;
    });
    nameInput.addEventListener('input', () => {
      if (!slugTouched) slugInput.value = slugify(nameInput.value);
    });
  }
}

// ==================== 任务管理 ====================

let tasksLoader = null;

const TASK_PRIORITY_OPTIONS = [
  ['low', '低'],
  ['medium', '中'],
  ['high', '高'],
];

function loadTasks() {
  const projectSelect = $('task-filter-project');
  const statusSelect = $('task-filter-status');

  tasksLoader = createListLoader({
    tbody: $('task-tbody'),
    footer: $('task-footer'),
    colspan: 6,
    path: '/api/tasks',
    getQuery: () => ({
      project_id: projectSelect.value || undefined,
      status: statusSelect.value || undefined,
    }),
    renderRow: renderTaskRow,
  });

  projectSelect.addEventListener('change', () => tasksLoader.reload());
  statusSelect.addEventListener('change', () => tasksLoader.reload());

  tasksLoader.reload();
}

function renderTaskRow(task) {
  const tr = document.createElement('tr');
  tr.innerHTML =
    `<td><div class="cell-main" title="${escapeHtml(task.title)}">${escapeHtml(task.title)}</div></td>` +
    `<td>${escapeHtml(task.project_name || '')}</td>` +
    `<td>${escapeHtml(task.priority_label || task.priority)}</td>` +
    `<td><span class="badge badge-status-${escapeHtml(task.status)}">${escapeHtml(task.status_label || task.status)}</span></td>` +
    `<td>${escapeHtml(task.due_date || '')}</td>` +
    `<td><span class="cell-actions"><button type="button" class="btn-sm" data-edit>编辑</button></span></td>`;
  tr.querySelector('[data-edit]').addEventListener('click', () => openTaskModal(task));
  return tr;
}

function openTaskModal(task) {
  const priorityOptions = TASK_PRIORITY_OPTIONS.map(
    ([value, label]) =>
      `<option value="${value}" ${task.priority === value ? 'selected' : ''}>${label}</option>`
  ).join('');

  openModal(
    '编辑任务',
    `<div class="readonly-field">当前状态：${escapeHtml(task.status_label || task.status)}（状态不可在此修改）</div>` +
      `<label><span>标题</span><input type="text" name="title" required value="${escapeHtml(task.title)}"></label>` +
      `<label><span>描述</span><textarea name="description" rows="3">${escapeHtml(task.description || '')}</textarea></label>` +
      `<label><span>优先级</span><select name="priority">${priorityOptions}</select></label>` +
      `<label><span>截止日期</span><input type="date" name="due_date" value="${escapeHtml(task.due_date || '')}"></label>`,
    async (form) => {
      const fd = new FormData(form);
      await apiFetch(`/api/tasks/${task.id}`, {
        method: 'PUT',
        body: {
          title: fd.get('title').trim(),
          description: fd.get('description').trim() || undefined,
          priority: fd.get('priority'),
          due_date: fd.get('due_date') || undefined,
        },
      });
      showToast('已保存');
      tasksLoader.reload();
    }
  );
}

// ==================== 开发日志（只读） ====================

let devLogsLoader = null;

function loadDevLogs() {
  const projectSelect = $('log-filter-project');
  const statusSelect = $('log-filter-status');

  devLogsLoader = createListLoader({
    tbody: $('log-tbody'),
    footer: $('log-footer'),
    colspan: 6,
    path: '/api/dev-logs',
    getQuery: () => ({
      project_id: projectSelect.value || undefined,
      status: statusSelect.value || undefined,
    }),
    renderRow: renderDevLogRow,
  });

  projectSelect.addEventListener('change', () => devLogsLoader.reload());
  statusSelect.addEventListener('change', () => devLogsLoader.reload());

  devLogsLoader.reload();
}

function renderDevLogRow(log) {
  const tr = document.createElement('tr');
  const content = String(log.content ?? '');
  tr.innerHTML =
    `<td>${escapeHtml(log.date || '')}</td>` +
    `<td>${escapeHtml(log.project_name || '')}</td>` +
    `<td><div class="cell-main" title="${escapeHtml(content)}">${escapeHtml(truncate(content, 80))}</div></td>` +
    `<td><span class="badge badge-status-${escapeHtml(log.status)}">${escapeHtml(log.status_label || log.status)}</span></td>` +
    `<td>${escapeHtml(log.category_label || log.category || '')}</td>` +
    `<td><div class="cell-main">${escapeHtml(log.latest_update ? log.latest_update.update : '')}</div></td>`;
  return tr;
}

init();
