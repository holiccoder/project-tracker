const $ = (id) => document.getElementById(id);

const loginView = $('login-view');
const appView = $('app-view');
const loginForm = $('login-form');
const loginBtn = $('login-btn');
const loginError = $('login-error');
const errorBar = $('error-bar');
const modalRoot = $('modal-root');
const toast = $('toast');

let me = null;
let schemaContract = null;
let allProjects = [];
let allUsers = null;
let allDevLogs = null;
let toastTimer = null;
const tabLoaded = {};
const loaders = {};

function showToast(message) {
  toast.textContent = message;
  toast.classList.remove('hidden');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.add('hidden'), 2500);
}

function showError(message) {
  if (!message) {
    errorBar.classList.add('hidden');
    errorBar.textContent = '';
    return;
  }
  errorBar.textContent = message;
  errorBar.classList.remove('hidden');
}

function handleApiError(error) {
  if (error && error.code === 'AUTH') {
    showLoginView();
    return;
  }
  showError((error && error.message) || 'Request failed.');
}

function truncate(text, length) {
  const value = String(text ?? '');
  return value.length > length ? `${value.slice(0, length)}…` : value;
}

function showLoginView() {
  appView.classList.add('hidden');
  loginView.classList.remove('hidden');
}

function showAppView() {
  loginView.classList.add('hidden');
  appView.classList.remove('hidden');
}

function modelSchema(name) {
  return schemaContract.models[name];
}

function relationSchema(name) {
  return schemaContract.relations[name];
}

function fieldSchema(definition, name) {
  const resolved = typeof definition === 'string'
    ? (schemaContract.models[definition] || schemaContract.relations[definition])
    : definition;
  return resolved && resolved.fields ? resolved.fields[name] : null;
}

function optionLabel(definition, name, value) {
  const field = fieldSchema(definition, name);
  const option = (field && (field.runtimeOptions || field.options || [])).find(
    (item) => String(item.value) === String(value),
  );
  return (option && option.label) || value || '';
}

function scalarDefault(field) {
  if (!field || field.default === undefined) return '';
  if (typeof field.default !== 'object') return field.default;
  if (field.default.kind === 'today') return new Date().toISOString().slice(0, 10);
  if (field.default.kind === 'now_plus_days') {
    const date = new Date(Date.now() + Number(field.default.days || 0) * 86400000);
    return date.toISOString().slice(0, 16);
  }
  return '';
}

function resourcePath(name) {
  return name.replaceAll('_', '-');
}

function formatDateTime(value) {
  if (!value) return '';
  const text = String(value).replace(' ', 'T');
  return text.length >= 16 ? text.slice(0, 16) : text;
}

async function ensureReferenceOptions(definition) {
  for (const [name, field] of Object.entries(definition.fields || {})) {
    if (!field.endpoint || field.options || field.runtimeOptions) continue;

    let records = [];
    if (field.endpoint === '/api/projects') {
      records = allProjects;
    } else if (field.endpoint === '/api/users') {
      if (allUsers === null) allUsers = await fetchAll('/api/users');
      records = allUsers;
    } else if (field.endpoint === '/api/dev-logs') {
      if (allDevLogs === null) allDevLogs = await fetchAll('/api/dev-logs');
      records = allDevLogs;
    } else {
      records = await fetchAll(field.endpoint);
    }

    field.runtimeOptions = records.map((record) => ({
      value: record.id,
      label:
        field.endpoint === '/api/dev-logs'
          ? `${record.date || ''} · ${record.project_name || ''} · ${truncate(record.content, 60)}`
          : `${record.name || record.website_name || record.email || record.title || ''}${record.email && record.name ? ` · ${record.email}` : ''}`,
    }));
  }
}

function setOptions(select, options, emptyLabel = 'All') {
  if (!select) return;
  const current = select.value;
  select.innerHTML = `<option value="">${escapeHtml(emptyLabel)}</option>` +
    (options || []).map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`).join('');
  if ((options || []).some((option) => String(option.value) === current)) select.value = current;
}

function modelOptions(name, fieldName) {
  const definition = modelSchema(name);
  const field = definition && definition.fields[fieldName];
  return field ? field.runtimeOptions || field.options || [] : [];
}

function populateFilters() {
  const projectOptions = allProjects.map((project) => ({ value: project.id, label: project.name }));
  [
    'acc-filter-project',
    'task-filter-project',
    'log-filter-project',
    'issue-filter-project',
    'contract-filter-project',
  ].forEach((id) => setOptions($(id), projectOptions, 'All projects'));

  setOptions($('proj-filter-status'), modelOptions('projects', 'status'), 'All statuses');
  setOptions($('task-filter-status'), modelOptions('tasks', 'status'), 'All statuses');
  setOptions($('task-filter-priority'), modelOptions('tasks', 'priority'), 'All priorities');
  setOptions($('log-filter-status'), modelOptions('dev_logs', 'status'), 'All statuses');
  setOptions($('log-filter-category'), modelOptions('dev_logs', 'category'), 'All categories');
  setOptions($('issue-filter-status'), modelOptions('issues', 'status'), 'All statuses');
  setOptions($('issue-filter-severity'), modelOptions('issues', 'severity'), 'All severities');
}

function bindTabs() {
  document.querySelectorAll('.tab').forEach((tab) => {
    tab.addEventListener('click', () => activateTab(tab.dataset.tab));
  });
}

const tabInitializers = {
  accounts: loadAccounts,
  projects: loadProjectsTab,
  tasks: loadTasks,
  devlogs: loadDevLogs,
  updates: loadUpdates,
  issues: loadIssues,
  contracts: loadContracts,
  users: loadUsers,
};

function activateTab(name) {
  document.querySelectorAll('.tab').forEach((tab) => tab.classList.toggle('active', tab.dataset.tab === name));
  document.querySelectorAll('.tab-panel').forEach((panel) => panel.classList.toggle('hidden', panel.id !== `tab-${name}`));
  showError(null);
  if (!tabLoaded[name]) {
    tabLoaded[name] = true;
    tabInitializers[name]();
  }
}

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
    footer.innerHTML = '<div class="loading-row"><span class="spinner"></span>Loading…</div>';
    try {
      const response = await apiFetch(path, { query: { ...getQuery(), page: state.page + 1 } });
      const items = response.data || [];
      state.page = (response.meta && response.meta.current_page) || state.page + 1;
      state.lastPage = (response.meta && response.meta.last_page) || 1;
      for (const item of items) tbody.appendChild(renderRow(item));

      if (tbody.children.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="empty-row">No records.</td></tr>`;
        footer.innerHTML = '';
      } else if (state.page < state.lastPage) {
        footer.innerHTML = '';
        const more = document.createElement('button');
        more.textContent = 'Load more';
        more.addEventListener('click', () => load(false));
        footer.appendChild(more);
      } else {
        footer.innerHTML = '';
      }
    } catch (error) {
      footer.innerHTML = '';
      handleApiError(error);
    } finally {
      state.loading = false;
    }
  }

  return { reload: () => load(true), loadMore: () => load(false) };
}

