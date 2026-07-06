<?php
require_once __DIR__ . '/init.php';
requirePermission('settings');

$pageTitle = '📋 更新日志';

// 更新日志数据库 — 每次发版在此追加记录
$changelog = array(
    array(
        'version' => '2.9.3',
        'date'    => '2026-07-06',
        'changes' => array(
            '🐛 修复后台「安全审计」页面 403 错误：blockSensitiveFiles() 误拦截 admin/security.php',
        ),
    ),
    array(
        'version' => '2.9.2',
        'date'    => '2026-07-05',
        'changes' => array(
            '🐛 修复前台文件大小列显示不全（增大列宽至 130px）',
            '🐛 修复前台文件类型列显示不全（列宽增至 175px）',
            '🐛 修复前台各列字体不一致：取消大小/时间列等宽字体，统一系统字体',
            '🐛 修复后台文件管理表格长文件名导致布局错位：table-layout:fixed + 省略号截断',
            '🔒 修复 Nginx 下 .db 文件可被直接下载的安全漏洞（PHP 拦截 + Nginx 配置说明）',
            '🔒 新增 .gitignore 防止运行期文件混入版本控制',
        ),
    ),
    array(
        'version' => '2.9.1',
        'date'    => '2026-07-05',
        'changes' => array(
            '🐛 修复跨平台路径问题：Windows 绝对路径部署到 Linux 后被当成目录名创建，loadConfig()/saveConfig() 自动归一化为程序根目录相对路径',
            '🐛 修复后台设置数据目录时 Windows 绝对路径被直接写入 config.json',
            '🐛 修复长文件名导致表格右侧列被挤出视口，添加固定列宽和省略号显示',
        ),
    ),
    array(
        'version' => '2.9.0',
        'date'    => '2026-07-04',
        'changes' => array(
            '🔐 内外网双模式安全框架：新增 security.php 安全中间件，一键切换内网/外网安全策略',
            '🔐 登录安全增强：速率限制（5次/分）、连续失败锁定（5次→15分钟）、延缓响应防暴力破解',
            '🌐 IP 黑白名单：支持按 IP 放行或拦截，白名单优先，管理后台可视化配置',
            '📝 审计日志系统：记录登录/登出/上传/下载/IP 规则变更，安全审计页面可查看和清理',
            '📊 安全审计管理页面：登录记录、审计日志、IP 规则、速率限制统一面板',
            '🔒 安全响应头：CSP + X-Frame-Options + X-Content-Type-Options + Referrer-Policy + XSS-Protection',
            '📥 下载速率限制：外网模式可配每分钟下载次数上限，防止恶意刷流量',
            '📤 上传安全增强：外网模式禁止危险扩展名、双扩展名伪装检测、可配大小上限',
            '⚙️ 系统设置新增「安全模式与防护」配置区块，可视化调整所有安全参数',
            '🔧 修复 deleteRole() / deleteUser() SQL 拼接注入风险，改用参数化查询',
        ),
    ),
    array(
        'version' => '2.8.2',
        'date'    => '2026-07-04',
        'changes' => array(
            '🐛 修复排序规则：符号 → 英文 → 中文拼音，三段分层排序，中文不再跑到英文前面',
        ),
    ),
    array(
        'version' => '2.8.1',
        'date'    => '2026-07-04',
        'changes' => array(
            '🐛 修复文件列表中文排序：Unicode 码点排序改为中文拼音排序（zh-CN locale）',
        ),
    ),
    array(
        'version' => '2.8.0',
        'date'    => '2026-07-04',
        'changes' => array(
            '🗑️ 移除 Font Awesome 图标库（assets/fontawesome-free-6.4.0-web/），减少约 5MB 项目体积',
            '🔥 图标方案从 4 种精简为 3 种（Emoji / SVG / CSS），已有 SVG 方案 7 套风格全面替代',
            '🔄 向后兼容：已选 Font Awesome 的用户自动迁移为 SVG 方案',
        ),
    ),
    array(
        'version' => '2.7.0',
        'date'    => '2026-07-04',
        'changes' => array(
            '🔍 新增递归搜索：遍历所有子目录匹配文件名（不区分大小写）',
            '🔍 搜索结果自动显示子目录路径前缀，定位文件更直观',
        ),
    ),
    array(
        'version' => '2.6.1',
        'date'    => '2026-07-03',
        'changes' => array(
            '🐛 修复前台个性化 SVG 图标风格切换无效，选择风格时自动切入 SVG 方案',
            '🐛 修复 initAppDB() 幂等性，避免 Docker 容器首次启动时重复插入数据报错',
            '🐛 修复 Docker 入口脚本在 Windows 上误将 config.json/app.db 建成目录的问题',
        ),
    ),
    array(
        'version' => '2.6.0',
        'date'    => '2026-07-03',
        'changes' => array(
            '🐳 新增 Docker 容器化支持：Dockerfile + docker-compose.yml，一键部署到群晖 NAS 等 Linux 环境',
            '📦 基于 php:8.2-apache 官方镜像，内置 SQLite3 + GD + mbstring，开箱即用',
            '🔧 入口脚本自动处理目录权限，支持离线导出镜像包部署到内网环境',
        ),
    ),
    array(
        'version' => '2.5.1',
        'date'    => '2026-07-03',
        'changes' => array(
            '🐛 修复部分中文文件名显示错误：safeBasename() 改用字符串切割替代 basename()，规避 GBK 编码第二字节 0x5C 被误判为路径分隔符',
            '🐛 getFileInfo() 新增 displayName 参数，scanDirectory() 传入 UTF-8 文件名，避免从混合编码路径重新提取',
            '🔄 CACHE_VERSION 递增至 5，旧缓存自动失效',
        ),
    ),
    array(
        'version' => '2.5.0',
        'date'    => '2026-07-02',
        'changes' => array(
            '🖥️ SVG 图标按扩展名细分：7 套风格新增可执行文件、压缩包、字体、磁盘映像 4 种专属图标',
            '📦 可执行文件 (exe/dll/msi/apk 等) 紫色系 | 压缩包 (zip/rar/7z/tar 等) 琥珀色系',
            '🔤 字体文件 (ttf/otf/woff 等) 粉色系 | 磁盘映像 (iso/dmg/vhd 等) 灰蓝色系',
        ),
    ),
    array(
        'version' => '2.4.0',
        'date'    => '2026-07-02',
        'changes' => array(
            '🎨 前台个性化 SVG 图标风格选择：访问者可在个性化面板中自主切换 7 种 SVG 风格，偏好保存至 localStorage，覆盖后台全局默认',
        ),
    ),
    array(
        'version' => '2.3.0',
        'date'    => '2026-07-02',
        'changes' => array(
            '🎨 SVG 图标多风格支持：Material / 卡通 / 科幻 / 极简线条 / 像素 / 渐变 / 手绘，7 种视觉风格可在后台一键切换',
            '🔧 Font Awesome 本地化：图标库置于 assets/fontawesome-free-6.4.0-web/，纯内网无需 CDN',
            '🎯 后台 SVG 子风格菜单：选中 SVG 方案后自动展开风格选择器',
        ),
    ),
    array(
        'version' => '2.2.0',
        'date'    => '2026-07-02',
        'changes' => array(
            '🎨 新增多套文件图标方案：后台「系统设置」新增「文件图标方案」选项，支持 Emoji / SVG / Font Awesome / CSS 四种方案自由切换',
            '✨ SVG 内联图标：纯矢量，零外部依赖，所有平台显示完全一致',
            '✨ Font Awesome 图标：专业图标库 CDN 按需加载，彩色区分文件类型',
            '✨ CSS 纯样式图标：纯 CSS 绘制几何图形，零依赖，极致轻量',
            '🔄 固定页眉页脚模式：wrapper 背景色统一使用主题 CSS 变量，彻底解决透明区域漏出滚动内容的问题',
        ),
    ),
    array(
        'version' => '2.1.0',
        'date'    => '2026-07-02',
        'changes' => array(
            '🎨 新增个性化选项：布局宽度切换 — 标准（左右各 10%）/ 窄版（左右各 15%），设置保存在浏览器 localStorage',
            '🧷 新增个性化选项：滚动模式切换 — 正常滚动 / 固定页眉页脚（仅文件列表区域滚动），长文件列表浏览更便捷',
            '📂 前端资源拆分：CSS/JS 从 template.php 分离为 assets/style.css 和 assets/script.js，支持浏览器缓存，大幅减少 HTML 体积（template.php 从 1865 行精简至 133 行）',
            '🎯 个性化弹窗优化：自动检测窗口边界重定位，小屏幕也不会超出可视区域',
            '📝 新增文档：CHANGELOG.md 版本日志、GitHub更新指南.md 发布流程说明、.gitignore 敏感文件排除',
        ),
    ),
    array(
        'version' => '2.0.8',
        'date'    => '2026-07-01',
        'changes' => array(
            '⚡ 性能优化：文件统计缓存 TTL 从 5 分钟提升到 30 分钟，大幅减少控制面板加载耗时',
            '✨ 新增：控制面板下载文件排行榜（Top 10）— 按被下载次数降序，金银铜牌标记前三',
            '✨ 新增：控制面板访问 IP 排行榜（Top 10）— 按下载行为次数降序，含最近访问时间',
            '✨ 新增：控制面板今日下载数 + 最近 8 条下载记录实时展示',
            '🔧 新增：手动"立即刷新统计"按钮 — 上传/删除文件后可强制更新文件数量和容量缓存',
            '🔧 新增：refreshStats() / getDownloadRanking() / getIpRanking() / getTodayDownloadCount() 函数',
            '✨ 新增：暂不支持预览弹窗增加「下载文件」和「复制链接」按钮，方便用户直接操作',
            '🐛 修复：复制链接功能在 HTTP 站点下失效 — 新增 document.execCommand("copy") fallback 兼容所有浏览器',
            '🐛 修复：目录缓存版本机制 — 新增 CACHE_VERSION 常量，数据结构变更后旧缓存自动失效',
            '🐛 修复：部分目录文件名异常显示为「文件夹名\\文件名」— 加入缓存版本号强制刷新旧缓存',
            '🐛 修复：clearDirCache() 父目录清除遗漏 CACHE_VERSION — 导致父目录缓存永不失效（CACHE_VERSION → 4）',
            '🛡️ 新增：safeBasename() 防御函数 — 二次清洗文件名，确保任何情况下不含路径分隔符',
            '✨ 新增：后台 → 文件管理 →「修复非法文件名」按钮 — 一键递归重命名含 \\ 或 / 的物理文件名',
            '🔧 安全过滤：上传/重命名文件名正则加入反斜杠和正斜杠替换 — 杜绝未来产生非法文件名',
        ),
    ),
    array(
        'version' => '2.0.7',
        'date'    => '2026-07-01',
        'changes' => array(
            '🐛 修复：音视频进度条无法拖动/播放卡顿 — PHP 后端 serveFile() 新增 HTTP Range 请求支持（206 Partial Content），浏览器可正常 seek 和缓冲',
            '🐛 修复：音频 emoji 图标乱码（� → 🎵）',
            '🎨 CSS：音频元素独立样式 — 去除多余的 border-radius/box-shadow/max-height 限制，width:100% 确保控件栏完整可见',
            '⚡ 前端：video/audio 添加 preload="auto"，浏览器启动即获取时长信息，进度条立即可交互',
        ),
    ),
    array(
        'version' => '2.0.6',
        'date'    => '2026-07-01',
        'changes' => array(
            '🐛 修复：Office 预览加载过早消失 → 去掉 1.5s 激进兜底，改为渐进式提示（3s / 12s / 60s 超时），嵌入 embed onerror 错误处理',
            '🐛 修复：音视频进度条无法拖动 → modal-body overflow:hidden 改为 overflow:auto，控件栏不再被裁切',
            '✨ 美化"不支持预览"提示 → 居中卡片式展示，含文件类型标签和下载提示',
            '🎯 Office 转换超时 / 失败时显示明确错误原因，引导用户排查',
        ),
    ),
    array(
        'version' => '2.0.5',
        'date'    => '2026-07-01',
        'changes' => array(
            '🐛 修复：图片悬浮预览定位偏移 — position: fixed + 图片加载后精确计算定位，tooltip 底部对齐文件名上方',
            '✨ Office 预览增加加载动画 — 转换等待期间显示 spinner，避免空白窗口造成的"卡死"错觉',
            '🛡️ 图片预览切换防抖：同一目标不重复加载，移走后忽略旧 onload',
        ),
    ),
    array(
        'version' => '2.0.4',
        'date'    => '2026-07-01',
        'changes' => array(
            '🐛 修复：图片/视频/音频图标和预览偶发消失 — 旧缓存缺少 is_image/is_video/is_audio 字段，新增统一 MIME 兜底判断',
            '🔄 重构前端类型判断：统一 isImage/isVideo/isAudio/isOffice 函数，缓存字段缺失时自动 MIME 降级',
        ),
    ),
    array(
        'version' => '2.0.3',
        'date'    => '2026-07-01',
        'changes' => array(
            '🐛 修复：LibreOffice 转换失败 — 路径含空格时命令解析异常，改用 escapeshellarg 统一处理',
            '📝 转换失败时自动写入 cache/office/error.log，方便排查错误原因',
        ),
    ),
    array(
        'version' => '2.0.2',
        'date'    => '2026-07-01',
        'changes' => array(
            '🐛 修复：Office 文件预览偶发失效 — 旧缓存缺少 is_office 字段，新增 MIME 类型前端兜底识别',
            '🔄 版本号同步：app_version 升级代码后自动同步到 config.json，不再依赖手动刷新缓存',
        ),
    ),
    array(
        'version' => '2.0.1',
        'date'    => '2026-07-01',
        'changes' => array(
            '📎 Office 文件预览：支持 Word/Excel/PowerPoint 在线预览（需配置 LibreOffice 或自定义 API）',
            '⚙️ 后台新增 Office 预览设置区块，三种模式自由切换（关闭 / LibreOffice 转换 / 自定义 API）',
            '🖼️ 前台 Office 文件显示专属图标，预览弹窗直接嵌入转换后的 PDF',
            '🔄 配置加载优化：自动合并默认配置中新增的键，旧 config.json 不会丢失新版本新增的配置项',
            '🐛 修复：修正前台页脚和后台更新日志页面版本号显示为"未知"的问题',
        ),
    ),
    array(
        'version' => '2.0.0',
        'date'    => '2026-07-01',
        'changes' => array(
            '🎯 数据库统一合并：downloads.db + users.db → app.db（首次启动自动迁移旧数据）',
            '💾 配置持久化：系统设置从 config.php 迁移到 config.json，部署覆盖代码不再丢失设置',
            '🔐 用户+角色系统：基于 SQLite 的 RBAC 权限管理，支持自定义角色、目录权限、上传大小限制',
            '📋 下载历史：记录文件下载日志，支持分页浏览、删除和清空',
            '🔍 文件搜索：前台支持按文件名实时搜索',
            '🎨 多主题支持：紫蓝渐变/暗夜/翡翠绿/日落橙/深海蓝 + 字体切换',
            '📂 目录扫描缓存：大幅提升大目录加载速度',
            '🖼️ 缩略图生成：图片目录支持缩略图预览',
            '📝 TXT 编码自动检测：预览 GBK/GB2312 等编码文件不再乱码',
            '🛡️ CSRF 防护 + 路径安全校验',
            '📎 导航链接管理：后台可自定义前台导航链接',
        ),
    ),
    array(
        'version' => '1.0.0',
        'date'    => '2026-06-01',
        'changes' => array(
            '🎉 初始版本发布',
            '📁 文件目录浏览（排序、预览、下载）',
            '🎨 图片/视频/音频/PDF 预览弹窗',
            '🔗 文件直链复制',
            '📊 基本信息统计（总大小、文件数）',
            '🧭 面包屑导航',
        ),
    ),
);

$currentVersion = getConfig('app_version') ?: '未知';
?>
<?php require __DIR__ . '/layout.php'; ?>

<div class="content-box">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin:0;">📋 更新日志</h3>
        <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600;">当前版本 v<?php echo htmlspecialchars($currentVersion); ?></span>
    </div>

    <?php foreach ($changelog as $release): ?>
        <div style="margin-bottom: 24px; padding: 20px; background: #f8f9fa; border-radius: 12px; border-left: 4px solid #667eea;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h4 style="margin:0; color: #667eea; font-size: 16px;">v<?php echo htmlspecialchars($release['version']); ?></h4>
                <span style="font-size: 12px; color: #888;"><?php echo htmlspecialchars($release['date']); ?></span>
            </div>
            <ul style="margin: 0; padding-left: 20px; line-height: 2; color: #495057; font-size: 14px;">
                <?php foreach ($release['changes'] as $change): ?>
                    <li><?php echo htmlspecialchars($change); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>
</div>
</body>
</html>
