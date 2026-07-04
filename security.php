<?php
/**
 * FileList 安全模块 — 外网模式安全增强
 * ===================================================
 * 通过 security_mode 配置控制：
 *  - 'intranet'：保持轻便，仅基础安全
 *  - 'internet'：启用全部安全层
 *
 * 注意：本文件依赖 functions.php（getConfig/getAppDbPath 等）
 *       需要在使用前 require_once 'functions.php'
 */

// ──────────────────────────────────────────────
// 1. 安全配置
// ──────────────────────────────────────────────

function securityMode() {
    return getConfig('security_mode') ?: 'intranet';
}

function isInternetMode() {
    return securityMode() === 'internet';
}


// ──────────────────────────────────────────────
// 2. HTTP 安全响应头
// ──────────────────────────────────────────────

function sendSecurityHeaders() {
    if (!isInternetMode()) return;

    // 强制 HTTPS（由反向代理处理时检查 X-Forwarded-Proto）
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // CSP 内容安全策略
    $csp = "default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
         . "style-src 'self' 'unsafe-inline'; "
         . "img-src 'self' data: blob:; "
         . "media-src 'self' blob:; "
         . "font-src 'self'; "
         . "frame-src 'none'; "
         . "object-src 'none'; "
         . "connect-src 'self';";
    header('Content-Security-Policy: ' . $csp);

    // 防止被嵌入 iframe
    header('X-Frame-Options: DENY');

    // 防止 MIME 嗅探
    header('X-Content-Type-Options: nosniff');

    // 控制 Referer 信息
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // 禁止浏览器缓存敏感页面
    header('X-Permitted-Cross-Domain-Policies: none');

    // 基础 XSS 防护
    header('X-XSS-Protection: 1; mode=block');
}


// ──────────────────────────────────────────────
// 3. IP 访问控制（白名单优先）
// ──────────────────────────────────────────────

function checkIpAccess() {
    if (!isInternetMode()) return;

    $ip = getClientIp();
    $db = new SQLite3(getAppDbPath());

    // 检查黑名单
    $blocked = $db->querySingle("SELECT COUNT(*) FROM ip_rules WHERE rule_type = 'block' AND ip_address = '" . SQLite3::escapeString($ip) . "'");
    if ($blocked > 0) {
        $db->close();
        http_response_code(403);
        die('Access denied: Your IP is blocked.');
    }

    // 如果有白名单条目，检查是否在白名单中
    $hasAllow = $db->querySingle("SELECT COUNT(*) FROM ip_rules WHERE rule_type = 'allow'");
    if ($hasAllow > 0) {
        $allowed = $db->querySingle("SELECT COUNT(*) FROM ip_rules WHERE rule_type = 'allow' AND ip_address = '" . SQLite3::escapeString($ip) . "'");
        if ($allowed == 0) {
            $db->close();
            http_response_code(403);
            die('Access denied: Your IP is not in the allowlist.');
        }
    }

    $db->close();
}


// ──────────────────────────────────────────────
// 4. 通用速率限制器
// ──────────────────────────────────────────────

/**
 * 检查是否超出速率限制
 * @param string $identifier  标识（通常是 IP）
 * @param string $resource    资源类型
 * @param int    $maxHits     最大请求数
 * @param int    $windowSec   时间窗口（秒）
 * @return bool  true=超限被阻止, false=正常
 */
function rateLimitCheck($identifier, $resource, $maxHits, $windowSec) {
    if (!isInternetMode()) return false;

    $now = time();
    $cutoff = $now - $windowSec;
    $db = new SQLite3(getAppDbPath());

    // 清理过期记录
    $db->exec("DELETE FROM rate_limits WHERE hit_at < {$cutoff}");

    // 计数
    $count = $db->querySingle(
        "SELECT COUNT(*) FROM rate_limits WHERE identifier = '" . SQLite3::escapeString($identifier)
        . "' AND resource = '" . SQLite3::escapeString($resource)
        . "' AND hit_at > {$cutoff}"
    );

    if ($count >= $maxHits) {
        $db->close();
        return true; // 超限
    }

    // 记录本次请求
    $db->exec("INSERT INTO rate_limits (identifier, resource, hit_at) VALUES ('"
        . SQLite3::escapeString($identifier) . "', '"
        . SQLite3::escapeString($resource) . "', {$now})");

    $db->close();
    return false;
}


// ──────────────────────────────────────────────
// 5. 登录安全：失败计数 + 锁定 + 速率限制
// ──────────────────────────────────────────────

/**
 * 记录登录尝试
 */
function logLoginAttempt($success) {
    $db = new SQLite3(getAppDbPath());
    $ip = getClientIp();
    $now = time();
    $s = $success ? 1 : 0;

    $db->exec("INSERT INTO login_attempts (ip_address, attempted_at, success) VALUES ('"
        . SQLite3::escapeString($ip) . "', {$now}, {$s})");

    $db->close();
}

