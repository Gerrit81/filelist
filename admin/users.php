<?php
require_once __DIR__ . '/init.php';
requirePermission('users');

$pageTitle = '👥 用户管理';
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    // 添加用户
    if (isset($_POST['add_user'])) {
        $password = trim($_POST['user_password'] ?? '');
        $roleId = isset($_POST['role_id']) ? intval($_POST['role_id']) : 2;
        $maxUpload = isset($_POST['max_upload_size']) ? intval($_POST['max_upload_size']) : 0;
        $username = trim($_POST['username'] ?? '');
        $allowedFolders = isset($_POST['allowed_folders']) ? $_POST['allowed_folders'] : array();
        if (is_string($allowedFolders)) {
            $allowedFolders = array_filter(array_map('trim', explode(',', $allowedFolders)));
        }

        if (empty($password)) {
            $message = '密码不能为空';
            $messageType = 'error';
        } else {
            addUser($password, $roleId, $maxUpload, $allowedFolders, $username);
            $message = '用户添加成功';
        }
    }

    // 编辑用户
    if (isset($_POST['edit_user'])) {
        $userId = intval($_POST['user_id']);
        $data = array();
        $data['username'] = trim($_POST['username'] ?? '');

        if (!empty($_POST['user_password'])) {
            $data['password'] = $_POST['user_password'];
        }
        if (isset($_POST['role_id'])) {
            $data['role_id'] = intval($_POST['role_id']);
        }
        if (isset($_POST['max_upload_size'])) {
            $data['max_upload_size'] = intval($_POST['max_upload_size']);
        }
        $allowedFolders = isset($_POST['allowed_folders']) ? $_POST['allowed_folders'] : array();
        if (is_string($allowedFolders)) {
            $allowedFolders = array_filter(array_map('trim', explode(',', $allowedFolders)));
        }
        $data['allowed_folders'] = $allowedFolders;

        updateUser($userId, $data);
        $message = '用户已更新';
    }

    // 删除用户
    if (isset($_POST['delete_user'])) {
        $userId = intval($_POST['user_id']);
        $result = deleteUser($userId);
        if ($result === true) {
            $message = '用户已删除';
        } else {
            $message = $result;
            $messageType = 'error';
        }
    }
}

