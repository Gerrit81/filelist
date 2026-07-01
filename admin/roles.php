<?php
require_once __DIR__ . '/init.php';
requirePermission('roles');

$pageTitle = '🎭 角色管理';
$message = '';
$messageType = 'success';

// 权限中文名映射
$permLabels = array(
    'dashboard'     => '控制面板',
    'upload'        => '文件上传',
    'files'         => '文件管理（查看）',
    'files_rename'  => '文件管理（重命名）',
    'files_delete'  => '文件管理（删除）',
    'downloads'     => '下载历史',
    'hidden'        => '隐藏文件管理',
    'settings'      => '系统设置',
    'users'         => '用户管理',
    'roles'         => '角色管理',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    // 添加角色
    if (isset($_POST['add_role'])) {
        $name = trim($_POST['role_name'] ?? '');
        $perms = array();
        foreach (array_keys($permLabels) as $key) {
            $perms[$key] = isset($_POST['perm_' . $key]) && $_POST['perm_' . $key] === '1';
        }
        if (empty($name)) {
            $message = '角色名称不能为空';
            $messageType = 'error';
        } else {
            addRole($name, $perms);
            $message = '角色 "' . htmlspecialchars($name) . '" 已创建';
        }
    }

    // 编辑角色
    if (isset($_POST['edit_role'])) {
        $roleId = intval($_POST['role_id']);
        $name = trim($_POST['role_name'] ?? '');
        $perms = array();
        foreach (array_keys($permLabels) as $key) {
            $perms[$key] = isset($_POST['perm_' . $key]) && $_POST['perm_' . $key] === '1';
        }
        if (empty($name)) {
            $message = '角色名称不能为空';
            $messageType = 'error';
        } else {
            updateRole($roleId, $name, $perms);
            $message = '角色已更新';
        }
    }

    // 删除角色
    if (isset($_POST['delete_role'])) {
        $roleId = intval($_POST['role_id']);
        $result = deleteRole($roleId);
        if ($result === true) {
            $message = '角色已删除';
        } else {
            $message = $result;
            $messageType = 'error';
        }
    }
}

$roles = getRoles();
$allPerms = getDefaultPermissionSet();
?>
<?php require __DIR__ . '/layout.php'; ?>

        <div class="content-box">
            <h3>已有角色</h3>

            <?php if ($message): ?>
                <div class="msg-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (empty($roles)): ?>
                <div style="text-align:center;padding:30px;color:#888;">暂无角色，请在下方创建</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>名称</th>
                            <th>权限摘要</th>
                            <th>用户数</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role):
                            $enabledPerms = array_keys(array_filter($role['permissions']));
                            $userCount = 0;
                            // 简单计数
                            $db = new SQLite3(getAppDbPath());
                            $userCount = $db->querySingle('SELECT COUNT(*) FROM users WHERE role_id = ' . (int)$role['id']);
                            $db->close();
                        ?>
                        <tr>
                            <td><?php echo $role['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($role['name']); ?></strong></td>
                            <td style="font-size:12px;color:#666;">
                                <?php echo !empty($enabledPerms) ? implode('、', array_intersect_key($permLabels, array_flip($enabledPerms))) : '<span style="color:#ccc;">无权限</span>'; ?>
                            </td>
                            <td><?php echo (int)$userCount; ?> 人</td>
                            <td>
                                <button type="button" class="btn-sm btn-edit" onclick="openRoleEdit(<?php echo htmlspecialchars(json_encode($role, JSON_UNESCAPED_UNICODE)); ?>)">编辑</button>
                                <?php if ((int)$userCount === 0 && count($roles) > 1): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('确定删除此角色？')">
                                    <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                    <button type="submit" name="delete_role" class="btn-sm btn-del">删除</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h3 class="divider">添加角色</h3>
            <form method="post" id="addRoleForm">
                <div class="form-item">
                    <label for="role_name_add">角色名称</label>
                    <input type="text" id="role_name_add" name="role_name" placeholder="如：高级操作员" required>
                </div>
                <div class="form-item">
                    <label>权限设置</label>
                    <div class="perm-grid">
                        <?php foreach ($permLabels as $key => $label): ?>
                        <label class="perm-item">
                            <input type="checkbox" name="perm_<?php echo $key; ?>" value="1">
                            <span><?php echo $label; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" name="add_role" class="btn-primary">添加角色</button>
            </form>

            <!-- 编辑弹窗 -->
            <div class="modal-overlay" id="editRoleModal" style="display:none;">
                <div class="modal-box" style="width:560px;">
                    <div class="modal-header">
                        <h4>编辑角色</h4>
                        <button type="button" class="modal-close" onclick="closeRoleEdit()">×</button>
                    </div>
                    <form method="post" id="editRoleForm">
                        <input type="hidden" name="role_id" id="edit_role_id">
                        <div class="form-item">
                            <label for="edit_role_name">角色名称</label>
                            <input type="text" id="edit_role_name" name="role_name" required>
                        </div>
                        <div class="form-item">
                            <label>权限设置</label>
                            <div class="perm-grid" id="editPermGrid">
                                <?php foreach ($permLabels as $key => $label): ?>
                                <label class="perm-item">
                                    <input type="checkbox" name="perm_<?php echo $key; ?>" value="1" id="edit_perm_<?php echo $key; ?>">
                                    <span><?php echo $label; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="submit" name="edit_role" class="btn-primary">保存修改</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openRoleEdit(role) {
        document.getElementById('edit_role_id').value = role.id;
        document.getElementById('edit_role_name').value = role.name;
        <?php foreach ($permLabels as $key => $_): ?>
        document.getElementById('edit_perm_<?php echo $key; ?>').checked = !!(role.permissions && role.permissions['<?php echo $key; ?>']);
        <?php endforeach; ?>
        document.getElementById('editRoleModal').style.display = 'flex';
    }
    function closeRoleEdit() {
        document.getElementById('editRoleModal').style.display = 'none';
    }
    document.getElementById('editRoleModal').addEventListener('click', function(e) {
        if (e.target === this) closeRoleEdit();
    });
    </script>
</body>
</html>