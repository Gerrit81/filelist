<?php
require_once 'functions.php';

initDirectories();

$sessionDir = getConfig('session_dir');
session_save_path($sessionDir);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();

function serveFile($filePath, $download = false) {
    if (!isSafePath($filePath)) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    
    $mime = getMimeType($filePath);
    $size = filesize($filePath);
    $basename = safeBasename($filePath);

    // 预览文本文件时，检测编码并转为 UTF-8（解决 GBK/GB2312 乱码）
    $isTextPreview = !$download && strpos($mime, 'text/') === 0;
    
    if ($download) {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $size);
        header('Content-Disposition: attachment; filename="' . urlencode($basename) . '"');
        header('Content-Transfer-Encoding: binary');
        // 下载也支持断点续传（Range 请求）
        if (isset($_SERVER['HTTP_RANGE'])) {
            serveRangeResponse($filePath, $mime, $size);
            exit;
        }
        
        $relativePath = getRelativePath($filePath);
        logDownload($relativePath, $basename);
        
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            http_response_code(500);
            echo 'Cannot open file';
            exit;
        }
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
        exit;
    }

    if ($isTextPreview && $size > 0) {
        // 读取文件内容并检测编码
        $content = file_get_contents($filePath);
        if ($content === false) {
            http_response_code(500);
            echo 'Cannot open file';
            exit;
        }

        // 检测编码（UTF-8 BOM 优先，然后自动检测）
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        } elseif (extension_loaded('mbstring')) {
            $detected = mb_detect_encoding($content, array('UTF-8', 'GBK', 'GB2312', 'GB18030', 'BIG5', 'EUC-CN', 'ASCII'), true);
            if ($detected && $detected !== 'UTF-8' && $detected !== 'ASCII') {
                $content = mb_convert_encoding($content, 'UTF-8', $detected);
            }
        }

        header('Content-Type: ' . $mime . '; charset=utf-8');
        header('Content-Length: ' . strlen($content));
        header('Content-Disposition: inline; filename="' . urlencode($basename) . '"');
        echo $content;
        exit;
    }

    // 非文本文件预览：支持 Range 请求（音视频 seek 依赖此功能）
    if (isset($_SERVER['HTTP_RANGE'])) {
        serveRangeResponse($filePath, $mime, $size);
        exit;
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $size);
    header('Accept-Ranges: bytes');
    header('Content-Disposition: inline; filename="' . urlencode($basename) . '"');

    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        http_response_code(500);
        echo 'Cannot open file';
        exit;
    }
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    fclose($handle);
    exit;
}

/**
 * 处理 HTTP Range 请求，返回 206 Partial Content 响应。
 * 浏览器播放音视频必须依赖此功能来 seek/缓冲，否则进度条无法拖动。
 */
function serveRangeResponse($filePath, $mime, $size) {
    $rangeHeader = $_SERVER['HTTP_RANGE'];

    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');

    // 解析 Range 格式: "bytes=start-end" 或 "bytes=start-"
    if (preg_match('/bytes\s*=\s*(\d+)\s*-\s*(\d*)/i', $rangeHeader, $matches)) {
        $start = (int)$matches[1];
        $end   = isset($matches[2]) && $matches[2] !== '' ? (int)$matches[2] : $size - 1;

        // 修正越界
        if ($start >= $size) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header('Content-Range: bytes */' . $size);
            exit;
        }
        if ($end >= $size) {
            $end = $size - 1;
        }
    } else {
        // 无法解析 → 回退为完整响应
        header('Content-Length: ' . $size);
        readfile($filePath);
        exit;
    }

    $length = $end - $start + 1;

    header('HTTP/1.1 206 Partial Content');
    header('Content-Length: ' . $length);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    header('Content-Disposition: inline');

    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        http_response_code(500);
        exit;
    }
    if ($start > 0) {
        fseek($handle, $start);
    }

    $bufferSize = 8192;
    $remaining = $length;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = min($bufferSize, $remaining);
        echo fread($handle, $chunk);
        flush();
        $remaining -= $chunk;
    }
    fclose($handle);
    exit;
}

function serveThumbnail($filePath) {
    if (!isSafePath($filePath)) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    
    $thumbPath = getThumbnail($filePath);
    
    if (!$thumbPath || !file_exists($thumbPath)) {
        http_response_code(404);
        echo 'Thumbnail not available';
        exit;
    }
    
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($thumbPath));
    readfile($thumbPath);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$path = isset($_GET['path']) ? $_GET['path'] : '';

