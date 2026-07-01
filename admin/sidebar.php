<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
    <div class="sidebar">
        <h2>🔧 管理后台</h2>
        <ul>
            <?php if (hasPermission('dashboard')): ?>
            <li><a href="index.php" <?php echo $currentPage === 'index.php' ? 'class="active"' : ''; ?>>控制面板</a></li>
            <?php endif; ?>
            <?php if (hasPermission('downloads')): ?>
            <li><a href="downloads.php" <?php echo $currentPage === 'downloads.php' ? 'class="active"' : ''; ?>>下载历史</a></li>
            <?php endif; ?>
            <?php if (hasPermission('upload')): ?>
            <li><a href="upload.php" <?php echo $currentPage === 'upload.php' ? 'class="active"' : ''; ?>>文件上传</a></li>
            <?php endif; ?>
            <?php if (hasPermission('files')): ?>
            <li><a href="files.php" <?php echo $currentPage === 'files.php' ? 'class="active"' : ''; ?>>文件管理</a></li>
            <?php endif; ?>
            <?php if (hasPermission('hidden')): ?>
            <li><a href="hidden.php" <?php echo $currentPage === 'hidden.php' ? 'class="active"' : ''; ?>>隐藏管理</a></li>
            <?php endif; ?>
            <?php if (hasPermission('users')): ?>
            <li><a href="users.php" <?php echo $currentPage === 'users.php' ? 'class="active"' : ''; ?>>用户管理</a></li>
            <?php endif; ?>
            <?php if (hasPermission('roles')): ?>
            <li><a href="roles.php" <?php echo $currentPage === 'roles.php' ? 'class="active"' : ''; ?>>角色管理</a></li>
            <?php endif; ?>
            <?php if (hasPermission('settings')): ?>
            <li><a href="settings.php" <?php echo $currentPage === 'settings.php' ? 'class="active"' : ''; ?>>系统设置</a></li>
            <?php endif; ?>
            <li><a href="changelog.php" <?php echo $currentPage === 'changelog.php' ? 'class="active"' : ''; ?>>更新日志</a></li>
            <li><a href="login.php?action=logout">退出登录</a></li>
        </ul>
    </div>