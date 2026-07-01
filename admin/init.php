<?php
require_once __DIR__ . '/../functions.php';

initDirectories();
initAppDB();

$sessionDir = getConfig('session_dir');
session_save_path($sessionDir);
// 增强 Session 安全性
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();

// ── CSRF 防护 ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verifyCsrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('CSRF 验证失败，请刷新页面重试。');
    }
    return true;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function userPermissions() {
    if (!isLoggedIn()) return getDefaultPermissionSet();
    if (!isset($_SESSION['user_permissions'])) return getDefaultPermissionSet();
    $p = $_SESSION['user_permissions'];
    if (is_string($p)) $p = json_decode($p, true);
    return is_array($p) ? $p : getDefaultPermissionSet();
}

function hasPermission($perm) {
    $perms = userPermissions();
    return isset($perms[$perm]) && $perms[$perm] === true;
}

function currentUserMaxUpload() {
    return isLoggedIn() ? (int)$_SESSION['max_upload_size'] : 0;
}

function currentUserAllowedFolders() {
    if (!isLoggedIn()) return array();
    $folders = isset($_SESSION['allowed_folders']) ? $_SESSION['allowed_folders'] : '[]';
    if (is_string($folders)) {
        $folders = json_decode($folders, true) ?: array();
    }
    // hasPermission('roles') 代表有全部管理权限 → 不限文件夹
    return hasPermission('roles') ? array() : $folders;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requirePermission($perm) {
    requireLogin();
    if (!hasPermission($perm)) {
        // 清除 session 防止死循环，跳回登录页
        $_SESSION = array();
        session_destroy();
        header('Location: login.php?error=no_permission');
        exit;
    }
}

// 向后兼容：旧 admin 角色拥有 roles 权限等价于管理员
function isAdmin() {
    return hasPermission('roles') || hasPermission('users') || hasPermission('settings');
}