function openModal(title, bodyHtml, onSubmit, { submitLabel = 'Save' } = {}) {
  const mask = document.createElement('div');
  mask.className = 'modal-mask';
  mask.innerHTML =
    `<div class="modal"><h3>${escapeHtml(title)}</h3><form id="modal-form">${bodyHtml}` +
    `<div class="modal-actions"><button type="button" id="modal-cancel">Cancel</button>` +
    `<button type="submit" id="modal-ok" class="btn-primary">${escapeHtml(submitLabel)}</button></div></form></div>`;
  modalRoot.appendChild(mask);

  const close = () => mask.remove();
  mask.addEventListener('click', (event) => {
    if (event.target === mask) close();
  });
  mask.querySelector('#modal-cancel').addEventListener('click', close);

  const form = mask.querySelector('#modal-form');
  const okButton = mask.querySelector('#modal-ok');
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    okButton.disabled = true;
    okButton.textContent = 'Saving…';
    try {
      await onSubmit(form, mask);
      close();
    } catch (error) {
      if (error && error.code === 'AUTH') {
        close();
        showLoginView();
        return;
      }
      showError((error && error.message) || 'Save failed.');
    } finally {
      okButton.disabled = false;
      okButton.textContent = submitLabel;
    }
  });

  return { close, form, mask };
}

function generatePassword(length = 16) {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%^&*';
  const values = new Uint32Array(length);
  crypto.getRandomValues(values);
  return Array.from(values, (value) => chars[value % chars.length]).join('');
}

function isRequired(field, operation) {
  return operation === 'create'
    ? Boolean(field.required_on_create ?? (field.required_on || []).includes('create'))
    : Boolean(field.required_on_edit ?? (field.required_on || []).includes('edit'));
}

function rawRecordValue(definitionKey, name, record, context) {
  if (context && context.values && Object.prototype.hasOwnProperty.call(context.values, name)) return context.values[name];
  if (!record) return scalarDefault(fieldSchema(definitionKey, name));
  if (name === 'members' && Array.isArray(record.members)) return record.members.map((member) => member.id);
  if ((name === 'user_id' || name === 'project_id') && record.id && record[name] === undefined) return record.id;
  if (name === 'unpaid_amount') return record.unpaid_amount;
  if (name === 'attachment_path' && record.attachment) return record.attachment.path;
  if (name === 'file_path' && record.file) return record.file.path;
  return record[name] ?? scalarDefault(fieldSchema(definitionKey, name));
}

function normalizedValue(definitionKey, name, value) {
  const field = fieldSchema(definitionKey, name);
  if (field && field.type === 'datetime') return formatDateTime(value);
  if (value && typeof value === 'object' && !Array.isArray(value)) return value.id ?? value.value ?? '';
  return value ?? '';
}

function secureDownloadPath(definitionKey, record, path) {
  if (!path || !record) return null;
  if (definitionKey === 'tasks') return `/api/tasks/${record.id}/attachments/${path}`;
  if (definitionKey === 'issues') return `/api/issues/${record.id}/attachment`;
  if (definitionKey === 'contracts') return `/api/contracts/${record.id}/download`;
  return null;
}

function fileObjects(definitionKey, name, record) {
  if (!record) return [];
  if (definitionKey === 'tasks' && Array.isArray(record.attachments)) return record.attachments;
  if (definitionKey === 'issues' && record.attachment) return [record.attachment];
  if (definitionKey === 'contracts' && record.file) return [record.file];
  return [];
}

function renderExistingFiles(definitionKey, name, record, operation, field) {
  const files = fileObjects(definitionKey, name, record);
  if (files.length === 0) return '';
  const removable = operation === 'edit' && definitionKey !== 'contracts';
  return `<div class="existing-files"><span class="field-hint">Existing files</span>` + files.map((file) => {
    const path = file.path || file.name || '';
    const downloadPath = secureDownloadPath(definitionKey, record, path);
    const action = downloadPath
      ? `<button type="button" class="btn-sm" data-download-file="${escapeHtml(downloadPath)}" data-download-name="${escapeHtml(file.name || path)}">Download</button>`
      : '';
    const remove = removable
      ? definitionKey === 'tasks'
        ? `<label class="inline-check"><input type="checkbox" data-remove-attachment="${escapeHtml(path)}">Remove</label>`
        : `<label class="inline-check"><input type="checkbox" data-remove-single="1">Remove</label>`
      : '';
    return `<div class="existing-file"><span>${escapeHtml(file.name || path)}</span>${action}${remove}</div>`;
  }).join('') + '</div>';
}

