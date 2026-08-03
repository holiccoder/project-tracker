# 项目实施与反馈系统 API 文档

所有接口均通过 `Authorization: Bearer <token>` 或请求参数 `token` 进行认证。  
可接受的 Token：

- `API_TOKEN`（推荐，用于新接口）
- `DEV_LOG_API_TOKEN`（兼容原有开发日志接口）

Base URL：`https://your-domain.com/api`

---

## 目录

- [通用约定](#通用约定)
- [开发日志](#开发日志)
- [项目](#项目)
- [任务](#任务)
- [问题](#问题)
- [合同](#合同)

---

## 通用约定

### 认证方式

```http
Authorization: Bearer your-api-token
```

或 URL/表单参数：

```http
GET /api/projects?token=your-api-token
```

未认证或 Token 错误返回：

```json
{
  "message": "Unauthorized."
}
```

### 通用响应格式

列表接口返回分页数据：

```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 0
  }
}
```

单个资源返回对象本身。

### 时间格式

所有时间字段均为 ISO 8601 格式，如 `2026-08-03T05:42:00.000000Z`；日期字段为 `Y-m-d` 格式，如 `2026-08-03`。

---

## 开发日志

### 列表开发日志

```http
GET /api/dev-logs
```

**Query 参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| project_id | integer | 否 | 按项目 ID 筛选 |
| project_slug | string | 否 | 按项目 slug 筛选 |
| status | string | 否 | `in_progress` / `completed` |
| category | string | 否 | `agent_independent` / `human_agent_collaboration` |

**响应示例：**

```json
{
  "data": [
    {
      "id": 1,
      "project_id": 1,
      "project_name": "官网改版",
      "date": "2026-08-02",
      "content": "完成首页设计稿",
      "status": "completed",
      "status_label": "已完成",
      "category": "agent_independent",
      "category_label": "AI 自主完成",
      "latest_update": {
        "id": 8,
        "dev_log_id": 1,
        "update": "补充了接口测试结果",
        "created_at": "2026-08-03T12:00:00.000000Z",
        "updated_at": "2026-08-03T12:00:00.000000Z"
      },
      "created_at": "2026-08-02T12:00:00.000000Z",
      "updated_at": "2026-08-02T12:00:00.000000Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 50, "total": 1 }
}
```

### 添加开发日志更新

```http
POST /api/dev-logs/{dev_log}/updates
```

**Body 参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| update | string | 是 | 更新内容 |

**响应示例：**

```json
{
  "id": 8,
  "dev_log_id": 1,
  "update": "补充了接口测试结果",
  "created_at": "2026-08-03T12:00:00.000000Z",
  "updated_at": "2026-08-03T12:00:00.000000Z"
}
```

### 更新开发日志更新

```http
PATCH /api/dev-logs/{dev_log}/updates/{update}
```

**Body 参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| update | string | 是 | 更新内容 |

### 创建开发日志

```http
POST /api/dev-logs
```

**Body 参数（`multipart/form-data` 或 `application/json`）：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| project_id | integer | 与 project_slug 二选一 | 项目 ID |
| project_slug | string | 与 project_id 二选一 | 项目 slug |
| content | string | 是 | 日志内容 |
| date | string | 否 | 日期，格式 `Y-m-d`，默认今天 |
| status | string | 否 | `in_progress` / `completed`，默认 `in_progress` |
| category | string | 否 | `agent_independent` / `human_agent_collaboration`，默认 `agent_independent` |

**响应：** 201，返回创建的开发日志对象。

---

## 项目

### 列表项目

```http
GET /api/projects
```

**Query 参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| status | string | 否 | `active` / `delivered` / `paused` |
| search | string | 否 | 按名称或 slug 搜索 |

**响应示例：**

```json
{
  "data": [
    {
      "id": 1,
      "name": "官网改版",
      "slug": "website-redesign",
      "description": "公司官网全新改版",
      "status": "active",
      "status_label": "进行中",
      "amount": "10000.00",
      "paid_amount": "5000.00",
      "unpaid_amount": "5000.00",
      "deadline": "2026-09-01",
      "repo_url": "https://github.com/example/website",
      "remark": "备注",
      "created_by": 1,
      "tasks_total": 10,
      "tasks_done": 3,
      "members": [
        { "id": 1, "name": "张三" }
      ],
      "created_at": "2026-08-01T00:00:00.000000Z",
      "updated_at": "2026-08-01T00:00:00.000000Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 50, "total": 1 }
}
```

### 获取单个项目

```http
GET /api/projects/{project}
```

`{project}` 可以是项目 ID 或 slug。

### 创建项目

```http
POST /api/projects
```

**Body 参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 是 | 项目名称 |
| slug | string | 是 | 唯一标识 |
| description | string | 否 | 项目描述 |
| status | string | 否 | `active` / `delivered` / `paused`，默认 `active` |
| amount | numeric | 否 | 项目金额 |
| paid_amount | numeric | 否 | 已付金额，默认 0 |
| deadline | string | 否 | 截止日期 `Y-m-d` |
| repo_url | string | 否 | 仓库地址 |
| remark | string | 否 | 备注 |
| created_by | integer | 是 | 创建者 admin ID |

**响应：** 201，返回创建的项目对象。

### 更新项目

```http
PUT /api/projects/{project}
```

**Body 参数：** 同创建项目，均为可选字段（`name`、`slug`、`created_by` 传值时必填）。

### 删除项目

```http
DELETE /api/projects/{project}
```

**响应：**

```json
{
  "message": "Project deleted."
}
```

---

## 任务

### 列表任务

```http
GET /api/tasks
```

**Query 参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| project_id | integer | 否 | 按项目 ID 筛选 |
| project_slug | string | 否 | 按项目 slug 筛选 |
| status | string | 否 | `pending` / `confirmed` / `in_progress` / `done` / `accepted` / `rejected` / `changes_requested` |
| priority | string | 否 | `low` / `medium` / `high` |

### 获取单个任务

```http
GET /api/tasks/{task}
```

### 创建任务

```http
POST /api/tasks
```

**Body 参数（`multipart/form-data`）：**}

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| project_id | integer | 与 project_slug 二选一 | 项目 ID |
| project_slug | string | 与 project_id 二选一 | 项目 slug |
| title | string | 是 | 任务标题 |
| description | string | 否 | 任务描述 |
| priority | string | 否 | `low` / `medium` / `high`，默认 `medium` |
| status | string | 否 | 默认 `pending` |
| due_date | string | 否 | 截止日期 `Y-m-d` |
| created_by | integer | 否 | 委派者 user ID |
| attachments | file[] | 否 | 附件，每个最大 10MB |

**响应示例：**

```json
{
  "id": 1,
  "project_id": 1,
  "project_name": "官网改版",
  "title": "设计首页",
  "description": "...",
  "priority": "high",
  "priority_label": "高",
  "status": "pending",
  "status_label": "待确认",
  "due_date": "2026-08-10",
  "reject_reason": null,
  "completed_at": null,
  "accepted_at": null,
  "created_by": { "id": 2, "name": "李四" },
  "attachments": [
    { "name": "home.png", "url": "https://your-domain.com/storage/task-attachments/xxx.png" }
  ],
  "comments": null,
  "created_at": "2026-08-03T05:42:00.000000Z",
  "updated_at": "2026-08-03T05:42:00.000000Z"
}
```

### 更新任务

```http
PUT /api/tasks/{task}
```

**Body 参数：** 标题、描述、优先级、截止日期、created_by 等，均为可选。

### 更新任务状态

```http
PATCH /api/tasks/{task}/status
```

**Body 参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| action | string | 是 | `confirm` / `reject` / `start` / `restart` / `complete` / `accept` / `request_changes` |
| reject_reason | string | action=reject 时必填 | 拒绝原因 |

状态流转规则：

- `pending` → `confirmed` / `rejected`
- `confirmed` → `in_progress`
- `in_progress` → `done`
- `done` → `accepted` / `changes_requested`
- `changes_requested` → `in_progress`

### 删除任务

```http
DELETE /api/tasks/{task}
```

### 下载任务附件

```http
GET /api/tasks/{task}/attachments/{path}
```

`{path}` 为附件存储路径，接口会返回文件下载响应。

---

## 问题

### 列表问题

```http
GET /api/issues
```

**Query 参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| project_id | integer | 否 | 按项目 ID 筛选 |
| project_slug | string | 否 | 按项目 slug 筛选 |
| status | string | 否 | `open` / `in_progress` / `resolved` / `closed` |
| severity | string | 否 | `normal` / `serious` / `blocking` |

### 获取单个问题

```http
GET /api/issues/{issue}
```

### 创建问题

```http
POST /api/issues
```

**Body 参数（`multipart/form-data`）：**}

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| project_id | integer | 与 project_slug 二选一 | 项目 ID |
| project_slug | string | 与 project_id 二选一 | 项目 slug |
| title | string | 是 | 问题标题 |
| description | string | 否 | 问题描述 |
| severity | string | 否 | `normal` / `serious` / `blocking`，默认 `normal` |
| status | string | 否 | 默认 `open` |
| attachment | file | 否 | 附件，最大 10MB |
| created_by | integer | 是 | 创建者 admin ID |

### 更新问题

```http
PUT /api/issues/{issue}
```

**Body 参数：** 标题、描述、严重程度、附件、created_by 等，均为可选。

### 更新问题状态

```http
PATCH /api/issues/{issue}/status
```

**Body 参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| status | string | 是 | `in_progress` / `resolved` / `closed` |

状态流转规则：

- `open` → `in_progress`
- `in_progress` → `resolved`
- `resolved` → `closed`

### 删除问题

```http
DELETE /api/issues/{issue}
```

### 下载问题附件

```http
GET /api/issues/{issue}/attachment
```

---

## 合同

### 列表合同

```http
GET /api/contracts
```

**Query 参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| project_id | integer | 否 | 按项目 ID 筛选 |
| project_slug | string | 否 | 按项目 slug 筛选 |

### 获取单个合同

```http
GET /api/contracts/{contract}
```

### 上传合同

```http
POST /api/contracts
```

**Body 参数（`multipart/form-data`）：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| project_id | integer | 与 project_slug 二选一 | 项目 ID |
| project_slug | string | 与 project_id 二选一 | 项目 slug |
| name | string | 是 | 合同名称 |
| file | file | 是 | 合同文件，最大 50MB |
| uploaded_by | integer | 是 | 上传者 admin ID |

**响应示例：**

```json
{
  "id": 1,
  "project_id": 1,
  "project_name": "官网改版",
  "name": "开发合同.pdf",
  "file_url": "https://your-domain.com/storage/contracts/xxx.pdf",
  "uploaded_by": { "id": 1, "name": "管理员" },
  "created_at": "2026-08-03T05:42:00.000000Z",
  "updated_at": "2026-08-03T05:42:00.000000Z"
}
```

### 更新合同

```http
PUT /api/contracts/{contract}
```

**Body 参数：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 否 | 合同名称 |
| file | file | 否 | 重新上传合同文件 |
| uploaded_by | integer | 否 | 上传者 admin ID |

### 删除合同

```http
DELETE /api/contracts/{contract}
```

### 下载合同文件

```http
GET /api/contracts/{contract}/download
```

返回文件下载响应。

---

## 环境变量

在 `.env` 中配置 API Token：

```env
DEV_LOG_API_TOKEN=your-existing-dev-log-token
API_TOKEN=your-new-api-token
```

建议两者设置为相同值，便于统一管理；或只设置 `API_TOKEN`，原有开发日志接口也会兼容 `DEV_LOG_API_TOKEN`。

---

## 错误码

| HTTP 状态码 | 说明 |
|------------|------|
| 200 | 成功 |
| 201 | 创建成功 |
| 401 | 未认证或 Token 错误 |
| 404 | 资源不存在 |
| 422 | 参数校验失败或非法状态流转 |
| 500 | 服务器错误，如 API Token 未配置 |
