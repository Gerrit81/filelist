<?php
require_once __DIR__ . '/init.php';
requirePermission('settings');

$pageTitle = '📋 更新日志';

// 更新日志数据库 — 每次发版在此追加记录
$changelog = array(
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
