<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件浏览器</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/style.css?v=<?php echo htmlspecialchars(getConfig('app_version')); ?>">
</head>
<body data-current-path="<?php echo htmlspecialchars($currentPath); ?>" data-office-preview-mode="<?php echo htmlspecialchars($officePreviewMode ?? 'off'); ?>" data-icon-scheme="<?php echo htmlspecialchars(getConfig('icon_scheme') ?: 'emoji'); ?>" data-svg-icon-style="<?php echo htmlspecialchars(getConfig('svg_icon_style') ?: 'material'); ?>">

    <div class="container">
        <div class="sticky-header-wrap">
            <header>
                <div class="header-left">
                    <div class="header-title">
                        <h1>📁 <?php echo htmlspecialchars(getConfig('site_name')); ?></h1>
                        <p><?php echo htmlspecialchars(getConfig('site_subtitle')); ?></p>
                    </div>
                </div>
                <div class="header-right">
                    <div class="nav-links">
                        <?php foreach (getConfig('nav_links') as $link): ?>
                            <a href="<?php echo htmlspecialchars($link['url']); ?>" target="<?php echo isset($link['target']) ? htmlspecialchars($link['target']) : '_self'; ?>"><?php echo htmlspecialchars($link['name']); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <button class="personalize-btn" id="personalizeBtn" title="个性化设置">
                        <span class="btn-icon">🎨</span> 个性化
                    </button>
                    <div class="search-bar">
                        <input type="text" id="searchInput" placeholder="搜索文件或目录...">
                        <button id="searchBtn">搜索</button>
                    </div>
                </div>
            </header>
        </div>

        <div class="content-scroll">
            <div class="breadcrumbs" id="breadcrumbs">
                <ul>
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <?php if ($crumb['path'] === $currentPath): ?>
                            <li class="current"><?php echo htmlspecialchars($crumb['name']); ?></li>
                        <?php else: ?>
                            <li><a href="?path=<?php echo urlencode($crumb['path']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a></li>
                            <li class="separator">/</li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="file-table-wrapper">
                <table class="file-table" id="fileTable">
                    <thead>
                        <tr>
                            <th data-sort="name">名称</th>
                            <th data-sort="size">大小</th>
                            <th data-sort="type">类型</th>
                            <th data-sort="mtime">修改时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="fileTableBody">
                        <tr><td colspan="5" class="loading">加载中</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="empty-state" id="emptyState" style="display: none;">
                <div class="icon">📭</div>
                <h3>目录为空</h3>
                <p>将文件或文件夹放入数据目录即可显示</p>
            </div>
        </div>

        <div class="sticky-footer-wrap">
            <footer>
                <p>&copy; 2026 文件浏览器. All rights reserved.</p>
                <p>轻量级文件目录浏览系统 | 基于 PHP + SQLite | v<?php echo htmlspecialchars(getConfig('app_version') ?: '--'); ?></p>
            </footer>
        </div>

        <div class="image-tooltip" id="imageTooltip"></div>
    </div>

    <div class="modal-overlay" id="previewModal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title" id="modalTitle">预览</span>
                <button class="modal-close" id="modalClose">×</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <div class="toast" id="toast">已复制到剪贴板</div>

    <div class="personalize-overlay" id="personalizeOverlay"></div>
    <div class="personalize-popup" id="personalizePopup">
        <div class="personalize-section">
            <div class="personalize-section-title">🎨 主题色彩</div>
            <div class="personalize-colors" id="personalizeColors">
                <span class="personalize-dot" data-value="default" title="紫蓝渐变"></span>
                <span class="personalize-dot" data-value="dark" title="暗夜模式"></span>
                <span class="personalize-dot" data-value="green" title="翡翠绿"></span>
                <span class="personalize-dot" data-value="orange" title="日落橙暖"></span>
                <span class="personalize-dot" data-value="blue" title="深海蓝"></span>
            </div>
        </div>
        <div class="personalize-section">
            <div class="personalize-section-title">🔤 字体样式</div>
            <select class="personalize-font-select" id="fontSelect">
                <option value="default">系统默认</option>
                <option value="yahei">微软雅黑</option>
                <option value="simsun">宋体</option>
                <option value="kaiti">楷体</option>
                <option value="dengxian">等线</option>
            </select>
        </div>
        <div class="personalize-section">
            <div class="personalize-section-title">📐 布局宽度</div>
            <div class="personalize-options-row">
                <button class="personalize-option-btn active" data-layout="default">标准 (10%)</button>
                <button class="personalize-option-btn" data-layout="narrow">窄版 (15%)</button>
            </div>
        </div>
        <div class="personalize-section">
            <div class="personalize-section-title">📌 滚动模式</div>
            <div class="personalize-options-row">
                <button class="personalize-option-btn active" data-scroll="normal">正常滚动</button>
                <button class="personalize-option-btn" data-scroll="sticky">固定页眉页脚</button>
            </div>
        </div>
        <div class="personalize-section">
            <div class="personalize-section-title">🎯 图标风格</div>
            <select class="personalize-font-select" id="svgStyleSelect">
                <option value="material">Material 现代简洁</option>
                <option value="cartoon">卡通圆润</option>
                <option value="scifi">科幻霓虹</option>
                <option value="minimal">极简线条</option>
                <option value="pixel">像素方块</option>
                <option value="gradient">渐变色彩</option>
                <option value="handdrawn">手绘草图</option>
            </select>
        </div>
    </div>

    <script src="assets/script.js?v=<?php echo htmlspecialchars(getConfig('app_version')); ?>"></script>
</body>
</html>
