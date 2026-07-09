<?php
require_once __DIR__ . '/init.php';
requirePermission('settings');

$pageTitle = '⚙️ 系统设置';
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $config = loadConfig();

    // ── AJAX: 排序 ──
    if (isset($_POST['action']) && $_POST['action'] === 'reorder_nav') {
        $order = isset($_POST['order']) ? json_decode($_POST['order'], true) : [];
        if (is_array($order) && !empty($order)) {
            $newLinks = [];
            $oldLinks = $config['nav_links'] ?? [];
            foreach ($order as $idx) {
                $idx = (int)$idx;
                if (isset($oldLinks[$idx])) {
                    $newLinks[] = $oldLinks[$idx];
                }
            }
            // 保留不在 order 中的项（理论上不应出现，但做保护）
            foreach ($oldLinks as $i => $link) {
                if (!in_array($i, $order)) {
                    $newLinks[] = $link;
                }
            }
            $config['nav_links'] = $newLinks;
            saveConfig($config);
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── AJAX: 编辑单个链接 ──
    if (isset($_POST['action']) && $_POST['action'] === 'edit_nav') {
        $idx = (int)$_POST['edit_index'];
        if (isset($config['nav_links'][$idx])) {
            $config['nav_links'][$idx]['name']   = trim($_POST['edit_name']);
            $config['nav_links'][$idx]['url']    = trim($_POST['edit_url']);
            $config['nav_links'][$idx]['target'] = in_array($_POST['edit_target'], ['_self', '_blank']) ? $_POST['edit_target'] : '_self';
            saveConfig($config);
            $message = '链接已更新';
        }
    }

    // ── 基本设置 ──
    if (isset($_POST['site_name'])) {
        $config['site_name'] = trim($_POST['site_name']);
    }
    if (isset($_POST['site_subtitle'])) {
        $config['site_subtitle'] = trim($_POST['site_subtitle']);
    }
    if (isset($_POST['data_dir'])) {
        $newDataDir = rtrim(trim($_POST['data_dir']), '/\\');
        // 清除文件状态缓存，确保 Linux 下实时检测
        clearstatcache(true);

        $exists = file_exists($newDataDir) || (function_exists('is_dir') && @is_dir($newDataDir));

        if (!$exists) {
            // 诊断：尝试 realpath 看是否由 open_basedir 导致
            $rp = @realpath($newDataDir);
            if ($rp !== false && is_dir($rp)) {
                // open_basedir 下 file_exists 可能失败，但 realpath 成功 → 信任 realpath
                $config['data_dir'] = normalizeConfigPath($newDataDir);
            } else {
                $basedir = ini_get('open_basedir');
                // 当 open_basedir 生效时，用 shell 确认目录是否真实存在
                $dirOnDisk = false;
                if (!empty($basedir)) {
                    if (function_exists('shell_exec') && stripos(ini_get('disable_functions'), 'shell_exec') === false) {
                        $escp = escapeshellarg($newDataDir);
                        $test = @shell_exec("test -d {$escp} && echo 1 2>/dev/null");
                        $dirOnDisk = (trim((string)$test) === '1');
                    }
                }

                if ($dirOnDisk) {
                    // 目录确实存在，但 open_basedir 挡住了
                    $message = '数据目录无效：<strong>目录在服务器上存在，但被 PHP open_basedir 限制无法访问。</strong>'
                        . '<br><br>当前 open_basedir：<code>' . htmlspecialchars($basedir) . '</code>'
                        . '<br><br><strong>解决方法（选其一）：</strong>'
                        . '<br>① WebStation → PHP 设置 → 核心设置 → open_basedir，追加 <code>:' . htmlspecialchars($newDataDir) . '</code>'
                        . '<br>② SSH 执行：<code>ln -s ' . htmlspecialchars($newDataDir) . ' ' . htmlspecialchars(__DIR__ . '/../data_link') . '</code>，然后将数据目录设为 <code>' . htmlspecialchars(__DIR__ . '/../data_link') . '</code>';
                    $messageType = 'error';
                } else {
                    $detail = '目录不存在或无法读取。';
                    if (!empty($basedir)) {
                        $detail .= ' 当前 PHP open_basedir 限制为：' . $basedir . '，请确认目标目录在该范围内。';
                    }
                    $message = '数据目录无效：' . $detail;
                    $messageType = 'error';
                }
            }
        } elseif (!is_dir($newDataDir)) {
            $message = '数据目录无效：该路径存在但不是目录（可能是文件）';
            $messageType = 'error';
        } else {
            // 最终安全检查：确保 realpath 能解析（路径遍历防护前置）
            $realPath = @realpath($newDataDir);
            if ($realPath === false || !is_dir($realPath)) {
                $message = '数据目录无效：无法解析为真实路径（可能存在符号链接或权限问题）';
                $messageType = 'error';
            } else {
                $config['data_dir'] = normalizeConfigPath($newDataDir);
            }
        }
    }
    if (isset($_POST['max_upload_size'])) {
        $config['max_upload_size'] = max(0, intval($_POST['max_upload_size']));
    }

    // ── 图标方案 ──
    if (isset($_POST['icon_scheme'])) {
        $config['icon_scheme'] = in_array($_POST['icon_scheme'], ['emoji', 'svg', 'css']) ? $_POST['icon_scheme'] : 'emoji';
    }

    // ── SVG 图标子风格 ──
    if (isset($_POST['svg_icon_style'])) {
        $validStyles = ['material', 'cartoon', 'scifi', 'minimal', 'pixel', 'gradient', 'handdrawn'];
        $config['svg_icon_style'] = in_array($_POST['svg_icon_style'], $validStyles) ? $_POST['svg_icon_style'] : 'material';
    }

    // ── Office 预览设置 ──
    if (isset($_POST['office_preview_mode'])) {
        $config['office_preview_mode'] = in_array($_POST['office_preview_mode'], ['off', 'libreoffice', 'custom']) ? $_POST['office_preview_mode'] : 'off';
    }
    if (isset($_POST['libreoffice_path'])) {
        $config['libreoffice_path'] = trim($_POST['libreoffice_path']);
    }
    if (isset($_POST['office_preview_api'])) {
        $config['office_preview_api'] = trim($_POST['office_preview_api']);
    }

    // ── 安全模式设置 ──
    if (isset($_POST['security_mode'])) {
        $config['security_mode'] = in_array($_POST['security_mode'], ['intranet', 'internet']) ? $_POST['security_mode'] : 'intranet';
    }
    if (isset($_POST['download_rate_limit'])) {
        $config['download_rate_limit'] = max(0, intval($_POST['download_rate_limit']));
    }
    if (isset($_POST['upload_max_size_mb'])) {
        $config['upload_max_size_mb'] = max(0, intval($_POST['upload_max_size_mb']));
    }
    if (isset($_POST['internet_allow_anonymous_view'])) {
        $config['internet_allow_anonymous_view'] = ($_POST['internet_allow_anonymous_view'] === '1');
    }
    if (isset($_POST['internet_force_https'])) {
        $config['internet_force_https'] = ($_POST['internet_force_https'] === '1');
    }

    // ── 添加链接 ──
    if (isset($_POST['add_nav'])) {
        $navName   = trim($_POST['nav_name']);
        $navUrl    = trim($_POST['nav_url']);
        $navTarget = in_array($_POST['nav_target'] ?? '_self', ['_self', '_blank']) ? $_POST['nav_target'] : '_self';
        if (!empty($navName) && !empty($navUrl)) {
            $config['nav_links'][] = array('name' => $navName, 'url' => $navUrl, 'target' => $navTarget);
        }
    }

    // ── 移除链接 ──
    if (isset($_POST['remove_nav'])) {
        $removeIndex = (int)$_POST['remove_nav'];
        if (isset($config['nav_links'][$removeIndex])) {
            array_splice($config['nav_links'], $removeIndex, 1);
        }
    }

    if ($message === '') {
        saveConfig($config);
        $message = '设置已保存';
    }
}

$config = loadConfig();
?>
<?php require __DIR__ . '/layout.php'; ?>

<div class="content-box">
    <h3>基本设置</h3>

    <?php if ($message): ?>
        <div class="msg-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-item">
            <label for="site_name">网站名称</label>
            <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($config['site_name']); ?>" placeholder="请输入网站名称">
        </div>
        <div class="form-item">
            <label for="site_subtitle">网站副标题</label>
            <input type="text" id="site_subtitle" name="site_subtitle" value="<?php echo htmlspecialchars($config['site_subtitle']); ?>" placeholder="请输入网站副标题">
        </div>
        <div class="form-item">
            <label for="data_dir">数据目录</label>
            <input type="text" id="data_dir" name="data_dir" value="<?php echo htmlspecialchars($config['data_dir']); ?>" placeholder="请输入数据目录路径">
            <div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 8px; font-size: 12px; margin-top: 5px;">⚠️ 修改此设置会影响所有文件的读取，请确保目录存在且有读写权限。</div>
        </div>
        <div class="form-item">
            <label for="max_upload_size">全局默认上传大小限制（字节，0=不限制）</label>
            <input type="number" id="max_upload_size" name="max_upload_size" value="<?php echo (int)($config['max_upload_size'] ?? 0); ?>" min="0" step="1" placeholder="0 表示不限制">
            <div style="font-size: 12px; color: #666; margin-top: 5px;">新建用户的默认上传大小限制，各用户可在「用户管理」中单独设置。</div>
        </div>
        <div class="form-item">
            <label for="icon_scheme">文件图标方案</label>
            <select id="icon_scheme" name="icon_scheme" onchange="toggleSvgStyle()" style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
                <option value="emoji" <?php echo ($config['icon_scheme'] ?? 'emoji') === 'emoji' ? 'selected' : ''; ?>>😀 Emoji 表情 — 系统原生，色彩丰富，但不同 OS 显示不一致</option>
                <option value="svg" <?php echo ($config['icon_scheme'] ?? '') === 'svg' ? 'selected' : ''; ?>>🖌️ SVG 内联 — 矢量图标，7 套风格，零外部依赖，所有平台完全一致</option>
                <option value="css" <?php echo ($config['icon_scheme'] ?? '') === 'css' ? 'selected' : ''; ?>>🎨 CSS 纯样式 — 纯 CSS 绘制，零依赖，极致轻量</option>
            </select>
        </div>
        <div class="form-item" id="svg_style_group" style="<?php echo ($config['icon_scheme'] ?? 'emoji') === 'svg' ? '' : 'display:none;'; ?>">
            <label for="svg_icon_style">SVG 图标风格</label>
            <select id="svg_icon_style" name="svg_icon_style" style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
                <option value="material" <?php echo ($config['svg_icon_style'] ?? 'material') === 'material' ? 'selected' : ''; ?>>🔷 Material — 现代简洁，扁平色彩，经典风格</option>
                <option value="cartoon" <?php echo ($config['svg_icon_style'] ?? '') === 'cartoon' ? 'selected' : ''; ?>>🎈 卡通风格 — 圆润饱满，粗描边，活泼可爱</option>
                <option value="scifi" <?php echo ($config['svg_icon_style'] ?? '') === 'scifi' ? 'selected' : ''; ?>>🚀 科幻风格 — 霓虹暗底，锐利棱角，未来科技感</option>
                <option value="minimal" <?php echo ($config['svg_icon_style'] ?? '') === 'minimal' ? 'selected' : ''; ?>>◻️ 极简线条 — 细线勾勒，低饱和度，优雅克制</option>
                <option value="pixel" <?php echo ($config['svg_icon_style'] ?? '') === 'pixel' ? 'selected' : ''; ?>>👾 像素风格 — 方块拼接，复古像素，怀旧游戏风</option>
                <option value="gradient" <?php echo ($config['svg_icon_style'] ?? '') === 'gradient' ? 'selected' : ''; ?>>🌈 渐变风格 — 平滑过渡，现代渐变，应用质感</option>
                <option value="handdrawn" <?php echo ($config['svg_icon_style'] ?? '') === 'handdrawn' ? 'selected' : ''; ?>>✏️ 手绘风格 — 不规则线条，草图质感，温暖自然</option>
            </select>
        </div>
        <button type="submit" class="btn-primary">保存设置</button>
    </form>

    <h3 class="divider">📎 Office 文件预览</h3>
    <div style="background: #f0f4ff; border: 1px solid #c3d0f0; border-radius: 10px; padding: 16px; margin-bottom: 20px; font-size: 13px; color: #445;">
        <p style="margin: 0 0 8px 0; font-weight: 600;">📘 功能说明</p>
        <p style="margin: 0;">支持预览 Word (.doc/.docx)、Excel (.xls/.xlsx)、PowerPoint (.ppt/.pptx) 文件。三种模式可选：</p>
        <ul style="margin: 8px 0 0 0; padding-left: 20px; line-height: 1.8;">
            <li><strong>关闭</strong> — 不在前台显示 Office 文件预览</li>
            <li><strong>LibreOffice 转换</strong> — 服务器安装 LibreOffice 后，自动将 Office 文件转为 PDF 在线预览</li>
            <li><strong>自定义 API</strong> — 使用你自己的 Office 预览服务（如 OnlyOffice、Office Web Viewer 等），传入文件下载地址</li>
        </ul>
    </div>

    <form method="post" style="margin-bottom: 20px;">
        <div class="form-item">
            <label for="office_preview_mode">预览模式</label>
            <select id="office_preview_mode" name="office_preview_mode" onchange="toggleOfficeFields()" style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
                <option value="off" <?php echo ($config['office_preview_mode'] ?? 'off') === 'off' ? 'selected' : ''; ?>>关闭</option>
                <option value="libreoffice" <?php echo ($config['office_preview_mode'] ?? '') === 'libreoffice' ? 'selected' : ''; ?>>LibreOffice 转换</option>
                <option value="custom" <?php echo ($config['office_preview_mode'] ?? '') === 'custom' ? 'selected' : ''; ?>>自定义 API</option>
            </select>
        </div>

        <div id="lo_path_group" class="form-item" style="display:none;">
            <label for="libreoffice_path">soffice.exe 路径</label>
            <input type="text" id="libreoffice_path" name="libreoffice_path" value="<?php echo htmlspecialchars($config['libreoffice_path'] ?? ''); ?>" placeholder='C:\Program Files\LibreOffice\program\soffice.exe'>
            <div style="font-size:12px;color:#888;margin-top:4px;">留空则自动尝试常见安装路径。Linux 下可填 <code>libreoffice</code> 或 <code>/usr/bin/soffice</code>。</div>
        </div>

        <div id="api_group" class="form-item" style="display:none;">
            <label for="office_preview_api">预览 API 地址</label>
            <input type="text" id="office_preview_api" name="office_preview_api" value="<?php echo htmlspecialchars($config['office_preview_api'] ?? ''); ?>" placeholder='https://your-preview-server/view?url={url}'>
            <div style="font-size:12px;color:#888;margin-top:4px;">
                <code>{url}</code> 会被替换为文件的下载地址（经 URL 编码）。示例：
                <br>• Microsoft Office Web Viewer: <code>https://view.officeapps.live.com/op/view.aspx?src={url}</code>（需外网）
                <br>• OnlyOffice: <code>https://your-onlyoffice/OnlineEditors?url={url}</code>
            </div>
        </div>

        <button type="submit" class="btn-primary">保存设置</button>
    </form>

    <script>
        function toggleOfficeFields() {
            const mode = document.getElementById('office_preview_mode').value;
            document.getElementById('lo_path_group').style.display = mode === 'libreoffice' ? '' : 'none';
            document.getElementById('api_group').style.display = mode === 'custom' ? '' : 'none';
        }
        function toggleSvgStyle() {
            const scheme = document.getElementById('icon_scheme').value;
            document.getElementById('svg_style_group').style.display = scheme === 'svg' ? '' : 'none';
        }
        toggleOfficeFields();
        toggleSvgStyle();
    </script>

    <h3 class="divider">🔐 安全模式与防护</h3>
    <div style="background: <?php echo ($config['security_mode'] ?? 'intranet') === 'internet' ? '#fff3cd' : '#f0fff4'; ?>; border: 1px solid <?php echo ($config['security_mode'] ?? 'intranet') === 'internet' ? '#ffc107' : '#b7ebbf'; ?>; border-radius: 10px; padding: 16px; margin-bottom: 20px; font-size: 13px; color: #445;">
        <p style="margin: 0 0 8px 0; font-weight: 600;">
            <?php echo ($config['security_mode'] ?? 'intranet') === 'internet' ? '⚠️ 当前为外网模式（高安全性）' : '✅ 当前为内网模式（轻便快捷）'; ?>
        </p>
        <p style="margin: 0;">
            <?php if (($config['security_mode'] ?? 'intranet') === 'internet'): ?>
            外网模式下启用了：登录限流+锁定、安全响应头、IP 黑白名单、审计日志、文件上传格式校验、下载速率限制、Session 超时等全部防护措施。
            <?php else: ?>
            内网模式下仅保留基础安全（密码认证+CSRF+路径防护），保持轻量便捷。切换到外网部署时请改为「外网模式」。
            <?php endif; ?>
        </p>
    </div>

    <form method="post" style="margin-bottom: 20px;">
        <div class="form-item">
            <label for="security_mode">安全模式</label>
            <select id="security_mode" name="security_mode" style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
                <option value="intranet" <?php echo ($config['security_mode'] ?? 'intranet') === 'intranet' ? 'selected' : ''; ?>>🏠 内网模式 — 轻便快捷，仅基础安全（默认）</option>
                <option value="internet" <?php echo ($config['security_mode'] ?? '') === 'internet' ? 'selected' : ''; ?>>🌐 外网模式 — 全面防护，零信任安全策略</option>
            </select>
            <div style="font-size:12px;color:#888;margin-top:4px;">⚠️ 切换模式后立即生效。外网模式会限制频繁操作，请确保已在 IP 白名单中添加管理员 IP。</div>
        </div>

        <div class="form-item">
            <label for="download_rate_limit">下载速率限制（次/分钟，外网模式，0=不限）</label>
            <input type="number" id="download_rate_limit" name="download_rate_limit" value="<?php echo (int)($config['download_rate_limit'] ?? 30); ?>" min="0" step="1" style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
            <div style="font-size:12px;color:#888;margin-top:4px;">防止外网用户恶意刷下载，建议值：30 次/分钟。</div>
        </div>

        <div class="form-item">
            <label for="upload_max_size_mb">上传文件大小限制（MB，外网模式，0=不限）</label>
            <input type="number" id="upload_max_size_mb" name="upload_max_size_mb" value="<?php echo (int)($config['upload_max_size_mb'] ?? 100); ?>" min="0" step="1" style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
            <div style="font-size:12px;color:#888;margin-top:4px;">建议值：100 MB，过大文件可能消耗服务器带宽。</div>
        </div>

        <div class="form-item">
            <label>
                <input type="checkbox" name="internet_allow_anonymous_view" value="1" <?php echo ($config['internet_allow_anonymous_view'] ?? true) ? 'checked' : ''; ?> style="margin-right:6px;">
                外网模式允许匿名浏览文件列表（无需登录即可查看）
            </label>
            <div style="font-size:12px;color:#888;margin-top:4px;">关闭后，外网用户必须登录才能访问任何文件。</div>
        </div>

        <div class="form-item">
            <label>
                <input type="checkbox" name="internet_force_https" value="1" <?php echo ($config['internet_force_https'] ?? true) ? 'checked' : ''; ?> style="margin-right:6px;">
                强制 HTTPS（部署在反向代理后面时应关闭）
            </label>
        </div>

        <button type="submit" class="btn-primary">保存设置</button>
    </form>

    <h3 class="divider">导航链接管理</h3>

    <form method="post" style="margin-bottom: 16px;">
        <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 120px;">
                <label for="nav_name">链接名称</label>
                <input type="text" id="nav_name" name="nav_name" placeholder="如：首页" required style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
            </div>
            <div style="flex: 2; min-width: 180px;">
                <label for="nav_url">链接地址</label>
                <input type="text" id="nav_url" name="nav_url" placeholder="如：https://example.com" required style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
            </div>
            <div style="width: 100px;">
                <label for="nav_target">打开方式</label>
                <select id="nav_target" name="nav_target" style="width:100%;padding:10px 8px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
                    <option value="_self">当前窗口</option>
                    <option value="_blank">新窗口</option>
                </select>
            </div>
            <div>
                <button type="submit" name="add_nav" class="btn-primary" style="white-space:nowrap;">＋ 添加</button>
            </div>
        </div>
    </form>

    <?php if (!empty($config['nav_links'])): ?>
        <div style="background: #f8f9fa; border-radius: 8px; padding: 4px;" id="nav-list">
            <?php foreach ($config['nav_links'] as $index => $link): ?>
                <div class="nav-item" data-index="<?php echo $index; ?>" style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:white;border-radius:6px;margin-bottom:4px;cursor:grab;transition:box-shadow 0.2s;user-select:none;">
                    <span style="color:#aaa;font-size:16px;cursor:grab;" title="拖拽排序">⠿</span>
                    <span style="flex:1;font-size:14px;color:#333;font-weight:500;"><?php echo htmlspecialchars($link['name']); ?></span>
                    <span style="color:#667eea;font-size:13px;font-family:monospace;"><?php echo htmlspecialchars($link['url']); ?></span>
                    <span style="font-size:11px;padding:2px 8px;border-radius:10px;background:<?php echo ($link['target'] ?? '_self') === '_blank' ? '#d4edda' : '#e2e3e5'; ?>;color:<?php echo ($link['target'] ?? '_self') === '_blank' ? '#155724' : '#383d41'; ?>;"><?php echo ($link['target'] ?? '_self') === '_blank' ? '新窗口' : '当前'; ?></span>
                    <button class="btn-sm btn-edit" onclick="openEditNav(<?php echo $index; ?>, '<?php echo htmlspecialchars(addslashes($link['name'])); ?>', '<?php echo htmlspecialchars(addslashes($link['url'])); ?>', '<?php echo htmlspecialchars($link['target'] ?? '_self'); ?>')" title="编辑">✎ 编辑</button>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="remove_nav" value="<?php echo $index; ?>">
                        <button type="submit" class="btn-sm btn-del" onclick="return confirm('确定移除此链接？')">✕</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="font-size:12px;color:#888;margin-top:6px;">💡 拖拽 ⠿ 图标可调整导航链接排序</div>
    <?php else: ?>
        <div style="text-align:center;padding:30px;color:#888;">暂无导航链接</div>
    <?php endif; ?>
</div>

<!-- 编辑导航弹窗 -->
<div class="modal-overlay" id="editNavModal" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <h4>编辑导航链接</h4>
            <button class="modal-close" onclick="closeEditNav()">×</button>
        </div>
        <form method="post" id="editNavForm">
            <input type="hidden" name="action" value="edit_nav">
            <input type="hidden" name="edit_index" id="editNavIndex">
            <div class="form-item">
                <label for="editNavName">链接名称</label>
                <input type="text" id="editNavName" name="edit_name" required>
            </div>
            <div class="form-item">
                <label for="editNavUrl">链接地址</label>
                <input type="text" id="editNavUrl" name="edit_url" required>
            </div>
            <div class="form-item">
                <label for="editNavTarget">打开方式</label>
                <select id="editNavTarget" name="edit_target">
                    <option value="_self">当前窗口 (_self)</option>
                    <option value="_blank">新窗口 (_blank)</option>
                </select>
            </div>
            <button type="submit" class="btn-primary">保存</button>
        </form>
    </div>
</div>

<style>
.nav-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.nav-item.dragging { opacity: 0.5; box-shadow: 0 4px 16px rgba(102,126,234,0.3); }
.nav-item.drag-over { border-top: 3px solid #667eea; }
</style>

<script>
// ── 拖拽排序 ──
(function() {
    const list = document.getElementById('nav-list');
    if (!list) return;

    let dragIndex = null;

    list.addEventListener('dragstart', function(e) {
        const item = e.target.closest('.nav-item');
        if (!item) return;
        dragIndex = item.dataset.index;
        item.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    list.addEventListener('dragend', function(e) {
        const item = e.target.closest('.nav-item');
        if (item) item.classList.remove('dragging');
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('drag-over'));
        dragIndex = null;
    });

    list.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const item = e.target.closest('.nav-item');
        if (item) {
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('drag-over'));
            item.classList.add('drag-over');
        }
    });

    list.addEventListener('drop', function(e) {
        e.preventDefault();
        const target = e.target.closest('.nav-item');
        if (!target || dragIndex === null || target.dataset.index === dragIndex) return;
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('drag-over'));

        // 计算新顺序
        const items = [...list.querySelectorAll('.nav-item')];
        const order = items.map(el => parseInt(el.dataset.index));
        const draggedIdx = order.indexOf(parseInt(dragIndex));
        const targetIdx = order.indexOf(parseInt(target.dataset.index));
        if (draggedIdx >= 0 && targetIdx >= 0) {
            order.splice(draggedIdx, 1);
            order.splice(targetIdx, 0, parseInt(dragIndex));
        }

        // AJAX 保存
        const formData = new FormData();
        formData.append('action', 'reorder_nav');
        formData.append('order', JSON.stringify(order));
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

        fetch('settings.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    // 重新排列 DOM 以匹配保存后的新顺序
                    const reordered = order.map(idx => list.querySelector(`.nav-item[data-index="${idx}"]`));
                    reordered.forEach(el => {
                        if (el) list.appendChild(el);
                    });
                }
            });
    });

    // 使每个 nav-item 可拖拽
    list.querySelectorAll('.nav-item').forEach(el => {
        el.setAttribute('draggable', 'true');
    });
})();

// ── 编辑弹窗 ──
function openEditNav(index, name, url, target) {
    document.getElementById('editNavIndex').value = index;
    document.getElementById('editNavName').value = name;
    document.getElementById('editNavUrl').value = url;
    document.getElementById('editNavTarget').value = target || '_self';
    document.getElementById('editNavModal').style.display = 'flex';
}
function closeEditNav() {
    document.getElementById('editNavModal').style.display = 'none';
}
document.getElementById('editNavModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditNav();
});
</script>

</div>
</body>
</html>
