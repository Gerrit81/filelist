<?php
require_once __DIR__ . '/init.php';
requirePermission('files');

$pageTitle = '📂 文件管理';
$message = '';
$messageType = 'success';

$dataDir = getConfig('data_dir');
$currentPath = isset($_GET['path']) ? trim($_GET['path'], '/\\') : '';

// 处理 POST 操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    // 删除单个
    if (isset($_POST['delete'])) {
        $target = $_POST['target'] ?? '';
        $fullPath = getFullPath($target);
        if (isSafePath($fullPath) && file_exists($fullPath)) {
            if (is_dir($fullPath)) {
                // 递归删除目录
                $it = new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($files as $file) {
                    if ($file->isDir()) {
                        rmdir($file->getRealPath());
                    } else {
                        unlink($file->getRealPath());
                    }
                }
                rmdir($fullPath);
            } else {
                unlink($fullPath);
            }
            $message = '已删除：' . safeBasename($fullPath);
            clearDirCache($currentPath);
            clearStatsCache();
        } else {
            $message = '删除失败：文件不存在或无权限';
            $messageType = 'error';
        }
    }
    // 批量删除
    elseif (isset($_POST['bulk_delete']) && hasPermission('files_delete')) {
        $targets = isset($_POST['targets']) ? (array)$_POST['targets'] : array();
        $successCount = 0;
        $failCount = 0;
        foreach ($targets as $target) {
            $target = trim($target);
            if (empty($target)) continue;
            $fullPath = getFullPath($target);
            if (isSafePath($fullPath) && file_exists($fullPath)) {
                if (is_dir($fullPath)) {
                    $it = new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS);
                    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
                    foreach ($files as $file) {
                        if ($file->isDir()) {
                            rmdir($file->getRealPath());
                        } else {
                            unlink($file->getRealPath());
                        }
                    }
                    rmdir($fullPath);
                } else {
                    unlink($fullPath);
                }
                $successCount++;
            } else {
                $failCount++;
            }
        }
        $message = "批量删除完成：成功 {$successCount} 个";
        if ($failCount > 0) {
            $message .= "，失败 {$failCount} 个";
            $messageType = 'error';
        }
        clearDirCache($currentPath);
        clearStatsCache();
    }

    // 修复非法文件名
    elseif (isset($_POST['fix_illegal_names']) && hasPermission('files_rename')) {
        $scanPath = getFullPath($currentPath);
        if (!isSafePath($scanPath) || !is_dir($scanPath)) {
            $message = '修复失败：非法路径';
            $messageType = 'error';
        } else {
            $toFix = array();
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($scanPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $file) {
                $name = $file->getFilename();
                // Windows 下 getFilename() 可能返回系统编码（GBK），先转 UTF-8
                $name = sysToUtf8($name);
                if (strpos($name, '\\') !== false || strpos($name, '/') !== false) {
                    $realPath = $file->getRealPath();
                    $safeName = preg_replace('/[\\\\\/]|[^\x{4e00}-\x{9fa5}a-zA-Z0-9._\-\[\]()（） ]/u', '_', $name);
                    $parent = $file->getPath();
                    $newPath = $parent . DIRECTORY_SEPARATOR . $safeName;

                    // 处理目标已存在的情况
                    $counter = 1;
                    $pathInfo = pathinfo($safeName);
                    $baseName = $pathInfo['filename'];
                    $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
                    while (file_exists($newPath)) {
                        $safeName = $baseName . '_' . $counter . $ext;
                        $newPath = $parent . DIRECTORY_SEPARATOR . $safeName;
                        $counter++;
                    }

                    $toFix[] = array(
                        'old' => $realPath,
                        'new' => $newPath,
                        'depth' => substr_count($realPath, DIRECTORY_SEPARATOR)
                    );
                }
            }

            // 按深度降序，先处理深层路径，避免父目录重命名后路径失效
            usort($toFix, function ($a, $b) {
                return $b['depth'] - $a['depth'];
            });

            $fixed = 0;
            $failed = 0;
            foreach ($toFix as $item) {
                if (rename($item['old'], $item['new'])) {
                    $fixed++;
                } else {
                    $failed++;
                }
            }

            $message = "非法文件名修复完成：成功 {$fixed} 个";
            if ($failed > 0) {
                $message .= "，失败 {$failed} 个";
                $messageType = 'error';
            }
            if ($fixed > 0) {
                clearDirCache($currentPath);
                clearStatsCache();
            }
        }
    }

    // 重命名
    elseif (isset($_POST['rename'])) {
        $target = $_POST['target'] ?? '';
        $newName = trim($_POST['new_name'] ?? '');
        $fullPath = getFullPath($target);
        if (isSafePath($fullPath) && file_exists($fullPath) && !empty($newName)) {
            $parentDir = dirname($fullPath);
            $newPath = $parentDir . DIRECTORY_SEPARATOR . $newName;
            // 安全过滤新名称：把路径分隔符和危险字符替换为下划线
            $safeName = preg_replace('/[\\\\\/]|[^\x{4e00}-\x{9fa5}a-zA-Z0-9._\-\[\]()（） ]/u', '_', $newName);
            $newPath = $parentDir . DIRECTORY_SEPARATOR . $safeName;
            if (!file_exists($newPath)) {
                rename($fullPath, $newPath);
                $message = '已重命名为：' . $safeName;
                clearDirCache($currentPath);
                clearStatsCache();
            } else {
                $message = '重命名失败：目标名称已存在';
                $messageType = 'error';
            }
        } else {
            $message = '重命名失败：参数无效';
            $messageType = 'error';
        }
    }

    // ── 复制所选到剪贴板 ──
    elseif (isset($_POST['copy_selected']) && hasPermission('files_move')) {
        $targets = isset($_POST['targets']) ? (array)$_POST['targets'] : array();
        $items = array();
        $names = array();
        foreach ($targets as $t) {
            $t = trim($t);
            if ($t === '') continue;
            $fp = getFullPath($t);
            if (isSafePath($fp) && file_exists($fp)) {
                $items[] = $t;
                $names[] = safeBasename($fp);
            }
        }
        if (empty($items)) {
            $message = '复制失败：未选中有效文件';
            $messageType = 'error';
        } else {
            $_SESSION['file_clipboard'] = array(
                'action' => 'copy',
                'items'  => $items,
                'names'  => $names,
                'time'   => time(),
            );
            $message = '已复制 ' . count($items) . ' 项，请到目标目录点击「粘贴到此处」';
        }
    }

    // ── 剪切所选到剪贴板 ──
    elseif (isset($_POST['cut_selected']) && hasPermission('files_move')) {
        $targets = isset($_POST['targets']) ? (array)$_POST['targets'] : array();
        $items = array();
        $names = array();
        foreach ($targets as $t) {
            $t = trim($t);
            if ($t === '') continue;
            $fp = getFullPath($t);
            if (isSafePath($fp) && file_exists($fp)) {
                $items[] = $t;
                $names[] = safeBasename($fp);
            }
        }
        if (empty($items)) {
            $message = '剪切失败：未选中有效文件';
            $messageType = 'error';
        } else {
            $_SESSION['file_clipboard'] = array(
                'action' => 'cut',
                'items'  => $items,
                'names'  => $names,
                'time'   => time(),
            );
            $message = '已剪切 ' . count($items) . ' 项，请到目标目录点击「粘贴到此处」完成移动';
        }
    }

    // ── 清空剪贴板 ──
    elseif (isset($_POST['clear_clipboard'])) {
        unset($_SESSION['file_clipboard']);
        $message = '已清空剪贴板';
    }

    // ── 粘贴（复制/移动）到目标目录 ──
    elseif (isset($_POST['paste']) && hasPermission('files_move')) {
        $clip = isset($_SESSION['file_clipboard']) ? $_SESSION['file_clipboard'] : null;

        if (empty($clip['items'])) {
            $message = '粘贴失败：剪贴板为空，请先复制或剪切文件';
            $messageType = 'error';
        } else {
            $destRel  = isset($_POST['dest_dir']) ? trim($_POST['dest_dir'], '/\\') : '';
            $destFull = getFullPath($destRel);

            if (!isSafePath($destFull) || !is_dir($destFull)) {
                $message = '粘贴失败：目标目录无效';
                $messageType = 'error';
            } else {
                $isCut = (isset($clip['action']) && $clip['action'] === 'cut');
                $successCount = 0;
                $failCount = 0;
                $failReasons = array();
                $destRealBase = realpath($destFull);

                foreach ($clip['items'] as $rel) {
                    $srcFull = getFullPath($rel);
                    if (!isSafePath($srcFull) || !file_exists($srcFull)) {
                        $failCount++;
                        continue;
                    }

                    $srcReal = realpath($srcFull);

                    // 禁止把目录移动/复制到它自己内部（会造成无限递归）
                    if (is_dir($srcFull) && $srcReal !== false && $destRealBase !== false
                        && ($destRealBase === $srcReal
                            || strpos($destRealBase . DIRECTORY_SEPARATOR, $srcReal . DIRECTORY_SEPARATOR) === 0)) {
                        $failCount++;
                        $failReasons[] = safeBasename($srcFull) . '：不能移动到自己或其子目录内';
                        continue;
                    }

                    $name    = basename($srcFull);
                    $dstFull = uniqueDestPath($destFull . DIRECTORY_SEPARATOR . $name);

                    if ($srcFull === $dstFull) {
                        $failCount++;
                        $failReasons[] = $name . '：源与目标相同';
                        continue;
                    }

                    $ok = $isCut ? movePathSafe($srcFull, $dstFull) : copyPathRecursive($srcFull, $dstFull);
                    if ($ok) {
                        $successCount++;
                    } else {
                        $failCount++;
                        $failReasons[] = $name . '：' . ($isCut ? '移动失败' : '复制失败');
                    }
                }

                $message = ($isCut ? '移动' : '复制') . "完成：成功 {$successCount} 个";
                if ($failCount > 0) {
                    $message .= "，失败 {$failCount} 个";
                    $messageType = 'error';
                    if (!empty($failReasons)) {
                        $message .= '（' . implode('；', array_slice($failReasons, 0, 3)) . '）';
                    }
                }

                // 剪切是一次性操作，完成后清空剪贴板
                if ($isCut) {
                    unset($_SESSION['file_clipboard']);
                }

                // 源目录与目标目录的缓存都要失效
                clearDirCache();
                clearStatsCache();
            }
        }
    }
}