switch ($action) {
    case 'list':
        $files = scanDirectoryCached($path);
        header('Content-Type: application/json');
        header('Cache-Control: private, max-age=5');
        echo json_encode($files);
        exit;

    case 'search':
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        if (mb_strlen($keyword) < 1) {
            header('Content-Type: application/json');
            echo json_encode([]);
            exit;
        }
        $results = recursiveSearch($keyword);
        header('Content-Type: application/json');
        header('Cache-Control: private, max-age=10');
        echo json_encode($results);
        exit;
    
    case 'preview':
        serveFile(getFullPath($path), false);
        exit;
    
    case 'download':
        serveFile(getFullPath($path), true);
        exit;
    
    case 'thumbnail':
        serveThumbnail(getFullPath($path));
        exit;

    case 'office_preview':
        $fullPath = getFullPath($path);
        if (!isSafePath($fullPath)) { http_response_code(403); echo 'Access denied'; exit; }
        if (!file_exists($fullPath)) { http_response_code(404); echo 'File not found'; exit; }
        $mode = getConfig('office_preview_mode') ?: 'off';

        // 自定义 API 模式 → 302 跳转
        if ($mode === 'custom') {
            $api = getConfig('office_preview_api');
            if (!empty($api)) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                $fileUrl = $scheme . '://' . $host . $scriptDir . '/?action=download&path=' . urlencode($path);
                $previewUrl = str_replace('{url}', urlencode($fileUrl), $api);
                header('Location: ' . $previewUrl);
                exit;
            }
            http_response_code(503);
            echo 'Office preview API not configured';
            exit;
        }

        // LibreOffice 模式
        if ($mode === 'libreoffice') {
            $soffice = getConfig('libreoffice_path');
            if (empty($soffice)) {
                // 尝试常见安装路径
                $candidates = [
                    '"C:\Program Files\LibreOffice\program\soffice.exe"',
                    '"C:\Program Files (x86)\LibreOffice\program\soffice.exe"',
                    'libreoffice',
                    'soffice',
                ];
                foreach ($candidates as $c) {
                    $test = str_replace('"', '', $c);
                    if (file_exists($test)) {
                        $soffice = $c;
                        break;
                    }
                }
                if (empty($soffice)) {
                    http_response_code(503);
                    echo 'LibreOffice not found. Please configure the path in settings.';
                    exit;
                }
            }

            $cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'office';
            if (!file_exists($cacheDir)) { mkdir($cacheDir, 0755, true); }
            $cacheKey = md5($fullPath . '|' . filemtime($fullPath)) . '.pdf';
            $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey;

            if (!file_exists($cacheFile)) {
                // 清理旧缓存（同一文件修改时间变了会生成新 key）
                $oldPattern = md5($fullPath) . '_';
                // 不做复杂清理，依赖 hash+key 自然过期

                // 去除可能已有的引号，再用 escapeshellarg 统一处理，避免含空格路径解析失败
                $sofficeClean = str_replace('"', '', $soffice);
                $cmd = escapeshellarg($sofficeClean) . ' --headless --convert-to pdf --outdir ' . escapeshellarg($cacheDir) . ' ' . escapeshellarg($fullPath) . ' 2>&1';
                exec($cmd, $output, $returnCode);

                // 转换失败的详细错误信息写入日志文件方便排查
                if ($returnCode !== 0 || !file_exists($cacheDir . DIRECTORY_SEPARATOR . pathinfo($fullPath, PATHINFO_FILENAME) . '.pdf')) {
                    $logMsg = date('[Y-m-d H:i:s]') . " Office conversion failed (rc={$returnCode})\n";
                    $logMsg .= '  File: ' . $fullPath . "\n";
                    $logMsg .= '  Cmd:  ' . str_replace('2>&1', '', $cmd) . "\n";
                    $logMsg .= '  Out:  ' . (!empty($output) ? implode("\n        ", $output) : '(empty)') . "\n\n";
                    file_put_contents($cacheDir . DIRECTORY_SEPARATOR . 'error.log', $logMsg, FILE_APPEND);
                }

                // LibreOffice 按原始文件名输出 → 重命名
                $outputName = $cacheDir . DIRECTORY_SEPARATOR . pathinfo($fullPath, PATHINFO_FILENAME) . '.pdf';
                if (file_exists($outputName)) {
                    rename($outputName, $cacheFile);
                }

                if (!file_exists($cacheFile)) {
                    http_response_code(500);
                    echo 'Office conversion failed (check cache/office/error.log for details)';
                    exit;
                }
            }

            header('Content-Type: application/pdf');
            header('Content-Length: ' . filesize($cacheFile));
            header('Cache-Control: private, max-age=3600');
            readfile($cacheFile);
            exit;
        }

        // 关闭状态
        http_response_code(403);
        echo 'Office preview is disabled';
        exit;

    default:
        $currentPath = $path;
        $breadcrumbs = getBreadcrumbs($currentPath);
        $officePreviewMode = getConfig('office_preview_mode') ?: 'off';
        $officePreviewApi  = getConfig('office_preview_api');
        include 'template.php';
        exit;
}
?>