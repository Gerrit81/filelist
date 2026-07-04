# 更新日志 (CHANGELOG)

## v2.9.1 (2026-07-05)

### 修复
- 🐛 修复跨平台路径问题：Windows 绝对路径（如 `D:\phpStudy_Pro\WWW\filelist\thumbs`）部署到 Linux 后会被当成目录名创建，现在 `loadConfig()` / `saveConfig()` 会自动归一化为程序根目录下的相对路径
- 🐛 修复后台设置数据目录时，Windows 绝对路径被直接写入 `config.json` 的问题
- 🐛 修复长文件名导致表格右侧列（大小、修改日期、操作）被挤出视口的问题，添加固定列宽和省略号显示

---

## v2.9.0 (2026-07-04)

### 新增
- 🔐 **内外网双模式安全框架**：新增 `security.php` 安全中间件模块，通过 `security_mode` 配置一键切换内网/外网安全策略
- 🔐 **登录安全增强**（外网模式）：速率限制（5次/分）、连续失败锁定（5次→15分钟）、攻击者延迟响应
- 🌐 **IP 黑白名单**：支持按 IP 放行或拦截，白名单优先，管理后台可视化配置
- 📝 **审计日志系统**：记录登录/登出/上传/下载/IP规则变更，安全审计页面可查看和清理
- 📊 **安全审计管理页面**：`admin/security.php` — 登录记录、审计日志、IP规则、速率限制统一面板
- 🔒 **安全响应头**（.htaccess）：CSP + X-Frame-Options + X-Content-Type-Options + Referrer-Policy + XSS-Protection
- 📥 **下载速率限制**：外网模式可配每分钟下载次数上限，防止恶意刷流量
- 📤 **上传安全增强**：外网模式禁止危险扩展名（`.php`, `.exe` 等）、双扩展名伪装检测、可配大小上限

### 变更
- ⚙️ `config.php` 新增 8 个安全配置项：`security_mode`, `download_rate_limit`, `login_max_failures`, `login_lock_minutes`, `login_rate_per_minute`, `upload_max_size_mb`, `internet_allow_anonymous_view`, `internet_force_https`
- ⚙️ 管理后台「系统设置」新增「安全模式与防护」设置区块，支持可视化配置所有安全参数
- 🧭 管理后台侧边栏新增「安全审计」入口

### 修复
- 🔧 **SQL 注入修复**：`deleteRole()` 和 `deleteUser()` 改用参数化查询，消除 SQL 拼接注入风险

---

## v2.8.2 (2026-07-04)

### 修复
- 🐛 修复排序规则：符号 → 英文 → 中文拼音，三段分层排序，中文不再跑到英文前面

---

## v2.8.1 (2026-07-04)

### 修复
- 🐛 修复文件列表中文排序规则：由 Unicode 码点排序改为中文拼音排序（`zh-CN`），中英文混合目录按字母/拼音升序排列

---

## v2.8.0 (2026-07-04)

### 变更
- 🗑️ **移除 Font Awesome**：删除 `assets/fontawesome-free-6.4.0-web/`（2020 个文件，~5MB），图标方案从 4 种精简为 3 种
- 🔄 已有 SVG 内联方案全面替代 Font Awesome（7 套风格，零外部依赖）
- 🔄 已配置为 Font Awesome 的用户自动迁移到 SVG 方案，无需手动修改

---

## v2.7.0 (2026-07-04)

### 新增
- 🔍 **递归搜索**：搜索功能现在遍历所有子目录，不再仅限当前目录
- 🔍 搜索结果中自动显示子目录路径前缀，方便定位文件位置
- 🔍 后端 `recursiveSearch()` 函数，支持不区分大小写的文件名匹配

---

## v2.6.1 (2026-07-03)

### 修复
- 🐛 修复前台个性化 SVG 图标风格切换无效，选择风格时自动切入 SVG 方案
- 🐛 修复 `initAppDB()` 幂等性：建表改为 `IF NOT EXISTS`，插入默认数据前先检测避免重复
- 🐛 修复 Docker 入口脚本在 Windows 上误将 `config.json`/`app.db` 建成目录的问题

---

## v2.6.0 (2026-07-03)

### 新增
- **Docker 容器化支持**：新增 `Dockerfile` + `docker-compose.yml` + `.dockerignore`，可一键部署到群晖 NAS 等 Linux 环境
- 基于 `php:8.2-apache` 官方镜像，内置 SQLite3 + GD + mbstring，开箱即用
- 入口脚本 `docker-entrypoint.sh` 自动处理目录权限，容器启动即就绪
- 支持离线部署：`docker save` 导出镜像包，拷贝到内网群晖 Container Manager 导入即可

---

## v2.5.1 (2026-07-03)

### 修复
- **修复部分中文文件名显示错误**：Windows Server GBK 环境下，`basename()` 在混合编码路径中可能将 GBK 编码第二字节 `0x5C` 误判为路径分隔符 `\\`，导致文件名显示为 `目录名_文件名`
- `safeBasename()` 改用 `explode('/') + end()` 替代 `basename()`，彻底规避混合编码解析问题
- `getFileInfo()` 新增 `$displayName` 参数，`scanDirectory()` 传入已转 UTF-8 的文件名，避免从混合编码路径中重新提取
- `CACHE_VERSION` 从 `4` 递增到 `5`，使旧缓存自动失效
- `admin/files.php` 删除消息改用 `safeBasename()` 替代 `basename()`

