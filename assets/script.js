/**
 * 文件浏览器 - 前端脚本
 * 获取 PHP 注入的变量：document.body.dataset.currentPath, dataset.officePreviewMode, dataset.iconScheme
 */
(function() {
    'use strict';

    const body = document.body;
    const currentPath = body.dataset.currentPath || '';
    const officePreviewMode = body.dataset.officePreviewMode || 'off';

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
    let iconScheme = localStorage.getItem('filelist-icon-scheme') || body.dataset.iconScheme || 'emoji';
    let svgIconStyle = localStorage.getItem('filelist-svg-icon-style') || body.dataset.svgIconStyle || 'material';
    let isSearchMode = false;

    function formatSize(bytes) {
        if (bytes === 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
    }

    function getFileIcon(file) {
        switch (iconScheme) {
            case 'svg': return getSvgIcon(file);
            case 'fontawesome': return getSvgIcon(file); // 已移除 Font Awesome，自动回退到 SVG
            case 'css': return getCssIcon(file);
            default: return getEmojiIcon(file);
        }
    }

    /* ========== 方案一：Emoji ========== */
    function getEmojiIcon(file) {
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

    /* ========== 方案二：SVG 内联（7 套风格） ========== */
    function svgWrap(s) { return '<svg viewBox="0 0 24 24">' + s + '</svg>'; }

    var SVG_STYLES = {
        // ── Material 现代简洁 ──
        material: {
            dir:  '<path d="M3 5a2 2 0 012-2h5l2 2h8a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2V5z" fill="#FBBF24" stroke="#F59E0B" stroke-width="0.8"/>',
            img:  '<rect x="2" y="4" width="20" height="16" rx="2" fill="#60A5FA"/><circle cx="7.5" cy="9.5" r="2" fill="#DBEAFE" opacity="0.6"/><path d="M2 18l5-6 3 3 4-5 8 8" fill="none" stroke="#DBEAFE" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.5"/>',
            vid:  '<rect x="2" y="4" width="20" height="16" rx="2" fill="#F87171"/><polygon points="9.5,7.5 17,12 9.5,16.5" fill="white"/>',
            aud:  '<circle cx="8" cy="15" r="5" fill="#A78BFA"/><circle cx="18" cy="12" r="5.5" fill="#A78BFA" opacity="0.7"/><path d="M18 12v-.5M8 15v-.5" stroke="#C4B5FD" stroke-width="1" stroke-linecap="round"/><path d="M9 5v6l2-2 2 2V5" fill="white" opacity="0.4"/>',
            pdf:  '<rect x="3" y="2" width="18" height="20" rx="2" fill="#EF4444"/><rect x="7.5" y="7.5" width="9" height="1.5" rx="0.75" fill="white" opacity="0.5"/><rect x="7.5" y="11" width="7" height="1.5" rx="0.75" fill="white" opacity="0.5"/><rect x="7.5" y="14.5" width="10" height="1.5" rx="0.75" fill="white" opacity="0.35"/><rect x="7.5" y="18" width="6" height="1.5" rx="0.75" fill="white" opacity="0.2"/>',
            doc:  '<rect x="3" y="2" width="18" height="20" rx="2" fill="#3B82F6"/><rect x="7.5" y="7.5" width="9" height="1.5" rx="0.75" fill="white" opacity="0.5"/><rect x="7.5" y="11" width="7" height="1.5" rx="0.75" fill="white" opacity="0.5"/><rect x="7.5" y="14.5" width="10" height="1.5" rx="0.75" fill="white" opacity="0.35"/><rect x="7.5" y="18" width="6" height="1.5" rx="0.75" fill="white" opacity="0.2"/>',
            xls:  '<rect x="3" y="2" width="18" height="20" rx="2" fill="#10B981"/><rect x="6" y="6" width="12" height="7" rx="0.3" fill="white" opacity="0.25"/><line x1="6" y1="8.5" x2="18" y2="8.5" stroke="white" stroke-width="0.8" opacity="0.5"/><line x1="6" y1="11" x2="18" y2="11" stroke="white" stroke-width="0.8" opacity="0.5"/><line x1="12" y1="6" x2="12" y2="13" stroke="white" stroke-width="0.8" opacity="0.5"/>',
            ppt:  '<rect x="3" y="2" width="18" height="20" rx="2" fill="#F97316"/><rect x="6" y="6" width="12" height="8" rx="0.3" fill="white" opacity="0.2"/><rect x="7" y="7" width="10" height="2" rx="0.5" fill="white" opacity="0.5"/><rect x="7" y="10.5" width="7" height="1" rx="0.5" fill="white" opacity="0.35"/><rect x="7" y="12.5" width="9" height="1" rx="0.5" fill="white" opacity="0.25"/>',
            code: '<rect x="2" y="3" width="20" height="18" rx="2" fill="#4B5563"/><path d="M8 8l-3 4 3 4" fill="none" stroke="#93C5FD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 8l3 4-3 4" fill="none" stroke="#93C5FD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><line x1="14" y1="7" x2="10" y2="17" stroke="#FCA5A5" stroke-width="1" stroke-linecap="round"/>',
            txt:  '<rect x="3" y="3" width="18" height="18" rx="2" fill="#9CA3AF"/><rect x="7.5" y="7" width="9" height="1.5" rx="0.75" fill="white" opacity="0.5"/><rect x="7.5" y="10.5" width="7" height="1.5" rx="0.75" fill="white" opacity="0.4"/><rect x="7.5" y="14" width="10" height="1.5" rx="0.75" fill="white" opacity="0.3"/><rect x="7.5" y="17.5" width="6" height="1.5" rx="0.75" fill="white" opacity="0.2"/>',
            file: '<rect x="3" y="2" width="18" height="20" rx="2" fill="#6B7280"/><rect x="7.5" y="7" width="9" height="1.5" rx="0.75" fill="white" opacity="0.5"/><rect x="7.5" y="10.5" width="7" height="1.5" rx="0.75" fill="white" opacity="0.35"/><rect x="7.5" y="14" width="10" height="1.5" rx="0.75" fill="white" opacity="0.25"/>',
            exe:  '<rect x="3" y="4" width="18" height="17" rx="2" fill="#8B5CF6"/><path d="M5 4a2 2 0 0 0-2 2v3h18V6a2 2 0 0 0-2-2H5z" fill="#7C3AED"/><circle cx="6.5" cy="6.5" r=".9" fill="#C4B5FD" opacity=".5"/><circle cx="9.5" cy="6.5" r=".9" fill="#C4B5FD" opacity=".5"/><circle cx="12.5" cy="6.5" r=".9" fill="#C4B5FD" opacity=".5"/><rect x="6" y="11" width="12" height="1.5" rx=".75" fill="white" opacity=".4"/><rect x="6" y="14" width="8" height="1.5" rx=".75" fill="white" opacity=".3"/><rect x="6" y="17" width="10" height="1.5" rx=".75" fill="white" opacity=".2"/>',
            archive: '<rect x="3" y="5" width="18" height="16" rx="2" fill="#D97706"/><rect x="3" y="5" width="18" height="4" rx="2" fill="#B45309"/><path d="M3 9h18v-1c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v1z" fill="#B45309"/><path d="M9 14l3 3 3-3" fill="none" stroke="white" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" opacity=".5"/><line x1="12" y1="10" x2="12" y2="21" stroke="white" stroke-width="1.2" opacity=".3"/>',
            font:  '<rect x="3" y="3" width="18" height="18" rx="2" fill="#EC4899"/><text x="12" y="17" text-anchor="middle" font-family="serif" font-size="14" font-weight="bold" fill="white" opacity=".85">Aa</text>',
            disk:  '<circle cx="12" cy="12" r="9" fill="#64748B"/><circle cx="12" cy="12" r="3" fill="none" stroke="white" stroke-width="1.5" opacity=".28"/><circle cx="12" cy="12" r="1.5" fill="white" opacity=".35"/><path d="M12 3a9 9 0 1 0 0 18" fill="none" stroke="white" stroke-width="1" opacity=".15"/>'
        },
        // ── 卡通风格：圆润粗描边 ──
        cartoon: {
            dir:  '<path d="M2.5 5.5a2.5 2.5 0 012.5-2.5h5.5l2.5 2.5h8a2.5 2.5 0 012.5 2.5v11a2.5 2.5 0 01-2.5 2.5H5a2.5 2.5 0 01-2.5-2.5V5.5z" fill="#FFD93D" stroke="#E6A800" stroke-width="2" stroke-linejoin="round"/><circle cx="17" cy="10" r="1.5" fill="#FFECB3" opacity="0.8"/>',
            img:  '<rect x="2" y="4" width="20" height="16" rx="5" fill="#7DD3FC" stroke="#0EA5E9" stroke-width="2"/><circle cx="8" cy="9" r="3" fill="#FFFBEB"/><circle cx="16" cy="7.5" r="4" fill="#FDE68A" opacity="0.7"/><path d="M3 18l4-5 3 3 5-6 6 8" fill="none" stroke="#0EA5E9" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',
            vid:  '<rect x="2" y="4" width="20" height="16" rx="5" fill="#FCA5A5" stroke="#DC2626" stroke-width="2"/><polygon points="9,7 18,12 9,17" fill="white" stroke="white" stroke-width="1"/>',
            aud:  '<rect x="3" y="3" width="18" height="18" rx="6" fill="#DDD6FE" stroke="#8B5CF6" stroke-width="2"/><circle cx="7" cy="14" r="3" fill="#A78BFA"/><circle cx="16" cy="11" r="4" fill="#C4B5FD"/><path d="M8 6v3l1.5-1.5L11 9V6" fill="white" opacity="0.6"/>',
            pdf:  '<rect x="2.5" y="1.5" width="19" height="21" rx="5" fill="#FCA5A5" stroke="#DC2626" stroke-width="2"/><rect x="7" y="7" width="10" height="2" rx="1" fill="white" opacity="0.5"/><rect x="7" y="11" width="8" height="2" rx="1" fill="white" opacity="0.5"/><rect x="7" y="15" width="11" height="2" rx="1" fill="white" opacity="0.35"/>',
            doc:  '<rect x="2.5" y="1.5" width="19" height="21" rx="5" fill="#93C5FD" stroke="#2563EB" stroke-width="2"/><rect x="7" y="7" width="10" height="2" rx="1" fill="white" opacity="0.5"/><rect x="7" y="11" width="8" height="2" rx="1" fill="white" opacity="0.5"/><rect x="7" y="15" width="11" height="2" rx="1" fill="white" opacity="0.35"/>',
            xls:  '<rect x="2.5" y="1.5" width="19" height="21" rx="5" fill="#6EE7B7" stroke="#059669" stroke-width="2"/><rect x="6" y="6" width="12" height="8" rx="1" fill="white" opacity="0.25"/><line x1="12" y1="6" x2="12" y2="14" stroke="white" stroke-width="1.5" opacity="0.5"/><line x1="6" y1="10" x2="18" y2="10" stroke="white" stroke-width="1.5" opacity="0.5"/>',
            ppt:  '<rect x="2.5" y="1.5" width="19" height="21" rx="5" fill="#FDBA74" stroke="#EA580C" stroke-width="2"/><rect x="6" y="6" width="12" height="9" rx="1" fill="white" opacity="0.2"/><rect x="7" y="7" width="10" height="2.5" rx="1.2" fill="white" opacity="0.5"/><rect x="7" y="11" width="7" height="1.5" rx="0.8" fill="white" opacity="0.35"/>',
            code: '<rect x="2" y="2.5" width="20" height="19" rx="5" fill="#94A3B8" stroke="#475569" stroke-width="2"/><path d="M7.5 8l-3 4 3 4" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M16.5 8l3 4-3 4" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="14" y1="7" x2="10" y2="17" stroke="#FECACA" stroke-width="1.5" stroke-linecap="round"/>',
            txt:  '<rect x="2.5" y="2.5" width="19" height="19" rx="5" fill="#CBD5E1" stroke="#64748B" stroke-width="2"/><rect x="7" y="7" width="10" height="2" rx="1" fill="white" opacity="0.5"/><rect x="7" y="11" width="8" height="2" rx="1" fill="white" opacity="0.4"/><rect x="7" y="15" width="11" height="2" rx="1" fill="white" opacity="0.3"/>',
            file: '<rect x="2.5" y="1.5" width="19" height="21" rx="5" fill="#CBD5E1" stroke="#64748B" stroke-width="2"/><rect x="7" y="7" width="10" height="2" rx="1" fill="white" opacity="0.5"/><rect x="7" y="11" width="7" height="2" rx="1" fill="white" opacity="0.35"/><rect x="7" y="15" width="11" height="2" rx="1" fill="white" opacity="0.25"/>',
            exe:  '<rect x="3" y="4" width="18" height="17" rx="5" fill="#C4B5FD" stroke="#7C3AED" stroke-width="2"/><path d="M5 4a3 3 0 0 0-3 3v3.5h20V7a3 3 0 0 0-3-3H5z" fill="#A78BFA" stroke="#7C3AED" stroke-width="2"/><circle cx="7" cy="7" r="1" fill="#EDE9FE"/><circle cx="10.5" cy="7" r="1" fill="#EDE9FE"/><circle cx="14" cy="7" r="1" fill="#EDE9FE"/><rect x="6" y="12" width="12" height="2" rx="1" fill="white" opacity=".45"/><rect x="6" y="16" width="8" height="2" rx="1" fill="white" opacity=".3"/>',
            archive: '<rect x="2.5" y="5" width="19" height="16" rx="5" fill="#FDE68A" stroke="#D97706" stroke-width="2"/><path d="M4.5 5l1-3h13l1 3H4.5z" fill="#FCD34D" stroke="#D97706" stroke-width="2"/><circle cx="12" cy="14" r="4" fill="none" stroke="#D97706" stroke-width="1.5" opacity=".45"/><line x1="12" y1="10" x2="12" y2="18" stroke="#D97706" stroke-width="1.2" opacity=".35"/>',
            font:  '<rect x="2.5" y="2.5" width="19" height="19" rx="5" fill="#FBCFE8" stroke="#DB2777" stroke-width="2"/><text x="12" y="17.5" text-anchor="middle" font-family="serif" font-size="14" font-weight="bold" fill="#DB2777" opacity=".8">Aa</text>',
            disk:  '<circle cx="12" cy="12" r="9" fill="#E2E8F0" stroke="#64748B" stroke-width="2"/><circle cx="12" cy="12" r="3.5" fill="none" stroke="#94A3B8" stroke-width="1.5"/><circle cx="12" cy="12" r="1.5" fill="#94A3B8" opacity=".5"/>'
        },
        // ── 科幻风格：霓虹暗底锐利棱角 ──
        scifi: {
            dir:  '<rect x="2" y="4" width="20" height="17" rx="3" fill="#1E1B4B" stroke="#22D3EE" stroke-width="1.5"/><path d="M2 6h24" stroke="#22D3EE" stroke-width="1" opacity="0.5"/><path d="M6 2l3 4H2l4-4z" fill="#22D3EE" opacity="0.8"/><circle cx="12" cy="12" r="2" fill="none" stroke="#A78BFA" stroke-width="0.8"/><circle cx="12" cy="12" r="0.8" fill="#A78BFA"/>',
            img:  '<rect x="2" y="3" width="20" height="18" rx="2" fill="#0F172A" stroke="#38BDF8" stroke-width="1.5"/><rect x="5" y="6" width="14" height="12" rx="1" fill="#1E293B" stroke="#38BDF8" stroke-width="0.8" stroke-dasharray="2 1"/><circle cx="8" cy="9" r="1.5" fill="#38BDF8"/><polygon points="14,7 20,12 14,17" fill="#38BDF8" opacity="0.6"/><path d="M5 16l3-4 3 3 4-6 4 7" fill="none" stroke="#38BDF8" stroke-width="0.8" opacity="0.5"/>',
            vid:  '<rect x="2" y="3" width="20" height="18" rx="2" fill="#1A0A1A" stroke="#F43F5E" stroke-width="1.5"/><rect x="6" y="7" width="12" height="10" rx="1" fill="#2D0A1A" stroke="#F43F5E" stroke-width="0.5"/><polygon points="10,8.5 18,12 10,15.5" fill="#F43F5E"/><line x1="6" y1="20" x2="18" y2="20" stroke="#F43F5E" stroke-width="0.5" stroke-dasharray="2 2"/>',
            aud:  '<rect x="3" y="3" width="18" height="18" rx="2" fill="#0F0F23" stroke="#A78BFA" stroke-width="1.5"/><rect x="7" y="7" width="2" height="10" rx="1" fill="#A78BFA"/><rect x="11" y="5" width="2" height="14" rx="1" fill="#C4B5FD" opacity="0.7"/><rect x="15" y="9" width="2" height="8" rx="1" fill="#C4B5FD" opacity="0.5"/><circle cx="12" cy="18" r="1" fill="#22D3EE"/>',
            pdf:  '<rect x="2.5" y="1.5" width="19" height="21" rx="2" fill="#1E0A0A" stroke="#EF4444" stroke-width="1.5"/><path d="M2.5 1.5l6 0 0 6-6 0z" fill="none" stroke="#EF4444" stroke-width="1"/><rect x="7" y="8" width="10" height="1.5" rx="0.5" fill="#EF4444" opacity="0.5"/><rect x="7" y="11" width="8" height="1.5" rx="0.5" fill="#EF4444" opacity="0.4"/><rect x="7" y="14" width="11" height="1.5" rx="0.5" fill="#EF4444" opacity="0.3"/><line x1="3" y1="19" x2="21" y2="19" stroke="#EF4444" stroke-width="0.5" opacity="0.3"/>',
            doc:  '<rect x="2.5" y="1.5" width="19" height="21" rx="2" fill="#0A1020" stroke="#3B82F6" stroke-width="1.5"/><rect x="7" y="8" width="10" height="1.5" rx="0.5" fill="#3B82F6" opacity="0.5"/><rect x="7" y="11" width="8" height="1.5" rx="0.5" fill="#3B82F6" opacity="0.4"/><rect x="7" y="14" width="11" height="1.5" rx="0.5" fill="#3B82F6" opacity="0.3"/>',
            xls:  '<rect x="2.5" y="1.5" width="19" height="21" rx="2" fill="#0A1A0A" stroke="#10B981" stroke-width="1.5"/><rect x="6" y="6" width="12" height="8" rx="0.5" fill="#10B981" opacity="0.15"/><line x1="6" y1="9" x2="18" y2="9" stroke="#10B981" stroke-width="0.8" opacity="0.5"/><line x1="6" y1="11.5" x2="18" y2="11.5" stroke="#10B981" stroke-width="0.8" opacity="0.5"/><line x1="12" y1="6" x2="12" y2="14" stroke="#10B981" stroke-width="0.8" opacity="0.4"/>',
            ppt:  '<rect x="2.5" y="1.5" width="19" height="21" rx="2" fill="#1A0A00" stroke="#F97316" stroke-width="1.5"/><rect x="6" y="6" width="12" height="8" rx="0.5" fill="#F97316" opacity="0.1"/><rect x="7" y="7" width="10" height="2" rx="0.5" fill="#F97316" opacity="0.4"/><rect x="7" y="10.5" width="7" height="1" rx="0.5" fill="#F97316" opacity="0.3"/>',
            code: '<rect x="2" y="2" width="20" height="20" rx="2" fill="#0F172A" stroke="#6366F1" stroke-width="1.5"/><path d="M7 7l-4 5 4 5" fill="none" stroke="#22D3EE" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M17 7l4 5-4 5" fill="none" stroke="#22D3EE" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><line x1="13.5" y1="6" x2="10.5" y2="18" stroke="#F43F5E" stroke-width="1.2" stroke-linecap="round"/>',
            txt:  '<rect x="2.5" y="2.5" width="19" height="19" rx="2" fill="#1A1A2E" stroke="#94A3B8" stroke-width="1.5"/><rect x="7" y="7" width="10" height="1.5" rx="0.5" fill="#94A3B8" opacity="0.5"/><rect x="7" y="10.5" width="8" height="1.5" rx="0.5" fill="#94A3B8" opacity="0.4"/><rect x="7" y="14" width="11" height="1.5" rx="0.5" fill="#94A3B8" opacity="0.3"/>',
            file: '<rect x="2.5" y="1.5" width="19" height="21" rx="2" fill="#1E293B" stroke="#64748B" stroke-width="1.5"/><rect x="7" y="7" width="10" height="1.5" rx="0.5" fill="#64748B" opacity="0.5"/><rect x="7" y="10.5" width="7" height="1.5" rx="0.5" fill="#64748B" opacity="0.35"/><rect x="7" y="14" width="11" height="1.5" rx="0.5" fill="#64748B" opacity="0.25"/>',
            exe:  '<rect x="3" y="4" width="18" height="17" rx="2" fill="#1E1B4B" stroke="#8B5CF6" stroke-width="1.5"/><path d="M5 4a2 2 0 0 0-2 2v3h18V6a2 2 0 0 0-2-2H5z" fill="#2D1B69"/><circle cx="7" cy="6.5" r=".8" fill="#A78BFA"/><circle cx="10.5" cy="6.5" r=".8" fill="#A78BFA"/><circle cx="14" cy="6.5" r=".8" fill="#A78BFA"/><path d="M6 11h12v1H6zM6 14h8v1H6zM6 17h10v1H6z" fill="#8B5CF6" opacity=".4"/>',
            archive: '<rect x="3" y="5" width="18" height="16" rx="2" fill="#1A1500" stroke="#F59E0B" stroke-width="1.5"/><rect x="3" y="5" width="18" height="4" rx="2" fill="#2D2200"/><path d="M3 9h18v-1c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v1z" fill="#3D3000"/><path d="M11 12v8M13 12v8" stroke="#F59E0B" stroke-width=".8" opacity=".4"/><line x1="8" y1="16" x2="16" y2="16" stroke="#F59E0B" stroke-width=".8" opacity=".3"/>',
            font:  '<rect x="3" y="3" width="18" height="18" rx="2" fill="#1A0520" stroke="#EC4899" stroke-width="1.5"/><text x="12" y="17" text-anchor="middle" font-family="monospace" font-size="14" font-weight="bold" fill="#F472B6" opacity=".9">Aa</text>',
            disk:  '<circle cx="12" cy="12" r="9" fill="#0F172A" stroke="#22D3EE" stroke-width="1.5"/><circle cx="12" cy="12" r="3" fill="none" stroke="#22D3EE" stroke-width="1" opacity=".4"/><circle cx="12" cy="12" r="1.2" fill="#22D3EE" opacity=".5"/>'
        },
        // ── 极简线条：细线勾勒 ──
        minimal: {
            dir:  '<path d="M3 5.5a2 2 0 012-2h5.5l2 2h8a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2V5.5z" fill="none" stroke="#6B7280" stroke-width="1.2" stroke-linejoin="round"/>',
            img:  '<rect x="3" y="4" width="18" height="16" rx="2" fill="none" stroke="#9CA3AF" stroke-width="1.2"/><circle cx="8" cy="9" r="2.5" fill="none" stroke="#9CA3AF" stroke-width="0.8"/><path d="M3 18l5-6 3 3 4-5 6 8" fill="none" stroke="#9CA3AF" stroke-width="0.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="7.5" r="1.5" fill="none" stroke="#9CA3AF" stroke-width="0.6"/>',
            vid:  '<rect x="3" y="4" width="18" height="16" rx="2" fill="none" stroke="#9CA3AF" stroke-width="1.2"/><polygon points="10,8 18,12 10,16" fill="none" stroke="#9CA3AF" stroke-width="1" stroke-linejoin="round"/>',
            aud:  '<circle cx="8" cy="14" r="4" fill="none" stroke="#9CA3AF" stroke-width="1.2"/><circle cx="18" cy="12" r="5" fill="none" stroke="#9CA3AF" stroke-width="1" opacity="0.7"/><path d="M10 6v4l1.5-1.5L13 10V6" fill="none" stroke="#9CA3AF" stroke-width="0.8" stroke-linecap="round" stroke-linejoin="round"/>',
            pdf:  '<rect x="3.5" y="2.5" width="17" height="19" rx="2" fill="none" stroke="#EF4444" stroke-width="1.2"/><line x1="7" y1="8" x2="17" y2="8" stroke="#EF4444" stroke-width="0.8" opacity="0.6"/><line x1="7" y1="11.5" x2="15" y2="11.5" stroke="#EF4444" stroke-width="0.8" opacity="0.5"/><line x1="7" y1="15" x2="17" y2="15" stroke="#EF4444" stroke-width="0.8" opacity="0.4"/>',
            doc:  '<rect x="3.5" y="2.5" width="17" height="19" rx="2" fill="none" stroke="#3B82F6" stroke-width="1.2"/><line x1="7" y1="8" x2="17" y2="8" stroke="#3B82F6" stroke-width="0.8" opacity="0.6"/><line x1="7" y1="11.5" x2="15" y2="11.5" stroke="#3B82F6" stroke-width="0.8" opacity="0.5"/><line x1="7" y1="15" x2="17" y2="15" stroke="#3B82F6" stroke-width="0.8" opacity="0.4"/>',
            xls:  '<rect x="3.5" y="2.5" width="17" height="19" rx="2" fill="none" stroke="#10B981" stroke-width="1.2"/><rect x="6.5" y="6.5" width="11" height="7" fill="none" stroke="#10B981" stroke-width="0.7" opacity="0.5"/><line x1="6.5" y1="10" x2="17.5" y2="10" stroke="#10B981" stroke-width="0.7" opacity="0.4"/><line x1="12" y1="6.5" x2="12" y2="13.5" stroke="#10B981" stroke-width="0.7" opacity="0.4"/>',
            ppt:  '<rect x="3.5" y="2.5" width="17" height="19" rx="2" fill="none" stroke="#F97316" stroke-width="1.2"/><rect x="6.5" y="6.5" width="11" height="8" fill="none" stroke="#F97316" stroke-width="0.7" opacity="0.4"/><line x1="7" y1="8" x2="17" y2="8" stroke="#F97316" stroke-width="0.8" opacity="0.5"/><line x1="7" y1="11" x2="13" y2="11" stroke="#F97316" stroke-width="0.8" opacity="0.35"/>',
            code: '<rect x="3" y="3" width="18" height="18" rx="2" fill="none" stroke="#6366F1" stroke-width="1.2"/><path d="M8 8l-2.5 4L8 16" fill="none" stroke="#6366F1" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 8l2.5 4L16 16" fill="none" stroke="#6366F1" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/><line x1="13.5" y1="7" x2="10.5" y2="17" stroke="#6366F1" stroke-width="0.8" stroke-linecap="round" opacity="0.5"/>',
            txt:  '<rect x="3.5" y="3.5" width="17" height="17" rx="2" fill="none" stroke="#9CA3AF" stroke-width="1.2"/><line x1="7" y1="8" x2="17" y2="8" stroke="#9CA3AF" stroke-width="0.8" opacity="0.6"/><line x1="7" y1="11.5" x2="15" y2="11.5" stroke="#9CA3AF" stroke-width="0.7" opacity="0.5"/><line x1="7" y1="15" x2="17" y2="15" stroke="#9CA3AF" stroke-width="0.7" opacity="0.4"/>',
            file: '<rect x="3.5" y="2.5" width="17" height="19" rx="2" fill="none" stroke="#9CA3AF" stroke-width="1.2"/><line x1="7" y1="8" x2="17" y2="8" stroke="#9CA3AF" stroke-width="0.7" opacity="0.5"/><line x1="7" y1="11.5" x2="14" y2="11.5" stroke="#9CA3AF" stroke-width="0.7" opacity="0.35"/>',
            exe:  '<rect x="3.5" y="4.5" width="17" height="16" rx="2" fill="none" stroke="#8B5CF6" stroke-width="1.2"/><line x1="3.5" y1="9.5" x2="20.5" y2="9.5" stroke="#8B5CF6" stroke-width="1" opacity=".5"/><circle cx="7" cy="7" r=".8" fill="none" stroke="#8B5CF6" stroke-width=".7" opacity=".5"/><circle cx="10" cy="7" r=".8" fill="none" stroke="#8B5CF6" stroke-width=".7" opacity=".5"/><circle cx="13" cy="7" r=".8" fill="none" stroke="#8B5CF6" stroke-width=".7" opacity=".5"/>',
            archive: '<rect x="3.5" y="5.5" width="17" height="15" rx="2" fill="none" stroke="#D97706" stroke-width="1.2"/><path d="M3.5 5.5l2-2h13l2 2H3.5z" fill="none" stroke="#D97706" stroke-width="1.2"/><path d="M9 12l3 3 3-3" fill="none" stroke="#D97706" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" opacity=".55"/>',
            font:  '<rect x="3.5" y="3.5" width="17" height="17" rx="2" fill="none" stroke="#EC4899" stroke-width="1.2"/><text x="12" y="16.5" text-anchor="middle" font-family="serif" font-size="13" font-weight="bold" fill="#EC4899" opacity=".75">Aa</text>',
            disk:  '<circle cx="12" cy="12" r="9" fill="none" stroke="#9CA3AF" stroke-width="1.2"/><circle cx="12" cy="12" r="3" fill="none" stroke="#9CA3AF" stroke-width=".8" opacity=".5"/><circle cx="12" cy="12" r="1.2" fill="#9CA3AF" opacity=".4"/>'
        },
        // ── 像素风格：方块拼接 ──
        pixel: {
            dir:  '<rect x="3" y="7" width="18" height="15" fill="#FBBF24"/><rect x="3" y="7" width="2" height="2" fill="#F59E0B"/><rect x="19" y="7" width="2" height="2" fill="#F59E0B"/><rect x="3" y="20" width="2" height="2" fill="#F59E0B"/><rect x="19" y="20" width="2" height="2" fill="#F59E0B"/><rect x="3" y="5" width="8" height="2" fill="#F59E0B"/><rect x="11" y="3" width="4" height="2" fill="#FBBF24"/><rect x="15" y="5" width="6" height="2" fill="#FBBF24"/>',
            img:  '<rect x="3" y="5" width="18" height="14" fill="#60A5FA"/><rect x="3" y="5" width="2" height="2" fill="#3B82F6"/><rect x="19" y="5" width="2" height="2" fill="#3B82F6"/><rect x="3" y="17" width="2" height="2" fill="#3B82F6"/><rect x="19" y="17" width="2" height="2" fill="#3B82F6"/><rect x="7" y="8" width="4" height="4" fill="#FDE68A"/><rect x="17" y="7" width="2" height="2" fill="white" opacity="0.4"/><rect x="17" y="9" width="2" height="2" fill="white" opacity="0.3"/><rect x="17" y="7" width="4" height="4" fill="none" stroke="white" stroke-width="0.5" opacity="0.3"/>',
            vid:  '<rect x="3" y="5" width="18" height="14" fill="#F87171"/><rect x="3" y="5" width="2" height="2" fill="#DC2626"/><rect x="19" y="5" width="2" height="2" fill="#DC2626"/><rect x="3" y="17" width="2" height="2" fill="#DC2626"/><rect x="19" y="17" width="2" height="2" fill="#DC2626"/><rect x="10" y="9" width="2" height="6" fill="white"/><rect x="12" y="9" width="2" height="6" fill="white"/><rect x="14" y="9" width="2" height="6" fill="white"/>',
            aud:  '<rect x="3" y="4" width="18" height="16" fill="#A78BFA"/><rect x="3" y="4" width="2" height="2" fill="#7C3AED"/><rect x="19" y="4" width="2" height="2" fill="#7C3AED"/><rect x="3" y="18" width="2" height="2" fill="#7C3AED"/><rect x="19" y="18" width="2" height="2" fill="#7C3AED"/><rect x="7" y="8" width="2" height="8" fill="#C4B5FD"/><rect x="11" y="6" width="2" height="10" fill="#C4B5FD" opacity="0.8"/><rect x="15" y="9" width="2" height="6" fill="#C4B5FD" opacity="0.6"/>',
            pdf:  '<rect x="3" y="3" width="18" height="18" fill="#EF4444"/><rect x="3" y="3" width="2" height="2" fill="#DC2626"/><rect x="19" y="3" width="2" height="2" fill="#DC2626"/><rect x="3" y="19" width="2" height="2" fill="#DC2626"/><rect x="19" y="19" width="2" height="2" fill="#DC2626"/><rect x="7" y="7" width="10" height="2" fill="white" opacity="0.5"/><rect x="7" y="11" width="8" height="2" fill="white" opacity="0.4"/><rect x="7" y="15" width="11" height="2" fill="white" opacity="0.3"/>',
            doc:  '<rect x="3" y="3" width="18" height="18" fill="#3B82F6"/><rect x="3" y="3" width="2" height="2" fill="#2563EB"/><rect x="19" y="3" width="2" height="2" fill="#2563EB"/><rect x="3" y="19" width="2" height="2" fill="#2563EB"/><rect x="19" y="19" width="2" height="2" fill="#2563EB"/><rect x="7" y="7" width="10" height="2" fill="white" opacity="0.5"/><rect x="7" y="11" width="8" height="2" fill="white" opacity="0.4"/><rect x="7" y="15" width="11" height="2" fill="white" opacity="0.3"/>',
            xls:  '<rect x="3" y="3" width="18" height="18" fill="#10B981"/><rect x="3" y="3" width="2" height="2" fill="#059669"/><rect x="19" y="3" width="2" height="2" fill="#059669"/><rect x="3" y="19" width="2" height="2" fill="#059669"/><rect x="19" y="19" width="2" height="2" fill="#059669"/><rect x="7" y="7" width="10" height="10" fill="white" opacity="0.2"/><line x1="7" y1="10" x2="17" y2="10" stroke="white" stroke-width="1.5" opacity="0.4"/><line x1="7" y1="13" x2="17" y2="13" stroke="white" stroke-width="1.5" opacity="0.4"/><line x1="12" y1="7" x2="12" y2="17" stroke="white" stroke-width="1.5" opacity="0.4"/>',
            ppt:  '<rect x="3" y="3" width="18" height="18" fill="#F97316"/><rect x="3" y="3" width="2" height="2" fill="#EA580C"/><rect x="19" y="3" width="2" height="2" fill="#EA580C"/><rect x="3" y="19" width="2" height="2" fill="#EA580C"/><rect x="19" y="19" width="2" height="2" fill="#EA580C"/><rect x="7" y="7" width="10" height="2" fill="white" opacity="0.5"/><rect x="7" y="11" width="7" height="2" fill="white" opacity="0.35"/><rect x="7" y="15" width="10" height="2" fill="white" opacity="0.2"/>',
            code: '<rect x="3" y="3" width="18" height="18" fill="#4B5563"/><rect x="3" y="3" width="2" height="2" fill="#374151"/><rect x="19" y="3" width="2" height="2" fill="#374151"/><rect x="3" y="19" width="2" height="2" fill="#374151"/><rect x="19" y="19" width="2" height="2" fill="#374151"/><rect x="7" y="9" width="2" height="2" fill="#93C5FD"/><rect x="9" y="9" width="2" height="2" fill="#FCA5A5"/><rect x="13" y="9" width="2" height="2" fill="#93C5FD"/><rect x="15" y="9" width="2" height="2" fill="#FCA5A5"/><rect x="7" y="13" width="2" height="2" fill="#FCA5A5"/><rect x="9" y="13" width="2" height="2" fill="#93C5FD"/><rect x="13" y="13" width="2" height="2" fill="#FCA5A5"/><rect x="15" y="13" width="2" height="2" fill="#93C5FD"/>',
            txt:  '<rect x="3" y="3" width="18" height="18" fill="#9CA3AF"/><rect x="3" y="3" width="2" height="2" fill="#6B7280"/><rect x="19" y="3" width="2" height="2" fill="#6B7280"/><rect x="3" y="19" width="2" height="2" fill="#6B7280"/><rect x="19" y="19" width="2" height="2" fill="#6B7280"/><rect x="7" y="7" width="10" height="2" fill="white" opacity="0.5"/><rect x="7" y="11" width="8" height="2" fill="white" opacity="0.4"/><rect x="7" y="15" width="6" height="2" fill="white" opacity="0.3"/>',
            file: '<rect x="3" y="3" width="18" height="18" fill="#6B7280"/><rect x="3" y="3" width="2" height="2" fill="#4B5563"/><rect x="19" y="3" width="2" height="2" fill="#4B5563"/><rect x="3" y="19" width="2" height="2" fill="#4B5563"/><rect x="19" y="19" width="2" height="2" fill="#4B5563"/><rect x="7" y="7" width="10" height="2" fill="white" opacity="0.5"/><rect x="7" y="11" width="8" height="2" fill="white" opacity="0.35"/><rect x="7" y="15" width="11" height="2" fill="white" opacity="0.25"/>',
            exe:  '<rect x="3" y="4" width="18" height="17" fill="#8B5CF6"/><rect x="3" y="4" width="2" height="2" fill="#6D28D9"/><rect x="19" y="4" width="2" height="2" fill="#6D28D9"/><rect x="3" y="19" width="2" height="2" fill="#6D28D9"/><rect x="19" y="19" width="2" height="2" fill="#6D28D9"/><rect x="3" y="9" width="18" height="2" fill="#7C3AED"/><rect x="7" y="6" width="2" height="2" fill="#C4B5FD"/><rect x="10" y="6" width="2" height="2" fill="#C4B5FD"/><rect x="13" y="6" width="2" height="2" fill="#C4B5FD"/>',
            archive: '<rect x="3" y="5" width="18" height="16" fill="#D97706"/><rect x="3" y="5" width="2" height="2" fill="#92400E"/><rect x="19" y="5" width="2" height="2" fill="#92400E"/><rect x="3" y="19" width="2" height="2" fill="#92400E"/><rect x="19" y="19" width="2" height="2" fill="#92400E"/><rect x="3" y="5" width="18" height="4" fill="#B45309"/><rect x="9" y="12" width="6" height="4" fill="#FDE68A" opacity=".6"/><rect x="11" y="10" width="2" height="2" fill="#FDE68A" opacity=".5"/>',
            font:  '<rect x="3" y="3" width="18" height="18" fill="#EC4899"/><rect x="3" y="3" width="2" height="2" fill="#BE185D"/><rect x="19" y="3" width="2" height="2" fill="#BE185D"/><rect x="3" y="19" width="2" height="2" fill="#BE185D"/><rect x="19" y="19" width="2" height="2" fill="#BE185D"/><rect x="7" y="8" width="10" height="2" fill="white" opacity=".5"/><rect x="7" y="12" width="2" height="6" fill="white" opacity=".4"/><rect x="11" y="12" width="6" height="2" fill="white" opacity=".35"/><rect x="15" y="14" width="2" height="4" fill="white" opacity=".3"/>',
            disk:  '<rect x="4" y="4" width="16" height="16" fill="#64748B"/><rect x="6" y="6" width="12" height="12" fill="#475569"/><rect x="4" y="4" width="2" height="2" fill="#334155"/><rect x="18" y="4" width="2" height="2" fill="#334155"/><rect x="4" y="18" width="2" height="2" fill="#334155"/><rect x="18" y="18" width="2" height="2" fill="#334155"/><rect x="10" y="10" width="4" height="4" fill="white" opacity=".3"/>'
        },
        // ── 渐变风格：平滑过渡 ──
        gradient: {
            dir:  '<defs><linearGradient id="g1" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#FBBF24"/><stop offset="100%" stop-color="#F59E0B"/></linearGradient></defs><path d="M3 5a2 2 0 012-2h5l2 2h8a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2V5z" fill="url(#g1)"/>',
            img:  '<defs><linearGradient id="g2" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#60A5FA"/><stop offset="100%" stop-color="#8B5CF6"/></linearGradient></defs><rect x="2" y="4" width="20" height="16" rx="3" fill="url(#g2)"/><circle cx="8" cy="9.5" r="2.5" fill="white" opacity="0.3"/><path d="M2 18l5-6 3 3 4-5 8 8" fill="none" stroke="white" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" opacity="0.5"/>',
            vid:  '<defs><linearGradient id="g3" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#F87171"/><stop offset="100%" stop-color="#FB923C"/></linearGradient></defs><rect x="2" y="4" width="20" height="16" rx="3" fill="url(#g3)"/><polygon points="9.5,7.5 17,12 9.5,16.5" fill="white"/>',
            aud:  '<defs><linearGradient id="g4" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#A78BFA"/><stop offset="100%" stop-color="#EC4899"/></linearGradient></defs><circle cx="8" cy="14" r="5" fill="url(#g4)"/><circle cx="18" cy="12" r="5.5" fill="url(#g4)" opacity="0.6"/><path d="M9 5v6l2-2 2 2V5" fill="white" opacity="0.5"/>',
            pdf:  '<defs><linearGradient id="g5" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#EF4444"/><stop offset="100%" stop-color="#DC2626"/></linearGradient></defs><rect x="3" y="2" width="18" height="20" rx="3" fill="url(#g5)"/><rect x="7.5" y="7" width="9" height="1.5" rx="0.75" fill="white" opacity="0.5"/><rect x="7.5" y="10.5" width="7" height="1.5" rx="0.75" fill="white" opacity="0.45"/><rect x="7.5" y="14" width="10" height="1.5" rx="0.75" fill="white" opacity="0.35"/><rect x="7.5" y="17.5" width="6" height="1.5" rx="0.75" fill="white" opacity="0.2"/>',
            doc:  '<defs><linearGradient id="g6" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#3B82F6"/><stop offset="100%" stop-color="#2563EB"/></linearGradient></defs><rect x="3" y="2" width="18" height="20" rx="3" fill="url(#g6)"/><rect x="7.5" y="7" width="9" height="1.5" rx="0.75" fill="white" opacity="0.5"/><rect x="7.5" y="10.5" width="7" height="1.5" rx="0.75" fill="white" opacity="0.45"/><rect x="7.5" y="14" width="10" height="1.5" rx="0.75" fill="white" opacity="0.35"/><rect x="7.5" y="17.5" width="6" height="1.5" rx="0.75" fill="white" opacity="0.2"/>',
            xls:  '<defs><linearGradient id="g7" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#10B981"/><stop offset="100%" stop-color="#059669"/></linearGradient></defs><rect x="3" y="2" width="18" height="20" rx="3" fill="url(#g7)"/><rect x="6.5" y="6.5" width="11" height="7" rx="0.5" fill="white" opacity="0.2"/><line x1="6.5" y1="9" x2="17.5" y2="9" stroke="white" stroke-width="0.8" opacity="0.5"/><line x1="6.5" y1="11.5" x2="17.5" y2="11.5" stroke="white" stroke-width="0.8" opacity="0.5"/><line x1="12" y1="6.5" x2="12" y2="13.5" stroke="white" stroke-width="0.8" opacity="0.4"/>',
            ppt:  '<defs><linearGradient id="g8" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#F97316"/><stop offset="100%" stop-color="#EA580C"/></linearGradient></defs><rect x="3" y="2" width="18" height="20" rx="3" fill="url(#g8)"/><rect x="6.5" y="6.5" width="11" height="8" rx="0.5" fill="white" opacity="0.15"/><rect x="7.5" y="7" width="9" height="2" rx="0.5" fill="white" opacity="0.5"/><rect x="7.5" y="10.5" width="6" height="1.2" rx="0.5" fill="white" opacity="0.35"/>',
            code: '<defs><linearGradient id="g9" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#4B5563"/><stop offset="100%" stop-color="#374151"/></linearGradient></defs><rect x="2.5" y="2.5" width="19" height="19" rx="3" fill="url(#g9)"/><path d="M8 8l-3 4 3 4" fill="none" stroke="#93C5FD" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 8l3 4-3 4" fill="none" stroke="#93C5FD" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><line x1="13.5" y1="7" x2="10.5" y2="17" stroke="#FCA5A5" stroke-width="1" stroke-linecap="round"/>',
            txt:  '<defs><linearGradient id="g10" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#9CA3AF"/><stop offset="100%" stop-color="#6B7280"/></linearGradient></defs><rect x="3" y="3" width="18" height="18" rx="3" fill="url(#g10)"/><rect x="7.5" y="7" width="9" height="1.5" rx="0.75" fill="white" opacity="0.5"/><rect x="7.5" y="10.5" width="7" height="1.5" rx="0.75" fill="white" opacity="0.4"/><rect x="7.5" y="14" width="10" height="1.5" rx="0.75" fill="white" opacity="0.3"/>',
            file: '<defs><linearGradient id="g11" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#6B7280"/><stop offset="100%" stop-color="#4B5563"/></linearGradient></defs><rect x="3" y="2" width="18" height="20" rx="3" fill="url(#g11)"/><rect x="7.5" y="7" width="9" height="1.5" rx="0.75" fill="white" opacity="0.5"/><rect x="7.5" y="10.5" width="7" height="1.5" rx="0.75" fill="white" opacity="0.35"/><rect x="7.5" y="14" width="10" height="1.5" rx="0.75" fill="white" opacity="0.25"/>',
            exe:  '<defs><linearGradient id="g12" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#8B5CF6"/><stop offset="100%" stop-color="#6D28D9"/></linearGradient></defs><rect x="3" y="4" width="18" height="17" rx="3" fill="url(#g12)"/><path d="M5 4a2 2 0 0 0-2 2v3h18V6a2 2 0 0 0-2-2H5z" fill="white" opacity=".15"/><circle cx="7" cy="6.5" r=".8" fill="white" opacity=".3"/><circle cx="10.5" cy="6.5" r=".8" fill="white" opacity=".3"/><circle cx="14" cy="6.5" r=".8" fill="white" opacity=".3"/><rect x="6" y="11" width="12" height="1.5" rx=".75" fill="white" opacity=".35"/><rect x="6" y="14" width="8" height="1.5" rx=".75" fill="white" opacity=".25"/><rect x="6" y="17" width="10" height="1.5" rx=".75" fill="white" opacity=".15"/>',
            archive: '<defs><linearGradient id="g13" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#D97706"/><stop offset="100%" stop-color="#B45309"/></linearGradient></defs><rect x="3" y="5" width="18" height="16" rx="3" fill="url(#g13)"/><rect x="3" y="5" width="18" height="4" rx="3" fill="white" opacity=".1"/><path d="M3 9h18v-1c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v1z" fill="white" opacity=".08"/><path d="M9 14l3 3 3-3" fill="none" stroke="white" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" opacity=".5"/><line x1="12" y1="10" x2="12" y2="21" stroke="white" stroke-width="1" opacity=".3"/>',
            font:  '<defs><linearGradient id="g14" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#EC4899"/><stop offset="100%" stop-color="#BE185D"/></linearGradient></defs><rect x="3" y="3" width="18" height="18" rx="3" fill="url(#g14)"/><text x="12" y="17" text-anchor="middle" font-family="serif" font-size="14" font-weight="bold" fill="white" opacity=".85">Aa</text>',
            disk:  '<defs><linearGradient id="g15" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#64748B"/><stop offset="100%" stop-color="#475569"/></linearGradient></defs><circle cx="12" cy="12" r="9" fill="url(#g15)"/><circle cx="12" cy="12" r="3" fill="none" stroke="white" stroke-width="1.2" opacity=".25"/><circle cx="12" cy="12" r="1.3" fill="white" opacity=".3"/><path d="M12 3a9 9 0 1 0 0 18" fill="none" stroke="white" stroke-width=".8" opacity=".12"/>'
        },
        // ── 手绘风格：不规则草图 ──
        handdrawn: {
            dir:  '<path d="M3.5 6.2c-.3.5-.6 1.2-.5 1.8.1.5.2 1 .5 1.4l3.5-5c-.6-.5-1.3-.8-2.1-.7-.9.1-1.5.8-1.4 2.5zm.8 2.5l.8 11.5c0 .8.3 1.5.8 1.9.6.5 1.3.7 2 .4.6-.2 1-.7 1.2-1.3L10 5.5c.2-.8-.2-1.5-.7-1.9-.6-.4-1.3-.5-1.9-.2-.5.2-.9.7-1.1 1.4L5 17.5c-.2.6 0 1.2.4 1.6.3.5.9.8 1.5.8h11.3c.7 0 1.4-.3 1.8-.9.5-.5.6-1.2.5-1.9-.2-.6-.6-1.1-1.2-1.3" fill="none" stroke="#92400E" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
            img:  '<rect x="2" y="4" width="20" height="16" rx="3" fill="#BFDBFE" stroke="#3B82F6" stroke-width="1.6" stroke-linejoin="round"/><circle cx="8.5" cy="9" r="3" fill="#93C5FD" opacity="0.7" transform="rotate(-5,8.5,9)"/><path d="M1.5 18.5l5.5-7 3.5 4 4.5-6 7 9" fill="none" stroke="#2563EB" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" opacity="0.6"/>',
            vid:  '<rect x="2" y="4" width="20" height="16" rx="3" fill="#FECACA" stroke="#DC2626" stroke-width="1.6" stroke-linejoin="round"/><polygon points="9.5,7 18,12.5 9.5,17" fill="#DC2626" opacity="0.8" transform="rotate(2,13.5,12)"/>',
            aud:  '<circle cx="8" cy="15" r="5" fill="#DDD6FE" stroke="#8B5CF6" stroke-width="1.5" stroke-linejoin="round"/><circle cx="18" cy="11" r="5.5" fill="#C4B5FD" stroke="#7C3AED" stroke-width="1.2" stroke-linejoin="round" opacity="0.7" transform="rotate(-3,18,11)"/><path d="M9 5v6l2-2.5 2 2.5V5" fill="none" stroke="#7C3AED" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" opacity="0.6"/>',
            pdf:  '<rect x="2.5" y="1.5" width="19" height="21" rx="3" fill="#FECACA" stroke="#DC2626" stroke-width="1.8" stroke-linejoin="round"/><rect x="7" y="7.5" width="10" height="2" rx="1" fill="#DC2626" opacity="0.35"/><rect x="7" y="11" width="8" height="2" rx="1" fill="#DC2626" opacity="0.3"/><rect x="7" y="14.5" width="11" height="2" rx="1" fill="#DC2626" opacity="0.2"/>',
            doc:  '<rect x="2.5" y="1.5" width="19" height="21" rx="3" fill="#BFDBFE" stroke="#2563EB" stroke-width="1.8" stroke-linejoin="round"/><rect x="7" y="7.5" width="10" height="2" rx="1" fill="#2563EB" opacity="0.35"/><rect x="7" y="11" width="8" height="2" rx="1" fill="#2563EB" opacity="0.3"/><rect x="7" y="14.5" width="11" height="2" rx="1" fill="#2563EB" opacity="0.2"/>',
            xls:  '<rect x="2.5" y="1.5" width="19" height="21" rx="3" fill="#D1FAE5" stroke="#059669" stroke-width="1.8" stroke-linejoin="round"/><rect x="6" y="6" width="12" height="8" rx="1" fill="#059669" opacity="0.12"/><line x1="6" y1="9" x2="18" y2="9" stroke="#059669" stroke-width="1" opacity="0.35"/><line x1="6" y1="12" x2="18" y2="12" stroke="#059669" stroke-width="1" opacity="0.35"/><line x1="12" y1="6" x2="12" y2="14" stroke="#059669" stroke-width="1" opacity="0.3"/>',
            ppt:  '<rect x="2.5" y="1.5" width="19" height="21" rx="3" fill="#FFEDD5" stroke="#EA580C" stroke-width="1.8" stroke-linejoin="round"/><rect x="6" y="6" width="12" height="8" rx="1" fill="#EA580C" opacity="0.1"/><rect x="7" y="7" width="10" height="2.5" rx="1.2" fill="#EA580C" opacity="0.35"/><rect x="7" y="11" width="7" height="1.5" rx="0.8" fill="#EA580C" opacity="0.25"/>',
            code: '<rect x="2" y="2" width="20" height="20" rx="3" fill="#E2E8F0" stroke="#475569" stroke-width="1.8" stroke-linejoin="round"/><path d="M7.5 8l-3.5 4 3.5 4" fill="none" stroke="#475569" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M16.5 8l3.5 4-3.5 4" fill="none" stroke="#475569" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><line x1="13.5" y1="6.5" x2="10.5" y2="17.5" stroke="#EF4444" stroke-width="1.2" stroke-linecap="round"/>',
            txt:  '<rect x="2.5" y="2.5" width="19" height="19" rx="3" fill="#F1F5F9" stroke="#64748B" stroke-width="1.8" stroke-linejoin="round"/><rect x="7" y="7.5" width="10" height="2" rx="1" fill="#64748B" opacity="0.35"/><rect x="7" y="11" width="8" height="2" rx="1" fill="#64748B" opacity="0.25"/><rect x="7" y="14.5" width="6" height="2" rx="1" fill="#64748B" opacity="0.2"/>',
            file: '<rect x="2.5" y="1.5" width="19" height="21" rx="3" fill="#F1F5F9" stroke="#64748B" stroke-width="1.8" stroke-linejoin="round"/><rect x="7" y="7.5" width="10" height="2" rx="1" fill="#64748B" opacity="0.35"/><rect x="7" y="11" width="7" height="2" rx="1" fill="#64748B" opacity="0.25"/><rect x="7" y="14.5" width="11" height="2" rx="1" fill="#64748B" opacity="0.18"/>',
            exe:  '<rect x="3" y="4" width="18" height="17" rx="3" fill="#EDE9FE" stroke="#7C3AED" stroke-width="1.8" stroke-linejoin="round"/><path d="M3 10h18" stroke="#7C3AED" stroke-width="1.5" stroke-linecap="round" opacity=".5"/><circle cx="7" cy="7" r="1" fill="#A78BFA" opacity=".6"/><circle cx="10.5" cy="7" r="1" fill="#A78BFA" opacity=".6"/><circle cx="14" cy="7" r="1" fill="#A78BFA" opacity=".6"/>',
            archive: '<rect x="3" y="5" width="18" height="16" rx="3" fill="#FEF3C7" stroke="#D97706" stroke-width="1.8" stroke-linejoin="round"/><path d="M3 5l3-2.5h12l3 2.5H3z" fill="#FDE68A" stroke="#D97706" stroke-width=".8" opacity=".7"/><path d="M9 12l3 4 3-4" fill="none" stroke="#D97706" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" opacity=".5"/>',
            font:  '<rect x="3" y="3" width="18" height="18" rx="3" fill="#FCE7F3" stroke="#DB2777" stroke-width="1.8" stroke-linejoin="round"/><text x="12" y="17" text-anchor="middle" font-family="serif" font-size="14" font-weight="bold" fill="#DB2777" opacity=".75">Aa</text>',
            disk:  '<circle cx="12" cy="12" r="9" fill="#F1F5F9" stroke="#64748B" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="3.5" fill="none" stroke="#94A3B8" stroke-width="1.2" opacity=".5"/><circle cx="12" cy="12" r="1.5" fill="#94A3B8" opacity=".4"/>'
        }
    };

    function getSvgIcon(file) {
        var icons = SVG_STYLES[svgIconStyle] || SVG_STYLES.material;
        var key;
        if (file.type === 'dir') key = 'dir';
        else if (isImage(file)) key = 'img';
        else if (isVideo(file)) key = 'vid';
        else if (isAudio(file)) key = 'aud';
        else if (file.mime === 'application/pdf') key = 'pdf';
        else if (isOffice(file)) {
            var ext = (file.ext || '').toLowerCase();
            if (ext.startsWith('doc')) key = 'doc';
            else if (ext.startsWith('xls')) key = 'xls';
            else if (ext.startsWith('ppt')) key = 'ppt';
            else key = 'file';
        } else if (isExe(file)) key = 'exe';
        else if (isArchive(file)) key = 'archive';
        else if (isDisk(file)) key = 'disk';
        else if (isFont(file)) key = 'font';
        else if (file.is_code) key = 'code';
        else if (file.is_text) key = 'txt';
        else key = 'file';
        return '<span class="file-icon fi-svg-icon">' + svgWrap(icons[key]) + '</span>';
    }

    /* ========== 方案三：CSS 纯样式 ========== */
    function getCssIcon(file) {
        if (file.type === 'dir') return '<span class="file-icon fi-css fi-css-dir"></span>';
        if (isImage(file)) return '<span class="file-icon fi-css fi-css-img"></span>';
        if (isVideo(file)) return '<span class="file-icon fi-css fi-css-vid"></span>';
        if (isAudio(file)) return '<span class="file-icon fi-css fi-css-aud"></span>';
        if (file.mime === 'application/pdf') return '<span class="file-icon fi-css fi-css-pdf"></span>';
        if (isOffice(file)) {
            var ext = (file.ext || '').toLowerCase();
            if (ext.startsWith('doc')) return '<span class="file-icon fi-css fi-css-doc"></span>';
            if (ext.startsWith('xls')) return '<span class="file-icon fi-css fi-css-xls"></span>';
            if (ext.startsWith('ppt')) return '<span class="file-icon fi-css fi-css-ppt"></span>';
            return '<span class="file-icon fi-css fi-css-file"></span>';
        }
        if (file.is_code) return '<span class="file-icon fi-css fi-css-code"></span>';
        if (file.is_text) return '<span class="file-icon fi-css fi-css-txt"></span>';
        return '<span class="file-icon fi-css fi-css-file"></span>';
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
            'application/msword': 'Word 文档',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word 文档',
            'application/vnd.ms-excel': 'Excel 表格',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel 表格',
            'application/vnd.ms-powerpoint': 'PowerPoint 演示文稿',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PowerPoint 演示文稿',
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
            fileName.className = 'file-name ' + nameClass;

            // 搜索模式下，显示子目录路径前缀
            if (isSearchMode) {
                const pathParts = file.path.split('/');
                if (pathParts.length > 1) {
                    const dirPrefix = document.createElement('span');
                    dirPrefix.className = 'search-path-prefix';
                    dirPrefix.textContent = pathParts.slice(0, -1).join('/') + '/';
                    fileName.appendChild(dirPrefix);
                }
            }
            fileName.appendChild(document.createTextNode(file.name));

            if (isImage(file)) {
                fileName.dataset.imagePath = encodeURIComponent(file.path);
            }

            fileName.addEventListener('click', () => {
                if (file.type === 'dir') {
                    window.location.href = '?path=' + encodeURIComponent(file.path);
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
                actionsCell.innerHTML =
                    '<button class="action-btn action-btn-preview" data-path="' + encodeURIComponent(file.path) + '">预览</button>' +
                    '<button class="action-btn action-btn-copy" data-path="' + encodeURIComponent(file.path) + '">复制链接</button>' +
                    '<button class="action-btn action-btn-download" data-path="' + encodeURIComponent(file.path) + '">下载</button>';
            } else {
                actionsCell.innerHTML =
                    '<button class="action-btn action-btn-copy" data-path="' + encodeURIComponent(file.path) + '">复制链接</button>';
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

    // MIME 类型兜底判断
    function isImageMime(mime) { return (mime || '').startsWith('image/'); }
    function isVideoMime(mime) { return (mime || '').startsWith('video/'); }
    function isAudioMime(mime) { return (mime || '').startsWith('audio/'); }
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

    function isImage(file)  { return file.is_image  || isImageMime(file.mime); }
    function isVideo(file)  { return file.is_video  || isVideoMime(file.mime); }
    function isAudio(file)  { return file.is_audio  || isAudioMime(file.mime); }
    function isOffice(file) { return file.is_office || isOfficeMime(file.mime); }

    // 扩展名集（用于 SVG 图标细分）
    var EXE_EXTS  = ['exe','dll','msi','apk','com','scr','sys','drv','ocx','cpl','appimage','bin','gadget'];
    var ARCH_EXTS = ['zip','rar','7z','tar','gz','bz2','xz','zst','tgz','tbz2','txz','lz','lz4','z','arj','cab','lzh','lha','sit','sitx','zst'];
    var FONT_EXTS = ['ttf','otf','woff','woff2','eot'];
    var DISK_EXTS = ['iso','dmg','vhd','vhdx','vmdk','qcow2','vdi','hdd'];

    function isExe(file)     { return EXE_EXTS.indexOf((file.ext || '').toLowerCase()) !== -1; }
    function isArchive(file) { return ARCH_EXTS.indexOf((file.ext || '').toLowerCase()) !== -1; }
    function isFont(file)    { return FONT_EXTS.indexOf((file.ext || '').toLowerCase()) !== -1; }
    function isDisk(file)    { return DISK_EXTS.indexOf((file.ext || '').toLowerCase()) !== -1; }

    function previewFile(file) {
        modalTitle.textContent = file.name;
        modalBody.innerHTML = '';

        if (isImage(file)) {
            const img = document.createElement('img');
            img.src = '?action=preview&path=' + encodeURIComponent(file.path);
            img.alt = file.name;
            modalBody.appendChild(img);
        } else if (file.mime === 'application/pdf') {
            const container = document.createElement('div');
            container.className = 'pdf-container';
            const embed = document.createElement('embed');
            embed.src = '?action=preview&path=' + encodeURIComponent(file.path);
            embed.type = 'application/pdf';
            embed.style.border = 'none';
            container.appendChild(embed);
            modalBody.appendChild(container);
        } else if (isVideo(file)) {
            const video = document.createElement('video');
            video.src = '?action=preview&path=' + encodeURIComponent(file.path);
            video.controls = true;
            video.autoplay = false;
            video.preload = 'auto';
            video.style.maxWidth = '100%';
            video.style.maxHeight = 'calc(90vh - 140px)';
            modalBody.appendChild(video);
        } else if (isAudio(file)) {
            const audio = document.createElement('audio');
            audio.src = '?action=preview&path=' + encodeURIComponent(file.path);
            audio.controls = true;
            audio.preload = 'auto';
            modalBody.appendChild(audio);
        } else if (file.is_text || file.is_code) {
            fetch('?action=preview&path=' + encodeURIComponent(file.path))
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
            const loadingEl = document.createElement('div');
            loadingEl.className = 'modal-loading';
            loadingEl.id = 'officeLoading';
            loadingEl.innerHTML =
                '<div class="spinner" id="officeSpinner"></div>' +
                '<div class="spinner-text" id="officeStatusText">正在转换文档</div>' +
                '<div class="spinner-hint" id="officeHintText">请稍候…</div>';
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

            const officePreviewUrl = '?action=office_preview&path=' + encodeURIComponent(file.path);
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
                const loaderEl = document.getElementById('officeLoading');
                if (loaderEl) loaderEl.remove();
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
            modalBody.innerHTML =
                '<div class="modal-not-supported">' +
                    '<div class="ns-icon">📄</div>' +
                    '<div class="ns-title">暂不支持预览此文件类型</div>' +
                    '<div class="ns-type">' + (file.mime || '未知') + (file.ext ? ' (.' + file.ext.toUpperCase() + ')' : '') + '</div>' +
                    '<div class="ns-hint">您可以下载后使用本地应用打开，或复制链接分享给他人</div>' +
                    '<div class="ns-actions">' +
                        '<button class="ns-btn ns-btn-download" id="nsDownloadBtn" data-path="' + nsPath + '">⬇ 下载文件</button>' +
                        '<button class="ns-btn ns-btn-copy" id="nsCopyBtn" data-path="' + nsPath + '">📋 复制链接</button>' +
                    '</div>' +
                '</div>';
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
        window.location.href = '?action=download&path=' + encodeURIComponent(file.path);
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
        const previewUrl = window.location.origin + window.location.pathname + '?action=preview&path=' + encodeURIComponent(file.path);
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

    // 判断字符串首字符类别：0=符号，1=英文/数字，2=中文
    function charCategory(str) {
        if (!str || str.length === 0) return 0;
        const c = str.charAt(0);
        if (/^[\u4e00-\u9fff\u3400-\u4dbf\uf900-\ufaff]$/.test(c)) return 2; // 中文
        if (/^[a-zA-Z0-9]$/.test(c)) return 1; // 英文/数字
        return 0; // 符号
    }

    // 智能排序：符号 → 英文/数字 → 中文拼音，每类内部按各自规则排序
    function smartStringCompare(a, b) {
        const catA = charCategory(a);
        const catB = charCategory(b);
        if (catA !== catB) return catA - catB;
        if (catA === 2) {
            // 中文：按拼音排序
            return a.toLowerCase().localeCompare(b.toLowerCase(), 'zh-CN');
        }
        // 英文/数字/符号：标准字母序
        return a.toLowerCase().localeCompare(b.toLowerCase());
    }

    function applySort() {
        sortableThs.forEach(th => {
            th.classList.remove('sorted-asc', 'sorted-desc');
        });
        const currentTh = document.querySelector('.file-table th[data-sort="' + sortField + '"]');
        if (currentTh) {
            currentTh.classList.add('sorted-' + sortOrder);
        }

        const sorted = [...allFiles].sort((a, b) => {
            if (a.type !== b.type) {
                return a.type === 'dir' ? -1 : 1;
            }
            let aVal = a[sortField];
            let bVal = b[sortField];
            if (typeof aVal === 'string') {
                const cmp = smartStringCompare(aVal, bVal);
                return sortOrder === 'asc' ? cmp : -cmp;
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
        isSearchMode = false;
        fileTableBody.innerHTML = '<tr><td colspan="5" class="loading">加载中</td></tr>';
        emptyState.style.display = 'none';
        document.querySelector('.file-table-wrapper').style.display = 'block';

        fetch('?action=list&path=' + encodeURIComponent(path))
            .then(response => response.json())
            .then(files => {
                if (files.error) {
                    fileTableBody.innerHTML = '<tr><td colspan="5" class="empty-state"><div class="icon">⚠️</div><h3>错误</h3><p>' + files.error + '</p></td></tr>';
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
        const keyword = searchInput.value.trim();
        if (!keyword) {
            // 清空搜索 → 恢复当前目录
            searchInput.value = '';
            loadFiles(currentPath);
            return;
        }

        fileTableBody.innerHTML = '<tr><td colspan="5" class="loading">搜索中...</td></tr>';
        emptyState.style.display = 'none';
        document.querySelector('.file-table-wrapper').style.display = 'block';

        fetch('?action=search&keyword=' + encodeURIComponent(keyword))
            .then(response => response.json())
            .then(files => {
                isSearchMode = true;
                if (files.error) {
                    fileTableBody.innerHTML = '<tr><td colspan="5" class="empty-state"><div class="icon">⚠️</div><h3>搜索出错</h3><p>' + files.error + '</p></td></tr>';
                    return;
                }
                if (files.length === 0) {
                    fileTableBody.innerHTML = '<tr><td colspan="5" class="empty-state"><div class="icon">🔍</div><h3>未找到匹配项</h3><p>没有搜索到包含 "' + escHtml(keyword) + '" 的文件或目录</p></td></tr>';
                    return;
                }
                allFiles = files;
                applySort();
            })
            .catch(() => {
                fileTableBody.innerHTML = '<tr><td colspan="5" class="empty-state"><div class="icon">❌</div><h3>搜索失败</h3><p>无法完成搜索，请重试</p></td></tr>';
            });
    }

    searchInput.addEventListener('keyup', (e) => {
        if (e.key === 'Enter') handleSearch();
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

    // 图片悬停预览
    const imageTooltip = document.getElementById('imageTooltip');
    let currentImageTarget = null;

    document.addEventListener('mouseover', (e) => {
        const target = e.target.closest('.file-name[data-image-path]');
        if (!target || target === currentImageTarget) return;
        currentImageTarget = target;

        const imagePath = target.dataset.imagePath;
        const rect = target.getBoundingClientRect();

        imageTooltip.innerHTML = '';
        imageTooltip.style.left = (rect.left + 10) + 'px';
        imageTooltip.style.top = (rect.top - 60) + 'px';
        imageTooltip.classList.add('active');

        const img = new Image();
        img.onload = function() {
            if (currentImageTarget !== target) return;
            imageTooltip.innerHTML = '';
            imageTooltip.appendChild(img);

            const tw = imageTooltip.offsetWidth;
            const th = imageTooltip.offsetHeight;

            let x = rect.left + 10;
            let y = rect.top - th - 10;

            if (x + tw > window.innerWidth) {
                x = rect.right - tw - 10;
            }
            if (x < 0) x = 10;
            if (y < 0) {
                y = rect.bottom + 10;
            }

            imageTooltip.style.left = x + 'px';
            imageTooltip.style.top = y + 'px';
        };
        img.src = '?action=preview&path=' + imagePath;
    });

    document.addEventListener('mouseout', (e) => {
        const target = e.target.closest('.file-name[data-image-path]');
        if (target) {
            currentImageTarget = null;
            imageTooltip.classList.remove('active');
        }
    });

    /* ========== 个性化面板 ========== */
    const personalizeBtn = document.getElementById('personalizeBtn');
    const personalizePopup = document.getElementById('personalizePopup');
    const personalizeOverlay = document.getElementById('personalizeOverlay');
    const themeDots = document.querySelectorAll('.personalize-dot');
    const fontSelect = document.getElementById('fontSelect');

    function openPersonalize() {
        const btnRect = personalizeBtn.getBoundingClientRect();
        let left = btnRect.right - 320;
        if (left < 10) left = 10;
        let top = btnRect.bottom + 8;
        if (top + 480 > window.innerHeight) {
            top = btnRect.top - 480;
            if (top < 10) top = 10;
        }
        personalizePopup.style.left = left + 'px';
        personalizePopup.style.top = top + 'px';
        personalizePopup.classList.add('active');
        personalizeOverlay.classList.add('active');
    }

    function closePersonalize() {
        personalizePopup.classList.remove('active');
        personalizeOverlay.classList.remove('active');
    }

    personalizeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (personalizePopup.classList.contains('active')) {
            closePersonalize();
        } else {
            openPersonalize();
        }
    });

    personalizeOverlay.addEventListener('click', closePersonalize);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && personalizePopup.classList.contains('active')) {
            closePersonalize();
            if (previewModal.classList.contains('active')) {
                previewModal.classList.remove('active');
            }
        }
    });

    window.addEventListener('resize', () => {
        if (personalizePopup.classList.contains('active')) {
            openPersonalize();
        }
    });

    // 主题
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

    // 布局宽度
    const layoutBtns = document.querySelectorAll('.personalize-option-btn[data-layout]');
    function getStoredLayout() {
        return localStorage.getItem('filelist-layout') || 'default';
    }
    function applyLayout(layout) {
        document.body.setAttribute('data-layout', layout);
        localStorage.setItem('filelist-layout', layout);
        layoutBtns.forEach(b => b.classList.toggle('active', b.dataset.layout === layout));
    }
    layoutBtns.forEach(btn => {
        btn.addEventListener('click', () => applyLayout(btn.dataset.layout));
    });
    applyLayout(getStoredLayout());

    // 滚动模式
    const scrollBtns = document.querySelectorAll('.personalize-option-btn[data-scroll]');
    function getStoredScroll() {
        return localStorage.getItem('filelist-scroll') || 'normal';
    }
    function applyScroll(mode) {
        document.body.setAttribute('data-scroll', mode);
        localStorage.setItem('filelist-scroll', mode);
        scrollBtns.forEach(b => b.classList.toggle('active', b.dataset.scroll === mode));
    }
    scrollBtns.forEach(btn => {
        btn.addEventListener('click', () => applyScroll(btn.dataset.scroll));
    });
    applyScroll(getStoredScroll());

    // SVG 图标风格
    const svgStyleSelect = document.getElementById('svgStyleSelect');
    function applySvgStyle(style) {
        if (svgIconStyle === style && iconScheme === 'svg') return;
        svgIconStyle = style;
        iconScheme = 'svg';
        localStorage.setItem('filelist-svg-icon-style', style);
        localStorage.setItem('filelist-icon-scheme', 'svg');
        if (svgStyleSelect) svgStyleSelect.value = style;
        // 更新 body 属性，确保 CSS 变量/样式也同步
        body.dataset.iconScheme = 'svg';
        applySort();
    }
    if (svgStyleSelect) {
        svgStyleSelect.addEventListener('change', () => applySvgStyle(svgStyleSelect.value));
        svgStyleSelect.value = svgIconStyle;
    }

    // 首次加载
    loadFiles(currentPath);
})();
