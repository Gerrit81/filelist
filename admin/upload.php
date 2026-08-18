<?php
require_once __DIR__ . '/init.php';
requirePermission('upload');

$pageTitle = '📤 文件上传';
$message = '';
$messageType = 'success';

function getDirectories($path = '') {
    $fullPath = getFullPath($path);
    if (!is_dir($fullPath)) {
        return array();
    }

    $dirs = array();
    $dh = opendir($fullPath);

    while (($file = readdir($dh)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $file = sysToUtf8($file);
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath) && !is_link($filePath)) {
            $dirs[] = array(
                'name' => $file,
                'path' => empty($path) ? $file : $path . '/' . $file
            );
        }
    }

    closedir($dh);
    sort($dirs);

    return $dirs;
}

// ── AJAX 上传处理器 ──
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['files'])) {
    verifyCsrf();
    header('Content-Type: application/json; charset=utf-8');

    $dataDir = getConfig('data_dir');
    $subDir = isset($_POST['sub_dir']) ? trim($_POST['sub_dir']) : '';
    $targetDir = $dataDir;

    if (!empty($subDir)) {
        // 检查用户是否有权限上传到此目录
        $userFolders = currentUserAllowedFolders();
        if (!empty($userFolders)) {
            $allowed = false;
            foreach ($userFolders as $af) {
                if ($subDir === trim($af) || strpos($subDir, trim($af) . '/') === 0) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                echo json_encode(array(
                    'success' => false,
                    'successCount' => 0,
                    'failCount' => 0,
                    'results' => array(),
                    'message' => '无权上传到目录：' . $subDir
                ), JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        $targetDir = $targetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subDir);
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        // 安全校验：确保最终路径在 data_dir 内
        if (!isSafePath($targetDir)) {
            echo json_encode(array(
                'success' => false,
                'successCount' => 0,
                'failCount' => 0,
                'results' => array(),
                'message' => '非法上传路径'
            ), JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $results = array();
    $successCount = 0;
    $failCount = 0;

    foreach ($_FILES['files']['tmp_name'] as $key => $tmpName) {
        $fileName = $_FILES['files']['name'][$key];
        $fileError = $_FILES['files']['error'][$key];
        $fileSize = $_FILES['files']['size'][$key];

        if ($fileError === UPLOAD_ERR_OK) {
            // 外网模式：文件类型安全检查
            if (isInternetMode()) {
                $nameCheck = validateUploadFilename($fileName);
                if (!$nameCheck[0]) {
                    $results[] = array('name' => $fileName, 'size' => $fileSize, 'status' => 'failed', 'reason' => $nameCheck[1]);
                    $failCount++;
                    continue;
                }
                // 外网模式：文件大小限制
                $maxMb = (int)(getConfig('upload_max_size_mb') ?? 100);
                if ($maxMb > 0 && $fileSize > $maxMb * 1048576) {
                    $results[] = array('name' => $fileName, 'size' => $fileSize, 'status' => 'failed', 'reason' => '超过外网上传限制（' . $maxMb . ' MB）');
                    $failCount++;
                    continue;
                }
            }
            // 检查用户上传大小限制
            $userLimit = currentUserMaxUpload();
            if ($userLimit > 0 && $fileSize > $userLimit) {
                $results[] = array('name' => $fileName, 'size' => $fileSize, 'status' => 'failed', 'reason' => '超过用户上传限制（' . formatSize($userLimit) . '）');
                $failCount++;
                continue;
            }

            // 安全文件名：把路径分隔符和危险字符替换为下划线
            $safeName = preg_replace('/[\\\\\/]|[^\x{4e00}-\x{9fa5}a-zA-Z0-9._\-\[\]()（）]/u', '_', $fileName);
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $safeName;

            // 处理同名文件
            $counter = 1;
            $pathInfo = pathinfo($safeName);
            $baseName = $pathInfo['filename'];
            $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            while (file_exists($targetPath)) {
                $safeName = $baseName . '_(' . $counter . ')' . $ext;
                $targetPath = $targetDir . DIRECTORY_SEPARATOR . $safeName;
                $counter++;
            }

            if (move_uploaded_file($tmpName, $targetPath)) {
                $results[] = array('name' => $safeName, 'size' => $fileSize, 'status' => 'success');
                $successCount++;
                // 清除该目录缓存
                clearDirCache($subDir);
                clearStatsCache();
            } else {
                $results[] = array('name' => $fileName, 'size' => $fileSize, 'status' => 'failed', 'reason' => '移动文件失败');
                $failCount++;
            }
        } else {
            $errorMsg = '未知错误';
            switch ($fileError) {
                case UPLOAD_ERR_INI_SIZE: $errorMsg = '文件超过 php.ini 限制'; break;
                case UPLOAD_ERR_FORM_SIZE: $errorMsg = '文件超过表单限制'; break;
                case UPLOAD_ERR_PARTIAL: $errorMsg = '文件上传不完整'; break;
                case UPLOAD_ERR_NO_FILE: $errorMsg = '未选择文件'; break;
                case UPLOAD_ERR_NO_TMP_DIR: $errorMsg = '缺少临时目录'; break;
                case UPLOAD_ERR_CANT_WRITE: $errorMsg = '无法写入磁盘'; break;
            }
            $results[] = array('name' => $fileName, 'size' => $fileSize, 'status' => 'failed', 'reason' => $errorMsg);
            $failCount++;
        }
    }

    if ($successCount > 0) {
        auditLog('upload', "上传 {$successCount} 个文件到目录: " . ($subDir ?: '/'));
    }

    echo json_encode(array(
        'success' => ($failCount === 0),
        'successCount' => $successCount,
        'failCount' => $failCount,
        'results' => $results,
        'message' => "上传完成：成功 {$successCount} 个" . ($failCount > 0 ? "，失败 {$failCount} 个" : '')
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 常规 POST（创建文件夹）──
$allDirs = getDirectories();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_folder'])) {
    verifyCsrf();
    $dataDir = getConfig('data_dir');
    $folderName = trim($_POST['folder_name']);
    $folderParent = isset($_POST['folder_parent']) ? trim($_POST['folder_parent']) : '';

    if (!empty($folderName)) {
        $safeName = preg_replace('/[\\\\\/]|[^\x{4e00}-\x{9fa5}a-zA-Z0-9._\-\[\]()（）]/u', '_', $folderName);
        $fullFolderPath = $dataDir;
        if (!empty($folderParent)) {
            $fullFolderPath = $fullFolderPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folderParent);
        }
        $fullFolderPath = $fullFolderPath . DIRECTORY_SEPARATOR . $safeName;

        // 校验父目录在 data_dir 内（新文件夹不存在，不能用 realpath）
        $realDataDir = realpath($dataDir);
        $realParent = realpath(dirname($fullFolderPath));
        if ($realParent === false || strpos($realParent, $realDataDir) !== 0) {
            $message = '非法路径';
            $messageType = 'error';
        } elseif (!file_exists($fullFolderPath)) {
            mkdir($fullFolderPath, 0755, true);
            $message = "文件夹 \"{$safeName}\" 创建成功";
            $allDirs = getDirectories();
            clearDirCache($folderParent);
            clearStatsCache();
        } else {
            $message = '文件夹已存在';
            $messageType = 'error';
        }
    } else {
        $message = '请输入文件夹名称';
        $messageType = 'error';
    }
}

// 大头文件限制 + 文件夹过滤
$postMaxSize = ini_get('post_max_size');
$uploadMaxFilesize = ini_get('upload_max_filesize');
$userLimit = currentUserMaxUpload();
$userFolders = currentUserAllowedFolders();

// 过滤可选目录
$availableDirs = array();
if (empty($userFolders)) {
    // 管理员或未设限制：显示全部目录
    $availableDirs = $allDirs;
} else {
    // 操作员：只显示允许的目录（含子目录匹配）
    foreach ($allDirs as $dir) {
        foreach ($userFolders as $uf) {
            $uf = trim($uf);
            if ($dir['path'] === $uf || strpos($dir['path'], $uf . '/') === 0) {
                $availableDirs[] = $dir;
                break;
            }
        }
    }
}
?>
<?php require __DIR__ . '/layout.php'; ?>

        <div class="content-box">
            <h3>上传文件到数据目录</h3>

            <?php if ($message): ?>
                <div class="msg-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div id="uploadMsg" class="upload-msg" style="display:none;"></div>

            <div class="form-item">
                <label for="sub_dir">目标目录</label>
                <select id="sub_dir" name="sub_dir">
                    <option value="">根目录</option>
                    <?php foreach ($availableDirs as $dir): ?>
                        <option value="<?php echo htmlspecialchars($dir['path']); ?>"><?php echo htmlspecialchars($dir['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="drop-zone" id="dropZone">
                <div class="folder-icon">📁</div>
                <p>点击或拖拽文件到此处选择</p>
                <p style="font-size: 12px; color: #999;">
                    <?php if ($userLimit > 0): ?>
                        您的上传限制：<?php echo formatSize($userLimit); ?>/文件
                    <?php elseif (hasPermission('roles')): ?>
                        管理员 — 无上传大小限制
                    <?php else: ?>
                        无上传大小限制
                    <?php endif; ?>
                    <br>服务器限制：单文件 <?php echo htmlspecialchars($uploadMaxFilesize); ?>，表单 <?php echo htmlspecialchars($postMaxSize); ?>
                </p>
                <input type="file" name="files[]" id="fileInput" multiple>
            </div>

            <!-- 文件列表 -->
            <div class="file-list-wrap" id="fileListWrap" style="display:none;">
                <div class="file-list-header">
                    <span>已选文件（<em id="fileCount">0</em> 个）</span>
                    <span class="file-list-total" id="fileTotalSize"></span>
                    <button type="button" class="btn-link" id="clearFiles">清空列表</button>
                </div>
                <ul class="file-list" id="fileList"></ul>
            </div>

            <!-- 进度条 -->
            <div class="progress-wrap" id="progressWrap" style="display:none;">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="progress-text">
                    <span id="progressPercent">0%</span>
                    <span id="progressStatus">准备上传...</span>
                </div>
            </div>

            <button type="button" class="btn-primary btn-upload" id="uploadBtn" disabled>开始上传</button>

            <div class="upload-result" id="uploadResult" style="display:none;"></div>

            <div class="dir-info">
                当前数据目录：<?php echo htmlspecialchars(getConfig('data_dir')); ?>
            </div>

            <h3 class="divider">创建文件夹</h3>

            <form method="post">
                <div class="form-item">
                    <label for="folder_name">文件夹名称</label>
                    <input type="text" id="folder_name" name="folder_name" placeholder="输入文件夹名称">
                </div>

                <div class="form-item">
                    <label for="folder_parent">父目录</label>
                    <select id="folder_parent" name="folder_parent">
                        <option value="">根目录</option>
                        <?php foreach ($allDirs as $dir): ?>
                            <option value="<?php echo htmlspecialchars($dir['path']); ?>"><?php echo htmlspecialchars($dir['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="create_folder" class="btn-primary">创建文件夹</button>
            </form>
        </div>
    </div>

    <script>
    (function () {
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const fileListWrap = document.getElementById('fileListWrap');
        const fileList = document.getElementById('fileList');
        const fileCount = document.getElementById('fileCount');
        const fileTotalSize = document.getElementById('fileTotalSize');
        const clearBtn = document.getElementById('clearFiles');
        const uploadBtn = document.getElementById('uploadBtn');
        const progressWrap = document.getElementById('progressWrap');
        const progressFill = document.getElementById('progressFill');
        const progressPercent = document.getElementById('progressPercent');
        const progressStatus = document.getElementById('progressStatus');
        const uploadResult = document.getElementById('uploadResult');
        const uploadMsg = document.getElementById('uploadMsg');
        const subDir = document.getElementById('sub_dir');

        let selectedFiles = [];

        function formatSize(bytes) {
            if (bytes === 0) return '0 B';
            var units = ['B', 'KB', 'MB', 'GB', 'TB'];
            var i = Math.floor(Math.log(bytes) / Math.log(1024));
            return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
        }

        function renderFileList() {
            fileList.innerHTML = '';
            var totalSize = 0;
            selectedFiles.forEach(function (file, index) {
                totalSize += file.size;
                var li = document.createElement('li');
                li.className = 'file-item';
                li.innerHTML =
                    '<span class="file-item-name" title="' + file.name + '">' + file.name + '</span>' +
                    '<span class="file-item-size">' + formatSize(file.size) + '</span>' +
                    '<button type="button" class="file-item-remove" data-index="' + index + '" title="移除">×</button>';
                fileList.appendChild(li);
            });
            fileCount.textContent = selectedFiles.length;
            fileTotalSize.textContent = '共 ' + formatSize(totalSize);
            fileListWrap.style.display = selectedFiles.length > 0 ? 'block' : 'none';
            uploadBtn.disabled = selectedFiles.length === 0;
        }

        function setFiles(files) {
            selectedFiles = [];
            for (var i = 0; i < files.length; i++) {
                selectedFiles.push({ name: files[i].name, size: files[i].size, file: files[i] });
            }
            renderFileList();
        }

        function clearFiles() {
            selectedFiles = [];
            fileInput.value = '';
            renderFileList();
            hideProgress();
        }

        function hideProgress() {
            progressWrap.style.display = 'none';
            progressFill.style.width = '0%';
            progressPercent.textContent = '0%';
            progressStatus.textContent = '准备上传...';
        }

        function showMsg(text, type) {
            uploadMsg.textContent = text;
            uploadMsg.className = 'upload-msg msg-' + type;
            uploadMsg.style.display = 'block';
        }
        function hideMsg() {
            uploadMsg.style.display = 'none';
        }

        // ── 事件绑定 ──

        dropZone.addEventListener('click', function () { fileInput.click(); });

        dropZone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', function () {
            dropZone.classList.remove('drag-over');
        });

        dropZone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            if (e.dataTransfer.files.length > 0) {
                setFiles(e.dataTransfer.files);
            }
        });

        fileInput.addEventListener('change', function () {
            if (fileInput.files.length > 0) {
                // 合并已有和新选的文件（追加模式）
                var existing = {};
                selectedFiles.forEach(function (f) { existing[f.name] = true; });
                var newFiles = [];
                for (var i = 0; i < fileInput.files.length; i++) {
                    var f = fileInput.files[i];
                    if (!existing[f.name]) {
                        newFiles.push(f);
                    }
                }
                // 用 DataTransfer 重建完整列表
                var dt = new DataTransfer();
                selectedFiles.forEach(function (f) { dt.items.add(f.file); });
                newFiles.forEach(function (f) { dt.items.add(f); });
                setFiles(dt.files);
            }
        });

        clearBtn.addEventListener('click', function () { clearFiles(); });

        document.getElementById('fileList').addEventListener('click', function (e) {
            if (e.target.classList.contains('file-item-remove')) {
                var idx = parseInt(e.target.dataset.index);
                selectedFiles.splice(idx, 1);
                // 同步回 fileInput
                var dt = new DataTransfer();
                selectedFiles.forEach(function (f) { dt.items.add(f.file); });
                fileInput.files = dt.files;
                renderFileList();
            }
        });

        // ── 上传逻辑 ──

        uploadBtn.addEventListener('click', function () {
            if (selectedFiles.length === 0) return;
            uploadBtn.disabled = true;
            hideMsg();
            uploadResult.style.display = 'none';

            var formData = new FormData();
            selectedFiles.forEach(function (f) {
                formData.append('files[]', f.file);
            });
            formData.append('sub_dir', subDir.value);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'upload.php', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            progressWrap.style.display = 'block';
            progressStatus.textContent = '正在上传...';

            xhr.upload.addEventListener('progress', function (e) {
                if (e.lengthComputable) {
                    var pct = Math.round((e.loaded / e.total) * 100);
                    progressFill.style.width = pct + '%';
                    progressPercent.textContent = pct + '%';
                    progressStatus.textContent = '已上传 ' + formatSize(e.loaded) + ' / ' + formatSize(e.total);
                }
            });

            xhr.addEventListener('load', function () {
                if (xhr.status === 200) {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.success) {
                            progressFill.style.width = '100%';
                            progressPercent.textContent = '100%';
                            progressStatus.textContent = resp.message;
                            showMsg(resp.message, 'success');

                            // 显示结果详情
                            var html = '<strong>上传结果：</strong><ul class="result-list">';
                            resp.results.forEach(function (r) {
                                var icon = r.status === 'success' ? '✅' : '❌';
                                var reason = r.reason ? ' - ' + r.reason : '';
                                html += '<li class="result-' + r.status + '">' + icon + ' ' + r.name + ' (' + formatSize(r.size) + ')' + reason + '</li>';
                            });
                            html += '</ul>';
                            uploadResult.innerHTML = html;
                            uploadResult.style.display = 'block';

                            // 全部成功后清空
                            if (resp.failCount === 0) {
                                selectedFiles = [];
                                fileInput.value = '';
                                renderFileList();
                            }
                        } else {
                            progressStatus.textContent = resp.message;
                            showMsg(resp.message, 'error');
                            uploadResult.innerHTML = '<p style="color:#c0392b;">' + resp.message + '</p>';
                            uploadResult.style.display = 'block';
                        }
                    } catch (e) {
                        showMsg('服务器返回解析失败', 'error');
                    }
                } else {
                    showMsg('上传失败：HTTP ' + xhr.status, 'error');
                }
                uploadBtn.disabled = false;
            });

            xhr.addEventListener('error', function () {
                showMsg('网络错误，上传失败', 'error');
                progressStatus.textContent = '网络错误';
                uploadBtn.disabled = false;
            });

            xhr.send(formData);
        });
    })();
    </script>
</body>
</html>