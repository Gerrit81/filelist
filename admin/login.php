<?php
require_once __DIR__ . '/init.php';

// 如果取消防 CSRF 的外网攻击（提前检查）
$lockError = preLoginCheck();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    auditLog('logout', '用户主动登出');
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if (isset($_GET['error']) && $_GET['error'] === 'no_permission') {
    $error = '您没有访问该页面的权限，请重新登录。';
}
if (isset($_GET['error']) && $_GET['error'] === 'timeout') {
    $error = '会话已超时，请重新登录。';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 外网模式：登录前置检查
    $preError = preLoginCheck();
    if ($preError) {
        $error = $preError;
    } else {
        $password = $_POST['password'] ?? '';
        $user = getUserByPassword($password);
        if ($user) {
            logLoginAttempt(true);
            session_regenerate_id(true);  // 防会话固定攻击
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'] ?? '未知';
            $_SESSION['max_upload_size'] = (int)$user['max_upload_size'];
            $_SESSION['allowed_folders'] = $user['allowed_folders'];
            $_SESSION['user_permissions'] = $user['role_permissions'];
            $_SESSION['username'] = $user['username'] ?: ('用户#' . $user['id']);

            auditLog('login', '登录成功 [' . ($_SESSION['username'] ?? '') . ']', $user['id']);

            header('Location: index.php');
            exit;
        } else {
            logLoginAttempt(false);
            auditLog('login_failed', '密码错误');

            // 检查是否即将被锁定，给出提示
            if (isInternetMode() && isLoginLocked(5, 15)) {
                $error = '连续登录失败次数过多，账户已临时锁定，请 15 分钟后再试。';
            } else {
                $error = '密码错误';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - 登录</title>
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Microsoft YaHei', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .login-box { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); width: 400px; }
        .login-box h2 { text-align: center; margin-bottom: 10px; color: #333; }
        .login-box .subtitle { text-align: center; color: #888; font-size: 13px; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; }
        .form-group input { width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.3s; }
        .form-group input:focus { border-color: #667eea; }
        .btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; color: white; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        .btn:hover { transform: translateY(-2px); }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #666; text-decoration: none; }
        .back-link:hover { color: #667eea; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🔒 管理后台</h2>
        <p class="subtitle">输入密码即可登录，不同密码对应不同权限</p>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" placeholder="请输入密码" required autofocus>
            </div>
            <button type="submit" class="btn">登录</button>
            <a href="../" class="back-link">← 返回文件浏览</a>
        </form>
    </div>
</body>
</html>