function renderField(definitionKey, name, field, record, operation, context = {}, nameOverride = null) {
  const inputName = nameOverride || field.request_name || name;
  const value = normalizedValue(definitionKey, name, rawRecordValue(definitionKey, name, record, context));
  const required = isRequired(field, operation);
  const readOnly = Boolean(field.read_only || field.display_only);
  const condition = field.visible_when;
  const conditionClass = condition && !conditionMatches(condition, record, context) ? ' hidden' : '';
  const wrapperAttrs = condition
    ? ` data-visible-field="${escapeHtml(condition.field)}" data-visible-equals="${escapeHtml(condition.equals)}"`
    : '';
  const label = `${field.label || name}${required ? ' *' : ''}`;

  if (field.type === 'computed') {
    const display = name === 'status'
      ? optionLabel(modelSchema(definitionKey), name, value)
      : value;
    return `<div class="readonly-field${conditionClass}"${wrapperAttrs}><span>${escapeHtml(label)}</span><strong>${escapeHtml(display)}</strong></div>`;
  }

  if (field.type === 'file') {
    const upload = field.upload || {};
    const accept = upload.accept ? ` accept="${escapeHtml(upload.accept.join(','))}"` : '';
    const multiple = field.multiple ? ' multiple' : '';
    const requiredAttr = required && operation === 'create' ? ' required' : '';
    return `<label class="field${conditionClass}"${wrapperAttrs}><span>${escapeHtml(label)}</span>` +
      renderExistingFiles(definitionKey, name, record, operation, field) +
      `<input type="file" data-input-field="${escapeHtml(name)}" name="${escapeHtml(inputName)}"${multiple}${accept}${requiredAttr}>` +
      `<span class="field-hint">${upload.max_size_kb ? `Max ${Math.round(upload.max_size_kb / 1024)} MB` : ''}${upload.max_files ? ` · ${upload.max_files} files` : ''}</span></label>`;
  }

  if (field.type === 'toggle') {
    const checked = value === true || value === 1 || value === '1';
    return `<label class="field checkbox-field${conditionClass}"${wrapperAttrs}><span>${escapeHtml(label)}</span><input type="checkbox" data-input-field="${escapeHtml(name)}" name="${escapeHtml(inputName)}"${checked ? ' checked' : ''}${readOnly ? ' disabled' : ''}></label>`;
  }

  if (field.type === 'select' || field.type === 'multi_select') {
    const options = field.runtimeOptions || field.options || [];
    const selected = field.type === 'multi_select' ? (Array.isArray(value) ? value : []) : [value];
    const multiple = field.type === 'multi_select' ? ' multiple' : '';
    const requiredAttr = required ? ' required' : '';
    const disabled = readOnly ? ' disabled' : '';
    return `<label class="field${conditionClass}"${wrapperAttrs}><span>${escapeHtml(label)}</span><select data-input-field="${escapeHtml(name)}" name="${escapeHtml(inputName)}"${multiple}${requiredAttr}${disabled}>` +
      (field.type === 'select' && !required ? '<option value="">—</option>' : '') +
      options.map((option) => `<option value="${escapeHtml(option.value)}"${selected.some((item) => String(item) === String(option.value)) ? ' selected' : ''}>${escapeHtml(option.label)}</option>`).join('') +
      '</select></label>';
  }

  const type = field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : field.type === 'datetime' ? 'datetime-local' : field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : field.type === 'tel' ? 'tel' : field.type === 'password' ? 'password' : 'text';
  const readonly = readOnly ? ' readonly' : '';
  const requiredAttr = required ? ' required' : '';
  const maxLength = field.max_length ? ` maxlength="${field.max_length}"` : '';
  const min = field.min !== undefined ? ` min="${field.min}"` : '';
  const step = field.type === 'number' ? ' step="0.01"' : '';
  const rows = field.rows ? ` rows="${field.rows}"` : '';
  const placeholder = field.placeholder ? ` placeholder="${escapeHtml(field.placeholder)}"` : '';
  const autocomplete = field.type === 'password' ? ' autocomplete="new-password"' : '';
  const control = field.type === 'textarea'
    ? `<textarea data-input-field="${escapeHtml(name)}" name="${escapeHtml(inputName)}"${requiredAttr}${maxLength}${readonly}${rows}>${escapeHtml(value)}</textarea>`
    : `<input type="${type}" data-input-field="${escapeHtml(name)}" name="${escapeHtml(inputName)}" value="${escapeHtml(value)}"${requiredAttr}${maxLength}${min}${step}${placeholder}${readonly}${autocomplete}>`;
  const generator = field.type === 'password' ? '<button type="button" class="btn-sm" data-generate-password>Generate</button>' : '';
  return `<label class="field${conditionClass}"${wrapperAttrs}><span>${escapeHtml(label)}</span><div class="input-with-action">${control}${generator}</div></label>`;
}

function conditionMatches(condition, record, context) {
  const value = (context && context.values && context.values[condition.field]) || (record && record[condition.field]) || (fieldSchema(context.definitionKey || '', condition.field)?.default ?? '');
  return String(value) === String(condition.equals);
}

function renderDefinitionFields(definitionKey, definition, record, operation, context = {}) {
  const hidden = new Set(context.hiddenFields || []);
  return Object.entries(definition.fields).filter(([name]) => !hidden.has(name)).map(([name, field]) =>
    renderField(definitionKey, name, field, record, operation, { ...context, definitionKey }),
  ).join('');
}

function renderRepeatable(definition, definitionKey, context) {
  const row = (index, record = null) => `<div class="repeatable-row" data-repeatable-row="${index}">${Object.entries(definition.fields).map(([name, field]) =>
    renderField(definitionKey, name, field, record, 'create', { ...context, definitionKey }, `logs[${index}][${name}]`),
  ).join('')}<button type="button" class="btn-sm btn-danger" data-remove-repeatable>Remove row</button></div>`;
  return `<div class="repeatable-fields" data-repeatable="logs"><div data-repeatable-rows>${row(0)}</div><button type="button" id="add-repeatable" class="btn-sm">Add row</button></div>`;
}

function readElementValue(element, field) {
  if (field.type === 'toggle') return element.checked;
  if (field.type === 'number') return element.value === '' ? null : Number(element.value);
  if (field.value_type === 'integer' || field.type === 'relation') return element.value === '' ? null : Number(element.value);
  return element.value;
}

function collectRepeatable(form, definition) {
  return [...form.querySelectorAll('[data-repeatable-row]')].map((row) => {
    const item = {};
    for (const [name, field] of Object.entries(definition.fields)) {
      const elements = [...row.querySelectorAll(`[data-input-field="${name}"]`)];
      if (field.type === 'computed' || field.type === 'file' || elements.length === 0) continue;
      if (field.type === 'multi_select') item[name] = elements.filter((element) => element.selected).map((element) => Number(element.value));
      else item[name] = readElementValue(elements[0], field);
    }
    return item;
  });
}

function collectDefinitionPayload(form, definitionKey, definition, context = {}) {
  if (definition.repeatable) return { payload: { logs: collectRepeatable(form, definition) }, files: [] };

  const payload = {};
  const files = [];
  for (const [name, field] of Object.entries(definition.fields)) {
    if (field.type === 'computed' || field.display_only) continue;
    const inputName = field.request_name || name;
    const elements = [...form.querySelectorAll(`[data-input-field="${name}"]`)];

    if (field.type === 'file') {
      for (const element of elements) {
        for (const file of [...(element.files || [])]) files.push({ key: inputName, file });
      }
      continue;
    }

    if (field.type === 'multi_select') {
      payload[inputName] = elements.filter((element) => element.selected).map((element) => Number(element.value));
      continue;
    }

    if (elements.length === 0) continue;
    const element = elements[0];
    const value = readElementValue(element, field);
    if (field.type === 'password' && value === '' && !isRequired(field, 'edit')) continue;
    if (value === '' && field.nullable) payload[inputName] = null;
    else payload[inputName] = value;
  }

  const removeAttachments = [...form.querySelectorAll('[data-remove-attachment]:checked')].map((input) => input.dataset.removeAttachment);
  if (removeAttachments.length) payload.remove_attachments = removeAttachments;
  if (form.querySelector('[data-remove-single]:checked')) payload.remove_attachment = true;

  for (const [key, value] of Object.entries(context.parentFields || {})) payload[key] = value;
  return { payload, files };
}

