# 代码审查报告 — 2026-08-02

> 审查范围：未提交的全部改动(52 个已跟踪文件 + 25 个新文件,涵盖第二/三期功能:评论、付款、邀请、通知、任务状态历史、工时报表、看板拖拽、问题附件)。
> 测试现状:`php artisan test` 全绿(69 tests / 170 assertions)。

## 高 —— 会直接报错的

### 1. 邀请链接对未登录用户必 500

`app/Http/Controllers/InvitationController.php:38` 使用 `route('register')` 重定向,但本次改动已删除命名注册路由(`routes/web.php:62` 的 `/register` 闭包路由未命名),全项目不存在名为 `register` 的路由。未登录客户点击邀请链接会抛 `RouteNotFoundException`。邀请流程无任何测试覆盖,因此测试全绿也无法暴露。

**修复方向**:重定向到登录入口(如 `redirect('/')` 并提示先登录),或恢复带名字的注册路由,并补 Feature 测试。

### 2. Issue 附件下载必 500,且 catch 的异常类错误

`app/Http/Controllers/IssueController.php:13,25-27`:

- `Storage::disk('local')->download()` 返回 `BinaryFileResponse`,而方法声明返回类型为 `StreamedResponse`,每次下载都会抛 `TypeError`;
- catch 的 `Illuminate\Contracts\Filesystem\FileNotFoundException` 是 `get()` 抛的类;文件缺失时 `BinaryFileResponse` 构造实际抛 `Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException`,catch 永远不会命中。

该端点无任何测试。

**修复方向**:先 `Storage::disk('local')->exists($path)` 判断并 `abort(404)`,修正返回类型声明,补 Feature 测试。

### 3. `RegisteredUserController` 整段是死代码

注册路由已删除(`RegistrationTest` 明确断言不能注册),`store()` 及其中 `pending_invitation_token` 的邀请处理逻辑永远不会执行。

**修复方向**:删除死代码;若邀请注册是设计目标,则恢复注册路由并与问题 1 一并修复。

## 中 —— 建议修改

### 4. 测试环境全局摘除 CSRF 中间件

`bootstrap/app.php:22-27` 在 `runningUnitTests()` 时移除 `PreventRequestForgery`,`tests/TestCase.php:11-26` 又重复做了类似处理。今后表单缺 CSRF token 的问题测试全部无法暴露。

**修复方向**:恢复中间件,只在需要的用例里局部 `$this->withoutMiddleware(...)`;`phpunit.xml` 已加 `force="true"`,`TestCase::setUp` 里的 `putenv`/`$_ENV`/`$_SERVER` 三重写入可删。

### 5. 邀请不校验邮箱,链接即凭据

`project_invitations` 表有 `email` 字段,但 `InvitationController::accept` 与 `AuthenticatedSessionController.php:39-46` 均未校验当前登录用户邮箱是否等于邀请邮箱,任何人拿到链接即可加入项目。

**修复方向**:accept 时校验 `$user->email === $invitation->email`,或在 `docs/PLAN.md` 明确记录"链接即凭据"是有意设计。

### 6. 金额计算用了 float

`app/Observers/PaymentObserver.php:23` 用 `(float)` 差值经 `increment()` 写回 decimal 字段,违反 PLAN.md"金额一律 decimal、禁止 float"的约定;`tests/Feature/PaymentTest.php:24,36` 也用 float 断言金额。

**修复方向**:用 `bcsub(..., 2)` 或整数分计算;测试改用字符串比较(如 `assertEquals('250.50', ...)`)。

### 7. 授权只认 `web` guard,admin 分支成死代码

- `IssueController.php:16` 附件下载用 `auth('web')` 判定,admin guard 登录的开发者访问会 403,且 Filament 表格没有附件下载入口;
- `tasks.comments.store` 路由挂在 `auth:web` 中间件组内,`CommentController.php:17-22` 的 admin 评论分支不可达(admin 实际走 Filament CommentsRelationManager);`CommentTest.php:16` 测试名声称覆盖 admin 但实际未测。

**修复方向**:附件下载授权同时接受 admin guard;删除 CommentController 不可达分支并修正测试名。

### 8. 项目详情页 N+1 查询

`ProjectController.php:21` 只预加载 `tasks.creator`,而 `ClientData::task()` 现在为每个 task 序列化 comments(含 `comments.author`),每个任务多 2 次查询。

**修复方向**:`load()` 加上 `tasks.comments.author`。

