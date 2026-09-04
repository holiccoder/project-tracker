# Project Assistant API

All endpoints except `POST /api/auth/login` require either
`Authorization: Bearer <token>` or the legacy `token` request parameter. A
Sanctum admin token issued by the login endpoint is preferred; `API_TOKEN` and
`DEV_LOG_API_TOKEN` remain accepted for legacy automation.

## Shared input contract

```http
GET /api/form-schemas
```

The authenticated response contains `models`, `relations`, `actions`, and
`filters`. Every field definition is the contract used by Filament and the
browser extension. It includes the ordered field name, label, type,
`required_on_create`, `required_on_edit`, default, `nullable`, limits,
relationship metadata, enum `options`, upload rules, visibility conditions,
`read_only`, and both `request_name` and `api_transport_key`.

The top-level model order is:

- `users`: name, email, wechat, phone, remark, password
- `projects`: name, slug, description, members, status, amount, paid_amount,
  unpaid_amount, deadline, repo_url, remark
- `tasks`: project, title, description, attachments, priority, read-only
  status, conditional rejection reason
- `dev_logs`: project, date, status, category, content
- `dev_log_updates`: development log, update
- `issues`: project, title, description, attachment, severity, read-only status
- `contracts`: project, name, contract file
- `accounts`: project, website name, login URL, username, password, note

`unpaid_amount`, statuses, authors, uploaders, timestamps, and invitation
tokens are display-only/system-managed. Task and issue status changes must use
the legal actions returned in `actions`; sending a status in an edit payload
does not change it.

## Common response and transport rules

List endpoints return `{ "data": [...], "meta": { ... } }`; show/create/update
endpoints return the resource object. Dates are `Y-m-d`, timestamps are ISO
8601. Send blank nullable values as JSON `null` (or an empty multipart value)
to clear them.

Use `multipart/form-data` for uploads. File updates may use POST with a
`_method=PUT` or `_method=PATCH` override. The API supports multiple task
attachments, task attachment removal via `remove_attachments[]`, issue
attachment removal via `remove_attachment`, and replacement of existing
issue/contract files.

## Authentication and users

```http
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
GET    /api/users?search=...
POST   /api/users
GET    /api/users/{user}
PUT    /api/users/{user}
DELETE /api/users/{user}
```

User fields follow the `users` schema. Passwords are accepted on create and
are optional when editing; blank edit passwords leave the existing password
unchanged.

## Projects and memberships

```http
GET    /api/projects?status=active&search=...
POST   /api/projects
GET    /api/projects/{project}
PUT    /api/projects/{project}
DELETE /api/projects/{project}
GET    /api/projects/{project}/members
POST   /api/projects/{project}/members
PATCH  /api/projects/{project}/members/{user}
DELETE /api/projects/{project}/members/{user}
GET    /api/users/{user}/projects
POST   /api/users/{user}/projects
PATCH  /api/users/{user}/projects/{project}
DELETE /api/users/{user}/projects/{project}
```

`{project}` accepts either a numeric ID or slug. A project slug may be omitted
on create; the server generates the same unique fallback used by Filament.
Membership inputs are `user_id`/`project_id`, `role`, and `can_view_price`.

## Development logs and updates

```http
GET    /api/dev-logs
POST   /api/dev-logs
GET    /api/dev-logs/{dev_log}
PUT    /api/dev-logs/{dev_log}
DELETE /api/dev-logs/{dev_log}
GET    /api/dev-log-updates?dev_log_id=...
POST   /api/dev-log-updates
PUT    /api/dev-log-updates/{update}
DELETE /api/dev-log-updates/{update}
POST   /api/dev-logs/{dev_log}/updates
PATCH  /api/dev-logs/{dev_log}/updates/{update}
POST   /api/projects/{project}/dev-logs/batch
```

The batch endpoint accepts `logs[]` entries with the four fields in the
`dev_log_batch` relation schema.

## Tasks, comments, and status actions

```http
GET    /api/tasks
POST   /api/tasks
GET    /api/tasks/{task}
PUT    /api/tasks/{task}
DELETE /api/tasks/{task}
PATCH  /api/tasks/{task}/status
GET    /api/tasks/{task}/comments
POST   /api/tasks/{task}/comments
DELETE /api/tasks/{task}/comments/{comment}
GET    /api/tasks/{task}/attachments/{path}
```

Task status actions are `confirm`, `reject`, `start`, `restart`, and
`complete`. `reject` requires `reject_reason`. Comment creation is an admin
workflow and accepts only the comment body, matching the Filament form.

## Issues and contracts

```http
GET    /api/issues
POST   /api/issues
GET    /api/issues/{issue}
PUT    /api/issues/{issue}
DELETE /api/issues/{issue}
PATCH  /api/issues/{issue}/status
GET    /api/issues/{issue}/attachment

GET    /api/contracts
POST   /api/contracts
GET    /api/contracts/{contract}
PUT    /api/contracts/{contract}
DELETE /api/contracts/{contract}
GET    /api/contracts/{contract}/download
```

Issue actions are `start`, `resolve`, and `close`. Issue status is never
edited as a field. Contract uploads use the `file` transport key and are
stored privately; use the authenticated download endpoint.

## Accounts, payments, and invitations

```http
GET    /api/accounts
POST   /api/accounts
GET    /api/accounts/{account}
PUT    /api/accounts/{account}
DELETE /api/accounts/{account}

GET    /api/projects/{project}/payments
POST   /api/projects/{project}/payments
PUT    /api/projects/{project}/payments/{payment}
DELETE /api/projects/{project}/payments/{payment}

GET    /api/projects/{project}/invitations
POST   /api/projects/{project}/invitations
DELETE /api/projects/{project}/invitations/{invitation}
```

Payments assign the authenticated admin as `created_by` and keep the
project's computed paid amount synchronized. Invitation tokens are generated
server-side; responses expose an authenticated `invite_link`, not a token
input field. Account passwords are encrypted at rest.

## Errors

`401` means authentication failed, `404` means the resource or scoped relation
does not exist, and `422` means validation failed or a requested state
transition is illegal. Validation responses include an `errors` object.
