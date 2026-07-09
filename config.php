<?php
return array(
    'site_name' => '文件浏览器',
    'site_subtitle' => '轻量级文件目录浏览系统',
    'data_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'data',
    'thumb_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'thumbs',
    'session_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'session',
    'max_upload_size' => 0,
    'hidden_files' => array(),
    'app_version' => '2.9.6',
    'icon_scheme' => 'emoji',
    'svg_icon_style' => 'material',
    'office_preview_mode' => 'off',
    'libreoffice_path' => '',
    'office_preview_api' => '',
    'nav_links' => array(
        array('name' => '首页', 'url' => '.', 'target' => '_self'),
        array('name' => '管理后台', 'url' => 'admin/', 'target' => '_self'),
    ),

    // ─── 安全模式配置 ────────────────────────
    // 'intranet'  = 内网模式：轻便快捷，仅基础安全
    // 'internet'  = 外网模式：启用全部安全措施
    'security_mode' => 'intranet',

    // 下载速率限制（外网模式，每分钟最大下载次数，0=不限）
    'download_rate_limit' => 30,

    // 登录失败锁定（外网模式）
    'login_max_failures' => 5,       // 最大连续失败次数
    'login_lock_minutes' => 15,      // 锁定时长（分钟）
    'login_rate_per_minute' => 5,    // 每分钟最多登录尝试次数

    // 文件上传限制（外网模式，单位 MB，0=不限）
    'upload_max_size_mb' => 100,

    // 外网模式下是否允许匿名预览（仅查看文件列表，不需登录）
    'internet_allow_anonymous_view' => true,

    // 外网模式是否强制 HTTPS（部署在反向代理后面时关闭此项）
    'internet_force_https' => true,
);
?>