# 📁 文件浏览器 (File Browser)

轻量级 PHP 文件目录浏览系统，支持在线预览、下载、管理文件，内置角色权限管理和后台控制面板。

基于 **PHP + SQLite** 构建，无需 MySQL，开箱即用。

## ✨ 功能特性

### 前端浏览
- 📂 文件目录树形浏览，支持多级目录
- 🔍 实时搜索过滤文件名
- 🖼️ 图片/视频/音频/PDF 在线预览
- 📝 文本文件在线预览（自动 GBK→UTF-8 转码）
- 📊 Office 文档预览（LibreOffice 转换 / 自定义 API）
- 📋 一键复制文件链接、下载文件
- 🎨 **5 套主题皮肤**：紫蓝渐变 / 暗夜模式 / 翡翠绿 / 日落橙暖 / 深海蓝
- 🔤 **5 种中文字体**：系统默认 / 微软雅黑 / 宋体 / 楷体 / 等线
- 🖱️ 图片悬停预览缩略图
- 🔽 视频支持 Range 请求（进度条拖动）

### 后台管理
- 📊 **控制面板**：文件总数/总大小统计、下载排行榜、访问 IP 排行
- 📁 **文件管理**：浏览、重命名、删除、下载（支持目录跳转）
- 📤 **文件上传**：支持拖拽上传、大文件分片、选择目标文件夹
- 📥 **下载历史**：记录每次下载的 IP、UA、时间，支持清空
- 🙈 **隐藏管理**：通配符规则隐藏指定文件/文件夹
- 👥 **用户管理**：多用户、独立角色、文件夹访问权限
- 🔐 **角色管理**：细粒度权限控制（10 项权限独立开关）
- ⚙️ **系统设置**：网站名称、导航链接、Office 预览模式等
- 🗑️ **缓存管理**：手动刷新统计缓存、清空目录缓存

### 安全特性
- ✅ 路径遍历防护（`isSafePath` + `realpath` 校验）
- ✅ CSRF 令牌保护
- ✅ XSS 防护（`htmlspecialchars` 输出转义）
- ✅ Session 安全（HttpOnly + SameSite）
- ✅ 密码哈希存储（`password_hash`）
- ✅ SQLite 参数化查询防注入
- ✅ .htaccess / web.config 数据库文件保护
- ✅ `.db` / `.json` 等敏感文件阻止直接访问

## 📋 运行要求

| 项目 | 最低要求 |
|------|---------|
| PHP | ≥ 7.4（推荐 8.0+） |
| PHP 扩展 | `sqlite3`、`mbstring`、`gd`（图片缩略图） |
| Web 服务器 | Apache / Nginx / IIS |
| 可选 | LibreOffice（Office 文档预览） |

## 🚀 快速开始

### 1. 下载代码

```bash
git clone https://github.com/你的用户名/仓库名.git
# 或直接下载 ZIP 解压
```

### 2. 部署到 Web 服务器

将整个文件夹放到 Web 服务器的文档根目录，例如：

- Apache: `htdocs/filelist/`
- Nginx: `/var/www/html/filelist/`
- IIS: `wwwroot/filelist/`

### 3. 创建必要目录（首次自动创建）

程序首次运行时会自动创建以下目录：
```
data/       ← 文件存储目录（把要分享的文件放这里）
cache/      ← 目录列表缓存
thumbs/     ← 图片缩略图缓存
session/    ← Session 存储
```

### 4. 访问

```
前端浏览：  http://你的域名/       （或 http://localhost/filelist/）
后台管理：  http://你的域名/admin/
```

**默认管理员密码：`admin123`**（首次登录后务必修改）

## ⚙️ 配置说明

### 配置文件

首次运行后会自动生成 `config.json`，支持直接在后台「系统设置」中修改：

| 配置项 | 说明 | 默认值 |
|--------|------|--------|
| `site_name` | 网站名称 | 文件浏览器 |
| `site_subtitle` | 网站副标题 | 轻量级文件目录浏览系统 |
| `data_dir` | 文件存储目录（绝对路径） | 程序目录下的 `data/` |
| `max_upload_size` | 全局上传大小限制（字节，0=不限制） | 0 |
| `hidden_files` | 隐藏文件规则（支持通配符） | [] |
| `office_preview_mode` | Office 预览模式：`off` / `libreoffice` / `custom` | off |
| `libreoffice_path` | LibreOffice 路径（模式 `libreoffice` 时有效） | 空（自动检测） |
| `office_preview_api` | 自定义 Office 预览 API 地址 | 空 |
| `nav_links` | 顶部导航链接 | 首页 + 管理后台 |