function appendFormValue(formData, key, value) {
  if (Array.isArray(value)) {
    value.forEach((item) => appendFormValue(formData, `${key}[]`, item));
    return;
  }
  if (value === null || value === undefined) formData.append(key, '');
  else if (typeof value === 'boolean') formData.append(key, value ? '1' : '0');
  else formData.append(key, String(value));
}

function requestBody(payload, files) {
  if (!files.length) return payload;
  const formData = new FormData();
  Object.entries(payload).forEach(([key, value]) => appendFormValue(formData, key, value));
  files.forEach(({ key, file }) => formData.append(key, file));
  return formData;
}

function relationRequest(definitionKey, operation, record, context) {
  const parent = context.parent || {};
  const id = parent.id;
  if (definitionKey === 'project_members') return { path: `/api/projects/${id}/members${operation === 'edit' ? `/${record.id}` : ''}`, method: operation === 'edit' ? 'PATCH' : 'POST' };
  if (definitionKey === 'user_projects') return { path: `/api/users/${id}/projects${operation === 'edit' ? `/${record.id}` : ''}`, method: operation === 'edit' ? 'PATCH' : 'POST' };
  if (definitionKey === 'project_payments') return { path: `/api/projects/${id}/payments${operation === 'edit' ? `/${record.id}` : ''}`, method: operation === 'edit' ? 'PUT' : 'POST' };
  if (definitionKey === 'project_invitations') return { path: `/api/projects/${id}/invitations`, method: 'POST' };
  if (definitionKey === 'task_comments') return { path: `/api/tasks/${id}/comments`, method: 'POST' };
  if (definitionKey === 'dev_log_updates' && operation === 'create' && id) return { path: `/api/dev-logs/${id}/updates`, method: 'POST' };
  if (definitionKey === 'dev_log_updates' && operation === 'edit') return { path: `/api/dev-log-updates/${record.id}`, method: 'PUT' };
  return null;
}

function relationListRequest(definitionKey, parent) {
  if (definitionKey === 'project_members' || definitionKey === 'project_payments' || definitionKey === 'project_invitations' || definitionKey === 'dev_log_batch') return `/api/projects/${parent.id}/${definitionKey === 'project_members' ? 'members' : definitionKey === 'project_payments' ? 'payments' : definitionKey === 'project_invitations' ? 'invitations' : 'dev-logs'}`;
  if (definitionKey === 'user_projects') return `/api/users/${parent.id}/projects`;
  if (definitionKey === 'task_comments') return `/api/tasks/${parent.id}/comments`;
  if (definitionKey === 'dev_log_updates') return `/api/dev-log-updates?dev_log_id=${encodeURIComponent(parent.id)}`;
  return null;
}

function relationDeleteRequest(definitionKey, parent, record) {
  if (definitionKey === 'project_members') return `/api/projects/${parent.id}/members/${record.id}`;
  if (definitionKey === 'user_projects') return `/api/users/${parent.id}/projects/${record.id}`;
  if (definitionKey === 'project_payments') return `/api/projects/${parent.id}/payments/${record.id}`;
  if (definitionKey === 'project_invitations') return `/api/projects/${parent.id}/invitations/${record.id}`;
  if (definitionKey === 'task_comments') return `/api/tasks/${parent.id}/comments/${record.id}`;
  if (definitionKey === 'dev_log_updates') return `/api/dev-log-updates/${record.id}`;
  return null;
}

function modelRequest(definitionKey, operation, record, context) {
  if (definitionKey === 'dev_log_updates' && operation === 'create' && context.parent && context.parent.id) return relationRequest(definitionKey, operation, record, context);
  const path = `/api/${resourcePath(definitionKey)}${operation === 'edit' ? `/${record.id}` : ''}`;
  return { path, method: operation === 'edit' ? 'PUT' : 'POST' };
}

async function openDefinitionModal(definitionKey, operation, record = null, context = {}) {
  try {
    const definition = schemaContract.models[definitionKey] || schemaContract.relations[definitionKey];
    await ensureReferenceOptions(definition);
    const isRelation = Boolean(context.relation);
    const prefix = context.listHtml || '';
    const fields = definition.repeatable
      ? renderRepeatable(definition, definitionKey, context)
      : renderDefinitionFields(definitionKey, definition, record, operation, context);
    const title = context.title || `${operation === 'edit' ? 'Edit' : 'New'} ${definitionKey.replaceAll('_', ' ')}`;
    const modal = openModal(title, prefix + fields, async (form) => {
      const { payload, files } = collectDefinitionPayload(form, definitionKey, definition, context);
      let request = isRelation ? relationRequest(definitionKey, operation, record, context) : modelRequest(definitionKey, operation, record, context);
      if (definition.repeatable) request = { path: `/api/projects/${context.parent.id}/dev-logs/batch`, method: 'POST' };
      if (!request) throw new ApiError('This form is not connected to an API endpoint.', 'HTTP', 500);
      await apiFetch(request.path, { method: request.method, body: requestBody(payload, files) });
      showToast(operation === 'edit' ? 'Saved.' : 'Created.');
      if (context.onSaved) await context.onSaved();
    });

    modal.form.dataset.status = record && record.status ? record.status : (definition.fields.status && definition.fields.status.default) || '';
    bindFormEnhancements(modal.form, definitionKey, definition, record, operation);
    bindDownloadButtons(modal.mask);
    if (context.relation) bindRelationActions(modal.mask, definitionKey, context.parent);
  } catch (error) {
    handleApiError(error);
  }
}

