<?php
return array(
    'site_name' => '文件浏览器',
    'site_subtitle' => '轻量级文件目录浏览系统',
    'data_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'data',
    'thumb_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'thumbs',
    'session_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'session',
    'max_upload_size' => 0,
    'hidden_files' => array(),
    'app_version' => '2.5.0',
    'icon_scheme' => 'emoji',
    'svg_icon_style' => 'material',
    'office_preview_mode' => 'off',
    'libreoffice_path' => '',
    'office_preview_api' => '',
    'nav_links' => array(
        array('name' => '首页', 'url' => '.', 'target' => '_self'),
        array('name' => '管理后台', 'url' => 'admin/', 'target' => '_self'),
    ),
);
?>