### 9. `MonthlyWorkHourReport` widget 行 key 全为 null

`app/Filament/Widgets/MonthlyWorkHourReport.php:23-28` 分组查询只 `select('project_id')` 没有主键,Filament 表格 `wire:key` 全为 null,会导致渲染异常;`project.name` 逐行懒加载产生 N+1;`hours_spent` 为 null 时显示 `0.0 h` 有误导性。

**修复方向**:加 `->selectRaw('MIN(id) as id')`、`->with('project')`,null 用 `->placeholder('—')`。

### 10. `payment.remark` 对客户可见

`ClientData.php:140` 把开发者在 Filament 填写的收款备注原样传给前端展示。在 `users.remark`/`projects.remark` 均为内部私有字段的语境下,开发者很可能写入内部备注而不知客户可见。

**修复方向**:确认设计意图;若有意外露,在 Filament 表单 label 注明"客户可见"。

### 11. 新增功能测试覆盖缺口

- 邀请全流程(有效/过期/无效 token、已登录/未登录);
- issue 附件下载(成员 200 / 非成员 403 / 无附件 404,对照 `ContractDownloadTest`);
- 付款记录创建与 `PaymentObserver` 同步;
- `TaskStatusHistory`(`transitionTo` 的历史记录,含 operator 可空分支);
- pivot `can_view_price` 的 attach/toggle 写路径。

## 低 —— 顺手清理

- `resources/js/types/index.d.ts:62` 及 `Show.tsx` 中 comments/issues/payments 用了 `any[]`,建议补 `CommentItem`、`PaymentItem` 等 interface;
- `Show.tsx` 手写的 `matchStatusLabel()` 与后端 `status_label` 字段重复,存在漂移风险;
- `routes/web.php:61-62` 闭包路由(含 `/`)使 `route:cache` 不可用,建议改 invokable 控制器;
- 货币符号不一致:`PaymentsRelationManager.php:31` 用全角 `￥`,`ProjectForm.php:49` 用半角 `¥`;
- `CommentsRelationManager.php:34` 用整条评论(最长 10000 字符)作 `recordTitleAttribute`;`PaymentsRelationManager.php:46` 的 `remark` 可空导致删除确认标题空白;
- issue 附件 `FileUpload` 未设 `maxSize`/`acceptedFileTypes`,删除 Issue 时磁盘附件不清理(孤儿文件);
- 无效/过期邀请 token 永久残留 session(`AuthenticatedSessionController.php:39`),建议取出后立即 `forget`;
- `ClientData::comment`(`ClientData.php:64-65`)未防 `$comment->author` 为 null(作者账号被删时 fatal);
- 客户自己操作也会收到自己引发的状态变更通知(`TaskController.php:93-95`),建议操作者等于 creator 时不通知;
- 守卫用法不一致:`CreateIssue/CreateContract/CreateProject` 用 `auth()->id()`,新 RelationManager 用 `auth('admin')->id()`,建议统一显式 `auth('admin')`;
- `routes/web.php:15-22` 仍向 Welcome 传 `canLogin/canRegister/laravelVersion/phpVersion`(新版 Welcome.tsx 已不使用);`routes/auth.php:10` 仍 `use RegisteredUserController` 的孤儿引用。

## 确认无问题的部分

- `users.remark` 防泄露不变量保持完好:`ClientData::member()` 只暴露 id/name,`User` 的 `#[Hidden]` 未变,`RemarkLeakTest` 存在且能兜住新增字段;`projects.remark` 也不在任何前端序列化中;
- `payments` prop 已用 `canViewPriceFor($user)` 门控(`ProjectController.php:46`);
- 合同下载仍走原有授权路由未改动;issue 附件走私有 `local` 盘 + 成员校验 + `scopeBindings()`;
- 任务状态机由 `TaskPolicy::updateStatus` + `transitionTo` 双重把关,非法流转被拒绝(`TaskStatusFlowTest` 覆盖);
- payments 迁移用 `DB::table()->insert()` 导入历史已付金额,不触发 Observer,不会重复累加 `paid_amount`;
- Filament 5 `ToggleColumn` 对 BelongsToMany pivot 列(`can_view_price`)的写入处理正确;
- `phpunit.xml` 全部加 `force="true"` 是正确修复;
- `Issue::booted` 和 `PaymentObserver::updated` 里的 `isDirty()` 在 `updated` 事件中有效,不是 bug。