function bindFormEnhancements(form, definitionKey, definition, record, operation) {
  form.querySelectorAll('[data-generate-password]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = button.closest('.input-with-action').querySelector('input');
      input.value = generatePassword();
    });
  });
  const add = form.querySelector('#add-repeatable');
  if (add) {
    add.addEventListener('click', () => {
      const rows = form.querySelector('[data-repeatable-rows]');
      const index = rows.children.length;
      const wrapper = document.createElement('div');
      wrapper.innerHTML = renderRepeatable(definition, definitionKey, {}).match(/<div class="repeatable-row"[^>]*>[\s\S]*?<\/div>/)[0].replaceAll('logs[0]', `logs[${index}]`).replace('data-repeatable-row="0"', `data-repeatable-row="${index}"`);
      rows.appendChild(wrapper.firstElementChild);
      bindRepeatableRemove(form);
    });
  }
  bindRepeatableRemove(form);
  form.querySelectorAll('[data-visible-field]').forEach((wrapper) => wrapper.classList.toggle('hidden', !conditionMatches({ field: wrapper.dataset.visibleField, equals: wrapper.dataset.visibleEquals }, record, { definitionKey, values: { status: form.dataset.status } })));
}

function bindRepeatableRemove(form) {
  form.querySelectorAll('[data-remove-repeatable]').forEach((button) => {
    if (button.dataset.bound) return;
    button.dataset.bound = '1';
    button.addEventListener('click', () => button.closest('[data-repeatable-row]').remove());
  });
}

