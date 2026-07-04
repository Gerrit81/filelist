<?php
function getConfigJsonPath() {
    return __DIR__ . DIRECTORY_SEPARATOR . 'config.json';
}

function loadConfig() {
    $jsonFile = getConfigJsonPath();
    $phpFile  = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

    $config = null;

    // 优先从 config.json 加载
    if (file_exists($jsonFile)) {
        $data = json_decode(file_get_contents($jsonFile), true);
        if (is_array($data)) {
            $config = $data;
        }
    }

    // config.json 不存在或无效 → 从 config.php 加载
    if ($config === null && file_exists($phpFile)) {
        $config = require $phpFile;
    }

    // 兜底
    if ($config === null) {
        $config = getDefaultConfig();
    }

    // 自动补全新版默认配置中新增的键（已有键不受影响）
    // 但 app_version 特殊处理：始终以代码默认值为准，确保升级代码后版本号自动更新
    $defaults = getDefaultConfig();
    $merged = false;
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $config)) {
            $config[$key] = $value;
            $merged = true;
        }
    }
    if (!isset($config['app_version']) || $config['app_version'] !== $defaults['app_version']) {
        $config['app_version'] = $defaults['app_version'];
        $merged = true;
    }

    // 有合并或 config.json 尚不存在 → 写回
    if ($merged || !file_exists($jsonFile)) {
        file_put_contents($jsonFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    // 向后兼容：跨平台路径归一化，避免 Windows 绝对路径在 Linux 上被当成目录名创建
    $config = normalizeConfigPaths($config);

    return $config;
}

function normalizeConfigPath($path) {
    if (empty($path) || !is_string($path)) {
        return $path;
    }

    // 仅在非 Windows 平台（Linux/macOS）上转换 Windows 绝对路径
    // Windows 绝对路径（如 D:\data）在 Windows 上完全有效，不应被修改
    if (DIRECTORY_SEPARATOR === '/') {
        // 统一为 / 再检测 [盘符]: 模式
        $normalized = str_replace('\\', '/', $path);
        if (preg_match('#^[a-zA-Z]:/#', $normalized)) {
            $baseName = basename(rtrim($normalized, '/'));
            return __DIR__ . DIRECTORY_SEPARATOR . $baseName;
        }
    }

    return $path;
}

function normalizeConfigPaths($config) {
    if (!is_array($config)) {
        return $config;
    }
    $pathKeys = array('data_dir', 'thumb_dir', 'session_dir');
    foreach ($pathKeys as $key) {
        if (isset($config[$key])) {
            $config[$key] = normalizeConfigPath($config[$key]);
        }
    }
    return $config;
}

function getDefaultConfig() {
    return array(
        'site_name' => '文件浏览器',
        'site_subtitle' => '轻量级文件目录浏览系统',
        'data_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'data',
        'thumb_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'thumbs',
        'session_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'session',
        'max_upload_size' => 0,
        'hidden_files' => array(),
        'app_version' => '2.9.2',
        'icon_scheme' => 'emoji',
        'svg_icon_style' => 'material',
        'office_preview_mode' => 'off',
        'libreoffice_path' => '',
        'office_preview_api' => '',
        'nav_links' => array(
            array('name' => '首页', 'url' => '.', 'target' => '_self'),
            array('name' => '管理后台', 'url' => 'admin/', 'target' => '_self'),
        ),
        // 安全模式配置
        'security_mode' => 'intranet',
        'download_rate_limit' => 30,
        'login_max_failures' => 5,
        'login_lock_minutes' => 15,
        'login_rate_per_minute' => 5,
        'upload_max_size_mb' => 100,
        'internet_allow_anonymous_view' => true,
        'internet_force_https' => true,
    );
}

function getConfig($key) {
    static $config = null;
    if ($config === null) {
        $config = loadConfig();
    }
    return isset($config[$key]) ? $config[$key] : null;
}

function saveConfig($config) {
    // admin_password 已迁移到 SQLite users 表，不再写入 config.php 明文
    unset($config['admin_password']);

    // 保存前对目录路径做跨平台归一化，避免 Windows 绝对路径被写入配置
    $config = normalizeConfigPaths($config);

    // 如果 data_dir 变更，清除所有目录缓存和统计缓存
    $oldDataDir = getConfig('data_dir');
    if (isset($config['data_dir']) && $config['data_dir'] !== $oldDataDir) {
        clearDirCache();          // 所有 dir_*.json
        $statsFile = getCacheDir() . DIRECTORY_SEPARATOR . 'stats.json';
        if (file_exists($statsFile)) { @unlink($statsFile); }
    }

    $configFile = getConfigJsonPath();
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function getCacheDir() {
    return __DIR__ . DIRECTORY_SEPARATOR . 'cache';
}

function initDirectories() {
    $dataDir = getConfig('data_dir');
    $thumbDir = getConfig('thumb_dir');
    $sessionDir = getConfig('session_dir');
    $cacheDir = getCacheDir();
    
    if (!file_exists($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    if (!file_exists($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }
    if (!file_exists($sessionDir)) {
        mkdir($sessionDir, 0755, true);
    }
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
}

function getAppDbPath() {
    return __DIR__ . DIRECTORY_SEPARATOR . 'app.db';
}

function initAppDB() {
    $dbFile = getAppDbPath();
    $isNew = !file_exists($dbFile);
    $db = new SQLite3($dbFile);

    // 初始化安全相关表（幂等）
    $db->exec('CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_address TEXT NOT NULL,
        attempted_at INTEGER NOT NULL,
        success INTEGER NOT NULL DEFAULT 0
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address, attempted_at)');
    $db->exec('CREATE TABLE IF NOT EXISTS audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_type TEXT NOT NULL,
        event_detail TEXT,
        ip_address TEXT,
        user_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS ip_rules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_address TEXT NOT NULL,
        rule_type TEXT NOT NULL,
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS rate_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        identifier TEXT NOT NULL,
        resource TEXT NOT NULL,
        hit_at INTEGER NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_rate_limits_cleanup ON rate_limits(identifier, resource, hit_at)');

    // ── 使用 IF NOT EXISTS 确保幂等，多次调用不报错 ──
    $db->exec('CREATE TABLE IF NOT EXISTS downloads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_path TEXT NOT NULL,
        file_name TEXT NOT NULL,
        ip_address TEXT,
        user_agent TEXT,
        download_time DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        permissions TEXT NOT NULL DEFAULT "{}"
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        password_hash TEXT NOT NULL,
        role_id INTEGER NOT NULL DEFAULT 2,
        max_upload_size INTEGER NOT NULL DEFAULT 0,
        allowed_folders TEXT NOT NULL DEFAULT "[]",
        username TEXT NOT NULL DEFAULT "",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    if ($isNew) {

        // 插入默认角色
        $allPerms = json_encode(array_fill_keys(array_keys(getDefaultPermissionSet()), true), JSON_UNESCAPED_UNICODE);
        $limitedPerms = getDefaultPermissionSet();
        $limitedPerms['dashboard'] = true;
        $limitedPerms['upload'] = true;
        $limitedPerms['files'] = true;
        $limitedPerms['files_rename'] = true;
        $limitedPerms['downloads'] = true;
        $limitedPermsJson = json_encode($limitedPerms, JSON_UNESCAPED_UNICODE);
        $db->exec("INSERT OR IGNORE INTO roles (id, name, permissions) VALUES (1, '管理员', '$allPerms')");
        $db->exec("INSERT OR IGNORE INTO roles (id, name, permissions) VALUES (2, '操作员', '$limitedPermsJson')");

        // 创建默认管理员（仅当不存在时）
        $existingAdmin = $db->querySingle('SELECT COUNT(*) FROM users WHERE id = 1');
        if ($existingAdmin == 0) {
            $adminPwd = getConfig('admin_password');
            if (empty($adminPwd)) {
                $adminPwd = 'admin123';
            }
            $hash = password_hash($adminPwd, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (id, password_hash, role_id, username) VALUES (1, :hash, 1, :uname)');
            $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
            $stmt->bindValue(':uname', '管理员', SQLITE3_TEXT);
            $stmt->execute();
        }

        // ── 从旧数据库迁移数据 ──
        $oldUsersDb = __DIR__ . DIRECTORY_SEPARATOR . 'users.db';
        if (file_exists($oldUsersDb)) {
            $oldDb = new SQLite3($oldUsersDb);
            $oldUsers = $oldDb->query('SELECT password_hash, role_id, max_upload_size, allowed_folders, username, created_at FROM users WHERE id > 1');
            while ($u = $oldUsers->fetchArray(SQLITE3_ASSOC)) {
                $stmt = $db->prepare('INSERT INTO users (password_hash, role_id, max_upload_size, allowed_folders, username, created_at) VALUES (:hash, :rid, :ms, :af, :un, :ca)');
                $stmt->bindValue(':hash', $u['password_hash'], SQLITE3_TEXT);
                $stmt->bindValue(':rid', (int)$u['role_id'], SQLITE3_INTEGER);
                $stmt->bindValue(':ms', (int)$u['max_upload_size'], SQLITE3_INTEGER);
                $stmt->bindValue(':af', $u['allowed_folders'], SQLITE3_TEXT);
                $stmt->bindValue(':un', $u['username'], SQLITE3_TEXT);
                $stmt->bindValue(':ca', $u['created_at'], SQLITE3_TEXT);
                $stmt->execute();
            }
            // 迁移旧角色（除默认角色外的自定义角色）
            $oldRoles = $oldDb->query('SELECT name, permissions FROM roles WHERE id > 2');
            while ($r = $oldRoles->fetchArray(SQLITE3_ASSOC)) {
                $stmt = $db->prepare('INSERT INTO roles (name, permissions) VALUES (:name, :perms)');
                $stmt->bindValue(':name', $r['name'], SQLITE3_TEXT);
                $stmt->bindValue(':perms', $r['permissions'], SQLITE3_TEXT);
                $stmt->execute();
            }
            $oldDb->close();
            @rename($oldUsersDb, $oldUsersDb . '.bak');
        }

        $oldDownloadsDb = __DIR__ . DIRECTORY_SEPARATOR . 'downloads.db';
        if (file_exists($oldDownloadsDb)) {
            $oldDb = new SQLite3($oldDownloadsDb);
            $oldDownloads = $oldDb->query('SELECT file_path, file_name, ip_address, user_agent, download_time FROM downloads');
            while ($d = $oldDownloads->fetchArray(SQLITE3_ASSOC)) {
                $stmt = $db->prepare('INSERT INTO downloads (file_path, file_name, ip_address, user_agent, download_time) VALUES (:fp, :fn, :ip, :ua, :dt)');
                $stmt->bindValue(':fp', $d['file_path'], SQLITE3_TEXT);
                $stmt->bindValue(':fn', $d['file_name'], SQLITE3_TEXT);
                $stmt->bindValue(':ip', $d['ip_address'], SQLITE3_TEXT);
                $stmt->bindValue(':ua', $d['user_agent'], SQLITE3_TEXT);
                $stmt->bindValue(':dt', $d['download_time'], SQLITE3_TEXT);
                $stmt->execute();
            }
            $oldDb->close();
            @rename($oldDownloadsDb, $oldDownloadsDb . '.bak');
        }
    }

    $db->close();
}

// 向后兼容别名
function initDatabase() { initAppDB(); }
function initUserDB()   { initAppDB(); }
function getUserDbPath(){ return getAppDbPath(); }

function logDownload($filePath, $fileName) {
    initDatabase();
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $db = new SQLite3(getAppDbPath());
    $stmt = $db->prepare('INSERT INTO downloads (file_path, file_name, ip_address, user_agent) VALUES (:path, :name, :ip, :ua)');
    $stmt->bindValue(':path', $filePath, SQLITE3_TEXT);
    $stmt->bindValue(':name', $fileName, SQLITE3_TEXT);
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->bindValue(':ua', $userAgent, SQLITE3_TEXT);
    $stmt->execute();
    $db->close();
}

function getDownloadHistory($limit = 100, $offset = 0) {
    initAppDB();
    $db = new SQLite3(getAppDbPath());
    $stmt = $db->prepare('SELECT * FROM downloads ORDER BY download_time DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $history = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $history[] = $row;
    }
    
    $db->close();
    return $history;
}

function getDownloadCount() {
    initAppDB();
    $db = new SQLite3(getAppDbPath());
    $result = $db->query('SELECT COUNT(*) as count FROM downloads');
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    
    return $row['count'] ?? 0;
}

function deleteDownloadHistory($id) {
    initAppDB();
    $db = new SQLite3(getAppDbPath());
    $stmt = $db->prepare('DELETE FROM downloads WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    $db->close();
}

function clearDownloadHistory() {
    initAppDB();
    $db = new SQLite3(getAppDbPath());
    $db->exec('DELETE FROM downloads');
    $db->close();
}

function isHiddenFile($fileName, $relativePath = '') {
    $hiddenFiles = getConfig('hidden_files');
    foreach ($hiddenFiles as $rule) {
        if (strpos($rule, '*') !== false) {
            $pattern = str_replace('*', '.*', $rule);
            if (preg_match('/^' . $pattern . '$/', $fileName)) {
                return true;
            }
        } else {
            if (substr($rule, -1) === '/') {
                $dirName = rtrim($rule, '/');
                if ($fileName === $dirName || strpos($relativePath, $dirName . '/') === 0) {
                    return true;
                }
            } else {
                if ($fileName === $rule || strpos($relativePath, $rule) === 0) {
                    return true;
                }
            }
        }
    }
    return false;
}

function isSafePath($path) {
    $realDataDir = realpath(getConfig('data_dir'));
    $realPath = realpath($path);
    if ($realPath === false) {
        return false;
    }
    return strpos($realPath, $realDataDir . DIRECTORY_SEPARATOR) === 0 || $realPath === $realDataDir;
}

function getFullPath($relativePath) {
    $relativePath = trim($relativePath, '/\\');
    if (empty($relativePath)) {
        return getConfig('data_dir');
    }
    $fullPath = getConfig('data_dir') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return $fullPath;
}

function getRelativePath($fullPath) {
    $realDataDir = realpath(getConfig('data_dir'));
    $realPath = realpath($fullPath);
    if (strpos($realPath, $realDataDir) !== 0) {
        return '';
    }
    $relativePath = substr($realPath, strlen($realDataDir));
    return trim(str_replace(DIRECTORY_SEPARATOR, '/', $relativePath), '/');
}

/**
 * 将系统编码字符串转为 UTF-8。
 * Windows 中文环境里，readdir()/basename() 等返回的是 GBK 编码，
 * 必须转换为 UTF-8，否则 json_encode / 页面显示会乱码。
 */
function sysToUtf8($str) {
    if ($str === '' || $str === null) return (string)$str;
    // 已经是有效 UTF-8 则跳过
    if (mb_check_encoding($str, 'UTF-8')) {
        return $str;
    }
    // 尝试从 GBK 系列编码转换
    if (extension_loaded('mbstring')) {
        // 依次尝试最常见的 Windows 中文编码
        foreach (array('GBK', 'GB2312', 'GB18030', 'BIG5', 'CP936', 'CP950') as $enc) {
            $converted = @mb_convert_encoding($str, 'UTF-8', $enc);
            if ($converted !== false && $converted !== $str) {
                return $converted;
            }
        }
    }
    // 全部失败则原样返回
    return $str;
}

function safeBasename($filePath) {
    $normalized = str_replace('\\', '/', $filePath);
    $parts = explode('/', $normalized);
    $name = end($parts);
    $name = sysToUtf8($name);
    return $name;
}

function getFileInfo($filePath, $relativePath, $displayName = null) {
    $info = array();
    $info['name'] = $displayName !== null ? $displayName : safeBasename($filePath);
    $info['path'] = $relativePath;
    
    // 一次 stat() 获取所有文件元信息，避免三次独立 I/O 调用
    $stat = @stat($filePath);
    if ($stat === false) {
        $info['size'] = 0;
        $info['mtime'] = 0;
    } else {
        $info['size'] = $stat['size'];
        $info['mtime'] = $stat['mtime'];
    }
    $info['type'] = @is_dir($filePath) ? 'dir' : 'file';
    $info['mtime_str'] = $info['mtime'] > 0 ? date('Y-m-d H:i:s', $info['mtime']) : '-';
    
    if ($info['type'] === 'file') {
        $info['mime'] = getMimeType($filePath);
        $info['ext'] = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $info['is_image'] = isImage($info['mime']);
        $info['is_video'] = isVideo($info['mime']);
        $info['is_audio'] = isAudio($info['mime']);
        $info['is_text'] = isText($info['mime']);
        $info['is_code'] = isCode($info['ext']);
        $info['is_office'] = isOffice($info['mime']);
    }
    
    return $info;
}

// 扩展名 → MIME 映射（覆盖常见类型，稳定且零开销，避免 Windows 上 finfo 底层崩溃）
function getExtensionMimeMap() {
    return array(
        // 图片
        'jpg'  => 'image/jpeg',    'jpeg' => 'image/jpeg',   'png'  => 'image/png',
        'gif'  => 'image/gif',     'webp' => 'image/webp',   'bmp'  => 'image/bmp',
        'svg'  => 'image/svg+xml', 'ico'  => 'image/x-icon', 'tiff' => 'image/tiff',
        'tif'  => 'image/tiff',    'psd'  => 'image/vnd.adobe.photoshop',
        // 视频
        'mp4'  => 'video/mp4',     'webm' => 'video/webm',   'avi'  => 'video/x-msvideo',
        'mov'  => 'video/quicktime','mkv'  => 'video/x-matroska','wmv' => 'video/x-ms-wmv',
        'flv'  => 'video/x-flv',   'm4v'  => 'video/x-m4v',  'mpg'  => 'video/mpeg',
        'mpeg' => 'video/mpeg',    '3gp'  => 'video/3gpp',
        // 音频
        'mp3'  => 'audio/mpeg',    'wav'  => 'audio/wav',    'ogg'  => 'audio/ogg',
        'flac' => 'audio/flac',    'aac'  => 'audio/aac',    'wma'  => 'audio/x-ms-wma',
        'm4a'  => 'audio/mp4',     'mid'  => 'audio/midi',   'midi' => 'audio/midi',
        // 文档
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // 压缩包
        'zip'  => 'application/zip',        'rar'  => 'application/x-rar-compressed',
        '7z'   => 'application/x-7z-compressed','tar' => 'application/x-tar',
        'gz'   => 'application/gzip',       'bz2'  => 'application/x-bzip2',
        // 代码/文本
        'txt'  => 'text/plain',     'html' => 'text/html',    'htm'  => 'text/html',
        'css'  => 'text/css',       'js'   => 'application/javascript',
        'json' => 'application/json','xml'  => 'application/xml',
        'php'  => 'text/x-php',     'md'   => 'text/markdown','csv'  => 'text/csv',
        'log'  => 'text/plain',     'ini'  => 'text/plain',   'yml'  => 'text/yaml',
        'yaml' => 'text/yaml',      'sql'  => 'text/x-sql',   'sh'   => 'text/x-shellscript',
        'bat'  => 'text/plain',     'py'   => 'text/x-python','c'    => 'text/x-c',
        'cpp'  => 'text/x-c++src',  'h'    => 'text/x-c',     'java' => 'text/x-java',
        'go'   => 'text/x-go',      'rs'   => 'text/x-rust',  'ts'   => 'application/typescript',
        'tsx'  => 'text/typescript-jsx','jsx'=> 'text/jsx',
        // 可执行/库
        'exe'  => 'application/x-msdownload','dll' => 'application/x-msdownload',
        'msi'  => 'application/x-msi','apk'  => 'application/vnd.android.package-archive',
        'iso'  => 'application/x-iso9660-image',
        // 字体
        'ttf'  => 'font/ttf',       'otf'  => 'font/otf',    'woff' => 'font/woff',
        'woff2'=> 'font/woff2',     'eot'  => 'application/vnd.ms-fontobject',
    );
}

function getMimeType($filePath) {
    static $mimeMap = null;
    if ($mimeMap === null) {
        $mimeMap = getExtensionMimeMap();
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    // 已知扩展名 → 直接返回（最快、最稳定）
    if (isset($mimeMap[$ext])) {
        return $mimeMap[$ext];
    }

    // 未知扩展名 → 不尝试 finfo_file()（Windows 上读取恶意/畸形二进制文件会导致 PHP 进程崩溃）
    return 'application/octet-stream';
}

function isImage($mime) {
    return strpos($mime, 'image/') === 0;
}

function isVideo($mime) {
    return strpos($mime, 'video/') === 0;
}

function isAudio($mime) {
    return strpos($mime, 'audio/') === 0;
}

function isText($mime) {
    return strpos($mime, 'text/') === 0 || in_array($mime, ['application/json', 'application/javascript', 'application/xml']);
}

function isCode($ext) {
    $codeExts = ['php', 'html', 'css', 'js', 'jsx', 'ts', 'tsx', 'py', 'go', 'java', 'c', 'cpp', 'cs', 'sql', 'json', 'xml', 'yml', 'yaml', 'md', 'txt'];
    return in_array($ext, $codeExts);
}

function isOffice($mime) {
    $officeMimes = [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    return in_array($mime, $officeMimes);
}

function scanDirectory($relativePath) {
    $fullPath = getFullPath($relativePath);
    
    if (!isSafePath($fullPath)) {
        return ['error' => '非法路径'];
    }
    
    if (!is_dir($fullPath)) {
        return ['error' => '目录不存在'];
    }
    
    $files = array();
    $dh = opendir($fullPath);
    
    while (($file = readdir($dh)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        // Windows 下 readdir() 返回系统编码（GBK），转为 UTF-8
        $file = sysToUtf8($file);
        
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
        $fileRelativePath = empty($relativePath) ? $file : $relativePath . '/' . $file;
        
        if (is_link($filePath)) {
            continue;
        }
        
        if (isHiddenFile($file, $fileRelativePath)) {
            continue;
        }
        
        $files[] = getFileInfo($filePath, $fileRelativePath, $file);
    }
    
    closedir($dh);
    
    usort($files, function($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'dir' ? -1 : 1;
        }
        return strcmp(strtolower($a['name']), strtolower($b['name']));
    });
    
    return $files;
}

// 带缓存的目录扫描 —— 缓存 key = md5(CACHE_VERSION + data_dir + relativePath)
// CACHE_VERSION 在数据结构或文件路径处理逻辑变更时手动递增，
// 以确保所有旧缓存自动失效
define('CACHE_VERSION', '5');

function scanDirectoryCached($relativePath) {
    $fullPath = getFullPath($relativePath);
    
    if (!isSafePath($fullPath)) {
        return ['error' => '非法路径'];
    }
    if (!is_dir($fullPath)) {
        return ['error' => '目录不存在'];
    }
    
    // 缓存 key 绑定 CACHE_VERSION、data_dir、relativePath
    // 版本号变更 → 所有旧缓存自动失效
    $cacheKey = md5(CACHE_VERSION . '||' . getConfig('data_dir') . '||' . $relativePath);
    $cacheFile = getCacheDir() . DIRECTORY_SEPARATOR . 'dir_' . $cacheKey . '.json';
    $dirMtime = filemtime($fullPath);
    
    if (file_exists($cacheFile)) {
        $cacheStat = @stat($cacheFile);
        if ($cacheStat !== false) {
            // 目录修改时间晚于缓存 → 缓存失效
            if ($cacheStat['mtime'] >= $dirMtime) {
                $cached = json_decode(file_get_contents($cacheFile), true);
                if (is_array($cached)) {
                    return $cached;
                }
            }
        }
    }
    
    // 实际扫描
    $files = scanDirectory($relativePath);
    
    // 写入缓存（原子写入避免并发读脏数据）
    $tmpFile = $cacheFile . '.tmp';
    file_put_contents($tmpFile, json_encode($files, JSON_UNESCAPED_UNICODE), LOCK_EX);
    rename($tmpFile, $cacheFile);
    
    return $files;
}

// ──────────────────────────────────────────────
// 递归搜索：遍历所有子目录，匹配文件名
// ──────────────────────────────────────────────
function recursiveSearch($keyword) {
    $results = array();
    _recursiveSearchWalk('', mb_strtolower(trim($keyword)), $results);

    // 目录优先 + 字母排序
    usort($results, function($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'dir' ? -1 : 1;
        }
        return strcmp(strtolower($a['path']), strtolower($b['path']));
    });

    return $results;
}

function _recursiveSearchWalk($relativePath, $keywordLower, &$results) {
    $fullPath = getFullPath($relativePath);

    if (!isSafePath($fullPath) || !is_dir($fullPath)) {
        return;
    }

    $dh = opendir($fullPath);
    if (!$dh) return;

    while (($file = readdir($dh)) !== false) {
        if ($file === '.' || $file === '..') continue;

        $fileNameUtf8 = sysToUtf8($file);
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
        $fileRelativePath = empty($relativePath) ? $fileNameUtf8 : $relativePath . '/' . $fileNameUtf8;

        if (is_link($filePath)) continue;
        if (isHiddenFile($fileNameUtf8, $fileRelativePath)) continue;

        $fileInfo = getFileInfo($filePath, $fileRelativePath, $fileNameUtf8);

        // 匹配关键词（不区分大小写）
        if (mb_stripos($fileNameUtf8, $keywordLower) !== false) {
            $results[] = $fileInfo;
        }

        // 递归进入子目录
        if ($fileInfo['type'] === 'dir') {
            _recursiveSearchWalk($fileRelativePath, $keywordLower, $results);
        }
    }

    closedir($dh);
}

// 清除某目录的缓存（文件增删/重命名后调用）
function clearDirCache($relativePath = null) {
    $cacheDir = getCacheDir();
    $dataDir = getConfig('data_dir');
    if ($relativePath === null) {
        // 清除所有缓存
        $files = glob($cacheDir . DIRECTORY_SEPARATOR . 'dir_*.json');
        foreach ($files as $f) { @unlink($f); }
        // 也清除统计缓存
        $statsFile = $cacheDir . DIRECTORY_SEPARATOR . 'stats.json';
        if (file_exists($statsFile)) { @unlink($statsFile); }
    } else {
        $cacheKey = md5(CACHE_VERSION . '||' . $dataDir . '||' . $relativePath);
        $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'dir_' . $cacheKey . '.json';
        if (file_exists($cacheFile)) { @unlink($cacheFile); }
        // 同时清除上级目录缓存（子目录增删影响上级）
        $parentPath = dirname($relativePath);
        if ($parentPath !== '.' && $parentPath !== '') {
            $parentKey = md5(CACHE_VERSION . '||' . $dataDir . '||' . $parentPath);
            $parentCache = $cacheDir . DIRECTORY_SEPARATOR . 'dir_' . $parentKey . '.json';
            if (file_exists($parentCache)) { @unlink($parentCache); }
        }
    }
}

function formatSize($bytes) {
    if ($bytes === 0) {
        return '0 B';
    }
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

function getThumbnail($filePath, $maxWidth = 200, $maxHeight = 200) {
    $relativePath = getRelativePath($filePath);
    $thumbFileName = md5($relativePath) . '.jpg';
    $thumbPath = getConfig('thumb_dir') . DIRECTORY_SEPARATOR . $thumbFileName;
    
    if (file_exists($thumbPath) && filemtime($thumbPath) >= filemtime($filePath)) {
        return $thumbPath;
    }
    
    $mime = getMimeType($filePath);
    
    if (!isImage($mime)) {
        return false;
    }
    
    switch ($mime) {
        case 'image/jpeg':
            $src = imagecreatefromjpeg($filePath);
            break;
        case 'image/png':
            $src = imagecreatefrompng($filePath);
            break;
        case 'image/gif':
            $src = imagecreatefromgif($filePath);
            break;
        case 'image/webp':
            $src = imagecreatefromwebp($filePath);
            break;
        default:
            return false;
    }
    
    if (!$src) {
        return false;
    }
    
    $srcWidth = imagesx($src);
    $srcHeight = imagesy($src);
    
    $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight);
    $newWidth = (int)($srcWidth * $ratio);
    $newHeight = (int)($srcHeight * $ratio);
    
    $dst = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);
    
    imagejpeg($dst, $thumbPath, 80);
    imagedestroy($src);
    imagedestroy($dst);
    
    return $thumbPath;
}

function getBreadcrumbs($relativePath) {
    $breadcrumbs = array();
    $breadcrumbs[] = array('name' => '根目录', 'path' => '');
    
    if (!empty($relativePath)) {
        $parts = explode('/', $relativePath);
        $currentPath = '';
        
        foreach ($parts as $part) {
            $currentPath .= ($currentPath === '' ? '' : '/') . $part;
            $breadcrumbs[] = array('name' => $part, 'path' => $currentPath);
        }
    }
    
    return $breadcrumbs;
}

function getTotalSize() {
    $dataDir = getConfig('data_dir');
    $cacheFile = getCacheDir() . DIRECTORY_SEPARATOR . 'stats.json';
    
    // TTL 缓存（30 分钟），避免每次都递归扫描整个 data 目录
    $cacheTTL = 1800;
    if (file_exists($cacheFile)) {
        $cacheStat = @stat($cacheFile);
        if ($cacheStat !== false && (time() - $cacheStat['mtime']) < $cacheTTL) {
            $cached = @json_decode(@file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['total_size'])) {
                return $cached['total_size'];
            }
        }
    }
    
    // 实际计算
    $totalSize = 0;
    $fileCount = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dataDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $totalSize += $file->getSize();
            $fileCount++;
        }
    }
    
    // 同时缓存 size 和 count（一次遍历）
    $tmpFile = $cacheFile . '.tmp';
    file_put_contents($tmpFile, json_encode(array(
        'total_size' => $totalSize,
        'file_count' => $fileCount,
    ), JSON_UNESCAPED_UNICODE), LOCK_EX);
    rename($tmpFile, $cacheFile);
    
    return $totalSize;
}

function getFileCount() {
    $dataDir = getConfig('data_dir');
    $cacheFile = getCacheDir() . DIRECTORY_SEPARATOR . 'stats.json';
    
    // TTL 缓存（30 分钟）
    $cacheTTL = 1800;
    if (file_exists($cacheFile)) {
        $cacheStat = @stat($cacheFile);
        if ($cacheStat !== false && (time() - $cacheStat['mtime']) < $cacheTTL) {
            $cached = @json_decode(@file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['file_count'])) {
                return $cached['file_count'];
            }
        }
    }
    
    // 缓存过期 → 触发 getTotalSize() 重建
    getTotalSize();
    
    if (file_exists($cacheFile)) {
        $cached = @json_decode(@file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['file_count'])) {
            return $cached['file_count'];
        }
    }
    
    // 极端 fallback
    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dataDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) $count++;
    }
    return $count;
}

/**
 * 强制刷新文件统计缓存（上传/删除文件后由 admin 模块调用）
 */
function refreshStats() {
    clearStatsCache();
    return array(
        'total_size' => getTotalSize(),
        'file_count' => getFileCount(),
    );
}

function clearStatsCache() {
    $cacheFile = getCacheDir() . DIRECTORY_SEPARATOR . 'stats.json';
    if (file_exists($cacheFile)) { unlink($cacheFile); }
}

// ──────────────── 统计面板数据（基于 SQLite downloads 表）────────────────

/**
 * 下载文件排行榜：按文件被下载次数降序
 */
function getDownloadRanking($limit = 10) {
    initAppDB();
    $db = new SQLite3(getAppDbPath());
    $stmt = $db->prepare('SELECT file_name, file_path, COUNT(*) AS cnt FROM downloads GROUP BY file_path ORDER BY cnt DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $rows = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    $db->close();
    return $rows;
}

/**
 * 访问 IP 排行榜：按 IP 下载次数降序
 */
function getIpRanking($limit = 10) {
    initAppDB();
    $db = new SQLite3(getAppDbPath());
    $stmt = $db->prepare('SELECT ip_address, COUNT(*) AS cnt, MAX(download_time) AS last_time FROM downloads GROUP BY ip_address ORDER BY cnt DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $rows = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    $db->close();
    return $rows;
}

/**
 * 最近 N 条下载记录
 */
function getRecentDownloads($limit = 10) {
    return getDownloadHistory($limit, 0);
}

/**
 * 今日下载次数
 */
function getTodayDownloadCount() {
    initAppDB();
    $db = new SQLite3(getAppDbPath());
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM downloads WHERE download_time >= :today");
    $stmt->bindValue(':today', $today, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $db->close();
    return $row['cnt'] ?? 0;
}


// ──────────────── 用户+角色系统（SQLite）────────────────

function getDefaultPermissionSet() {
    return array(
        'dashboard'     => false,
        'upload'        => false,
        'files'         => false,
        'files_rename'  => false,
        'files_delete'  => false,
        'downloads'     => false,
        'hidden'        => false,
        'settings'      => false,
        'users'         => false,
        'roles'         => false,
    );
}

// ── 角色 CRUD ──

function getRoles() {
    initUserDB();
    $db = new SQLite3(getUserDbPath());
    $result = $db->query('SELECT * FROM roles ORDER BY id ASC');
    $roles = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['permissions'] = json_decode($row['permissions'], true) ?: getDefaultPermissionSet();
        $roles[] = $row;
    }
    $db->close();
    return $roles;
}

function getRoleById($id) {
    initUserDB();
    $db = new SQLite3(getUserDbPath());
    $stmt = $db->prepare('SELECT * FROM roles WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    if ($row) {
        $row['permissions'] = json_decode($row['permissions'], true) ?: getDefaultPermissionSet();
    }
    return $row;
}

function addRole($name, $permissions) {
    initUserDB();
    $db = new SQLite3(getUserDbPath());
    $permJson = json_encode($permissions, JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare('INSERT INTO roles (name, permissions) VALUES (:name, :perms)');
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':perms', $permJson, SQLITE3_TEXT);
    $stmt->execute();
    $id = $db->lastInsertRowID();
    $db->close();
    return $id;
}

function updateRole($id, $name, $permissions) {
    initUserDB();
    $db = new SQLite3(getUserDbPath());
    $permJson = json_encode($permissions, JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare('UPDATE roles SET name = :name, permissions = :perms WHERE id = :id');
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':perms', $permJson, SQLITE3_TEXT);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    $db->close();
    return true;
}

function deleteRole($id) {
    initUserDB();
    $db = new SQLite3(getUserDbPath());
    // 不允许删除被用户引用的角色
    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE role_id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $count = $stmt->execute()->fetchArray(SQLITE3_NUM)[0];
    if ($count > 0) {
        $db->close();
        return '该角色下还有用户，请先迁移用户到其他角色';
    }
    // 至少保留一个角色
    $totalRoles = $db->querySingle('SELECT COUNT(*) FROM roles');
    if ($totalRoles <= 1) {
        $db->close();
        return '至少保留一个角色';
    }
    $stmt = $db->prepare('DELETE FROM roles WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    $db->close();
    return true;
}

// ── 用户 CRUD（适配 role_id）──

function getUsers() {
    initUserDB();
    $db = new SQLite3(getUserDbPath());
    $result = $db->query('SELECT u.*, r.name AS role_name, r.permissions AS role_permissions FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.id ASC');
    $users = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['allowed_folders'] = json_decode($row['allowed_folders'], true) ?: array();
        $row['role_permissions'] = json_decode($row['role_permissions'], true) ?: getDefaultPermissionSet();
        $users[] = $row;
    }
    $db->close();
    return $users;
}

function getUserById($id) {
    initUserDB();
    $db = new SQLite3(getUserDbPath());
    $stmt = $db->prepare('SELECT u.*, r.name AS role_name, r.permissions AS role_permissions FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    if ($row) {
        $row['allowed_folders'] = json_decode($row['allowed_folders'], true) ?: array();
        $row['role_permissions'] = json_decode($row['role_permissions'], true) ?: getDefaultPermissionSet();
    }
    return $row;
}

function getUserByPassword($password) {
    initUserDB();
    $db = new SQLite3(getUserDbPath());
    $result = $db->query('SELECT u.*, r.name AS role_name, r.permissions AS role_permissions FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.id ASC');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if (password_verify($password, $row['password_hash'])) {
            $row['allowed_folders'] = json_decode($row['allowed_folders'], true) ?: array();
            $row['role_permissions'] = json_decode($row['role_permissions'], true) ?: getDefaultPermissionSet();
            $db->close();
            return $row;
        }
    }
    $db->close();
    return null;
}

function addUser($password, $roleId = 2, $maxUploadSize = 0, $allowedFolders = array(), $username = '') {
    initUserDB();
    $db = new SQLite3(getUserDbPath());
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $foldersJson = json_encode($allowedFolders, JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare('INSERT INTO users (password_hash, role_id, max_upload_size, allowed_folders, username) VALUES (:hash, :rid, :maxsize, :folders, :uname)');
    $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
    $stmt->bindValue(':rid', (int)$roleId, SQLITE3_INTEGER);
    $stmt->bindValue(':maxsize', $maxUploadSize, SQLITE3_INTEGER);
    $stmt->bindValue(':folders', $foldersJson, SQLITE3_TEXT);
    $stmt->bindValue(':uname', $username, SQLITE3_TEXT);
    $stmt->execute();
    $id = $db->lastInsertRowID();
    $db->close();
    return $id;
}

function updateUser($id, $data) {
    initUserDB();
    $db = new SQLite3(getUserDbPath());
    $sets = array();
    $binds = array();

    if (isset($data['password'])) {
        $sets[] = 'password_hash = :hash';
        $binds[':hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    if (isset($data['role_id'])) {
        $sets[] = 'role_id = :rid';
        $binds[':rid'] = (int)$data['role_id'];
    }
    if (isset($data['max_upload_size'])) {
        $sets[] = 'max_upload_size = :maxsize';
        $binds[':maxsize'] = (int)$data['max_upload_size'];
    }
    if (isset($data['allowed_folders'])) {
        $sets[] = 'allowed_folders = :folders';
        $binds[':folders'] = json_encode($data['allowed_folders'], JSON_UNESCAPED_UNICODE);
    }
    if (isset($data['username'])) {
        $sets[] = 'username = :uname';
        $binds[':uname'] = $data['username'];
    }

    if (empty($sets)) return false;

    $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    foreach ($binds as $key => $val) {
        $stmt->bindValue($key, $val, is_int($val) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    }
    $stmt->execute();
    $db->close();
    return true;
}

function deleteUser($id) {
    initUserDB();
    $db = new SQLite3(getUserDbPath());
    // 不允许删除自己
    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$id) {
        $db->close();
        return '不能删除自己';
    }
    // 检查是否至少还有一个有 users+roles 权限的用户
    $stmt = $db->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id != :id AND (r.permissions LIKE '%\"users\":true%' OR r.permissions LIKE '%\"roles\":true%')");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $remaining = $stmt->execute()->fetchArray(SQLITE3_NUM)[0];
    if ($remaining < 1) {
        $db->close();
        return '至少保留一个有用户管理或角色管理权限的账户';
    }
    $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    $db->close();
    return true;
}

function getAllFolders($path = '') {
    $fullPath = getFullPath($path);
    if (!is_dir($fullPath)) return array();
    $dirs = array();
    $dh = opendir($fullPath);
    while (($file = readdir($dh)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $file = sysToUtf8($file);
        $fp = $fullPath . DIRECTORY_SEPARATOR . $file;
        if (is_dir($fp) && !is_link($fp)) {
            $relPath = empty($path) ? $file : $path . '/' . $file;
            $dirs[] = array('name' => $file, 'path' => $relPath);
            $subDirs = getAllFolders($relPath);
            $dirs = array_merge($dirs, $subDirs);
        }
    }
    closedir($dh);
    return $dirs;
}