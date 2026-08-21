# 项目助手（浏览器插件）

项目管理系统的配套浏览器插件，支持 Chrome 与 Firefox（Manifest V3）。用于管理各项目对应的网站登录账号，并可一键打开登录页自动填写账号密码。另内置完整的管理后台页面（账号 / 项目 / 任务 / 开发日志）。

## 目录结构

```
extensions/
├── build.mjs                 # 构建脚本（node extensions/build.mjs）
├── scripts/
│   └── generate-icons.mjs    # 图标生成脚本（仅依赖 node:zlib，构建时自动调用）
├── chrome/manifest.json      # Chrome 清单
├── firefox/manifest.json     # Firefox 清单
├── shared/                   # 双浏览器共用的全部代码与资源
│   ├── background.js         # 后台脚本：分发一次性自动填写凭据
│   ├── content-autofill.js   # 内容脚本：在登录页自动填写账号密码
│   ├── common.js             # API 客户端 / 会话 / 工具函数（popup 与 dashboard 共用）
│   ├── styles.css            # 共用样式（CSS 变量与通用组件）
│   ├── popup.html/.js/.css   # 浏览器工具栏弹窗
│   ├── dashboard.html/.js/.css # 管理后台整页
│   └── icons/                # 构建时自动生成的 PNG 图标
└── dist/                     # 构建产物（已被 .gitignore 忽略）
    ├── chrome/
    ├── edge/
    └── firefox/
```

## 构建方法

需要 Node.js（仅用标准库，无需 npm install）：

```bash
node extensions/build.mjs
```

该命令会先生成 `shared/icons/` 下的图标，然后把 `shared/*` 与对应 manifest 复制到 `extensions/dist/chrome/`、`extensions/dist/edge/` 和 `extensions/dist/firefox/`。

## 安装

### Chrome

1. 打开 `chrome://extensions`
2. 开启右上角「开发者模式」
3. 点击「加载已解压的扩展程序」
4. 选择 `extensions/dist/chrome/` 目录

### Edge

Edge 与 Chrome 同为 Chromium，安装方式相同：

1. 打开 `edge://extensions`
2. 开启左下角「开发人员模式」
3. 点击「加载解压缩的扩展」
4. 选择 `extensions/dist/edge/` 目录

### Firefox

1. 打开 `about:debugging#/runtime/this-firefox`
2. 点击「临时载入附加组件…」
3. 选择 `extensions/dist/firefox/manifest.json` 文件

注意：Firefox 临时载入的附加组件在浏览器重启后会消失，需要重新载入。

## 使用说明

### 1. 登录

点击工具栏的插件图标打开弹窗，首次使用会显示登录表单：

- **服务器地址**：已内置默认值（见 `shared/config.js` 的 `EXTENSION_CONFIG.serverUrl`），无需手填；如需临时指向其它环境（如本地 `http://127.0.0.1:8899`）可直接修改，插件会记住修改后的值。要改内置默认值，编辑 `shared/config.js` 后重新构建即可。
- **邮箱 / 密码**：使用管理后台的账号登录。

登录成功后，所有请求都会携带 `Authorization: Bearer <token>`；token 失效（401）时会自动清除本地 token 并回到登录页。

### 2. popup（弹窗）用法

- 顶部显示当前管理员姓名，右侧是「管理后台」（在新标签页打开 dashboard）和「退出」按钮。
- 搜索框输入即过滤项目（300ms 防抖）。
- 点击项目行展开该项目的账号列表，每行显示网站名称、用户名和备注。
- 点击某个账号行：在新标签页打开该账号的登录地址，并**自动填写账号密码**。弹窗会提示「已打开登录页，将自动填写」。
- 每行右侧的「复制账号」「复制密码」按钮可直接复制凭据到剪贴板。

### 3. dashboard（管理后台）用法

点击 popup 的「管理后台」按钮打开，包含四个标签页：

- **账号管理**：按项目/关键字筛选；新建、编辑、删除账号；密码默认掩码显示，可切换明文、一键复制；新建/编辑时支持随机生成密码。
- **项目管理**：项目列表、新建、编辑（不提供删除）。新建时 slug 会根据名称自动生成（小写 + 非字母数字转连字符），可手动修改；`created_by` 自动取当前登录管理员。
- **任务管理**：按项目/状态筛选；编辑任务的标题、描述、优先级、截止日期（状态只读展示）。
- **开发日志**：按项目/状态筛选的只读列表，内容超长截断并可通过悬停查看全文。

所有列表分页为「加载更多」，空数据显示「暂无数据」。

### 4. 自动填写说明

- 从 popup 点击账号时，凭据会以 5 分钟有效期临时存入 `chrome.storage.local`，打开登录页后由内容脚本消费，**消费后立即删除**（一次性），且只在登录地址的 hostname 与目标页面一致时才发放。
- 填写通过原生 setter + `input`/`change` 事件完成，兼容 React/Vue 受控组件。
- 如果页面加载较慢找不到输入框，会自动重试约 10 秒。
- 填写完成后页面右上角会显示「已自动填写账号密码」提示条，**不会自动提交表单**。

## 手动验证清单

- [ ] `node extensions/build.mjs` 成功，`dist/chrome`、`dist/edge` 与 `dist/firefox` 均包含 manifest.json、全部 JS/CSS/HTML 与 icons/
- [ ] Chrome：加载 dist/chrome，工具栏出现插件图标（蓝底白色钥匙孔）
- [ ] Edge：edge://extensions 加载 dist/edge 成功
- [ ] Firefox：about:debugging 临时载入 dist/firefox/manifest.json 成功
- [ ] 不填/填错服务器地址时登录提示「无法连接服务器」；凭据错误时提示「邮箱或密码错误」
- [ ] 登录成功后 popup 显示管理员姓名，项目列表加载正常，搜索即时过滤
- [ ] 点击项目展开账号列表；「复制账号」「复制密码」生效
- [ ] 点击账号行打开登录页并自动填写，页面右上角出现提示条，表单未被自动提交
- [ ] 同一凭据刷新页面后不会再次填写（一次性消费）；5 分钟后过期
- [ ] 「管理后台」打开 dashboard，四个标签页切换正常
- [ ] 账号管理：新建/编辑/删除、密码显示切换与复制、随机生成密码均正常
- [ ] 项目管理：新建时 slug 自动生成、created_by 正确；无删除入口
- [ ] 任务管理：编辑保存成功，状态只读；筛选生效
- [ ] 开发日志：只读展示、筛选生效、长内容截断
- [ ] 退出登录后回到登录页；token 过期后自动回到登录页
