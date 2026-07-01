<?php
require_once __DIR__ . '/init.php';
requirePermission('downloads');

$pageTitle = '📥 下载历史';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$history = getDownloadHistory($limit, $offset);
$total = getDownloadCount();
$totalPages = max(1, ceil($total / $limit));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    verifyCsrf();
    deleteDownloadHistory((int)$_POST['delete']);
    header('Location: downloads.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear'])) {
    verifyCsrf();
    clearDownloadHistory();
    header('Location: downloads.php');
    exit;
}
?>
<?php require __DIR__ . '/layout.php'; ?>
        
        <div class="content-box" style="margin-bottom: 20px;">
            总下载次数：<span style="font-size: 24px; font-weight: 700; color: #667eea;"><?php echo $total; ?></span> 次
        </div>
        
        <form method="post" style="display:inline;margin-bottom:20px;" onsubmit="return confirm('确定要清空所有下载记录吗？')">
            <input type="hidden" name="clear" value="1">
            <button type="submit" class="btn-danger">清空记录</button>
        </form>
        
        <div class="content-box">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>文件名</th>
                        <th>文件路径</th>
                        <th>IP 地址</th>
                        <th>下载时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 60px; color: #888;">暂无下载记录</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $record): ?>
                            <tr>
                                <td><?php echo $record['id']; ?></td>
                                <td><?php echo htmlspecialchars($record['file_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['file_path']); ?></td>
                                <td><?php echo htmlspecialchars($record['ip_address']); ?></td>
                                <td><?php echo $record['download_time']; ?></td>
                                <td>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('确定删除此记录？')">
                                        <input type="hidden" name="delete" value="<?php echo $record['id']; ?>">
                                        <button type="submit" class="btn-danger">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="downloads.php?page=<?php echo $i; ?>" <?php echo $i === $page ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>