$users = getUsers();
$roles = getRoles();
$allFolders = getAllFolders();
?>
<?php require __DIR__ . '/layout.php'; ?>

        <div class="content-box">
            <h3>用户列表</h3>

            <?php if ($message): ?>
                <div class="msg-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>标识名</th>
                        <th>角色</th>
                        <th>上传大小限制</th>
                        <th>允许文件夹</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo $u['id']; ?></td>
                        <td><?php echo htmlspecialchars($u['username'] ?: '-'); ?></td>
                        <td>
                            <span class="role-badge <?php echo ($u['role_id'] == 1) ? 'role-admin' : 'role-operator'; ?>">
                                <?php echo htmlspecialchars($u['role_name'] ?? '未知'); ?>
                            </span>
                        </td>
                        <td><?php echo $u['max_upload_size'] > 0 ? formatSize($u['max_upload_size']) : '不限制'; ?></td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo !empty($u['allowed_folders']) ? htmlspecialchars(implode(', ', $u['allowed_folders'])) : '全部'; ?>"><?php echo !empty($u['allowed_folders']) ? htmlspecialchars(implode(', ', array_slice($u['allowed_folders'], 0, 3))) . (count($u['allowed_folders']) > 3 ? ' ...' : '') : '全部'; ?></td>
                        <td><?php echo $u['created_at']; ?></td>
                        <td>
                            <button type="button" class="btn-sm btn-edit" onclick="openEdit(<?php 
                                $safeUser = $u;
                                unset($safeUser['password_hash'], $safeUser['role_permissions']);  // 不暴露敏感数据到前端
                                echo htmlspecialchars(json_encode($safeUser)); 
                            ?>)">编辑</button>
                            <?php if ($u['id'] !== ($_SESSION['user_id'] ?? 0)): ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('确定删除此用户？')">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="delete_user" class="btn-sm btn-del">删除</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3 class="divider">添加用户</h3>
            <form method="post">
                <input type="hidden" name="user_id" value="">
                <div class="user-form-grid">
                    <div class="form-item">
                        <label for="username_add">标识名（可选）</label>
                        <input type="text" id="username_add" name="username" placeholder="如：张三">
                    </div>
                    <div class="form-item">
                        <label for="user_password_add">密码 *</label>
                        <input type="password" id="user_password_add" name="user_password" placeholder="设置登录密码" required>
                    </div>
                    <div class="form-item">
                        <label for="role_id_add">角色</label>
                        <select id="role_id_add" name="role_id">
                            <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo $r['id'] == 2 ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-item">
                        <label for="max_upload_size_add">上传大小限制（字节，0=不限制）</label>
                        <input type="number" id="max_upload_size_add" name="max_upload_size" value="0" min="0" step="1" placeholder="0 表示不限制">
                        <span style="font-size:11px;color:#999;">例：104857600 = 100MB，1073741824 = 1GB</span>
                    </div>
                    <div class="form-item" style="grid-column: 1 / -1;">
                        <label for="allowed_folders_add">允许上传的文件夹（逗号分隔，留空=全部）</label>
                        <input type="text" id="allowed_folders_add" name="allowed_folders" placeholder="如：public, images">
                        <span style="font-size:11px;color:#999;">
                            示例：
                            <?php $showCount = min(5, count($allFolders)); ?>
                            <?php for ($i = 0; $i < $showCount; $i++): ?>
                                <code style="background:#f0f0f0;padding:1px 4px;border-radius:3px;margin:0 2px;"><?php echo htmlspecialchars($allFolders[$i]['path']); ?></code>
                            <?php endfor; ?>
                            <?php if (count($allFolders) > 5): ?>
                                <details style="display:inline;margin-left:4px;">
                                    <summary style="cursor:pointer;color:#667eea;">查看全部 (<?php echo count($allFolders); ?>)</summary>
                                    <span style="display:block;margin-top:4px;">
                                        <?php for ($i = 5; $i < count($allFolders); $i++): ?>
                                            <code style="background:#f0f0f0;padding:1px 4px;border-radius:3px;margin:0 2px;"><?php echo htmlspecialchars($allFolders[$i]['path']); ?></code>
                                        <?php endfor; ?>
                                    </span>
                                </details>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <button type="submit" name="add_user" class="btn-primary">添加用户</button>
            </form>

            <!-- 编辑弹窗 -->
            <div class="modal-overlay" id="editModal" style="display:none;">
                <div class="modal-box">
                    <div class="modal-header">
                        <h4>编辑用户</h4>
                        <button type="button" class="modal-close" onclick="closeEdit()">×</button>
                    </div>
                    <form method="post" id="editForm">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="form-item">
                            <label for="edit_username">标识名</label>
                            <input type="text" id="edit_username" name="username">
                        </div>
                        <div class="form-item">
                            <label for="edit_password">新密码（留空不修改）</label>
                            <input type="password" id="edit_password" name="user_password" placeholder="留空则不修改密码">
                        </div>
                        <div class="form-item">
                            <label for="edit_role">角色</label>
                            <select id="edit_role" name="role_id">
                                <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-item">
                            <label for="edit_max_upload">上传大小限制（字节，0=不限制）</label>
                            <input type="number" id="edit_max_upload" name="max_upload_size" value="0" min="0" step="1">
                        </div>
                        <div class="form-item">
                            <label for="edit_folders">允许上传的文件夹（逗号分隔，留空=全部）</label>
                            <input type="text" id="edit_folders" name="allowed_folders">
                        </div>
                        <button type="submit" name="edit_user" class="btn-primary">保存修改</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openEdit(user) {
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_username').value = user.username || '';
        document.getElementById('edit_password').value = '';
        document.getElementById('edit_role').value = user.role_id;
        document.getElementById('edit_max_upload').value = user.max_upload_size;
        document.getElementById('edit_folders').value = Array.isArray(user.allowed_folders) ? user.allowed_folders.join(', ') : '';
        document.getElementById('editModal').style.display = 'flex';
    }
    function closeEdit() {
        document.getElementById('editModal').style.display = 'none';
    }
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEdit();
    });
    </script>
</body>
</html>