/**
 * 检查 IP 是否因过多失败被临时锁定
 * @param int $maxFailures  允许的最大连续失败次数
 * @param int $lockMinutes  锁定时长（分钟）
 * @return bool  true=被锁定, false=正常
 */
function isLoginLocked($maxFailures = 5, $lockMinutes = 15) {
    if (!isInternetMode()) return false;

    $db = new SQLite3(getAppDbPath());
    $ip = getClientIp();
    $cutoff = time() - ($lockMinutes * 60);

    // 找到最近一次失败之后的连续失败次数
    // 策略：找到在锁定窗口期间最近一次成功登录 → 从该时间点后统计失败次数
    $lastSuccess = $db->querySingle(
        "SELECT COALESCE(MAX(attempted_at), 0) FROM login_attempts WHERE ip_address = '"
        . SQLite3::escapeString($ip) . "' AND success = 1 AND attempted_at > {$cutoff}"
    );

    $failCount = $db->querySingle(
        "SELECT COUNT(*) FROM login_attempts WHERE ip_address = '"
        . SQLite3::escapeString($ip) . "' AND success = 0 AND attempted_at > {$cutoff}"
        . " AND attempted_at > {$lastSuccess}"
    );

    // 清理过期记录
    $db->exec("DELETE FROM login_attempts WHERE attempted_at < " . (time() - 86400));

    $db->close();

    return $failCount >= $maxFailures;
}

/**
 * 尝试登录前检查：IP 锁定 + 速率限制
 * @return string|null  错误消息，或 null 表示通过
 */
function preLoginCheck() {
    if (!isInternetMode()) return null;

    $ip = getClientIp();

    // 1. 频率限制：每分钟最多 5 次
    if (rateLimitCheck($ip, 'login', 5, 60)) {
        return '登录请求过于频繁，请 1 分钟后再试。';
    }

    // 2. 锁定检查：连续失败 5 次锁定 15 分钟
    if (isLoginLocked(5, 15)) {
        return '连续登录失败次数过多，账户已临时锁定，请 15 分钟后再试。';
    }

    return null;
}


// ──────────────────────────────────────────────
// 6. 密码强度校验（外网模式强制）
// ──────────────────────────────────────────────

/**
 * 校验密码强度
 * @return array [bool valid, string message]
 */
function validatePasswordStrength($password) {
    if (!isInternetMode()) return [true, ''];

    $issues = [];
    if (mb_strlen($password) < 8) {
        $issues[] = '至少 8 个字符';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $issues[] = '包含大写字母';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $issues[] = '包含小写字母';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $issues[] = '包含数字';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $issues[] = '包含特殊字符';
    }

    if (count($issues) >= 4) {
        // 缺少太多要求时提示
        return [false, '外网模式要求密码强度：至少 8 位，含大小写字母+数字+特殊字符中至少 3 类（当前缺少：' . implode('、', $issues) . '）'];
    }

    return [true, ''];
}


// ──────────────────────────────────────────────
// 7. 审计日志
// ──────────────────────────────────────────────

function auditLog($eventType, $eventDetail = '', $userId = null) {
    if (!isInternetMode()) return;

    $db = new SQLite3(getAppDbPath());
    $ip = getClientIp();
    $uid = $userId ?? ($_SESSION['user_id'] ?? null);
    $uidVal = $uid !== null ? (int)$uid : 'NULL';
    $uidCol = $uid !== null ? (int)$uid : 'NULL';

    $stmt = $db->prepare('INSERT INTO audit_log (event_type, event_detail, ip_address, user_id) VALUES (:type, :detail, :ip, :uid)');
    $stmt->bindValue(':type', $eventType, SQLITE3_TEXT);
    $stmt->bindValue(':detail', $eventDetail, SQLITE3_TEXT);
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    if ($uid !== null) {
        $stmt->bindValue(':uid', $uid, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':uid', null, SQLITE3_NULL);
    }
    $stmt->execute();
    $db->close();
}


// ──────────────────────────────────────────────
// 8. 文件上传安全校验（扩展名 + 大小）
// ──────────────────────────────────────────────

/**
 * 获取危险扩展名列表
 */
function dangerousExtensions() {
    return ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8',
            'phar', 'shtml', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp',
            'sh', 'bash', 'zsh', 'bat', 'cmd', 'exe', 'com', 'msi',
            'dll', 'so', 'htaccess'];
}

/**
 * 校检上传文件名是否安全
 */