function renderRelationList(definitionKey, records) {
  if (!records || !records.length) return '<div class="relation-list empty-row">No related records.</div>';
  return `<div class="relation-list">${records.map((record) => {
    const title = record.name || record.email || record.website_name || record.remark || record.body || record.update || record.content || `#${record.id}`;
    const detail = record.role || record.amount || record.expires_at || record.created_at || '';
    const edit = ['project_members', 'user_projects', 'project_payments', 'dev_log_updates'].includes(definitionKey)
      ? '<button type="button" class="btn-sm" data-relation-edit>Edit</button>'
      : '';
    const remove = '<button type="button" class="btn-sm btn-danger" data-relation-remove>Remove</button>';
    return `<div class="relation-item" data-relation-record="${escapeHtml(JSON.stringify(record))}"><span><strong>${escapeHtml(title)}</strong><small>${escapeHtml(detail)}</small></span><span class="cell-actions">${edit}${remove}</span></div>`;
  }).join('')}</div>`;
}

async function openRelationManager(definitionKey, parent, title) {
  try {
    const endpoint = relationListRequest(definitionKey, parent);
    const response = definitionKey === 'dev_log_batch' ? { data: [] } : await apiFetch(endpoint);
    const records = response.data || [];
    const definition = relationSchema(definitionKey) || modelSchema(definitionKey);
    await ensureReferenceOptions(definition);
    const context = {
      relation: true,
      parent,
      title,
      listHtml: renderRelationList(definitionKey === 'dev_log_batch' ? 'dev_logs' : definitionKey, records),
      hiddenFields: definitionKey === 'dev_log_updates' ? ['dev_log_id'] : [],
      parentFields: definitionKey === 'dev_log_updates' ? { dev_log_id: parent.id } : {},
      onSaved: async () => {
        if (definitionKey === 'project_payments' && loaders.projects) loaders.projects.reload();
        if (definitionKey === 'task_comments' && loaders.tasks) loaders.tasks.reload();
      },
    };
    await openDefinitionModal(definitionKey, 'create', null, context);
  } catch (error) {
    handleApiError(error);
  }
}

function bindRelationActions(mask, definitionKey, parent) {
  mask.querySelectorAll('[data-relation-edit]').forEach((button) => {
    button.addEventListener('click', () => {
      const record = JSON.parse(button.closest('[data-relation-record]').dataset.relationRecord);
      openDefinitionModal(definitionKey, 'edit', record, { relation: true, parent, title: `Edit ${definitionKey.replaceAll('_', ' ')}` });
    });
  });
  mask.querySelectorAll('[data-relation-remove]').forEach((button) => {
    button.addEventListener('click', async () => {
      const record = JSON.parse(button.closest('[data-relation-record]').dataset.relationRecord);
      const path = relationDeleteRequest(definitionKey, parent, record);
      if (!path || !confirm('Remove this record?')) return;
      try {
        await apiFetch(path, { method: 'DELETE' });
        showToast('Removed.');
        const title = mask.querySelector('h3')?.textContent || 'Related records';
        mask.remove();
        openRelationManager(definitionKey, parent, title);
      } catch (error) {
        handleApiError(error);
      }
    });
  });
}

function bindDownloadButtons(root) {
  root.querySelectorAll('[data-download-file]').forEach((button) => {
    if (button.dataset.bound) return;
    button.dataset.bound = '1';
    button.addEventListener('click', async () => {
      try {
        await apiDownload(button.dataset.downloadFile, button.dataset.downloadName);
      } catch (error) {
        handleApiError(error);
      }
    });
  });
}

function actionFor(resource, status) {
  return (schemaContract.actions[resource] || []).filter((action) => (action.from || []).includes(status));
}

function renderStateActions(resource, record) {
  return actionFor(resource, record.status).map((action) => `<button type="button" class="btn-sm" data-state-action="${escapeHtml(action.name)}">${escapeHtml(action.label)}</button>`).join('');
}

async function openStateAction(resource, record, loader) {
  const action = (schemaContract.actions[resource] || []).find((item) => item.name === record.__action);
  if (!action) return;
  const requiresReason = (action.requires || []).includes('reject_reason');
  const reasonField = modelSchema('tasks').fields.reject_reason;
  const body = requiresReason
    ? `<label class="field"><span>${escapeHtml(reasonField.label)} *</span><textarea data-input-field="reject_reason" name="reject_reason" rows="${reasonField.rows || 2}" required></textarea></label>`
    : `<div class="readonly-field">${escapeHtml(action.label)}: ${escapeHtml(record.title || record.name || '')}</div>`;
  openModal(action.label, body, async (form) => {
    const payload = { action: action.name };
    if (requiresReason) payload.reject_reason = form.querySelector('[data-input-field="reject_reason"]').value;
    await apiFetch(`/${resource === 'tasks' ? 'api/tasks' : 'api/issues'}/${record.id}/status`, { method: 'PATCH', body: payload });
    showToast('Status updated.');
    loader.reload();
  });
}

function refreshLoader(name) {
  if (loaders[name]) loaders[name].reload();
}

function loadAccounts() {
  const search = $('acc-filter-search');
  const project = $('acc-filter-project');
  loaders.accounts = createListLoader({
    tbody: $('acc-tbody'), footer: $('acc-footer'), colspan: 7, path: '/api/accounts',
    getQuery: () => ({ project_id: project.value || undefined, search: search.value.trim() || undefined }),
    renderRow: renderAccountRow,
  });
  search.addEventListener('input', debounce(() => loaders.accounts.reload(), 300));
  project.addEventListener('change', () => loaders.accounts.reload());
  $('acc-new').addEventListener('click', () => openDefinitionModal('accounts', 'create', null, { title: 'New account', onSaved: () => refreshLoader('accounts') }));
  loaders.accounts.reload();
}

function renderAccountRow(account) {
  const tr = document.createElement('tr');
  tr.innerHTML = `<td>${escapeHtml(account.website_name)}</td><td>${escapeHtml(account.project_name || '')}</td><td>${escapeHtml(account.username)}</td>` +
    `<td><span class="password-cell"><span data-password-value>******</span><button type="button" class="btn-sm" data-toggle-password>Show</button><button type="button" class="btn-sm" data-copy-password>Copy</button></span></td>` +
    `<td>${account.login_url ? `<a href="${escapeHtml(account.login_url)}" target="_blank" rel="noopener">${escapeHtml(truncate(account.login_url, 40))}</a>` : ''}</td>` +
    `<td><div class="cell-main" title="${escapeHtml(account.note || '')}">${escapeHtml(account.note || '')}</div></td>` +
    `<td><span class="cell-actions"><button type="button" class="btn-sm" data-edit>Edit</button><button type="button" class="btn-sm btn-danger" data-delete>Delete</button></span></td>`;
  const password = tr.querySelector('[data-password-value]');
  tr.querySelector('[data-toggle-password]').addEventListener('click', (event) => {
    const visible = password.textContent !== '******';
    password.textContent = visible ? '******' : account.password;
    event.currentTarget.textContent = visible ? 'Show' : 'Hide';
  });
  tr.querySelector('[data-copy-password]').addEventListener('click', async () => { await copyText(account.password); showToast('Copied.'); });
  tr.querySelector('[data-edit]').addEventListener('click', () => openDefinitionModal('accounts', 'edit', account, { title: 'Edit account', onSaved: () => refreshLoader('accounts') }));
  tr.querySelector('[data-delete]').addEventListener('click', async () => {
    if (!confirm(`Delete ${account.website_name}?`)) return;
    try { await apiFetch(`/api/accounts/${account.id}`, { method: 'DELETE' }); showToast('Deleted.'); loaders.accounts.reload(); } catch (error) { handleApiError(error); }
  });
  return tr;
}

function loadProjectsTab() {
  const search = $('proj-filter-search');
  const status = $('proj-filter-status');
  loaders.projects = createListLoader({
    tbody: $('proj-tbody'), footer: $('proj-footer'), colspan: 7, path: '/api/projects',
    getQuery: () => ({ search: search.value.trim() || undefined, status: status.value || undefined }),
    renderRow: renderProjectRow,
  });
  search.addEventListener('input', debounce(() => loaders.projects.reload(), 300));
  status.addEventListener('change', () => loaders.projects.reload());
  $('proj-new').addEventListener('click', () => openDefinitionModal('projects', 'create', null, { title: 'New project', onSaved: refreshProjectReferences }));
  loaders.projects.reload();
}

async function refreshProjectReferences() {
  allProjects = await fetchAllProjects();
  populateFilters();
  refreshLoader('projects');
}

function renderProjectRow(project) {
  const tr = document.createElement('tr');
  tr.innerHTML = `<td>${escapeHtml(project.name)}</td><td>${escapeHtml(project.slug)}</td><td><span class="badge badge-status-${escapeHtml(project.status)}">${escapeHtml(project.status_label || optionLabel(modelSchema('projects'), 'status', project.status))}</span></td>` +
    `<td>${escapeHtml(project.amount ?? '')}</td><td>${escapeHtml(project.unpaid_amount ?? '')}</td><td>${escapeHtml(project.deadline || '')}</td>` +
    `<td><span class="cell-actions"><button type="button" class="btn-sm" data-edit>Edit</button><button type="button" class="btn-sm" data-members>Members</button><button type="button" class="btn-sm" data-payments>Payments</button><button type="button" class="btn-sm" data-invitations>Invitations</button><button type="button" class="btn-sm" data-batch-logs>Batch logs</button></span></td>`;
  tr.querySelector('[data-edit]').addEventListener('click', () => openDefinitionModal('projects', 'edit', project, { title: 'Edit project', onSaved: refreshProjectReferences }));
  tr.querySelector('[data-members]').addEventListener('click', () => openRelationManager('project_members', { id: project.id }, 'Project members'));
  tr.querySelector('[data-payments]').addEventListener('click', () => openRelationManager('project_payments', { id: project.id }, 'Project payments'));
  tr.querySelector('[data-invitations]').addEventListener('click', () => openRelationManager('project_invitations', { id: project.id }, 'Project invitations'));
  tr.querySelector('[data-batch-logs]').addEventListener('click', () => openRelationManager('dev_log_batch', { id: project.id }, 'Batch development logs'));
  return tr;
}

function loadTasks() {
  const project = $('task-filter-project');
  const status = $('task-filter-status');
  const priority = $('task-filter-priority');
  loaders.tasks = createListLoader({
    tbody: $('task-tbody'), footer: $('task-footer'), colspan: 6, path: '/api/tasks',
    getQuery: () => ({ project_id: project.value || undefined, status: status.value || undefined, priority: priority.value || undefined }),
    renderRow: renderTaskRow,
  });
  project.addEventListener('change', () => loaders.tasks.reload());
  status.addEventListener('change', () => loaders.tasks.reload());
  priority.addEventListener('change', () => loaders.tasks.reload());
  $('task-new').addEventListener('click', () => openDefinitionModal('tasks', 'create', null, { title: 'New task', onSaved: () => refreshLoader('tasks') }));
  loaders.tasks.reload();
}

function renderTaskRow(task) {
  const tr = document.createElement('tr');
  const attachments = task.attachments || [];
  tr.innerHTML = `<td><div class="cell-main" title="${escapeHtml(task.title)}">${escapeHtml(task.title)}</div></td><td>${escapeHtml(task.project_name || '')}</td><td>${escapeHtml(task.priority_label || optionLabel(modelSchema('tasks'), 'priority', task.priority))}</td>` +
    `<td><span class="badge badge-status-${escapeHtml(task.status)}">${escapeHtml(task.status_label || optionLabel(modelSchema('tasks'), 'status', task.status))}</span></td><td>${attachments.length}</td>` +
    `<td><span class="cell-actions"><button type="button" class="btn-sm" data-edit>Edit</button><button type="button" class="btn-sm" data-comments>Comments</button>${renderStateActions('tasks', task)}</span></td>`;
  tr.querySelector('[data-edit]').addEventListener('click', () => openDefinitionModal('tasks', 'edit', task, { title: 'Edit task', onSaved: () => refreshLoader('tasks') }));
  tr.querySelector('[data-comments]').addEventListener('click', () => openRelationManager('task_comments', { id: task.id }, 'Task comments'));
  tr.querySelectorAll('[data-state-action]').forEach((button) => button.addEventListener('click', () => openStateAction('tasks', { ...task, __action: button.dataset.stateAction }, loaders.tasks)));
  return tr;
}

function loadDevLogs() {
  const project = $('log-filter-project');
  const status = $('log-filter-status');
  const category = $('log-filter-category');
  loaders.devlogs = createListLoader({
    tbody: $('log-tbody'), footer: $('log-footer'), colspan: 7, path: '/api/dev-logs',
    getQuery: () => ({ project_id: project.value || undefined, status: status.value || undefined, category: category.value || undefined }),
    renderRow: renderDevLogRow,
  });
  project.addEventListener('change', () => loaders.devlogs.reload());
  status.addEventListener('change', () => loaders.devlogs.reload());
  category.addEventListener('change', () => loaders.devlogs.reload());
  $('log-new').addEventListener('click', () => openDefinitionModal('dev_logs', 'create', null, { title: 'New development log', onSaved: () => { allDevLogs = null; refreshLoader('devlogs'); } }));
  $('log-batch').addEventListener('click', () => {
    if (allProjects.length === 1) openRelationManager('dev_log_batch', { id: allProjects[0].id }, 'Batch development logs');
    else openDefinitionModal('dev_logs', 'create', null, { title: 'Choose a project in the log form' });
  });
  loaders.devlogs.reload();
}

function renderDevLogRow(log) {
  const tr = document.createElement('tr');
  const content = String(log.content || '');
  tr.innerHTML = `<td>${escapeHtml(log.date || '')}</td><td>${escapeHtml(log.project_name || '')}</td><td><div class="cell-main" title="${escapeHtml(content)}">${escapeHtml(truncate(content, 80))}</div></td>` +
    `<td><span class="badge badge-status-${escapeHtml(log.status)}">${escapeHtml(log.status_label || optionLabel(modelSchema('dev_logs'), 'status', log.status))}</span></td><td>${escapeHtml(log.category_label || optionLabel(modelSchema('dev_logs'), 'category', log.category))}</td>` +
    `<td><div class="cell-main">${escapeHtml(log.latest_update ? log.latest_update.update : '')}</div></td><td><span class="cell-actions"><button type="button" class="btn-sm" data-edit>Edit</button><button type="button" class="btn-sm" data-updates>Updates</button><button type="button" class="btn-sm btn-danger" data-delete>Delete</button></span></td>`;
  tr.querySelector('[data-edit]').addEventListener('click', () => openDefinitionModal('dev_logs', 'edit', log, { title: 'Edit development log', onSaved: () => { allDevLogs = null; refreshLoader('devlogs'); } }));
  tr.querySelector('[data-updates]').addEventListener('click', () => openRelationManager('dev_log_updates', { id: log.id }, 'Log updates'));
  tr.querySelector('[data-delete]').addEventListener('click', async () => {
    if (!confirm('Delete this development log?')) return;
    try { await apiFetch(`/api/dev-logs/${log.id}`, { method: 'DELETE' }); showToast('Deleted.'); loaders.devlogs.reload(); } catch (error) { handleApiError(error); }
  });
  return tr;
}

function loadUpdates() {
  const filter = $('update-filter-log');
  (async () => {
    try {
      if (allDevLogs === null) allDevLogs = await fetchAll('/api/dev-logs');
      setOptions(filter, allDevLogs.map((log) => ({ value: log.id, label: `${log.date} · ${log.project_name || ''} · ${truncate(log.content, 50)}` })), 'All logs');
    } catch (error) { handleApiError(error); }
  })();
  loaders.updates = createListLoader({
    tbody: $('update-tbody'), footer: $('update-footer'), colspan: 5, path: '/api/dev-log-updates',
    getQuery: () => ({ dev_log_id: filter.value || undefined }), renderRow: renderUpdateRow,
  });
  filter.addEventListener('change', () => loaders.updates.reload());
  $('update-new').addEventListener('click', () => openDefinitionModal('dev_log_updates', 'create', null, { title: 'New log update', onSaved: () => refreshLoader('updates') }));
  loaders.updates.reload();
}

function renderUpdateRow(update) {
  const tr = document.createElement('tr');
  tr.innerHTML = `<td>${escapeHtml(update.dev_log_id)}</td><td>${escapeHtml(update.project_name || '')}</td><td><div class="cell-main" title="${escapeHtml(update.update)}">${escapeHtml(truncate(update.update, 100))}</div></td><td>${escapeHtml((update.created_at || '').slice(0, 16))}</td><td><span class="cell-actions"><button type="button" class="btn-sm" data-edit>Edit</button><button type="button" class="btn-sm btn-danger" data-delete>Delete</button></span></td>`;
  tr.querySelector('[data-edit]').addEventListener('click', () => openDefinitionModal('dev_log_updates', 'edit', update, { title: 'Edit log update', onSaved: () => refreshLoader('updates') }));
  tr.querySelector('[data-delete]').addEventListener('click', async () => {
    if (!confirm('Delete this update?')) return;
    try { await apiFetch(`/api/dev-log-updates/${update.id}`, { method: 'DELETE' }); showToast('Deleted.'); loaders.updates.reload(); } catch (error) { handleApiError(error); }
  });
  return tr;
}

function loadIssues() {
  const project = $('issue-filter-project');
  const status = $('issue-filter-status');
  const severity = $('issue-filter-severity');
  loaders.issues = createListLoader({
    tbody: $('issue-tbody'), footer: $('issue-footer'), colspan: 6, path: '/api/issues',
    getQuery: () => ({ project_id: project.value || undefined, status: status.value || undefined, severity: severity.value || undefined }), renderRow: renderIssueRow,
  });
  project.addEventListener('change', () => loaders.issues.reload());
  status.addEventListener('change', () => loaders.issues.reload());
  severity.addEventListener('change', () => loaders.issues.reload());
  $('issue-new').addEventListener('click', () => openDefinitionModal('issues', 'create', null, { title: 'New issue', onSaved: () => refreshLoader('issues') }));
  loaders.issues.reload();
}

function renderIssueRow(issue) {
  const tr = document.createElement('tr');
  const attachment = issue.attachment;
  tr.innerHTML = `<td>${escapeHtml(issue.title)}</td><td>${escapeHtml(issue.project_name || '')}</td><td>${escapeHtml(issue.severity_label || optionLabel(modelSchema('issues'), 'severity', issue.severity))}</td>` +
    `<td><span class="badge badge-status-${escapeHtml(issue.status)}">${escapeHtml(issue.status_label || optionLabel(modelSchema('issues'), 'status', issue.status))}</span></td>` +
    `<td>${attachment ? '<button type="button" class="btn-sm" data-download>Download</button>' : ''}</td><td><span class="cell-actions"><button type="button" class="btn-sm" data-edit>Edit</button>${renderStateActions('issues', issue)}<button type="button" class="btn-sm btn-danger" data-delete>Delete</button></span></td>`;
  tr.querySelector('[data-edit]').addEventListener('click', () => openDefinitionModal('issues', 'edit', issue, { title: 'Edit issue', onSaved: () => refreshLoader('issues') }));
  if (attachment) tr.querySelector('[data-download]').addEventListener('click', () => apiDownload(`/api/issues/${issue.id}/attachment`, attachment.name).catch(handleApiError));
  tr.querySelectorAll('[data-state-action]').forEach((button) => button.addEventListener('click', () => openStateAction('issues', { ...issue, __action: button.dataset.stateAction }, loaders.issues)));
  tr.querySelector('[data-delete]').addEventListener('click', async () => {
    if (!confirm('Delete this issue?')) return;
    try { await apiFetch(`/api/issues/${issue.id}`, { method: 'DELETE' }); showToast('Deleted.'); loaders.issues.reload(); } catch (error) { handleApiError(error); }
  });
  return tr;
}

function loadContracts() {
  const project = $('contract-filter-project');
  loaders.contracts = createListLoader({
    tbody: $('contract-tbody'), footer: $('contract-footer'), colspan: 5, path: '/api/contracts',
    getQuery: () => ({ project_id: project.value || undefined }), renderRow: renderContractRow,
  });
  project.addEventListener('change', () => loaders.contracts.reload());
  $('contract-new').addEventListener('click', () => openDefinitionModal('contracts', 'create', null, { title: 'New contract', onSaved: () => refreshLoader('contracts') }));
  loaders.contracts.reload();
}

function renderContractRow(contract) {
  const tr = document.createElement('tr');
  tr.innerHTML = `<td>${escapeHtml(contract.name)}</td><td>${escapeHtml(contract.project_name || '')}</td><td>${escapeHtml(contract.file?.name || contract.name)}</td><td>${escapeHtml(contract.uploaded_by?.name || '')}</td>` +
    `<td><span class="cell-actions"><button type="button" class="btn-sm" data-download>Download</button><button type="button" class="btn-sm" data-edit>Edit</button><button type="button" class="btn-sm btn-danger" data-delete>Delete</button></span></td>`;
  tr.querySelector('[data-download]').addEventListener('click', () => apiDownload(`/api/contracts/${contract.id}/download`, contract.file?.name || contract.name).catch(handleApiError));
  tr.querySelector('[data-edit]').addEventListener('click', () => openDefinitionModal('contracts', 'edit', contract, { title: 'Edit contract', onSaved: () => refreshLoader('contracts') }));
  tr.querySelector('[data-delete]').addEventListener('click', async () => {
    if (!confirm('Delete this contract?')) return;
    try { await apiFetch(`/api/contracts/${contract.id}`, { method: 'DELETE' }); showToast('Deleted.'); loaders.contracts.reload(); } catch (error) { handleApiError(error); }
  });
  return tr;
}

function loadUsers() {
  const search = $('user-filter-search');
  loaders.users = createListLoader({
    tbody: $('user-tbody'), footer: $('user-footer'), colspan: 7, path: '/api/users',
    getQuery: () => ({ search: search.value.trim() || undefined }), renderRow: renderUserRow,
  });
  search.addEventListener('input', debounce(() => { allUsers = null; loaders.users.reload(); }, 300));
  $('user-new').addEventListener('click', () => openDefinitionModal('users', 'create', null, { title: 'New user', onSaved: () => { allUsers = null; refreshLoader('users'); } }));
  loaders.users.reload();
}

function renderUserRow(user) {
  const tr = document.createElement('tr');
  tr.innerHTML = `<td>${escapeHtml(user.name)}</td><td>${escapeHtml(user.email)}</td><td>${escapeHtml(user.wechat || '')}</td><td>${escapeHtml(user.phone || '')}</td><td>${escapeHtml(user.projects_count || 0)}</td><td>${escapeHtml(user.remark || '')}</td>` +
    `<td><span class="cell-actions"><button type="button" class="btn-sm" data-edit>Edit</button><button type="button" class="btn-sm" data-projects>Projects</button><button type="button" class="btn-sm btn-danger" data-delete>Delete</button></span></td>`;
  tr.querySelector('[data-edit]').addEventListener('click', () => openDefinitionModal('users', 'edit', user, { title: 'Edit user', onSaved: () => { allUsers = null; refreshLoader('users'); } }));
  tr.querySelector('[data-projects]').addEventListener('click', () => openRelationManager('user_projects', { id: user.id }, 'User projects'));
  tr.querySelector('[data-delete]').addEventListener('click', async () => {
    if (!confirm(`Delete ${user.name}?`)) return;
    try { await apiFetch(`/api/users/${user.id}`, { method: 'DELETE' }); showToast('Deleted.'); loaders.users.reload(); } catch (error) { handleApiError(error); }
  });
  return tr;
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
    schemaContract = await apiFetch('/api/form-schemas');
    allProjects = await fetchAllProjects();
    populateFilters();
    bindTabs();
    activateTab('accounts');
  } catch (error) {
    handleApiError(error);
  }
}

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  loginError.classList.add('hidden');
  loginBtn.disabled = true;
  loginBtn.textContent = 'Signing in…';
  try {
    const serverUrl = $('login-server').value.trim();
    const data = await apiLogin(serverUrl, $('login-email').value.trim(), $('login-password').value);
    await Auth.saveSession({ serverUrl: serverUrl.replace(/\/+$/, ''), token: data.token, admin: data.admin });
    await init();
  } catch (error) {
    loginError.textContent = error.message || 'Sign in failed.';
    loginError.classList.remove('hidden');
  } finally {
    loginBtn.disabled = false;
    loginBtn.textContent = 'Sign in';
  }
});

$('logout-btn').addEventListener('click', async () => {
  await apiLogout();
  me = null;
  schemaContract = null;
  showLoginView();
});

init();
