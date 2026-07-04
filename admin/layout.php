<?php
$pageTitle = isset($pageTitle) ? $pageTitle : '管理后台';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; min-height: 100vh; }
        .sidebar { width: 220px; background: #2d3748; color: white; position: fixed; top: 0; left: 0; bottom: 0; padding: 20px; z-index: 1000; }
        .sidebar h2 { font-size: 18px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #4a5568; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar li { margin-bottom: 5px; }
        .sidebar a { display: block; padding: 12px 15px; color: #e2e8f0; text-decoration: none; border-radius: 8px; transition: background 0.2s; }
        .sidebar a:hover, .sidebar a.active { background: #4a5568; }
        .main-content { margin-left: 220px; padding: 20px; position: relative; }
        .page-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .page-header h1 { font-size: 22px; margin: 0; }
        .btn-logout { padding: 8px 16px; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); border-radius: 8px; color: white; text-decoration: none; font-weight: 500; }
        .btn-logout:hover { background: rgba(255,255,255,0.3); }
        .content-box { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .content-box h3 { margin: 0 0 20px 0; color: #333; font-size: 18px; }
        .form-item { margin-bottom: 20px; }
        .form-item label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px; }
        .form-item input, .form-item select, .form-item textarea { width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.3s; background: white; font-family: inherit; }
        .form-item input:focus, .form-item select:focus, .form-item textarea:focus { border-color: #667eea; }
        .btn-primary { padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; color: white; font-size: 14px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-danger { padding: 8px 16px; background: #dc3545; border: none; border-radius: 6px; color: white; font-size: 13px; cursor: pointer; }
        .btn-danger:hover { background: #c82333; }
        .msg-success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .msg-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        table th { background: #f8f9fa; font-weight: 600; color: #555; }
        table tr:hover { background: #f8f9fa; }
        .drop-zone { display: block; border: 2px dashed #e0e0e0; border-radius: 12px; padding: 40px; text-align: center; background: #fafafa; cursor: pointer; transition: all 0.3s; margin-bottom: 20px; }
        .drop-zone:hover { border-color: #667eea; background: #f5f7fa; }
        .drop-zone.drag-over { border-color: #667eea; background: #e8eaf6; }
        .drop-zone .folder-icon { font-size: 48px; margin-bottom: 15px; color: #667eea; }
        .drop-zone p { color: #666; margin: 5px 0; font-size: 14px; }
        .drop-zone input[type="file"] { display: none; }

        /* 文件列表 */
        .file-list-wrap { margin-bottom: 20px; border: 1px solid #e0e0e0; border-radius: 10px; overflow: hidden; }
        .file-list-header { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #f8f9fa; font-size: 13px; color: #555; border-bottom: 1px solid #eee; }
        .file-list-header em { font-style: normal; font-weight: 700; color: #667eea; }
        .file-list-total { flex: 1; text-align: right; color: #888; font-size: 12px; }
        .btn-link { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 13px; padding: 0; text-decoration: underline; }
        .btn-link:hover { color: #a71d2a; }
        .file-list { list-style: none; margin: 0; padding: 0; max-height: 280px; overflow-y: auto; }
        .file-item { display: flex; align-items: center; gap: 10px; padding: 10px 16px; border-bottom: 1px solid #f0f0f0; transition: background 0.15s; }
        .file-item:last-child { border-bottom: none; }
        .file-item:hover { background: #f8f9fa; }
        .file-item-name { flex: 1; font-size: 13px; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .file-item-size { font-size: 12px; color: #999; white-space: nowrap; min-width: 60px; text-align: right; }
        .file-item-remove { width: 24px; height: 24px; border: none; background: #fce4e4; color: #dc3545; border-radius: 50%; cursor: pointer; font-size: 16px; line-height: 1; transition: background 0.2s; flex-shrink: 0; }
        .file-item-remove:hover { background: #f5c6cb; }

        /* 上传按钮 */
        .btn-upload { width: 100%; margin-bottom: 0; }
        .btn-upload:disabled { opacity: 0.45; cursor: not-allowed; transform: none !important; }

        /* 进度条 */
        .progress-wrap { margin-bottom: 20px; }
        .progress-bar { width: 100%; height: 12px; background: #e9ecef; border-radius: 6px; overflow: hidden; }
        .progress-fill { height: 100%; width: 0%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); border-radius: 6px; transition: width 0.2s; }
        .progress-text { display: flex; justify-content: space-between; margin-top: 8px; font-size: 13px; color: #666; }

        /* 上传结果 */
        .upload-result { margin-top: 15px; padding: 15px 18px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e0e0e0; font-size: 13px; }
        .result-list { list-style: none; margin: 8px 0 0; padding: 0; }
        .result-list li { padding: 4px 0; font-size: 13px; border-bottom: 1px solid #f0f0f0; }
        .result-list li:last-child { border-bottom: none; }
        .result-success { color: #155724; }
        .result-failed { color: #721c24; }

        /* 上传消息提示 */
        .upload-msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .upload-msg.msg-success { background: #d4edda; color: #155724; }
        .upload-msg.msg-error { background: #f8d7da; color: #721c24; }
        .divider { margin-top: 30px; padding-top: 25px; border-top: 1px solid #eee; }
        .dir-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; font-size: 13px; color: #666; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { display: inline-block; padding: 8px 16px; margin: 0 4px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #667eea; }
        .pagination a:hover, .pagination a.active { background: #667eea; color: white; border-color: #667eea; }

        /* 角色标签 */
        .role-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .role-admin { background: #e8d5f5; color: #6d28d9; }
        .role-operator { background: #dbeafe; color: #2563eb; }

        /* 用户表单网格 */
        .user-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }

        /* 小按钮 */
        .btn-sm { padding: 5px 12px; border: none; border-radius: 5px; font-size: 12px; cursor: pointer; margin-right: 4px; }
        .btn-edit { background: #e8f0fe; color: #1a73e8; }
        .btn-edit:hover { background: #d2e3fc; }
        .btn-del { background: #fce8e6; color: #c5221f; }
        .btn-del:hover { background: #f8d7da; }

        /* 编辑弹窗 */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: flex; align-items: center; justify-content: center; }
        .modal-box { background: white; padding: 28px; border-radius: 14px; width: 500px; max-width: 90vw; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h4 { margin: 0; font-size: 18px; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #888; line-height: 1; padding: 0; }
        .modal-close:hover { color: #333; }

        /* 文件管理 */
        .file-manager-toolbar { display: flex; gap: 10px; margin-bottom: 15px; align-items: center; flex-wrap: wrap; }
        .file-manager-toolbar .btn-primary { padding: 8px 16px; font-size: 13px; }
        .file-actions form { display: inline; }

        /* 批量操作栏 */
        .bulk-bar { display: inline-flex; gap: 8px; align-items: center; margin-left: auto; padding: 6px 14px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffc107; }
        .bulk-count { font-size: 13px; font-weight: 600; color: #856404; }
        .bulk-bar .btn-danger { font-size: 13px; padding: 6px 14px; }
        .bulk-bar .btn-edit { font-size: 13px; }

        .file-row td:first-child input[type="checkbox"] { width: 16px; height: 16px; accent-color: #667eea; cursor: pointer; }
        table thead th input[type="checkbox"] { width: 16px; height: 16px; accent-color: #667eea; cursor: pointer; }

        /* 文件管理表格 - 固定布局，防止长文件名撑开错位 */
        .file-admin-table { table-layout: fixed; }
        .file-name-col { min-width: 0; }
        .file-name-td { overflow: hidden; }
        .file-admin-name {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .file-admin-name-dir { font-weight: 600; color: #667eea; text-decoration: none; }
        .file-admin-name-dir:hover { text-decoration: underline; color: #5a6fd6; }

        /* 面包屑（文件管理用） */
        .breadcrumbs { margin-bottom: 12px; }
        .breadcrumbs ul { list-style: none; display: flex; flex-wrap: wrap; align-items: center; gap: 4px; padding: 0; }
        .breadcrumbs li { font-size: 14px; }
        .breadcrumbs li a { color: #667eea; text-decoration: none; padding: 4px 8px; border-radius: 4px; }
        .breadcrumbs li a:hover { background: #e8eaf6; }
        .breadcrumbs li.current { color: #333; font-weight: 600; }
        .breadcrumbs .separator { color: #ccc; }

        /* 权限网格 */
        .perm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; margin-top: 4px; }
        .perm-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f8f9fa; border-radius: 6px; cursor: pointer; font-size: 13px; color: #444; transition: background 0.15s; user-select: none; }
        .perm-item:hover { background: #e8eaf6; }
        .perm-item input[type="checkbox"] { width: 16px; height: 16px; accent-color: #667eea; cursor: pointer; }
    </style>
    <script>
    // CSRF 防护：自动给所有 POST 表单注入 token
    document.addEventListener('DOMContentLoaded', function() {
        var token = '<?php echo $_SESSION["csrf_token"]; ?>';
        document.querySelectorAll('form[method="post"]').forEach(function(form) {
            if (!form.querySelector('input[name="csrf_token"]')) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'csrf_token';
                input.value = token;
                form.appendChild(input);
            }
        });
        // 同时给 AJAX 请求设置 header
        var origOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function() {
            this.addEventListener('readystatechange', function() {
                if (this.readyState === 1) {
                    this.setRequestHeader('X-CSRF-TOKEN', token);
                }
            });
            origOpen.apply(this, arguments);
        };
    });
    </script>
</head>
<body>
    <?php require __DIR__ . '/sidebar.php'; ?>
    <?php require __DIR__ . '/header.php'; ?>