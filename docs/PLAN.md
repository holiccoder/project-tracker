# 项目追踪反馈系统 — 实施规划

> 个人开发者管理客户的项目追踪反馈系统。开发者创建项目，客户就项目委派任务，开发者记录开发记录供客户查看进度。

## 一、技术栈与整体架构

基于当前仓库现状搭建，不引入新框架：

- **后端**:Laravel 13(已有)
- **开发者端(后台)**:Filament 5(已安装，需创建 Panel),基于已有 `Admin` 模型，访问路径 `/admin`
- **客户端(前台)**:Breeze + Inertia React + TypeScript(已有),`web` guard + `User` 模型
- **数据库**:开发环境 SQLite,生产可平滑换 MySQL/PostgreSQL

双端分离原则：开发者的一切管理操作走 Filament;客户的一切操作走 Inertia 前台。两端不混用界面，只共享模型与业务逻辑(Policy、枚举、Service)。

## 二、角色与权限总览

| 能力 | 开发者 (Admin) | 客户 (User) |
|---|---|---|
| 创建/编辑项目 | ✅(唯一入口) | ❌ |
| 关联客户到项目 | ✅ | ❌ |
| 给客户设备注名 | ✅(仅后台可见) | ❌(不可见) |
| 委派任务 | ✅ | ✅(项目成员即可) |
| 任务状态流转 | 确认/拒绝/进行中/完成 | 验收/要求返工 |
| 写开发记录(可选工时) | ✅ | 只读 |
| 提问题(issues) | ✅(唯一入口) | 只读 |
| 上传/下载合同 | ✅ | 仅下载/查看 |
| 编辑项目金额、已付金额 | ✅ | 只读 |
| 评论 | ✅ | ✅(第二期) |

## 三、数据模型

```
users (客户, remark 仅后台可见)
  └── project_user (多对多, role)
         │
admins ── projects (amount / paid_amount)
         │
   ┌─────┼──────────┬───────────┐
 tasks  dev_logs  contracts   issues
   │
comments (第二期)
```

### 3.1 `users`(在现有表上追加)

| 字段 | 类型 | 说明 |
|---|---|---|
| remark | string, nullable | 开发者给客户起的备注名,**只在 Filament 展示**,前端任何接口/共享数据不得返回 |

### 3.2 `projects`

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint | |
| name | string | 项目名 |
| slug | string, unique | URL 友好标识 |
| description | text, nullable | |
| status | string (enum) | `active` / `delivered` / `paused`,默认 `active` |
| amount | decimal(10,2), nullable | 项目总金额,双方可见 |
| paid_amount | decimal(10,2), default 0 | 已付金额;未付 = amount − paid_amount,计算得出不存储 |
| deadline | date, nullable | |
| repo_url | string, nullable | 仓库地址(可选) |
| created_by | foreignId → admins | 创建者 |
| timestamps | | |

未付金额做成 Model accessor:`getUnpaidAmountAttribute()`。

### 3.3 `project_user`(中间表)

| 字段 | 类型 | 说明 |
|---|---|---|
| project_id | foreignId | 联合唯一索引 (project_id, user_id) |
| user_id | foreignId | |
| role | string | `owner`(主联系人)/ `member`,默认 `member` |
| timestamps | | |

一个项目可有多个客户,**每个成员都有委派任务的权限**。

### 3.4 `tasks`

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint | |
| project_id | foreignId | |
| created_by | foreignId → users | 委派者 |
| title | string | |
| description | text, nullable | |
| priority | string (enum) | `low` / `medium` / `high`,默认 `medium` |
| status | string (enum) | 见状态机,默认 `pending` |
| due_date | date, nullable | 期望完成时间 |
| reject_reason | text, nullable | 开发者拒绝时填写 |
| completed_at | timestamp, nullable | |
| accepted_at | timestamp, nullable | |
| timestamps | | |

### 3.5 `dev_logs`

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint | |
| project_id | foreignId | |
| task_id | foreignId, nullable | 可关联到具体任务 |
| date | date | 记录日期 |
| content | text | 做了什么 |
| hours_spent | decimal(5,1), nullable | **工时可选**,不填则前端不显示 |
| timestamps | | |

### 3.6 `contracts`

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint | |
| project_id | foreignId | |
| name | string | 文件名/合同名 |
| file_path | string | 私有磁盘 (`local`) 路径 |
| uploaded_by | foreignId → admins | |
| timestamps | | |

一个项目可传多份(合同、补充协议等)。**文件存私有磁盘,不经公开 URL**,下载走带 Policy 校验的路由。

### 3.7 `issues`

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint | |
| project_id | foreignId | |
| title | string | |
| description | text, nullable | |
| severity | string (enum) | `normal` / `serious` / `blocking`,默认 `normal` |
| status | string (enum) | `open` / `in_progress` / `resolved` / `closed` |
| created_by | foreignId → admins | 只有开发者能提 |
| resolved_at | timestamp, nullable | |
| timestamps | | |

### 3.8 `comments`(第二期)

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint | |
| commentable_id / commentable_type | morphs | 挂在 task(后续可扩展到 issue) |
| body | text | |
| author 归属 | user_id + user_type 或分开两列 | 客户与开发者两种作者,需支持 polymorphic 作者 |
| timestamps | | |

## 四、状态机

### 4.1 任务状态流转

```
pending(客户提交,待确认)
  ├─ confirmed(开发者接受) → in_progress → done(开发者标记完成)
  │                                              ├─ accepted(客户验收,终态)
  │                                              └─ changes_requested(客户要求返工) → in_progress
  └─ rejected(开发者拒绝,必填 reject_reason,终态)
```

每次状态变更记录操作者与时间(第二期可加 `task_status_histories` 表做审计)。