function validateUploadFilename($filename) {
    if (!isInternetMode()) return [true, ''];

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // 无扩展名视为安全（可能是 .gitignore 这类文件）
    if ($ext === '') return [true, ''];

    // 检查危险扩展名
    if (in_array($ext, dangerousExtensions())) {
        return [false, '外网模式下禁止上传 ' . strtoupper($ext) . ' 类型文件'];
    }

    // 禁止双扩展名伪装（如 file.php.jpg 实际可能被某些服务器当作 php 执行）
    $parts = explode('.', $filename);
    if (count($parts) > 2) {
        $secondExt = strtolower($parts[count($parts) - 2]);
        if (in_array($secondExt, dangerousExtensions())) {
            return [false, '检测到危险的双扩展名文件'];
        }
    }

    return [true, ''];
}


// ──────────────────────────────────────────────
// 9. Session 安全增强
// ──────────────────────────────────────────────

function enhanceSessionSecurity() {
    if (!isInternetMode()) return;

    // Secure cookie（仅 HTTPS 时设置）
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');

    // 外网模式 Session 30 分钟超时
    $sessionTimeout = 1800; // 30 分钟
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $sessionTimeout) {
        // 超时 → 销毁 Session
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
        session_destroy();

        // 仅管理后台页面要求重新登录，前端页面看文件不需要登录
        if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) {
            header('Location: login.php?error=timeout');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}


// ──────────────────────────────────────────────
// 10. 获取真实客户端 IP
// ──────────────────────────────────────────────

function getClientIp() {
    // 从代理链中获取真实 IP
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}


// ──────────────────────────────────────────────
// 11. 下载频率限制（外网模式防滥用）
// ──────────────────────────────────────────────

function checkDownloadRateLimit() {
    if (!isInternetMode()) return null;

    $ip = getClientIp();
    $config = getConfig('download_rate_limit') ?: 30; // 默认每分钟 30 次

    if (rateLimitCheck($ip, 'download', $config, 60)) {
        return '下载请求过于频繁，请稍后再试。';
    }

    return null;
}


// ──────────────────────────────────────────────
// 12. 全量安全检查入口
// ──────────────────────────────────────────────

/**
 * 拦截对敏感文件的直接访问
 * 注意：此防护仅在请求经过 PHP 时生效。
 * Nginx 用户必须额外在 server 块中配置 deny 规则（见 README）。
 */
function blockSensitiveFiles() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $uri = parse_url($uri, PHP_URL_PATH); // 去掉 query string
    $uri = rtrim($uri, '/');
    $basename = basename($uri);

    // 拦截 .db 数据库文件
    if (preg_match('/\.db$/i', $basename)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        die('403 Forbidden — 数据库文件禁止直接访问');
    }

    // 拦截配置文件
    if (in_array($basename, array('config.json', 'config.php', '.htaccess', 'web.config'))) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        die('403 Forbidden — 配置文件禁止直接访问');
    }

    // 拦截核心 PHP 文件
    if (in_array($basename, array('functions.php', 'security.php'))) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        die('403 Forbidden — 核心文件禁止直接访问');
    }
}

/**
 * 页面加载时调用，执行所有安全检测
 * @param bool $isAdminPage 是否为管理后台页面
 */
function securityBootstrap($isAdminPage = false) {
    // 始终拦截敏感文件（不区分内外网，这是基础安全）
    blockSensitiveFiles();

    sendSecurityHeaders();
    checkIpAccess();

    if ($isAdminPage) {
        enhanceSessionSecurity();
    }
}


// ──────────────────────────────────────────────
// 13. 管理端 — 安全事件查看接口
// ──────────────────────────────────────────────

function getAuditLogs($limit = 100, $offset = 0) {
    $db = new SQLite3(getAppDbPath());
    $stmt = $db->prepare('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $logs = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $logs[] = $row;
    }
    $db->close();
    return $logs;
}

function getIpRules() {
    $db = new SQLite3(getAppDbPath());
    $result = $db->query('SELECT * FROM ip_rules ORDER BY created_at DESC');
    $rules = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rules[] = $row;
    }
    $db->close();
    return $rules;
}

function addIpRule($ip, $type, $note = '') {
    $db = new SQLite3(getAppDbPath());
    $stmt = $db->prepare('INSERT INTO ip_rules (ip_address, rule_type, note) VALUES (:ip, :type, :note)');
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    $stmt->bindValue(':note', $note, SQLITE3_TEXT);
    $stmt->execute();
    $db->close();
}

function removeIpRule($id) {
    $db = new SQLite3(getAppDbPath());
    $stmt = $db->prepare('DELETE FROM ip_rules WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    $db->close();
}

function clearRateLimits() {
    $db = new SQLite3(getAppDbPath());
    $db->exec('DELETE FROM rate_limits');
    $db->close();
}

function getLoginAttempts($limit = 50) {
    $db = new SQLite3(getAppDbPath());
    $stmt = $db->prepare('SELECT * FROM login_attempts ORDER BY attempted_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $attempts = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $attempts[] = $row;
    }
    $db->close();
    return $attempts;
}