### 修改数据目录

```json
"data_dir": "D:\\共享文件"
```

> ⚠ 注意：Windows 路径使用 `\\` 双反斜杠。

### Office 文档预览

支持三种模式（在后台系统设置中切换）：

1. **关闭** (`off`)：Office 文件仅可下载
2. **LibreOffice** (`libreoffice`)：服务器端安装 LibreOffice，自动转 PDF 预览
3. **自定义 API** (`custom`)：配置第三方预览 API 的地址，`{url}` 占位符会被替换为文件下载链接

## 📁 目录结构

```
filelist/
├── index.php             # 前端入口（文件浏览 / API 路由）
├── template.php          # 前端 HTML 模板
├── functions.php         # 核心函数库
├── config.php            # 默认配置（首次自动生成 config.json）
├── favicon.svg           # 网站图标
├── .htaccess             # Apache 安全规则
├── web.config            # IIS 安全规则
├── admin/                # 后台管理模块
│   ├── index.php         # 控制面板
│   ├── login.php         # 登录 / 退出
│   ├── upload.php        # 文件上传
│   ├── files.php         # 文件管理
│   ├── downloads.php     # 下载历史
│   ├── hidden.php        # 隐藏管理
│   ├── users.php         # 用户管理
│   ├── roles.php         # 角色管理
│   ├── settings.php      # 系统设置
│   ├── changelog.php     # 更新日志
│   ├── header.php        # 后台头部
│   ├── sidebar.php       # 后台侧边栏
│   ├── layout.php        # 后台布局
│   └── init.php          # 后台初始化（Session / 认证）
├── data/                 # 文件存储目录
├── cache/                # 缓存目录
├── thumbs/               # 缩略图目录
└── session/              # Session 目录
```

## 🔐 角色与权限

系统预置两个角色：

| 角色 | 权限范围 |
|------|---------|
| 管理员 | 全部权限（10 项） |
| 操作员 | 控制面板、上传、文件管理、下载历史 |

权限项说明：

| 权限标识 | 说明 |
|----------|------|
| `dashboard` | 控制面板 |
| `upload` | 文件上传 |
| `files` | 文件管理 |
| `files_rename` | 重命名文件 |
| `files_delete` | 删除文件 |
| `downloads` | 下载历史 |
| `hidden` | 隐藏管理 |
| `settings` | 系统设置 |
| `users` | 用户管理 |
| `roles` | 角色管理 |

> 💡 在后台「角色管理」中可创建自定义角色并灵活分配权限。

## 🔒 安全建议

1. **修改默认密码**：首次登录后台后立即修改管理员密码（用户管理 → 编辑管理员）
2. **保护敏感文件**：务必配置 Web 服务器阻止 `.db` / `.json` 等文件直接访问
   - **Apache**：确保 `AllowOverride All` 已启用，项目自带 `.htaccess` 自动生效
   - **Nginx**：在 `server` 块中添加以下规则（**必须手动配置，Nginx 不读取 .htaccess**）：
     ```nginx
     # 阻止访问 .db / .json / .htaccess 等敏感文件
     location ~* \.(db|sqlite|sqlite3)$ {
         deny all;
         return 403;
     }
     location ~* /(config\.json|config\.php|\.htaccess|web\.config)$ {
         deny all;
         return 403;
     }
     ```
   - 代码层面也已内置 PHP 拦截兜底（`security.php`）
3. **HTTPS**：生产环境务必使用 HTTPS
4. **PHP 配置**：建议在 `php.ini` 中设置 `expose_php = Off`
5. **文件上传限制**：在 `php.ini` 中合理配置 `upload_max_filesize` 和 `post_max_size`

## ⚠️ 已知问题

- **Windows 中文文件名编码**：部分 Windows 服务器环境下中文文件名可能存在编码显示问题（GBK ↔ UTF-8），已在代码中做了自动编码检测和转换，但不保证 100% 覆盖所有场景。如遇乱码，在后台「控制面板 → 数据缓存」中点击「清空所有缓存」后重试。

## 📝 License

MIT License

---

**版本**: v2.9.3  
**技术栈**: PHP + SQLite + 原生 JavaScript (无框架)