---

## v2.5.0 (2026-07-02)

### 新增
- **SVG 图标按扩展名细分**：7 套风格全部新增 4 种文件类型专属图标：
  - 🖥️ **可执行文件**（`exe`）— `.exe` `.dll` `.msi` `.apk` `.appimage` `.com` `.scr` `.sys` 等（紫色系）
  - 📦 **压缩包**（`archive`）— `.zip` `.rar` `.7z` `.tar` `.gz` `.bz2` `.xz` `.zst` `.tgz` 等（琥珀色系）
  - 🔤 **字体文件**（`font`）— `.ttf` `.otf` `.woff` `.woff2` `.eot`（粉色系）
  - 💿 **磁盘映像**（`disk`）— `.iso` `.dmg` `.vhd` `.vhdx` `.vmdk` `.qcow2` `.vdi` `.hdd`（灰蓝色系）
- 图标判别逻辑：`exe → archive → disk → font → code → txt → file`，按优先级依次匹配

---

## v2.4.0 (2026-07-02)

### 新增
- **前台个性化 SVG 图标风格选择**：访问者可在「个性化设置」面板中自主选择 SVG 图标风格（Material / 卡通 / 科幻 / 极简线条 / 像素 / 渐变 / 手绘），设置保存到浏览器 localStorage，覆盖后台全局默认

### 优化
- SVG 风格优先级：前台个人偏好 > 后台全局设置 > material 兜底

---

## v2.3.0 (2026-07-02)

### 新增
- **SVG 图标多风格支持**：选中 SVG 方案后，后台可选择 7 种视觉风格：
  - **Material**（默认）— 现代简洁，扁平色彩
  - **卡通风格** — 圆润饱满，粗描边，活泼可爱
  - **科幻风格** — 霓虹暗底，锐利棱角，未来科技感
  - **极简线条** — 细线勾勒，低饱和度，优雅克制
  - **像素风格** — 方块拼接，复古像素，怀旧游戏风
  - **渐变风格** — 平滑过渡，现代渐变，应用质感
  - **手绘风格** — 不规则线条，草图质感，温暖自然

### 优化
- **Font Awesome 本地化**：图标库文件置于 `assets/fontawesome-free-6.4.0-web/`，纯内网环境无需 CDN 即可使用
- 后台 Font Awesome 描述从「CDN 加载」更新为「本地托管，纯内网可用」

---

## v2.2.0 (2026-07-02)

### 新增
- **多套文件图标方案**：后台「系统设置」新增「文件图标方案」选项，支持四种方案自由切换：
  - **Emoji 表情**（默认）— 系统原生 emoji，色彩丰富，但不同 OS 显示效果不同
  - **SVG 内联** — 纯矢量图标，零外部依赖，所有平台完全一致
  - **Font Awesome** — 专业图标库（CDN 加载），图标种类丰富，彩色区分文件类型
  - **CSS 纯样式** — 纯 CSS 绘制的几何图形图标，零依赖，极致轻量

### 优化
- 图标方案切换即时生效，无需刷新页面
- Font Awesome 仅在选中该方案时按需加载 CDN，避免不必要的网络请求
- SVG 图标按文件类型着色，视觉效果清晰直观

---

## v2.1.0 (2026-07-02)

### 新增
- **个性化面板 - 布局宽度**：新增"标准(10%留白)"和"窄版(15%留白)"两种布局宽度选项
- **个性化面板 - 固定页眉页脚**：新增"固定页眉页脚"滚动模式，页眉页脚固定不动，仅文件列表区域滚动
- 个性化面板重新定位逻辑优化：窗口缩放时自动重新计算弹窗位置

### 优化
- **文件拆分**：将 `template.php` 中的 CSS 和 JS 提取为独立文件 `assets/style.css` 和 `assets/script.js`
  - 浏览器可缓存静态资源，后续页面加载更快
  - `template.php` 从 1865 行精简为 130 行，纯 PHP+HTML 更易维护
  - 版本号 `v` 参数自动更新，刷新缓存

### 关于 `functions.php` (40KB/1098行) 拆分评估
- **结论：暂不拆分**。PHP 加载 1098 行文件只需亚毫秒级，性能影响可忽略。
- 全部 admin 模块和 index.php 都 `require_once 'functions.php'`，拆分需同步修改多处 include，风险大于收益。
- 如果后续继续膨胀到 2000+ 行，可考虑按功能域拆分为：
  - `functions/config.php` — 配置加载
  - `functions/db.php` — 数据库与下载跟踪
  - `functions/files.php` — 文件扫描、缓存、缩略图
  - `functions/users.php` — 用户/角色 CRUD
  - `functions.php` — 改为 loader

---

## v2.0.8 (2026-06) — 之前版本
- 个性化面板（主题色彩 + 字体样式）
- 文件浏览、预览、下载功能
- 管理后台（用户、角色、权限、上传、下载记录）
- Office 文件预览（LibreOffice 转换）
- 目录缓存机制
- SQLite 数据库（用户/角色/下载记录）