### 4.2 问题状态流转(开发者单方管理)

```
open → in_progress → resolved → closed
```

## 五、权限设计(Policy)

- `ProjectPolicy`:客户侧 `view` 需是 `project_user` 成员;`create/update/delete` 仅 admin(客户侧路由不开放创建入口)。
- `TaskPolicy`:`view` 随项目成员关系;`create` 限项目成员(客户)与开发者;`updateStatus` 按状态机分动作校验(确认/拒绝/完成=开发者动作,验收/返工=项目成员动作)。
- `DevLogPolicy`:客户只读,写操作仅开发者。
- `IssuePolicy`:客户只读,写操作仅开发者。
- `ContractPolicy`:项目成员与开发者可下载;上传/删除仅开发者。
- `users.remark` 防泄露:Inertia 共享数据(`HandleInertiaRequests`)、所有前端序列化点显式排除 `remark`;Filament 端正常展示。

## 六、客户端(Inertia React)页面规划

路由统一挂在 `auth:web` + `verified` 下:

| 路由 | 页面 | 说明 |
|---|---|---|
| `GET /dashboard` | Dashboard | 我的项目卡片列表:进度、金额摘要、最近动态;无项目时显示"等待开发者为你分配项目"引导 |
| `GET /projects/{project}` | 项目详情 | 标签页结构,见下 |
| `POST /projects/{project}/tasks` | — | 委派任务 |
| `GET /projects/{project}/tasks/{task}` | 任务详情 | 状态按钮(验收/返工)、评论区(第二期) |
| `PATCH /tasks/{task}/status` | — | 状态流转(服务端校验状态机) |
| `GET /projects/{project}/contracts/{contract}/download` | — | 授权下载合同 |

项目详情标签页:

1. **任务** — 按状态分组的列表,新建任务按钮(标题、描述、优先级、期望完成时间)
2. **开发记录** — 按日期倒序时间线,有工时才显示工时
3. **问题** — 只读列表,严重程度 + 状态徽章,无提交入口
4. **合同** — 文件列表 + 下载按钮
5. **项目信息** — 描述、金额(总额/已付/未付)、截止日期等

## 七、开发者端(Filament)规划

创建 Panel:`php artisan filament:install --panels`,`AdminPanelProvider`,`/admin` 路径,auth 走 `admins` guard(需确认 `config/auth.php` 已配置 admin guard,若无则补)。

Resource 六个:

| Resource | 要点 |
|---|---|
| Project | 表单含金额/已付(未付做只读计算字段)、状态、deadline;RelationManager:客户成员、任务、开发记录、合同、问题 |
| User(客户) | 列表/表单显示 `remark`;支持代建客户(生成初始密码或触发密码重置邮件);RelationManager:参与的项目 |
| Task | 按项目/状态筛选,确认/拒绝/完成等 Action 按钮 |
| DevLog | 表单含可选工时输入、关联任务下拉 |
| Contract | FileUpload 存私有磁盘,按项目归档 |
| Issue | 状态/严重程度徽章,状态流转 Action |

Dashboard Widgets:待确认任务数、进行中问题数、本周工时合计、即将到期任务列表。

## 八、通知(第二期)

| 触发 | 接收者 |
|---|---|
| 客户委派新任务 | 开发者 |
| 任务状态变更(确认/拒绝/完成/返工) | 任务委派人 |
| 新开发记录 | 项目客户成员 |
| 问题状态变更 | 项目客户成员 |
| 新评论 | 对方 |

渠道:站内通知(database)+ 邮件,`ShouldQueue` 异步;客户与开发者两种 notifiable,注意 `Admin`/`User` 都要走通。

## 九、注册与邀请

1. **自助注册**:Breeze 注册页开放;注册后无项目,Dashboard 显示等待分配引导
2. **后台代建**:Filament User Resource 创建,发密码重置邮件让客户设密码
3. **邀请链接**(第二期):`project_invitations` 表(`project_id`、`email`、`token`、`expires_at`),链接打开后:已登录直接加入项目;未注册则注册后自动加入

## 十、实施分期

### 第一期(MVP)

1. 迁移与模型:projects、project_user、tasks、dev_logs、contracts、issues;users 追加 remark
2. 枚举与状态机:TaskStatus、IssueStatus 等 PHP enum;状态流转校验逻辑(可放 Model 方法或 Action 类)
3. Policy 五个 + remark 防泄露检查
4. Filament Panel + 六个 Resource + 基础 Widgets
5. 客户认证调整:注册后空项目引导页
6. 客户端页面:Dashboard、项目详情五标签页、委派任务、任务详情与验收流转、合同下载路由
7. 测试:Policy 越权测试、任务状态机测试(非法流转拒绝)、合同下载授权测试、remark 不泄露测试

### 第二期

1. comments(polymorphic)+ 任务详情评论区
2. 站内 + 邮件通知(全部触发点)
3. 邀请链接注册
4. 任务状态变更历史表

### 第三期

1. `payments` 表(付款明细:金额、日期、备注),`paid_amount` 改为由明细汇总
2. 工时报表(按项目/月汇总,基于 dev_logs.hours_spent)
3. 任务看板拖拽(Inertia 端)
4. 问题支持附件截图

## 十一、关键风险与约定

- **remark 泄露**:任何给前端的数据出口(Inertia props、API)都必须过白名单,禁止直接 `toArray()` 整个 User
- **合同文件安全**:私有磁盘 + 授权路由,禁止 symlink 到 public
- **越权访问**:所有 `{project}` 路由模型绑定后必须过 Policy,测试覆盖"改 ID 访问他人项目"场景
- **金额精度**:一律 decimal,禁止 float 运算
