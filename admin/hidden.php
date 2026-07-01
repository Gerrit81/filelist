<?php
require_once __DIR__ . '/init.php';
requirePermission('hidden');

$pageTitle = '🔒 隐藏文件管理';
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $config = loadConfig();
    
    if (isset($_POST['add_hidden'])) {
        $newHidden = trim($_POST['new_hidden']);
        if (!empty($newHidden) && !in_array($newHidden, $config['hidden_files'])) {
            $config['hidden_files'][] = $newHidden;
            saveConfig($config);
            clearDirCache();  // 隐藏规则变更 → 清空所有目录缓存
            $message = '已添加隐藏规则';
        } else {
            $message = '规则已存在或为空';
            $messageType = 'error';
        }
    }
    
    if (isset($_POST['remove_hidden'])) {
        $removeIndex = (int)$_POST['remove_hidden'];
        if (isset($config['hidden_files'][$removeIndex])) {
            array_splice($config['hidden_files'], $removeIndex, 1);
            saveConfig($config);
            clearDirCache();  // 隐藏规则变更 → 清空所有目录缓存
            $message = '已移除隐藏规则';
        }
    }
    
    if (isset($_POST['clear_hidden'])) {
        $config['hidden_files'] = array();
        saveConfig($config);
        clearDirCache();  // 隐藏规则变更 → 清空所有目录缓存
        $message = '已清空所有隐藏规则';
    }
}

$config = loadConfig();
$hiddenFiles = $config['hidden_files'];
?>
<?php require __DIR__ . '/layout.php'; ?>
        
        <div class="content-box">
            <h3>添加隐藏规则</h3>
            
            <?php if ($message): ?>
                <div class="msg-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-item">
                    <label for="new_hidden">文件名或路径（支持通配符 *）</label>
                    <input type="text" id="new_hidden" name="new_hidden" placeholder="例如：*.tmp 或 secret.txt">
                    <div style="font-size: 12px; color: #999; margin-top: 5px;">支持通配符，如 <span style="background: #e8eaf6; color: #3949ab; padding: 5px 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">*.tmp</span> 隐藏所有临时文件，<span style="background: #e8eaf6; color: #3949ab; padding: 5px 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">private/</span> 隐藏 private 目录</div>
                </div>
                <button type="submit" name="add_hidden" class="btn-primary">添加规则</button>
            </form>
            
            <h3 class="divider">当前隐藏规则</h3>
            
            <?php if (empty($hiddenFiles)): ?>
                <div style="text-align: center; padding: 30px; color: #888;">暂无隐藏规则</div>
            <?php else: ?>
                <button class="btn-danger" style="margin-bottom: 20px;" onclick="if(confirm('确定要清空所有隐藏规则吗？')) { document.getElementById('clearForm').submit(); }">清空所有规则</button>
                
                <div style="background: #f8f9fa; border-radius: 8px; padding: 10px;">
                    <?php foreach ($hiddenFiles as $index => $rule): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #e9ecef;">
                            <span style="font-family: monospace; color: #495057;"><?php echo htmlspecialchars($rule); ?></span>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="remove_hidden" value="<?php echo $index; ?>">
                                <button type="submit" class="btn-danger" onclick="return confirm('确定移除此规则？')">移除</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form id="clearForm" method="post">
                <input type="hidden" name="clear_hidden" value="1">
            </form>
        </div>
    </div>
</body>
</html>