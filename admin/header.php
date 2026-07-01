    <div class="main-content">
        <div class="page-header">
            <div>
                <h1><?php echo $pageTitle; ?></h1>
                <span style="font-size:12px;opacity:0.8;">
                    👤 <?php echo htmlspecialchars($_SESSION['username'] ?? '用户'); ?>
                    （<?php echo htmlspecialchars($_SESSION['role_name'] ?? '未知'); ?>）
                </span>
            </div>
            <div>
                <a href="../index.php" target="_blank" class="btn-logout" style="margin-right:8px;">📂 浏览文件</a>
                <a href="login.php?action=logout" class="btn-logout">退出登录</a>
            </div>
        </div>