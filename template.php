<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件浏览器</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --primary-dark: #5a6fd6;
            --secondary-color: #764ba2;
            --bg-color: #f0f2f5;
            --bg-gradient-end: #e8eaf6;
            --card-bg: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
            --table-header-bg: linear-gradient(135deg, #f8f9fa 0%, #f1f3f4 100%);
            --table-header-color: #495057;
            --table-hover-bg: rgba(102, 126, 234, 0.05);
            --element-bg: #f1f3f4;
            --element-color: #495057;
            --header-color: white;
            --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Microsoft YaHei', sans-serif;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 15px rgba(102, 126, 234, 0.15);
            --shadow-lg: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: var(--font-family);
            background: linear-gradient(135deg, var(--bg-color) 0%, var(--bg-gradient-end) 100%);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            width: 80%;
            margin: 0 auto;
            padding: 20px 0;
            min-width: 900px;
        }

        header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: var(--header-color);
            padding: 16px 24px;
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .nav-links a {
            display: inline-block;
            padding: 8px 18px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-1px);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-title {
            display: flex;
            flex-direction: column;
        }

        header h1 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        header p {
            font-size: 13px;
            opacity: 0.9;
            font-weight: 400;
        }

        .search-bar {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .search-bar input {
            padding: 10px 16px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .search-bar input:focus {
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3), 0 4px 12px rgba(0, 0, 0, 0.15);
            width: 320px;
        }

        .search-bar input::placeholder {
            color: rgba(0, 0, 0, 0.4);
        }

        .search-bar button {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            color: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .search-bar button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .breadcrumbs {
            background: var(--card-bg);
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .breadcrumbs ul {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .breadcrumbs li {
            display: flex;
            align-items: center;
        }

        .breadcrumbs li a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .breadcrumbs li a:hover {
            color: var(--primary-dark);
            background: color-mix(in srgb, var(--primary-color) 10%, transparent);
        }

        .breadcrumbs li.separator {
            color: #adb5bd;
            font-weight: 300;
        }

        .breadcrumbs li.current {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14px;
        }

        .file-table-wrapper {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .file-table {
            width: 100%;
            border-collapse: collapse;
        }

        .file-table thead {
            background: var(--table-header-bg);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .file-table th {
            padding: 14px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: var(--table-header-color);
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
            position: relative;
            transition: background 0.2s;
            letter-spacing: 0.3px;
        }

        .file-table th:hover {
            background: color-mix(in srgb, var(--primary-color) 8%, transparent);
        }

        .file-table th::after {
            content: '';
            display: inline-block;
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-bottom: 4px solid #adb5bd;
            margin-left: 6px;
            vertical-align: middle;
            opacity: 0.6;
            transition: all 0.2s;
        }

        .file-table th.sorted-asc::after {
            border-top: 4px solid var(--primary-color);
            border-bottom: none;
            opacity: 1;
        }

        .file-table th.sorted-desc::after {
            border-bottom: 4px solid var(--primary-color);
            opacity: 1;
        }

        .file-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s;
        }

        .file-table tbody tr:hover {
            background: var(--table-hover-bg);
            transform: scale(1.002);
        }

        .file-table tbody tr:last-child {
            border-bottom: none;
        }

        .file-table td {
            padding: 12px 20px;
            font-size: 14px;
            vertical-align: middle;
        }

        .file-name-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 350px;
        }

        .file-icon {
            font-size: 32px;
            line-height: 1;
            transition: transform 0.2s;
        }

        .file-icon img {
            width: 32px;
            height: 32px;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .file-name-cell:hover .file-icon {
            transform: scale(1.1);
        }

        .file-name {
            cursor: pointer;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .file-name:hover {
            color: var(--primary-color);
            background: rgba(102, 126, 234, 0.1);
        }

        .file-name.folder {
            color: #0078d4;
            font-weight: 600;
        }

        .file-name.folder:hover {
            color: var(--primary-color);
        }

        .file-size {
            white-space: nowrap;
            color: var(--text-secondary);
            width: 130px;
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', monospace;
        }

        .file-type {
            white-space: nowrap;
            color: var(--text-secondary);
            width: 160px;
        }

        .file-mtime {
            white-space: nowrap;
            color: var(--text-secondary);
            width: 170px;
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', monospace;
        }

        .file-actions {
            white-space: nowrap;
            width: 180px;
        }

        .action-btn {
            padding: 6px 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            margin-right: 6px;
            transition: all 0.2s;
            letter-spacing: 0.3px;
        }

        .action-btn-preview {
            background: var(--element-bg);
            color: var(--element-color);
        }

        .action-btn-preview:hover {
            background: #e8eaed;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .action-btn-copy {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .action-btn-copy:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--secondary-color) 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .action-btn-download {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .action-btn-download:hover {
            background: linear-gradient(135deg, #218838 0%, #17a2b8 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(4px);
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 20px;
            max-width: 98%;
            max-height: 95%;
            overflow: hidden;
            transform: scale(0.9) translateY(20px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-overlay.active .modal-content {
            transform: scale(1) translateY(0);
        }

        .modal-header {
            padding: 16px 24px;
            background: var(--table-header-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 70%;
        }

        .modal-close {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 50%;
            background: var(--element-bg);
            color: var(--element-color);
            cursor: pointer;
            font-size: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: #dadce0;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            max-height: calc(95vh - 100px);
            overflow: auto;   /* 允许视频控件溢出显示，不裁掉进度条 */
        }

        .modal-body img,
        .modal-body video {
            max-width: 100%;
            max-height: calc(90vh - 140px);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
        }

        /* 音频元素独立样式：无截断高度，确保控件栏完整可见 */
        .modal-body audio {
            width: 100%;
            min-width: 400px;
            max-width: 600px;
            display: block;
        }

        .modal-body .pdf-container {
            width: 100%;
            height: calc(95vh - 140px);
            overflow: auto;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            background: #f5f5f5;
            border-radius: 12px;
            padding: 0;
        }

        .modal-body .pdf-container embed {
            width: 100%;
            min-width: 1200px;
            min-height: 1500px;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }

        .modal-body pre {
            max-width: 100%;
            max-height: calc(90vh - 140px);
            overflow: auto;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 24px;
            border-radius: 12px;
            font-family: 'Consolas', 'Monaco', 'SF Mono', monospace;
            font-size: 14px;
            white-space: pre-wrap;
            word-break: break-all;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px dashed var(--border-color);
        }

        .empty-state .icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.6;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .empty-state p {
            font-size: 14px;
            max-width: 300px;
            margin: 0 auto;
            line-height: 1.8;
        }

        .loading {
            text-align: center;
            padding: 60px;
            color: var(--text-secondary);
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
        }

        .loading::after {
            content: '';
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid #e8eaed;
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-left: 12px;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #333 0%, #444 100%);
            color: white;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2000;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toast::before {
            content: '✓';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: #28a745;
            border-radius: 50%;
            font-size: 12px;
            font-weight: bold;
        }

        .toast.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(-10px);
        }

        .toast.error {
            background: linear-gradient(135deg, #dc3545 0%, #e85d6a 100%);
        }

        .toast.error::before {
            content: '✗';
            background: rgba(255, 255, 255, 0.3);
        }

        .dir-icon {
            color: #ffd93d;
            filter: drop-shadow(0 2px 4px rgba(255, 217, 61, 0.3));
        }

        footer {
            text-align: center;
            padding: 12px 20px;
            margin-top: 20px;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        footer p {
            font-size: 13px;
            color: var(--text-secondary);
            margin: 4px 0;
            letter-spacing: 0.3px;
        }

        footer p:first-child {
            font-weight: 500;
            color: var(--table-header-color);
        }

        .image-tooltip {
            position: fixed;
            z-index: 1000;
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
            border-radius: 8px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 400px;
            max-height: 300px;
            background: white;
            padding: 4px;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .image-tooltip.active {
            opacity: 1;
            visibility: visible;
        }

        .image-tooltip img {
            width: auto;
            height: auto;
            max-width: 392px;
            max-height: 292px;
            border-radius: 6px;
            object-fit: contain;
        }
        /* Office 预览 / 通用模态框加载动画 */
        .modal-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 40px;
            gap: 16px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        .modal-loading .spinner {
            width: 44px;
            height: 44px;
            border: 4px solid var(--border-color);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        .modal-loading .spinner-text {
            font-size: 15px;
            font-weight: 500;
            letter-spacing: 0.5px;
            color: var(--text-primary);
        }
        .modal-loading .spinner-hint {
            font-size: 12px;
            opacity: 0.6;
        }
        .modal-loading .spinner-error {
            color: #dc3545;
            font-size: 13px;
            text-align: center;
            max-width: 300px;
            line-height: 1.6;
        }
        /* 无法预览的友好提示 */
        .modal-not-supported {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 40px;
            gap: 12px;
            color: var(--text-secondary);
        }
        .modal-not-supported .ns-icon {
            font-size: 64px;
            opacity: 0.5;
        }
        .modal-not-supported .ns-title {
            font-size: 17px;
            font-weight: 600;
            color: var(--text-primary);
        }
        .modal-not-supported .ns-type {
            font-size: 13px;
            padding: 4px 12px;
            background: var(--element-bg);
            border-radius: 6px;
            font-family: monospace;
            letter-spacing: 0.5px;
        }
        .modal-not-supported .ns-hint {
            font-size: 13px;
            opacity: 0.6;
            margin-top: 4px;
        }
        .modal-not-supported .ns-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .modal-not-supported .ns-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 22px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: white;
        }
        .modal-not-supported .ns-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .ns-btn-download {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .ns-btn-copy {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        @media (max-width: 1024px) {
            .container {
                width: 95%;
                min-width: auto;
                padding: 15px;
            }

            .file-table {
                font-size: 13px;
            }

            .file-table th,
            .file-table td {
                padding: 10px 12px;
            }

            .file-name-cell {
                min-width: 200px;
            }

            .action-btn {
                padding: 5px 10px;
                font-size: 11px;
                margin-right: 4px;
            }

            header {
                flex-direction: column;
                align-items: stretch;
            }

            .header-left {
                justify-content: center;
            }

            .search-bar {
                justify-content: center;
            }

            .search-bar input {
                width: 100%;
                max-width: 300px;
            }
        }

        @media (max-width: 768px) {
            header h1 {
                font-size: 18px;
            }

            header p {
                font-size: 12px;
            }

            .search-bar input {
                width: 100%;
                max-width: 250px;
            }

            .file-table th,
            .file-table td {
                padding: 8px 10px;
                font-size: 12px;
            }

            .file-icon {
                font-size: 24px;
            }

            .file-icon img {
                width: 24px;
                height: 24px;
            }
        }
        /* ========== 皮肤主题 ========== */

        /* 暗夜模式 */
        body[data-theme="dark"] {
            --primary-color: #818cf8;
            --primary-dark: #6366f1;
            --secondary-color: #a78bfa;
            --bg-color: #1a1a2e;
            --bg-gradient-end: #16213e;
            --card-bg: #252540;
            --text-primary: #e0e0e0;
            --text-secondary: #a0a0b0;
            --border-color: #3a3a55;
            --table-header-bg: #2d2d44;
            --table-header-color: #c0c0d0;
            --table-hover-bg: rgba(129, 140, 248, 0.08);
            --element-bg: #353550;
            --element-color: #b0b0c0;
            --header-color: #ffffff;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 15px rgba(0,0,0,0.4);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.5);
        }
        body[data-theme="dark"] .search-bar input {
            background: rgba(0, 0, 0, 0.3);
            color: #e0e0e0;
        }
        body[data-theme="dark"] .action-btn-preview:hover {
            background: #454560;
        }
        body[data-theme="dark"] .modal-close:hover {
            background: #454560;
        }

        /* 翡翠绿 */
        body[data-theme="green"] {
            --primary-color: #059669;
            --primary-dark: #047857;
            --secondary-color: #0d9488;
            --bg-color: #ecfdf5;
            --bg-gradient-end: #d1fae5;
            --card-bg: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #6c757d;
            --border-color: #d1fae5;
            --table-header-bg: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            --table-header-color: #495057;
            --table-hover-bg: rgba(5, 150, 105, 0.05);
            --element-bg: #d1fae5;
            --element-color: #495057;
            --header-color: #ffffff;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 15px rgba(5,150,105,0.15);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.1);
        }

        /* 日落橙暖 */
        body[data-theme="orange"] {
            --primary-color: #ea580c;
            --primary-dark: #c2410c;
            --secondary-color: #f97316;
            --bg-color: #fff7ed;
            --bg-gradient-end: #ffedd5;
            --card-bg: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #6c757d;
            --border-color: #fed7aa;
            --table-header-bg: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
            --table-header-color: #495057;
            --table-hover-bg: rgba(234, 88, 12, 0.05);
            --element-bg: #ffedd5;
            --element-color: #495057;
            --header-color: #ffffff;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 15px rgba(234,88,12,0.15);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.1);
        }

        /* 深海蓝 */
        body[data-theme="blue"] {
            --primary-color: #0369a1;
            --primary-dark: #075985;
            --secondary-color: #0284c7;
            --bg-color: #f0f9ff;
            --bg-gradient-end: #e0f2fe;
            --card-bg: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #6c757d;
            --border-color: #bae6fd;
            --table-header-bg: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            --table-header-color: #495057;
            --table-hover-bg: rgba(3, 105, 161, 0.05);
            --element-bg: #e0f2fe;
            --element-color: #495057;
            --header-color: #ffffff;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 15px rgba(3,105,161,0.15);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.1);
        }

        /* ========== 字体切换 ========== */
        body[data-font="default"] { --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Microsoft YaHei', sans-serif; }
        body[data-font="yahei"] { --font-family: 'Microsoft YaHei', '微软雅黑', 'PingFang SC', sans-serif; }
        body[data-font="simsun"] { --font-family: 'SimSun', '宋体', 'STSong', serif; }
        body[data-font="kaiti"] { --font-family: 'KaiTi', '楷体', 'STKaiti', cursive; }
        body[data-font="dengxian"] { --font-family: 'DengXian', '等线', 'PingFang SC', sans-serif; }

        /* ========== 皮肤/字体切换器 ========== */
        .theme-picker-inline {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 8px;
        }
        .theme-picker-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.85);
            white-space: nowrap;
        }
        .theme-colors {
            display: flex;
            gap: 6px;
        }
        .theme-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid rgba(255, 255, 255, 0.5);
            transition: all 0.2s;
            position: relative;
        }
        .theme-dot:hover {
            transform: scale(1.15);
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .theme-dot.active {
            border-color: #fff;
            box-shadow: 0 0 0 2px rgba(255,255,255,0.4);
        }
        .theme-dot[data-value="default"] { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .theme-dot[data-value="dark"] { background: linear-gradient(135deg, #1a1a2e 0%, #252540 100%); }
        .theme-dot[data-value="green"] { background: linear-gradient(135deg, #059669 0%, #0d9488 100%); }
        .theme-dot[data-value="orange"] { background: linear-gradient(135deg, #ea580c 0%, #f97316 100%); }
        .theme-dot[data-value="blue"] { background: linear-gradient(135deg, #0369a1 0%, #0284c7 100%); }

        .font-select-inline {
            padding: 6px 10px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            font-size: 13px;
            background: rgba(255, 255, 255, 0.12);
            color: white;
            cursor: pointer;
            outline: none;
            font-family: var(--font-family);
        }
        .font-select-inline:focus {
            border-color: rgba(255, 255, 255, 0.6);
            background: rgba(255, 255, 255, 0.18);
        }
        .font-select-inline option {
            background: #333;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <div class="header-title">
                    <h1>📁 <?php echo htmlspecialchars(getConfig('site_name')); ?></h1>
                    <p><?php echo htmlspecialchars(getConfig('site_subtitle')); ?></p>
                </div>
            </div>
            <div class="header-right">
                <div class="nav-links">
                    <?php foreach (getConfig('nav_links') as $link): ?>
                        <a href="<?php echo htmlspecialchars($link['url']); ?>" target="<?php echo isset($link['target']) ? htmlspecialchars($link['target']) : '_self'; ?>"><?php echo htmlspecialchars($link['name']); ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="theme-picker-inline">
                    <span class="theme-colors">
                        <span class="theme-dot" data-value="default" title="紫蓝渐变"></span>
                        <span class="theme-dot" data-value="dark" title="暗夜模式"></span>
                        <span class="theme-dot" data-value="green" title="翡翠绿"></span>
                        <span class="theme-dot" data-value="orange" title="日落橙暖"></span>
                        <span class="theme-dot" data-value="blue" title="深海蓝"></span>
                    </span>
                    <select class="font-select-inline" id="fontSelect">
                        <option value="default">系统默认</option>
                        <option value="yahei">微软雅黑</option>
                        <option value="simsun">宋体</option>
                        <option value="kaiti">楷体</option>
                        <option value="dengxian">等线</option>
                    </select>
                </div>
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="搜索文件或目录...">
                    <button id="searchBtn">搜索</button>
                </div>
            </div>
        </header>

        <div class="breadcrumbs" id="breadcrumbs">
            <ul>
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <?php if ($crumb['path'] === $currentPath): ?>
                        <li class="current"><?php echo htmlspecialchars($crumb['name']); ?></li>
                    <?php else: ?>
                        <li><a href="?path=<?php echo urlencode($crumb['path']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a></li>
                        <li class="separator">/</li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>


        <div class="file-table-wrapper">
            <table class="file-table" id="fileTable">
                <thead>
                    <tr>
                        <th data-sort="name">名称</th>
                        <th data-sort="size">大小</th>
                        <th data-sort="type">类型</th>
                        <th data-sort="mtime">修改时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="fileTableBody">
                    <tr><td colspan="5" class="loading">加载中</td></tr>
                </tbody>
            </table>
        </div>

        <div class="empty-state" id="emptyState" style="display: none;">
            <div class="icon">📭</div>
            <h3>目录为空</h3>
            <p>将文件或文件夹放入数据目录即可显示</p>
        </div>

        <footer>
            <p>&copy; 2026 文件浏览器. All rights reserved.</p>
            <p>轻量级文件目录浏览系统 | 基于 PHP + SQLite | v<?php echo htmlspecialchars(getConfig('app_version') ?: '--'); ?></p>
        </footer>

        <div class="image-tooltip" id="imageTooltip"></div>
    </div>

    <div class="modal-overlay" id="previewModal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title" id="modalTitle">预览</span>
                <button class="modal-close" id="modalClose">×</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <div class="toast" id="toast">已复制到剪贴板</div>

    <script>
        const currentPath = '<?php echo htmlspecialchars($currentPath); ?>';
        const officePreviewMode = '<?php echo htmlspecialchars($officePreviewMode ?? 'off'); ?>';
        const fileTableBody = document.getElementById('fileTableBody');
        const emptyState = document.getElementById('emptyState');
        const searchInput = document.getElementById('searchInput');
        const previewModal = document.getElementById('previewModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');
        const modalClose = document.getElementById('modalClose');
        const toast = document.getElementById('toast');
        const sortableThs = document.querySelectorAll('.file-table th[data-sort]');

        let allFiles = [];
        let sortField = localStorage.getItem('filelist_sort_field') || 'name';
        let sortOrder = localStorage.getItem('filelist_sort_order') || 'asc';

        function formatSize(bytes) {
            if (bytes === 0) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
        }

        function getFileIcon(file) {
            if (file.type === 'dir') {
                return '<span class="file-icon dir-icon">📁</span>';
            }
            if (isImage(file)) {
                return '<span class="file-icon file-icon-image">🖼️</span>';
            }
            if (isVideo(file)) {
                return '<span class="file-icon">🎬</span>';
            }
            if (isAudio(file)) {
                return '<span class="file-icon">🎵</span>';
            }
            if (file.mime === 'application/pdf') {
                return '<span class="file-icon">📕</span>';
            }
            if (isOffice(file)) {
                const ext = (file.ext || '').toLowerCase();
                if (ext.startsWith('doc')) return '<span class="file-icon">📘</span>';
                if (ext.startsWith('xls')) return '<span class="file-icon">📗</span>';
                if (ext.startsWith('ppt')) return '<span class="file-icon">📙</span>';
                return '<span class="file-icon">📄</span>';
            }
            if (file.is_code) {
                return '<span class="file-icon">📄</span>';
            }
            if (file.is_text) {
                return '<span class="file-icon">📝</span>';
            }
            return '<span class="file-icon">📎</span>';
        }

        function getFileTypeDescription(file) {
            if (file.type === 'dir') {
                return '文件夹';
            }
            const typeMap = {
                'image/jpeg': 'JPEG 图像',
                'image/png': 'PNG 图像',
                'image/gif': 'GIF 图像',
                'image/webp': 'WebP 图像',
                'image/bmp': 'BMP 图像',
                'video/mp4': 'MP4 视频',
                'video/webm': 'WebM 视频',
                'video/ogg': 'OGG 视频',
                'video/quicktime': 'QuickTime 视频',
                'video/x-msvideo': 'AVI 视频',
                'audio/mpeg': 'MP3 音频',
                'audio/wav': 'WAV 音频',
                'audio/ogg': 'OGG 音频',
                'audio/flac': 'FLAC 音频',
                'text/plain': '文本文件',
                'text/html': 'HTML 文档',
                'text/css': 'CSS 样式表',
                'application/javascript': 'JavaScript 文件',
                'application/json': 'JSON 文件',
                'application/xml': 'XML 文件',
                'application/pdf': 'PDF 文档',
                'application/zip': 'ZIP 压缩文件',
                'application/x-rar-compressed': 'RAR 压缩文件',
                'application/x-7z-compressed': '7Z 压缩文件',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word 文档',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel 表格',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PowerPoint 演示文稿',
                'application/msword': 'Word 文档 (doc)',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word 文档 (docx)',
                'application/vnd.ms-excel': 'Excel 表格 (xls)',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel 表格 (xlsx)',
                'application/vnd.ms-powerpoint': 'PowerPoint 演示文稿 (ppt)',
            };
            if (typeMap[file.mime]) {
                return typeMap[file.mime];
            }
            if (file.ext) {
                return file.ext.toUpperCase() + ' 文件';
            }
            return '文件';
        }

        function renderFiles(files) {
            fileTableBody.innerHTML = '';

            if (files.length === 0) {
                emptyState.style.display = 'block';
                document.querySelector('.file-table-wrapper').style.display = 'none';
                return;
            }

            emptyState.style.display = 'none';
            document.querySelector('.file-table-wrapper').style.display = 'block';

            files.forEach(file => {
                const row = document.createElement('tr');
                row.dataset.path = file.path;

                const nameClass = file.type === 'dir' ? 'folder' : '';
                const fileName = document.createElement('span');
                fileName.className = `file-name ${nameClass}`;
                fileName.textContent = file.name;

                if (isImage(file)) {
                    fileName.dataset.imagePath = encodeURIComponent(file.path);
                }

                fileName.addEventListener('click', () => {
                    if (file.type === 'dir') {
                        window.location.href = `?path=${encodeURIComponent(file.path)}`;
                    } else {
                        previewFile(file);
                    }
                });

                const nameCell = document.createElement('td');
                nameCell.className = 'file-name-cell';
                nameCell.innerHTML = getFileIcon(file);
                nameCell.appendChild(fileName);

                const sizeCell = document.createElement('td');
                sizeCell.className = 'file-size';
                sizeCell.textContent = file.type === 'dir' ? '-' : formatSize(file.size);

                const typeCell = document.createElement('td');
                typeCell.className = 'file-type';
                typeCell.textContent = getFileTypeDescription(file);

                const mtimeCell = document.createElement('td');
                mtimeCell.className = 'file-mtime';
                mtimeCell.textContent = file.mtime_str;

                const actionsCell = document.createElement('td');
                actionsCell.className = 'file-actions';

                if (file.type !== 'dir') {
                    actionsCell.innerHTML = `
                        <button class="action-btn action-btn-preview" data-path="${encodeURIComponent(file.path)}">预览</button>
                        <button class="action-btn action-btn-copy" data-path="${encodeURIComponent(file.path)}">复制链接</button>
                        <button class="action-btn action-btn-download" data-path="${encodeURIComponent(file.path)}">下载</button>
                    `;
                } else {
                    actionsCell.innerHTML = `
                        <button class="action-btn action-btn-copy" data-path="${encodeURIComponent(file.path)}">复制链接</button>
                    `;
                }

                row.appendChild(nameCell);
                row.appendChild(sizeCell);
                row.appendChild(typeCell);
                row.appendChild(mtimeCell);
                row.appendChild(actionsCell);

                fileTableBody.appendChild(row);
            });

            document.querySelectorAll('.action-btn-preview').forEach(btn => {
                btn.addEventListener('click', () => {
                    const path = decodeURIComponent(btn.dataset.path);
                    const file = allFiles.find(f => f.path === path);
                    if (file) previewFile(file);
                });
            });

            document.querySelectorAll('.action-btn-copy').forEach(btn => {
                btn.addEventListener('click', () => {
                    const path = decodeURIComponent(btn.dataset.path);
                    const file = allFiles.find(f => f.path === path);
                    if (file) copyFileLink(file);
                });
            });

            document.querySelectorAll('.action-btn-download').forEach(btn => {
                btn.addEventListener('click', () => {
                    const path = decodeURIComponent(btn.dataset.path);
                    const file = allFiles.find(f => f.path === path);
                    if (file) downloadFile(file);
                });
            });
        }

        // MIME 类型兜底判断 — 旧缓存可能缺少 is_image/is_video/is_audio/is_office 字段
        function isImageMime(mime)           { return (mime || '').startsWith('image/'); }
        function isVideoMime(mime)           { return (mime || '').startsWith('video/'); }
        function isAudioMime(mime)           { return (mime || '').startsWith('audio/'); }
        function isOfficeMime(mime) {
            const officeMimes = [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ];
            return officeMimes.includes(mime);
        }
        // 统一的"是否为某类型文件"判断（优先用缓存字段，缺失时 MIME 兜底）
        function isImage(file)  { return file.is_image  || isImageMime(file.mime); }
        function isVideo(file)  { return file.is_video  || isVideoMime(file.mime); }
        function isAudio(file)  { return file.is_audio  || isAudioMime(file.mime); }
        function isOffice(file) { return file.is_office || isOfficeMime(file.mime); }

        function previewFile(file) {
            modalTitle.textContent = file.name;
            modalBody.innerHTML = '';

            if (isImage(file)) {
                const img = document.createElement('img');
                img.src = `?action=preview&path=${encodeURIComponent(file.path)}`;
                img.alt = file.name;
                modalBody.appendChild(img);
            } else if (file.mime === 'application/pdf') {
                const container = document.createElement('div');
                container.className = 'pdf-container';
                
                const embed = document.createElement('embed');
                embed.src = `?action=preview&path=${encodeURIComponent(file.path)}`;
                embed.type = 'application/pdf';
                embed.style.border = 'none';
                
                container.appendChild(embed);
                modalBody.appendChild(container);
            } else if (isVideo(file)) {
                const video = document.createElement('video');
                video.src = `?action=preview&path=${encodeURIComponent(file.path)}`;
                video.controls = true;
                video.autoplay = false;
                video.preload = 'auto';
                video.style.maxWidth = '100%';
                video.style.maxHeight = 'calc(90vh - 140px)';
                modalBody.appendChild(video);
            } else if (isAudio(file)) {
                const audio = document.createElement('audio');
                audio.src = `?action=preview&path=${encodeURIComponent(file.path)}`;
                audio.controls = true;
                audio.preload = 'auto';
                modalBody.appendChild(audio);
            } else if (file.is_text || file.is_code) {
                fetch(`?action=preview&path=${encodeURIComponent(file.path)}`)
                    .then(response => response.text())
                    .then(content => {
                        const pre = document.createElement('pre');
                        pre.textContent = content;
                        modalBody.appendChild(pre);
                    })
                    .catch(() => {
                        modalBody.innerHTML = '<p>无法预览此文件</p>';
                    });
            } else if (isOffice(file) && officePreviewMode && officePreviewMode !== 'off') {
                // 加载动画：渐进式提示，最大等待 60s
                const loadingEl = document.createElement('div');
                loadingEl.className = 'modal-loading';
                loadingEl.id = 'officeLoading';
                loadingEl.innerHTML = `
                    <div class="spinner" id="officeSpinner"></div>
                    <div class="spinner-text" id="officeStatusText">正在转换文档</div>
                    <div class="spinner-hint" id="officeHintText">请稍候…</div>
                `;
                modalBody.appendChild(loadingEl);

                const updateStatus = function(text, hint, isError) {
                    const st = document.getElementById('officeStatusText');
                    const ht = document.getElementById('officeHintText');
                    const sp = document.getElementById('officeSpinner');
                    if (st) st.textContent = text;
                    if (ht) { ht.textContent = hint || ''; ht.style.display = hint ? '' : 'none'; }
                    if (sp && isError) { sp.style.display = 'none'; }
                };

                let timedOut = false;
                const timers = [];
                timers.push(setTimeout(() => updateStatus('正在处理…', '大文件可能需要数十秒'), 3000));
                timers.push(setTimeout(() => updateStatus('仍在处理中', '请耐心等待，文件越大耗时越久'), 12000));
                timers.push(setTimeout(() => {
                    if (!timedOut) {
                        timedOut = true;
                        updateStatus('转换超时', '请检查服务器 LibreOffice 是否正常运行，或关闭 Office 预览功能', true);
                    }
                }, 60000));

                const clearAllTimers = () => timers.forEach(t => clearTimeout(t));

                const officePreviewUrl = `?action=office_preview&path=${encodeURIComponent(file.path)}`;
                const container = document.createElement('div');
                container.className = 'pdf-container';
                container.style.display = 'none';
                const embed = document.createElement('embed');
                embed.src = officePreviewUrl;
                embed.type = 'application/pdf';
                embed.style.border = 'none';
                embed.addEventListener('load', function() {
                    if (timedOut) return;
                    clearAllTimers();
                    document.getElementById('officeLoading')?.remove();
                    container.style.display = '';
                });
                embed.addEventListener('error', function() {
                    clearAllTimers();
                    updateStatus('加载失败', '服务器可能未安装 LibreOffice，或转换过程出错', true);
                });
                container.appendChild(embed);
                modalBody.appendChild(container);
            } else {
                const nsPath = encodeURIComponent(file.path);
                modalBody.innerHTML = `
                    <div class="modal-not-supported">
                        <div class="ns-icon">📄</div>
                        <div class="ns-title">暂不支持预览此文件类型</div>
                        <div class="ns-type">${file.mime || '未知'}${file.ext ? ' (.' + file.ext.toUpperCase() + ')' : ''}</div>
                        <div class="ns-hint">您可以下载后使用本地应用打开，或复制链接分享给他人</div>
                        <div class="ns-actions">
                            <button class="ns-btn ns-btn-download" id="nsDownloadBtn" data-path="${nsPath}">⬇ 下载文件</button>
                            <button class="ns-btn ns-btn-copy" id="nsCopyBtn" data-path="${nsPath}">📋 复制链接</button>
                        </div>
                    </div>
                `;
                // 绑定按钮事件（innerHTML 渲染后 DOM 节点已存在）
                setTimeout(() => {
                    const dlBtn = document.getElementById('nsDownloadBtn');
                    const cpBtn = document.getElementById('nsCopyBtn');
                    if (dlBtn) dlBtn.addEventListener('click', () => downloadFile(file));
                    if (cpBtn) cpBtn.addEventListener('click', () => copyFileLink(file));
                }, 0);
            }

            previewModal.classList.add('active');
        }

        function downloadFile(file) {
            window.location.href = `?action=download&path=${encodeURIComponent(file.path)}`;
        }

        function showCopyToast(success) {
            if (success) {
                toast.textContent = '已复制文件链接';
                toast.classList.remove('error');
            } else {
                toast.textContent = '复制失败';
                toast.classList.add('error');
            }
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show', 'error');
            }, 2000);
        }

        function fallbackCopyText(text) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            ta.style.top = '-9999px';
            ta.setAttribute('readonly', '');
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try {
                document.execCommand('copy');
                showCopyToast(true);
            } catch (e) {
                showCopyToast(false);
            }
            document.body.removeChild(ta);
        }

        function copyFileLink(file) {
            const previewUrl = window.location.origin + window.location.pathname + `?action=preview&path=${encodeURIComponent(file.path)}`;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(previewUrl).then(() => {
                    showCopyToast(true);
                }).catch(() => {
                    fallbackCopyText(previewUrl);
                });
            } else {
                fallbackCopyText(previewUrl);
            }
        }

        function applySort() {
            sortableThs.forEach(th => {
                th.classList.remove('sorted-asc', 'sorted-desc');
            });
            const currentTh = document.querySelector(`.file-table th[data-sort="${sortField}"]`);
            if (currentTh) {
                currentTh.classList.add(`sorted-${sortOrder}`);
            }

            const sorted = [...allFiles].sort((a, b) => {
                if (a.type !== b.type) {
                    return a.type === 'dir' ? -1 : 1;
                }

                let aVal = a[sortField];
                let bVal = b[sortField];

                if (typeof aVal === 'string') {
                    aVal = aVal.toLowerCase();
                    bVal = bVal.toLowerCase();
                }

                if (aVal < bVal) return sortOrder === 'asc' ? -1 : 1;
                if (aVal > bVal) return sortOrder === 'asc' ? 1 : -1;
                return 0;
            });

            renderFiles(sorted);
        }

        function sortFiles(field) {
            if (sortField === field) {
                sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                sortField = field;
                sortOrder = 'asc';
            }
            localStorage.setItem('filelist_sort_field', sortField);
            localStorage.setItem('filelist_sort_order', sortOrder);
            applySort();
        }

        function loadFiles(path) {
            fileTableBody.innerHTML = '<tr><td colspan="5" class="loading">加载中</td></tr>';
            emptyState.style.display = 'none';
            document.querySelector('.file-table-wrapper').style.display = 'block';

            fetch(`?action=list&path=${encodeURIComponent(path)}`)
                .then(response => response.json())
                .then(files => {
                    if (files.error) {
                        fileTableBody.innerHTML = `<tr><td colspan="5" class="empty-state"><div class="icon">⚠️</div><h3>错误</h3><p>${files.error}</p></td></tr>`;
                        return;
                    }
                    allFiles = files;
                    applySort();
                })
                .catch(() => {
                    fileTableBody.innerHTML = '<tr><td colspan="5" class="empty-state"><div class="icon">❌</div><h3>加载失败</h3><p>无法加载文件列表</p></td></tr>';
                });
        }

        function handleSearch() {
            const keyword = searchInput.value.trim().toLowerCase();
            if (!keyword) {
                applySort();
                return;
            }
            const filtered = allFiles.filter(file => 
                file.name.toLowerCase().includes(keyword)
            );
            renderFiles(filtered);
        }

        searchInput.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                handleSearch();
            }
        });

        document.getElementById('searchBtn').addEventListener('click', handleSearch);

        sortableThs.forEach(th => {
            th.addEventListener('click', () => {
                sortFiles(th.dataset.sort);
            });
        });

        modalClose.addEventListener('click', () => {
            previewModal.classList.remove('active');
            const video = modalBody.querySelector('video');
            const audio = modalBody.querySelector('audio');
            if (video) video.pause();
            if (audio) audio.pause();
        });

        previewModal.addEventListener('click', (e) => {
            if (e.target === previewModal) {
                previewModal.classList.remove('active');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && previewModal.classList.contains('active')) {
                previewModal.classList.remove('active');
            }
        });

        const imageTooltip = document.getElementById('imageTooltip');
        let currentImageTarget = null;

        document.addEventListener('mouseover', (e) => {
            const target = e.target.closest('.file-name[data-image-path]');
            if (!target || target === currentImageTarget) return;
            currentImageTarget = target;

            const imagePath = target.dataset.imagePath;
            const rect = target.getBoundingClientRect();

            // 先用占位尺寸快速定位，避免闪烁
            imageTooltip.innerHTML = '';
            imageTooltip.style.left = (rect.left + 10) + 'px';
            imageTooltip.style.top = (rect.top - 60) + 'px';
            imageTooltip.classList.add('active');

            // 图片加载完成后精确计算定位
            const img = new Image();
            img.onload = function() {
                if (currentImageTarget !== target) return; // 鼠标已移走
                imageTooltip.innerHTML = '';
                imageTooltip.appendChild(img);

                const tw = imageTooltip.offsetWidth;
                const th = imageTooltip.offsetHeight;

                let x = rect.left + 10;
                let y = rect.top - th - 10; // 底部对齐目标元素上方 10px

                if (x + tw > window.innerWidth) {
                    x = rect.right - tw - 10;
                }
                if (x < 0) x = 10;
                if (y < 0) {
                    y = rect.bottom + 10; // 空间不足时显示在下方
                }

                imageTooltip.style.left = x + 'px';
                imageTooltip.style.top = y + 'px';
            };
            img.src = `?action=preview&path=${imagePath}`;
        });

        document.addEventListener('mouseout', (e) => {
            const target = e.target.closest('.file-name[data-image-path]');
            if (target) {
                currentImageTarget = null;
                imageTooltip.classList.remove('active');
            }
        });

        /* ========== 皮肤/字体切换 ========== */
        const themeDots = document.querySelectorAll('.theme-dot');
        const fontSelect = document.getElementById('fontSelect');

        function getStoredTheme() {
            return localStorage.getItem('filelist-theme') || 'default';
        }
        function getStoredFont() {
            return localStorage.getItem('filelist-font') || 'default';
        }
        function applyTheme(theme) {
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('filelist-theme', theme);
            themeDots.forEach(d => d.classList.toggle('active', d.dataset.value === theme));
        }
        function applyFont(font) {
            document.body.setAttribute('data-font', font);
            localStorage.setItem('filelist-font', font);
            if (fontSelect) fontSelect.value = font;
        }

        themeDots.forEach(dot => {
            dot.addEventListener('click', () => applyTheme(dot.dataset.value));
        });
        if (fontSelect) {
            fontSelect.addEventListener('change', () => applyFont(fontSelect.value));
        }

        applyTheme(getStoredTheme());
        applyFont(getStoredFont());

        loadFiles(currentPath);
    </script>
</body>
</html>