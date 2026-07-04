<?php
require_once __DIR__ . '/init.php';
requirePermission('settings');

$pageTitle = '🛡️ 安全审计';
$message = '';
$messageType = 'success';

// ── IP 规则管理 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ip_rule'])) {
    verifyCsrf();
    $ip = trim($_POST['ip_address'] ?? '');
    $type = in_array($_POST['rule_type'] ?? '', ['allow', 'block']) ? $_POST['rule_type'] : 'block';
    $note = trim($_POST['note'] ?? '');

    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        addIpRule($ip, $type, $note);
        auditLog('ip_rule_add', "添加 IP {$type} 规则: {$ip}" . ($note ? " ({$note})" : ''));
        $message = "IP 规则已添加";
    } else {
        $message = '请输入有效的 IP 地址';
        $messageType = 'error';
    }
}

if (isset($_GET['remove_ip'])) {
    $id = (int)$_GET['remove_ip'];
    removeIpRule($id);
    auditLog('ip_rule_remove', "删除 IP 规则 #{$id}");
    $message = 'IP 规则已删除';
}

// ── 清空速率限制 ──
if (isset($_POST['clear_rates'])) {
    verifyCsrf();
    clearRateLimits();
    $message = '速率限制记录已清空';
}

// ── 数据 ──
$auditLogs = getAuditLogs(100, 0);
$ipRules = getIpRules();
$loginAttempts = getLoginAttempts(50);
$securityMode = securityMode();
?>

<?php require __DIR__ . '/layout.php'; ?>

<div class="content-box">
    <h3>安全模式状态</h3>
    <div style="background: <?php echo $securityMode === 'internet' ? '#fff3cd' : '#f0fff4'; ?>; border: 1px solid <?php echo $securityMode === 'internet' ? '#ffc107' : '#b7ebbf'; ?>; border-radius: 10px; padding: 16px; margin-bottom: 20px; font-size: 13px; color: #445;">
        <p style="margin: 0; font-weight: 600;">
            当前模式：<?php echo $securityMode === 'internet' ? '🌐 外网模式（高安全性）' : '🏠 内网模式（轻便快捷）'; ?>
        </p>
        <p style="margin: 8px 0 0 0;">
            <?php if ($securityMode === 'internet'): ?>
            已启用：登录限流+锁定、安全响应头、IP 黑白名单、审计日志、文件上传格式校验、下载速率限制。
            <?php else: ?>
            仅启用基础安全（密码认证 + CSRF + 路径防护）。切换到外网模式请在「系统设置」中修改。
            <?php endif; ?>
        </p>
    </div>

    <?php if ($message): ?>
        <div class="msg-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- IP 黑白名单 -->
    <h3 class="divider">📋 IP 访问控制</h3>
    <div style="background:#f0f4ff;border:1px solid #c3d0f0;border-radius:10px;padding:14px;margin-bottom:16px;font-size:13px;color:#445;">
        <p style="margin:0;">💡 <strong>白名单逻辑</strong>：添加一条 allow 规则后，仅白名单中的 IP 可访问。若无白名单条目，仅检查黑名单。此功能仅在外网模式下生效。</p>
    </div>

    <form method="post" style="margin-bottom: 16px;">
        <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 150px;">
                <label for="ip_address">IP 地址</label>
                <input type="text" id="ip_address" name="ip_address" placeholder="如：192.168.1.100" required style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
            </div>
            <div style="width: 120px;">
                <label for="rule_type">规则类型</label>
                <select id="rule_type" name="rule_type" style="width:100%;padding:10px 8px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
                    <option value="block">黑名单</option>
                    <option value="allow">白名单</option>
                </select>
            </div>
            <div style="flex: 1; min-width: 120px;">
                <label for="note">备注</label>
                <input type="text" id="note" name="note" placeholder="可选备注" style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
            </div>
            <div>
                <button type="submit" name="add_ip_rule" class="btn-primary">＋ 添加</button>
            </div>
        </div>
    </form>

    <?php if (!empty($ipRules)): ?>
        <table class="data-table">
            <thead>
                <tr><th>IP 地址</th><th>类型</th><th>备注</th><th>添加时间</th><th>操作</th></tr>
            </thead>
            <tbody>
                <?php foreach ($ipRules as $rule): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($rule['ip_address']); ?></code></td>
                    <td>
                        <span style="padding:2px 10px;border-radius:10px;font-size:12px;background:<?php echo $rule['rule_type'] === 'allow' ? '#d4edda' : '#f8d7da'; ?>;color:<?php echo $rule['rule_type'] === 'allow' ? '#155724' : '#721c24'; ?>;">
                            <?php echo $rule['rule_type'] === 'allow' ? '白名单' : '黑名单'; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($rule['note'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($rule['created_at']); ?></td>
                    <td><a href="?remove_ip=<?php echo $rule['id']; ?>" class="btn-sm btn-del" onclick="return confirm('确定移除此 IP 规则？')">删除</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="text-align:center;padding:20px;color:#888;">暂无 IP 规则</div>
    <?php endif; ?>

    <!-- 登录尝试记录 -->
    <h3 class="divider">🔑 最近登录尝试</h3>
    <?php if (!empty($loginAttempts)): ?>
        <table class="data-table">
            <thead>
                <tr><th>时间</th><th>IP 地址</th><th>结果</th></tr>
            </thead>
            <tbody>
                <?php foreach ($loginAttempts as $att): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i:s', $att['attempted_at']); ?></td>
                    <td><code><?php echo htmlspecialchars($att['ip_address']); ?></code></td>
                    <td>
                        <span style="color:<?php echo $att['success'] ? '#27ae60' : '#e74c3c'; ?>;font-weight:600;">
                            <?php echo $att['success'] ? '✅ 成功' : '❌ 失败'; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="text-align:center;padding:20px;color:#888;">暂无登录记录</div>
    <?php endif; ?>

    <!-- 审计日志 -->
    <h3 class="divider">📝 审计日志（最近 100 条）</h3>
    <?php if (!empty($auditLogs)): ?>
        <table class="data-table">
            <thead>
                <tr><th>时间</th><th>事件类型</th><th>详情</th><th>IP 地址</th><th>用户</th></tr>
            </thead>
            <tbody>
                <?php foreach ($auditLogs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                    <td><code><?php echo htmlspecialchars($log['event_type']); ?></code></td>
                    <td><?php echo htmlspecialchars($log['event_detail'] ?? ''); ?></td>
                    <td><code><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></code></td>
                    <td><?php echo $log['user_id'] ? '#' . $log['user_id'] : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="text-align:center;padding:20px;color:#888;">暂无审计日志（仅外网模式记录）</div>
    <?php endif; ?>

    <!-- 速率限制管理 -->
    <h3 class="divider">⚡ 速率限制</h3>
    <form method="post" style="margin-bottom:16px;">
        <button type="submit" name="clear_rates" class="btn-sm btn-del" style="font-size:14px;">清空所有速率限制记录</button>
    </form>
</div>

</div>
</body>
</html>