// 扫描当前目录
$fullPath = getFullPath($currentPath);
$items = array();
if (is_dir($fullPath) && isSafePath($fullPath)) {
    $dh = opendir($fullPath);
    while (($file = readdir($dh)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $file = sysToUtf8($file);
        $fp = $fullPath . DIRECTORY_SEPARATOR . $file;
        if (is_link($fp)) continue;
        $relPath = empty($currentPath) ? $file : $currentPath . '/' . $file;
        $items[] = array(
            'name' => $file,
            'path' => $relPath,
            'type' => is_dir($fp) ? 'dir' : 'file',
            'size' => is_file($fp) ? filesize($fp) : 0,
            'mtime' => date('Y-m-d H:i', filemtime($fp)),
        );
    }
    closedir($dh);
}

// 排序：目录在前，按名称排序
usort($items, function ($a, $b) {
    if ($a['type'] !== $b['type']) return $a['type'] === 'dir' ? -1 : 1;
    return strcmp(strtolower($a['name']), strtolower($b['name']));
});

// 面包屑
$breadcrumbs = array(array('name' => '根目录', 'path' => ''));
if (!empty($currentPath)) {
    $parts = explode('/', $currentPath);
    $cumPath = '';
    foreach ($parts as $part) {
        $cumPath .= ($cumPath === '' ? '' : '/') . $part;
        $breadcrumbs[] = array('name' => $part, 'path' => $cumPath);
    }
}

// 剪贴板状态（复制/剪切）
$clipboard = isset($_SESSION['file_clipboard']) ? $_SESSION['file_clipboard'] : null;

// 递归目录列表（粘贴目标下拉使用，含二级及更深目录）
// 仅在剪贴板非空时才扫描，避免目录树很大时每次进页面都全量递归
$allDirs = (!empty($clipboard['items']) && hasPermission('files_move')) ? getAllDirectories() : array();

// 是否显示复选框：拥有删除或移动/复制任一权限即可勾选
$canSelect = hasPermission('files_delete') || hasPermission('files_move');
?>
<?php require __DIR__ . '/layout.php'; ?>

        <div class="content-box">
            <div class="file-manager-toolbar">
                <a href="files.php" class="btn-primary" style="text-decoration:none;">📁 根目录</a>
                <?php if (!empty($currentPath)): ?>
                    <a href="?path=<?php echo urlencode(dirname($currentPath) === '.' ? '' : dirname($currentPath)); ?>" class="btn-primary" style="text-decoration:none;">⬆ 上级目录</a>
                <?php endif; ?>
                <?php if (hasPermission('files_rename')): ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('将递归扫描当前目录，把文件名中的 \\ 和 / 替换为 _。确定继续？');">
                    <button type="submit" name="fix_illegal_names" class="btn-sm btn-edit">🔧 修复非法文件名</button>
                </form>
                <?php endif; ?>
                <?php if ($canSelect): ?>
                <span class="bulk-bar" id="bulkBar" style="display:none;">
                    <span class="bulk-count" id="bulkCount">已选 0 项</span>
                    <?php if (hasPermission('files_move')): ?>
                    <button type="button" class="btn-sm btn-edit" id="bulkCopyBtn">📋 复制</button>
                    <button type="button" class="btn-sm btn-edit" id="bulkCutBtn">✂ 剪切</button>
                    <?php endif; ?>
                    <?php if (hasPermission('files_delete')): ?>
                    <button type="button" class="btn-danger" id="bulkDeleteBtn">🗑 批量删除</button>
                    <?php endif; ?>
                    <button type="button" class="btn-sm btn-edit" id="bulkClearBtn">取消选择</button>
                </span>
                <?php endif; ?>
            </div>

            <?php if (!empty($clipboard['items']) && hasPermission('files_move')): ?>
            <div class="clipboard-bar">
                <span class="clipboard-info">
                    <?php echo ($clipboard['action'] === 'cut') ? '✂ 已剪切' : '📋 已复制'; ?>
                    <strong><?php echo count($clipboard['items']); ?></strong> 项：
                    <?php
                        $shown = array_slice($clipboard['names'], 0, 3);
                        echo htmlspecialchars(implode('、', $shown));
                        echo (count($clipboard['names']) > 3) ? ' 等' : '';
                    ?>
                </span>
                <form method="post" style="display:inline-flex; align-items:center; gap:8px;">
                    <input type="text" id="destDirFilter" placeholder="搜索目录..." style="padding:6px 10px; border:1px solid #ccc; border-radius:6px; font-size:13px; width:150px;">
                    <select name="dest_dir" id="dest_dir" title="选择粘贴的目标目录">
                        <option value="<?php echo htmlspecialchars($currentPath); ?>">📍 当前目录（<?php echo htmlspecialchars($currentPath ?: '根目录'); ?>）</option>
                        <?php foreach ($allDirs as $dir): ?>
                            <?php if ($dir['path'] === $currentPath) continue; ?>
                            <option value="<?php echo htmlspecialchars($dir['path']); ?>"><?php echo htmlspecialchars(isset($dir['label']) ? $dir['label'] : $dir['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="paste" class="btn-primary">📥 粘贴到此处</button>
                </form>
                <form method="post" style="display:inline;">
                    <button type="submit" name="clear_clipboard" class="btn-sm btn-edit">清空剪贴板</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="breadcrumbs" style="margin-bottom:15px;">
                <ul>
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($i === count($breadcrumbs) - 1): ?>
                            <li class="current"><?php echo htmlspecialchars($crumb['name']); ?></li>
                        <?php else: ?>
                            <li><a href="?path=<?php echo urlencode($crumb['path']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a></li>
                            <li class="separator">/</li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if ($message): ?>
                <div class="msg-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <table class="file-admin-table">
                <thead>
                    <tr>
                        <?php if ($canSelect): ?>
                        <th style="width:40px;"><input type="checkbox" id="selectAll" title="全选/取消"></th>
                        <?php endif; ?>
                        <th class="file-name-col">名称</th>
                        <th style="width:110px;">大小</th>
                        <th style="width:170px;">修改时间</th>
                        <th style="width:195px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="<?php echo $canSelect ? 5 : 4; ?>" style="text-align:center;color:#999;padding:30px;">此目录为空</td></tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <tr class="file-row">
                            <?php if ($canSelect): ?>
                            <td style="text-align:center;"><input type="checkbox" class="item-check" data-path="<?php echo htmlspecialchars($item['path']); ?>" data-name="<?php echo htmlspecialchars($item['name']); ?>"></td>
                            <?php endif; ?>
                            <td class="file-name-td">
                                <?php if ($item['type'] === 'dir'): ?>
                                    <a class="file-admin-name file-admin-name-dir" href="?path=<?php echo urlencode($item['path']); ?>">📁 <?php echo htmlspecialchars($item['name']); ?>/</a>
                                <?php else: ?>
                                    <span class="file-admin-name file-admin-name-file">📄 <?php echo htmlspecialchars($item['name']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:13px;color:#888;"><?php echo $item['type'] === 'dir' ? '-' : formatSize($item['size']); ?></td>
                            <td style="font-size:13px;color:#888;"><?php echo $item['mtime']; ?></td>
                            <td class="file-actions">
                                <?php if (hasPermission('files_rename')): ?>
                                <button type="button" class="btn-sm btn-edit" onclick="openRename('<?php echo htmlspecialchars($item['path']); ?>', '<?php echo htmlspecialchars($item['name']); ?>')">✏ 重命名</button>
                                <?php endif; ?>
                                <?php if (hasPermission('files_delete')): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('确定删除「<?php echo htmlspecialchars($item['name']); ?>」？<?php echo $item['type'] === 'dir' ? ' 目录内所有文件将被删除！' : ''; ?>')">
                                    <input type="hidden" name="target" value="<?php echo htmlspecialchars($item['path']); ?>">
                                    <button type="submit" name="delete" class="btn-sm btn-del">🗑 删除</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="dir-info">
                当前目录：<?php echo htmlspecialchars($currentPath ?: '根目录'); ?>
            </div>
        </div>
    </div>

    <!-- 批量删除隐藏表单 -->
    <form method="post" id="bulkDeleteForm">
        <input type="hidden" name="bulk_delete" value="1">
        <div id="bulkTargetsContainer"></div>
    </form>

    <!-- 复制/剪切隐藏表单 -->
    <?php if (hasPermission('files_move')): ?>
    <form method="post" id="bulkActionForm">
        <div id="bulkActionInputs"></div>
        <div id="bulkActionTargets"></div>
    </form>
    <?php endif; ?>

    <!-- 重命名弹窗 -->
    <div class="modal-overlay" id="renameModal" style="display:none;">
        <div class="modal-box">
            <div class="modal-header">
                <h4>重命名文件</h4>
                <button type="button" class="modal-close" onclick="closeRename()">×</button>
            </div>
            <form method="post" id="renameForm">
                <input type="hidden" name="target" id="rename_target">
                <div class="form-item">
                    <label for="rename_new_name">新名称</label>
                    <input type="text" id="rename_new_name" name="new_name" placeholder="输入新名称" required autofocus>
                </div>
                <button type="submit" name="rename" class="btn-primary">确认重命名</button>
            </form>
        </div>
    </div>

    <script>
    function openRename(path, name) {
        document.getElementById('rename_target').value = path;
        document.getElementById('rename_new_name').value = name;
        document.getElementById('renameModal').style.display = 'flex';
        setTimeout(function () {
            document.getElementById('rename_new_name').focus();
            document.getElementById('rename_new_name').select();
        }, 100);
    }
    function closeRename() {
        document.getElementById('renameModal').style.display = 'none';
    }
    document.getElementById('renameModal').addEventListener('click', function(e) {
        if (e.target === this) closeRename();
    });

    // 批量选择相关
    <?php if ($canSelect): ?>
    var selectAll = document.getElementById('selectAll');
    var itemChecks = document.querySelectorAll('.item-check');
    var bulkBar = document.getElementById('bulkBar');
    var bulkCount = document.getElementById('bulkCount');
    var bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    var bulkClearBtn = document.getElementById('bulkClearBtn');
    var bulkTargetsContainer = document.getElementById('bulkTargetsContainer');
    var bulkDeleteForm = document.getElementById('bulkDeleteForm');

    function updateBulkBar() {
        var checked = document.querySelectorAll('.item-check:checked');
        var count = checked.length;
        if (count > 0) {
            bulkBar.style.display = 'inline-flex';
            bulkCount.textContent = '已选 ' + count + ' 项';
        } else {
            bulkBar.style.display = 'none';
        }
        // 同步全选状态
        if (itemChecks.length > 0) {
            selectAll.checked = (count === itemChecks.length);
            selectAll.indeterminate = (count > 0 && count < itemChecks.length);
        }
    }

    selectAll.addEventListener('change', function() {
        itemChecks.forEach(function(cb) { cb.checked = selectAll.checked; });
        updateBulkBar();
    });

    itemChecks.forEach(function(cb) {
        cb.addEventListener('change', updateBulkBar);
    });

    bulkClearBtn.addEventListener('click', function() {
        itemChecks.forEach(function(cb) { cb.checked = false; });
        selectAll.checked = false;
        selectAll.indeterminate = false;
        updateBulkBar();
    });

    bulkDeleteBtn.addEventListener('click', function() {
        var checked = document.querySelectorAll('.item-check:checked');
        if (checked.length === 0) return;
        var names = [];
        checked.forEach(function(cb) { names.push(cb.dataset.name); });
        var msg = '确定批量删除以下 ' + checked.length + ' 个文件/目录？\n\n• ' + names.join('\n• ');
        msg += '\n\n目录内的所有文件都将被删除！此操作不可撤销！';
        if (!confirm(msg)) return;

        // 构建隐藏 input
        bulkTargetsContainer.innerHTML = '';
        checked.forEach(function(cb) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'targets[]';
            input.value = cb.dataset.path;
            bulkTargetsContainer.appendChild(input);
        });
        bulkDeleteForm.submit();
    });
    <?php endif; ?>

    <?php if (hasPermission('files_move')): ?>
    // 复制 / 剪切到剪贴板
    var bulkActionForm = document.getElementById('bulkActionForm');
    var bulkActionInputs = document.getElementById('bulkActionInputs');
    var bulkActionTargets = document.getElementById('bulkActionTargets');

    function submitBulkAction(actionName) {
        var checked = document.querySelectorAll('.item-check:checked');
        if (checked.length === 0) return;

        bulkActionInputs.innerHTML = '';
        bulkActionTargets.innerHTML = '';

        var act = document.createElement('input');
        act.type = 'hidden';
        act.name = actionName;
        act.value = '1';
        bulkActionInputs.appendChild(act);

        checked.forEach(function (cb) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'targets[]';
            input.value = cb.dataset.path;
            bulkActionTargets.appendChild(input);
        });

        bulkActionForm.submit();
    }

    document.getElementById('bulkCopyBtn').addEventListener('click', function () {
        submitBulkAction('copy_selected');
    });

    document.getElementById('bulkCutBtn').addEventListener('click', function () {
        var checked = document.querySelectorAll('.item-check:checked');
        if (checked.length === 0) return;
        if (!confirm('剪切后需到目标目录点击「粘贴到此处」才会真正移动。\n\n确定剪切这 ' + checked.length + ' 项？')) return;
        submitBulkAction('cut_selected');
    });
    <?php endif; ?>

    // 粘贴目标目录搜索过滤
    attachSelectFilter('destDirFilter', 'dest_dir');
    </script>
</body>
</html>