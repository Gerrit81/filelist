<?php
require_once __DIR__ . '/init.php';
requirePermission('dashboard');

// 处理强制刷新缓存
$statsRefreshed = false;
$cacheCleared = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_stats'])) {
    verifyCsrf();
    refreshStats();
    $statsRefreshed = true;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all_cache'])) {
    verifyCsrf();
    clearDirCache();      // 清空所有目录列表缓存
    clearStatsCache();    // 清空统计缓存
    $cacheCleared = true;
}

$pageTitle = '📊 控制面板';
$downloadCount = getDownloadCount();
$totalSize = getTotalSize();
$fileCount = getFileCount();
$todayDownloads = getTodayDownloadCount();

// 排行榜数据（来自 SQLite，轻量查询）
$downloadRanking = getDownloadRanking(10);
$ipRanking = getIpRanking(10);
$recentDownloads = getRecentDownloads(8);
?>
<?php require __DIR__ . '/layout.php'; ?>
        
        <?php if ($statsRefreshed): ?>
        <div class="msg-success" style="margin-bottom: 20px;">✅ 文件统计缓存已刷新！</div>
        <?php endif; ?>
        <?php if ($cacheCleared): ?>
        <div class="msg-success" style="margin-bottom: 20px;">✅ 所有目录缓存和统计缓存已清空！下次访问时将重新扫描。</div>
        <?php endif; ?>

        <!-- 顶部统计卡片 -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px;">
            <div class="content-box" style="text-align: center; padding: 20px;">
                <div style="font-size: 32px; margin-bottom: 8px;">📁</div>
                <div style="font-size: 26px; font-weight: 700; color: #667eea; margin-bottom: 4px;"><?php echo $fileCount; ?></div>
                <div style="color: #6c757d; font-size: 13px;">文件总数</div>
            </div>
            <div class="content-box" style="text-align: center; padding: 20px;">
                <div style="font-size: 32px; margin-bottom: 8px;">💾</div>
                <div style="font-size: 26px; font-weight: 700; color: #667eea; margin-bottom: 4px;"><?php echo formatSize($totalSize); ?></div>
                <div style="color: #6c757d; font-size: 13px;">存储空间</div>
            </div>
            <div class="content-box" style="text-align: center; padding: 20px;">
                <div style="font-size: 32px; margin-bottom: 8px;">📥</div>
                <div style="font-size: 26px; font-weight: 700; color: #667eea; margin-bottom: 4px;"><?php echo $downloadCount; ?></div>
                <div style="color: #6c757d; font-size: 13px;">总下载次数</div>
            </div>
            <div class="content-box" style="text-align: center; padding: 20px;">
                <div style="font-size: 32px; margin-bottom: 8px;">📅</div>
                <div style="font-size: 26px; font-weight: 700; color: #667eea; margin-bottom: 4px;"><?php echo $todayDownloads; ?></div>
                <div style="color: #6c757d; font-size: 13px;">今日下载</div>
            </div>
        </div>

        <!-- 双栏布局：下载排行 + IP 排行 -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
            <!-- 下载文件排行 -->
            <div class="content-box" style="padding: 20px;">
                <h3 style="margin: 0 0 16px 0; font-size: 16px;">🏆 下载文件排行</h3>
                <?php if (empty($downloadRanking)): ?>
                    <p style="color: #999; text-align: center; padding: 30px 0; font-size: 13px;">暂无下载数据</p>
                <?php else: ?>
                    <div style="max-height: 380px; overflow-y: auto;">
                        <table style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th style="width: 50px; text-align: center;">#</th>
                                    <th>文件名</th>
                                    <th style="width: 70px; text-align: center;">次数</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($downloadRanking as $item): ?>
                                <tr>
                                    <td style="text-align: center;">
                                        <?php if ($rank <= 3): ?>
                                            <span style="font-size: 18px;"><?php echo ['🥇','🥈','🥉'][$rank-1]; ?></span>
                                        <?php else: ?>
                                            <span style="color: #999;"><?php echo $rank; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span title="<?php echo htmlspecialchars($item['file_path']); ?>"><?php echo htmlspecialchars($item['file_name']); ?></span>
                                    </td>
                                    <td style="text-align: center; font-weight: 600; color: #667eea;"><?php echo $item['cnt']; ?></td>
                                </tr>
                                <?php $rank++; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- IP 访问排行 -->
            <div class="content-box" style="padding: 20px;">
                <h3 style="margin: 0 0 16px 0; font-size: 16px;">🌐 访问 IP 排行</h3>
                <?php if (empty($ipRanking)): ?>
                    <p style="color: #999; text-align: center; padding: 30px 0; font-size: 13px;">暂无访问数据</p>
                <?php else: ?>
                    <div style="max-height: 380px; overflow-y: auto;">
                        <table style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th style="width: 50px; text-align: center;">#</th>
                                    <th>IP 地址</th>
                                    <th style="width: 70px; text-align: center;">次数</th>
                                    <th style="width: 130px; text-align: right;">最后访问</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($ipRanking as $item): ?>
                                <tr>
                                    <td style="text-align: center;">
                                        <?php if ($rank <= 3): ?>
                                            <span style="font-size: 18px;"><?php echo ['🥇','🥈','🥉'][$rank-1]; ?></span>
                                        <?php else: ?>
                                            <span style="color: #999;"><?php echo $rank; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($item['ip_address']); ?></td>
                                    <td style="text-align: center; font-weight: 600; color: #667eea;"><?php echo $item['cnt']; ?></td>
                                    <td style="text-align: right; font-size: 11px; color: #999;"><?php echo substr($item['last_time'], 0, 16); ?></td>
                                </tr>
                                <?php $rank++; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 最近下载 + 快捷操作 -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <!-- 最近下载 -->
            <div class="content-box" style="padding: 20px;">
                <h3 style="margin: 0 0 16px 0; font-size: 16px;">🕐 最近下载</h3>
                <?php if (empty($recentDownloads)): ?>
                    <p style="color: #999; text-align: center; padding: 30px 0; font-size: 13px;">暂无下载记录</p>
                <?php else: ?>
                    <div style="max-height: 320px; overflow-y: auto;">
                        <table style="font-size: 12px;">
                            <thead>
                                <tr>
                                    <th>文件名</th>
                                    <th style="width: 110px;">IP</th>
                                    <th style="width: 130px; text-align: right;">时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentDownloads as $item): ?>
                                <tr>
                                    <td style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;" title="<?php echo htmlspecialchars($item['file_name']); ?>"><?php echo htmlspecialchars($item['file_name']); ?></td>
                                    <td style="font-family: monospace; font-size: 11px;"><?php echo htmlspecialchars($item['ip_address']); ?></td>
                                    <td style="text-align: right; font-size: 11px; color: #999;"><?php echo substr($item['download_time'], 0, 16); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 快捷操作 + 刷新 -->
            <div>
                <div class="content-box" style="padding: 20px; margin-bottom: 16px;">
                    <h3 style="margin: 0 0 16px 0; font-size: 16px;">⚡ 快捷操作</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php if (hasPermission('upload')): ?>
                        <a href="upload.php" class="btn-quick">📤 上传文件</a>
                        <?php endif; ?>
                        <?php if (hasPermission('files')): ?>
                        <a href="files.php" class="btn-quick">📂 文件管理</a>
                        <?php endif; ?>
                        <?php if (hasPermission('downloads')): ?>
                        <a href="downloads.php" class="btn-quick">📋 下载记录</a>
                        <?php endif; ?>
                        <?php if (hasPermission('hidden')): ?>
                        <a href="hidden.php" class="btn-quick">🙈 隐藏文件</a>
                        <?php endif; ?>
                        <?php if (hasPermission('settings')): ?>
                        <a href="settings.php" class="btn-quick">⚙️ 系统设置</a>
                        <?php endif; ?>
                        <?php if (hasPermission('users')): ?>
                        <a href="users.php" class="btn-quick">👥 用户管理</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="content-box" style="padding: 20px;">
                    <h3 style="margin: 0 0 12px 0; font-size: 16px;">🔄 数据缓存</h3>
                    <p style="font-size: 13px; color: #888; margin-bottom: 14px; line-height: 1.6;">
                        文件数量和存储空间统计每 <strong>30 分钟</strong> 自动刷新一次（避免扫描整个文件系统）。<br>
                        上传或删除文件后可手动刷新。<br>
                        <span style="color:#e67e22;">⚠ 如遇文件名乱码 / 显示异常，请使用「清空所有缓存」。</span>
                    </p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <form method="post" style="display: inline;">
                            <button type="submit" name="refresh_stats" value="1" class="btn-primary" style="background: #6c757d;">🔄 立即刷新统计</button>
                        </form>
                        <form method="post" style="display: inline;">
                            <button type="submit" name="clear_all_cache" value="1" class="btn-primary" style="background: #e67e22;">🗑 清空所有缓存</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <style>
        .btn-quick {
            display: inline-block;
            padding: 8px 16px;
            background: #f0f2f5;
            border-radius: 8px;
            text-decoration: none;
            color: #495057;
            font-size: 13px;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .btn-quick:hover {
            background: #e8eaf6;
            border-color: #667eea;
            color: #667eea;
        }
    </style>
</body>
</html>