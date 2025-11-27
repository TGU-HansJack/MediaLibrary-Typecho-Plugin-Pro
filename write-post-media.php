<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/PanelHelper.php';

$options = Helper::options();
$pluginUrl = $options->pluginUrl . '/MediaLibrary';
$configOptions = MediaLibrary_PanelHelper::getPluginConfig();
$webdavStatus = MediaLibrary_PanelHelper::getWebDAVStatus($configOptions);
$objectStorageStatus = MediaLibrary_PanelHelper::getObjectStorageStatus();

$uploadStorageOptions = array(
    array(
        'value' => 'local',
        'label' => '本地存储',
        'description' => '保存到服务器的上传目录',
        'available' => true
    )
);

if (!empty($webdavStatus['enabled'])) {
    $uploadStorageOptions[] = array(
        'value' => 'webdav',
        'label' => 'WebDAV',
        'description' => $webdavStatus['message'],
        'available' => !empty($webdavStatus['configured']) && !empty($webdavStatus['connected'])
    );
}

if (!empty($objectStorageStatus) && $objectStorageStatus['class'] !== 'disabled') {
    $uploadStorageOptions[] = array(
        'value' => 'object_storage',
        'label' => $objectStorageStatus['name'],
        'description' => $objectStorageStatus['description'],
        'available' => $objectStorageStatus['class'] === 'active'
    );
}

$storageFilterOptions = array(
    array('value' => 'all', 'label' => '全部存储'),
    array('value' => 'local', 'label' => '本地')
);

if (!empty($webdavStatus['enabled'])) {
    $storageFilterOptions[] = array(
        'value' => 'webdav',
        'label' => 'WebDAV'
    );
}

if (!empty($objectStorageStatus) && $objectStorageStatus['class'] !== 'disabled') {
    $storageFilterOptions[] = array(
        'value' => 'object_storage',
        'label' => $objectStorageStatus['name']
    );
}

$typeFilterOptions = array(
    array('value' => 'all', 'label' => '全部类型'),
    array('value' => 'image', 'label' => '图片'),
    array('value' => 'video', 'label' => '视频'),
    array('value' => 'audio', 'label' => '音频'),
    array('value' => 'document', 'label' => '文档')
);

$defaultUploadStorage = 'local';
$hasDefaultUploadStorage = false;
foreach ($uploadStorageOptions as $storageOption) {
    if ($storageOption['value'] === $defaultUploadStorage && !empty($storageOption['available'])) {
        $hasDefaultUploadStorage = true;
        break;
    }
}
if (!$hasDefaultUploadStorage) {
    $defaultUploadStorage = 'local';
}

$phpMaxFilesize = function_exists('ini_get') ? trim(ini_get('upload_max_filesize')) : '2M';
if (preg_match("/^([0-9]+)([a-z]{1,2})$/i", $phpMaxFilesize, $matches)) {
    $phpMaxFilesize = strtolower($matches[1] . $matches[2] . (1 == strlen($matches[2]) ? 'b' : ''));
}

$allowedTypes = 'jpg,jpeg,png,gif,bmp,webp,svg,mp4,avi,mov,wmv,flv,mp3,wav,ogg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,avif';
$adminAjaxBase = $options->adminUrl . 'extending.php?panel=MediaLibrary%2Fpanel.php';
$editorMediaAjaxUrl = $options->adminUrl . 'extending.php?panel=MediaLibrary/editor-media-ajax.php';
?>

<style>
/* ========================================
   写作页面媒体库组件 - GitHub 风格
   优化版：响应式布局 + 深色模式适配
   ======================================== */

/* CSS 变量定义 - 亮色主题 */
#media-library-container,
.media-library-overlay,
.ml-modal,
.ml-confirm-dialog {
    --ml-bg: #ffffff;
    --ml-bg-secondary: #f6f8fa;
    --ml-bg-tertiary: #eaeef2;
    --ml-bg-overlay: rgba(27, 31, 36, 0.5);
    --ml-border: #d0d7de;
    --ml-border-muted: #d8dee4;
    --ml-border-subtle: #eaeef2;
    --ml-text: #1f2328;
    --ml-text-secondary: #59636e;
    --ml-text-muted: #6e7781;
    --ml-text-placeholder: #8c959f;
    --ml-primary: #0969da;
    --ml-primary-hover: #0860ca;
    --ml-primary-bg: #ddf4ff;
    --ml-primary-border: #54aeff;
    --ml-success: #1a7f37;
    --ml-success-bg: #dafbe1;
    --ml-success-border: #4ac26b;
    --ml-danger: #d1242f;
    --ml-danger-bg: #ffebe9;
    --ml-danger-border: #ff8182;
    --ml-warning: #9a6700;
    --ml-warning-bg: #fff8c5;
    --ml-shadow-sm: 0 1px 0 rgba(31, 35, 40, 0.04);
    --ml-shadow: 0 1px 3px rgba(31, 35, 40, 0.12), 0 8px 24px rgba(66, 74, 83, 0.12);
    --ml-shadow-md: 0 3px 6px rgba(140, 149, 159, 0.15);
    --ml-shadow-lg: 0 8px 24px rgba(140, 149, 159, 0.2);
    --ml-shadow-xl: 0 12px 28px rgba(140, 149, 159, 0.3);
    --ml-radius-sm: 4px;
    --ml-radius: 6px;
    --ml-radius-md: 8px;
    --ml-radius-lg: 12px;
    --ml-transition-fast: 80ms cubic-bezier(0.33, 1, 0.68, 1);
    --ml-transition: 150ms cubic-bezier(0.33, 1, 0.68, 1);
    --ml-transition-slow: 250ms cubic-bezier(0.33, 1, 0.68, 1);
    --ml-focus-ring: 0 0 0 3px rgba(9, 105, 218, 0.3);
    --ml-btn-bg: #f6f8fa;
    --ml-btn-border: rgba(31, 35, 40, 0.15);
    --ml-btn-hover-bg: #f3f4f6;
    --ml-btn-active-bg: #ebecf0;
    color-scheme: light;
}

/* 深色模式变量 */
@media (prefers-color-scheme: dark) {
    #media-library-container,
    .media-library-overlay,
    .ml-modal,
    .ml-confirm-dialog {
        --ml-bg: #0d1117;
        --ml-bg-secondary: #161b22;
        --ml-bg-tertiary: #21262d;
        --ml-bg-overlay: rgba(1, 4, 9, 0.8);
        --ml-border: #30363d;
        --ml-border-muted: #21262d;
        --ml-border-subtle: #30363d;
        --ml-text: #e6edf3;
        --ml-text-secondary: #8d96a0;
        --ml-text-muted: #7d8590;
        --ml-text-placeholder: #6e7681;
        --ml-primary: #2f81f7;
        --ml-primary-hover: #58a6ff;
        --ml-primary-bg: #1c2d41;
        --ml-primary-border: #388bfd;
        --ml-success: #3fb950;
        --ml-success-bg: #1c3d2e;
        --ml-success-border: #2ea043;
        --ml-danger: #f85149;
        --ml-danger-bg: #3d1f20;
        --ml-danger-border: #f85149;
        --ml-warning: #d29922;
        --ml-warning-bg: #3b2e1a;
        --ml-shadow-sm: 0 1px 0 rgba(1, 4, 9, 0.1);
        --ml-shadow: 0 0 0 1px #30363d, 0 16px 32px rgba(1, 4, 9, 0.85);
        --ml-shadow-md: 0 3px 6px rgba(1, 4, 9, 0.4);
        --ml-shadow-lg: 0 8px 24px rgba(1, 4, 9, 0.5);
        --ml-shadow-xl: 0 12px 28px rgba(1, 4, 9, 0.6);
        --ml-focus-ring: 0 0 0 3px rgba(47, 129, 247, 0.4);
        --ml-btn-bg: #21262d;
        --ml-btn-border: rgba(240, 246, 252, 0.1);
        --ml-btn-hover-bg: #30363d;
        --ml-btn-active-bg: #3c444d;
        color-scheme: dark;
    }
}

/* 主容器 */
#media-library-container .media-library-editor {
    background: var(--ml-bg);
    border: 1px solid var(--ml-border);
    border-radius: var(--ml-radius-md);
    padding: clamp(10px, 2vw, 16px);
    margin-top: 12px;
    box-shadow: var(--ml-shadow-sm);
    transition: border-color var(--ml-transition);
}

#media-library-container .media-library-editor:hover {
    border-color: var(--ml-border-muted);
}

/* 工具栏 - GitHub 风格 */
#media-library-container .media-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--ml-border-subtle);
    flex-wrap: wrap;
}

/* GitHub 风格按钮基础 */
#media-library-container .media-toolbar .btn,
.overlay-toolbar .btn,
.ml-modal .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 5px 12px;
    font-size: 14px;
    font-weight: 500;
    line-height: 20px;
    color: var(--ml-text);
    background: var(--ml-btn-bg);
    border: 1px solid var(--ml-btn-border);
    border-radius: var(--ml-radius);
    cursor: pointer;
    transition: background var(--ml-transition-fast), border-color var(--ml-transition-fast), box-shadow var(--ml-transition-fast);
    white-space: nowrap;
    user-select: none;
    vertical-align: middle;
    appearance: none;
    text-decoration: none;
}

#media-library-container .media-toolbar .btn:hover,
.overlay-toolbar .btn:hover,
.ml-modal .btn:hover {
    background: var(--ml-btn-hover-bg);
    border-color: var(--ml-border);
}

#media-library-container .media-toolbar .btn:active,
.overlay-toolbar .btn:active,
.ml-modal .btn:active {
    background: var(--ml-btn-active-bg);
}

#media-library-container .media-toolbar .btn:focus-visible,
.overlay-toolbar .btn:focus-visible,
.ml-modal .btn:focus-visible {
    outline: none;
    box-shadow: var(--ml-focus-ring);
}

#media-library-container .media-toolbar .btn:disabled,
.overlay-toolbar .btn:disabled,
.ml-modal .btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* 主要按钮 - 绿色 */
#media-library-container .media-toolbar .btn-primary,
.overlay-toolbar .btn-primary,
.ml-modal .btn-primary {
    color: #ffffff;
    background: var(--ml-success);
    border-color: var(--ml-success-border);
    box-shadow: var(--ml-shadow-sm), inset 0 1px 0 rgba(255, 255, 255, 0.03);
}

#media-library-container .media-toolbar .btn-primary:hover,
.overlay-toolbar .btn-primary:hover,
.ml-modal .btn-primary:hover {
    background: #1a8039;
}

@media (prefers-color-scheme: dark) {
    #media-library-container .media-toolbar .btn-primary,
    .overlay-toolbar .btn-primary,
    .ml-modal .btn-primary {
        background: #238636;
        border-color: #2ea043;
    }
    #media-library-container .media-toolbar .btn-primary:hover,
    .overlay-toolbar .btn-primary:hover,
    .ml-modal .btn-primary:hover {
        background: #2ea043;
    }
}

/* 危险按钮 */
.overlay-toolbar .btn-danger,
.ml-confirm-dialog .btn-confirm-danger {
    color: #ffffff;
    background: var(--ml-danger);
    border-color: var(--ml-danger-border);
}

.overlay-toolbar .btn-danger:hover,
.ml-confirm-dialog .btn-confirm-danger:hover {
    background: #b91c1c;
}

@media (prefers-color-scheme: dark) {
    .overlay-toolbar .btn-danger:hover,
    .ml-confirm-dialog .btn-confirm-danger:hover {
        background: #da3633;
    }
}

/* 媒体网格容器 - 响应式带滚动 */
#media-library-container .media-grid {
    max-height: clamp(200px, 30vh, 280px);
    min-height: 100px;
    overflow-y: auto;
    overflow-x: hidden;
    border: 1px solid var(--ml-border-muted);
    border-radius: var(--ml-radius);
    padding: clamp(8px, 1.5vw, 12px);
    display: block;
    background: var(--ml-bg-secondary);
    scrollbar-width: thin;
    scrollbar-color: var(--ml-border) transparent;
    position: relative;
    scroll-behavior: smooth;
}

#media-library-container .media-page-group {
    margin-bottom: 16px;
}

#media-library-container .media-page-group:last-child {
    margin-bottom: 0;
}

#media-library-container .media-page-label {
    font-size: 12px;
    font-weight: 500;
    color: var(--ml-text-muted);
    margin: 4px 0 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

#media-library-container .media-page-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--ml-border-subtle);
}

/* 响应式网格布局 */
#media-library-container .media-page-items {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(clamp(80px, 12vw, 100px), 1fr));
    gap: clamp(6px, 1vw, 10px);
    background: var(--ml-bg-secondary);
}

/* 小屏幕 */
@media (max-width: 480px) {
    #media-library-container .media-page-items {
        grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
        gap: 6px;
    }
}

/* 中等屏幕 */
@media (min-width: 768px) {
    #media-library-container .media-page-items {
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
    }
}

/* 大屏幕 */
@media (min-width: 1200px) {
    #media-library-container .media-page-items {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
    }
}

#media-library-container .media-end-indicator {
    text-align: center;
    color: var(--ml-text-muted);
    font-size: 12px;
    padding: 12px 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

#media-library-container .media-end-indicator::before,
#media-library-container .media-end-indicator::after {
    content: '';
    width: 40px;
    height: 1px;
    background: var(--ml-border-subtle);
}

#media-library-container .media-page-items .loading,
#media-library-container .media-page-items .empty-state {
    grid-column: 1 / -1;
}

/* 滚动条样式 */
#media-library-container .media-grid::-webkit-scrollbar {
    width: 8px;
}

#media-library-container .media-grid::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 4px;
}

#media-library-container .media-grid::-webkit-scrollbar-thumb {
    background: var(--ml-border);
    border-radius: 4px;
    border: 2px solid var(--ml-bg-secondary);
}

#media-library-container .media-grid::-webkit-scrollbar-thumb:hover {
    background: var(--ml-text-muted);
}

#media-library-container .media-grid .loading,
#media-library-container .media-grid .empty-state {
    grid-column: 1 / -1;
    text-align: center;
    color: var(--ml-text-muted);
    padding: 32px 16px;
    font-size: 14px;
}

/* 媒体项 - GitHub 风格卡片 */
#media-library-container .editor-media-item {
    background: var(--ml-bg);
    border: 1px solid var(--ml-border-muted);
    border-radius: var(--ml-radius);
    padding: clamp(6px, 1vw, 8px);
    cursor: pointer;
    transition: all var(--ml-transition);
    position: relative;
}

#media-library-container .editor-media-item:hover {
    border-color: var(--ml-border);
    box-shadow: var(--ml-shadow-md);
    transform: translateY(-2px);
}

#media-library-container .editor-media-item:focus-visible {
    outline: none;
    box-shadow: var(--ml-focus-ring);
}

#media-library-container .editor-media-item.selected {
    border-color: var(--ml-primary);
    box-shadow: 0 0 0 3px var(--ml-primary-bg);
    background: var(--ml-primary-bg);
}

#media-library-container .editor-media-item .media-preview {
    width: 100%;
    aspect-ratio: 1;
    min-height: 56px;
    max-height: 80px;
    background: var(--ml-bg-secondary);
    border-radius: var(--ml-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}

#media-library-container .editor-media-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform var(--ml-transition);
}

#media-library-container .editor-media-item:hover img {
    transform: scale(1.05);
}

#media-library-container .editor-media-item .file-icon {
    font-size: clamp(14px, 3vw, 18px);
    color: var(--ml-text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: -0.5px;
}

#media-library-container .editor-media-item .media-title {
    margin-top: 6px;
    font-size: 11px;
    font-weight: 500;
    color: var(--ml-text-secondary);
    text-align: center;
    max-height: 30px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-height: 1.35;
    word-break: break-all;
}

#media-library-container .editor-media-item .media-meta {
    margin-top: 3px;
    font-size: 10px;
    color: var(--ml-text-muted);
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 上传模态框 - GitHub 风格 */
.ml-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--ml-bg-overlay);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 16px;
    opacity: 0;
    pointer-events: none;
    visibility: hidden;
    transition: opacity var(--ml-transition-slow), visibility var(--ml-transition-slow);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}

.ml-modal.active {
    opacity: 1;
    pointer-events: auto;
    visibility: visible;
}

.ml-modal-dialog {
    background: var(--ml-bg);
    width: 440px;
    max-width: calc(100vw - 32px);
    max-height: calc(100vh - 32px);
    border-radius: var(--ml-radius-lg);
    border: 1px solid var(--ml-border);
    box-shadow: var(--ml-shadow);
    padding: 0;
    animation: mlModalScale var(--ml-transition-slow) cubic-bezier(0.33, 1, 0.68, 1);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.ml-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    border-bottom: 1px solid var(--ml-border-muted);
    background: var(--ml-bg-secondary);
}

.ml-modal-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--ml-text);
}

.ml-modal-close {
    cursor: pointer;
    font-size: 20px;
    color: var(--ml-text-muted);
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--ml-radius);
    transition: background var(--ml-transition-fast), color var(--ml-transition-fast);
}

.ml-modal-close:hover {
    color: var(--ml-text);
    background: var(--ml-bg-tertiary);
}

.ml-modal-body {
    padding: 16px;
    font-size: 14px;
    color: var(--ml-text-secondary);
    overflow-y: auto;
    flex: 1;
}

/* 存储选择控件 */
.ml-upload-storage-control {
    margin-bottom: 16px;
}

.ml-upload-storage-control > div:first-child {
    font-size: 13px;
    font-weight: 500;
    color: var(--ml-text);
    margin-bottom: 8px;
}

.ml-storage-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.ml-storage-pill {
    border: 1px solid var(--ml-border);
    border-radius: var(--ml-radius);
    padding: 10px 12px;
    font-size: 13px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    min-width: 110px;
    flex: 1;
    background: var(--ml-bg);
    transition: all var(--ml-transition);
}

.ml-storage-pill:hover:not(.disabled) {
    border-color: var(--ml-primary-border);
    background: var(--ml-bg-secondary);
}

.ml-storage-pill:has(input:checked) {
    border-color: var(--ml-primary);
    background: var(--ml-primary-bg);
}

.ml-storage-pill input {
    display: none;
}

.ml-storage-pill .storage-pill-name {
    font-weight: 600;
    color: var(--ml-text);
    font-size: 13px;
}

.ml-storage-pill:has(input:checked) .storage-pill-name {
    color: var(--ml-primary);
}

.ml-storage-pill .storage-pill-desc {
    color: var(--ml-text-muted);
    font-size: 11px;
    margin-top: 3px;
    line-height: 1.3;
}

.ml-storage-pill.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.ml-upload-storage-hint {
    margin-top: 10px;
    font-size: 12px;
    color: var(--ml-text-secondary);
    padding: 8px 10px;
    background: var(--ml-bg-secondary);
    border-radius: var(--ml-radius-sm);
}

.ml-upload-storage-hint strong {
    color: var(--ml-primary);
}

/* 上传区域 */
.ml-upload-area {
    border: 2px dashed var(--ml-border);
    border-radius: var(--ml-radius-md);
    padding: clamp(20px, 4vw, 32px) 20px;
    text-align: center;
    background: var(--ml-bg-secondary);
    transition: all var(--ml-transition);
}

.ml-upload-area:hover {
    border-color: var(--ml-primary-border);
    background: var(--ml-primary-bg);
}

.ml-upload-area.dragover {
    border-color: var(--ml-primary);
    background: var(--ml-primary-bg);
    transform: scale(1.01);
}

.ml-upload-area .upload-hint {
    color: var(--ml-text-muted);
    margin-bottom: 12px;
    font-size: 14px;
}

/* 文件列表 */
#editor-file-list {
    list-style: none;
    margin: 16px 0 0;
    padding: 0;
    max-height: 200px;
    overflow-y: auto;
}

#editor-file-list li {
    border: 1px solid var(--ml-border);
    border-left: 3px solid var(--ml-border);
    border-radius: var(--ml-radius);
    padding: 10px 12px;
    margin-bottom: 8px;
    font-size: 13px;
    color: var(--ml-text-secondary);
    background: var(--ml-bg);
    transition: border-color var(--ml-transition);
}

#editor-file-list li.success {
    border-left-color: var(--ml-success);
    background: var(--ml-success-bg);
}

#editor-file-list li.error {
    border-left-color: var(--ml-danger);
    background: var(--ml-danger-bg);
    color: var(--ml-danger);
}

#editor-file-list .file-name {
    font-weight: 600;
    display: inline-block;
    max-width: 65%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--ml-text);
    vertical-align: middle;
}

#editor-file-list .file-size {
    color: var(--ml-text-muted);
    margin-left: 8px;
    font-size: 12px;
}

#editor-file-list .progress-bar {
    width: 100%;
    height: 4px;
    background: var(--ml-border-muted);
    border-radius: 2px;
    margin-top: 8px;
    overflow: hidden;
}

#editor-file-list .progress-fill {
    width: 0%;
    height: 100%;
    border-radius: 2px;
    background: linear-gradient(90deg, var(--ml-primary), var(--ml-primary-hover));
    transition: width var(--ml-transition);
}

#editor-file-list .status {
    margin-top: 6px;
    font-size: 12px;
}

.media-toolbar .toolbar-actions {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 8px;
}

.media-toolbar .btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: var(--ml-radius);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    background: var(--ml-btn-bg);
    color: var(--ml-text-secondary);
    border: 1px solid var(--ml-btn-border);
    cursor: pointer;
    transition: all var(--ml-transition-fast);
}

.media-toolbar .btn-icon:hover {
    background: var(--ml-btn-hover-bg);
    color: var(--ml-text);
}

.media-toolbar .btn-icon svg {
    width: 16px;
    height: 16px;
}

.media-pagination {
    margin-top: 12px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
}

.media-pagination .pager-btn {
    width: 32px;
    height: 32px;
    border-radius: var(--ml-radius);
    border: 1px solid var(--ml-border);
    background: var(--ml-bg);
    color: var(--ml-text);
    cursor: pointer;
    transition: all var(--ml-transition-fast);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.media-pagination .pager-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.media-pagination .pager-btn:not(:disabled):hover {
    background: var(--ml-btn-hover-bg);
    border-color: var(--ml-primary-border);
}

/* ========================================
   展开版 Overlay - 全屏媒体库
   ======================================== */
.media-library-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--ml-bg-overlay);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: clamp(16px, 3vw, 32px);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.media-library-overlay.active {
    display: flex;
}

/* Overlay 面板 - 高度自适应 */
.media-overlay-panel {
    width: min(96vw, 1200px);
    max-height: calc(100vh - clamp(32px, 6vw, 64px));
    min-height: min(400px, 60vh);
    background: var(--ml-bg);
    border-radius: var(--ml-radius-lg);
    border: 1px solid var(--ml-border);
    display: flex;
    flex-direction: column;
    box-shadow: var(--ml-shadow-xl);
    animation: mlPanelSlideIn var(--ml-transition-slow) cubic-bezier(0.34, 1.56, 0.64, 1);
    overflow: hidden;
}

/* 展开版 Header */
.overlay-header {
    padding: clamp(12px, 2vw, 20px);
    border-bottom: 1px solid var(--ml-border-muted);
    display: flex;
    flex-wrap: wrap;
    gap: clamp(8px, 1.5vw, 16px);
    align-items: center;
    justify-content: space-between;
    background: var(--ml-bg-secondary);
}

.overlay-title {
    font-size: clamp(16px, 2vw, 20px);
    font-weight: 600;
    color: var(--ml-text);
    display: flex;
    align-items: center;
    gap: 10px;
}

.overlay-controls {
    display: flex;
    gap: clamp(6px, 1vw, 10px);
    align-items: center;
    flex-wrap: wrap;
    flex: 1;
    justify-content: flex-end;
}

.overlay-controls input[type="text"] {
    width: clamp(140px, 20vw, 220px);
    border: 1px solid var(--ml-border);
    border-radius: var(--ml-radius);
    padding: 7px 12px;
    font-size: 14px;
    background: var(--ml-bg);
    color: var(--ml-text);
    transition: all var(--ml-transition-fast);
}

.overlay-controls input[type="text"]::placeholder {
    color: var(--ml-text-placeholder);
}

.overlay-controls input[type="text"]:focus {
    outline: none;
    border-color: var(--ml-primary);
    box-shadow: var(--ml-focus-ring);
}

.overlay-controls select {
    border: 1px solid var(--ml-border);
    border-radius: var(--ml-radius);
    padding: 7px 12px;
    font-size: 14px;
    background: var(--ml-bg);
    color: var(--ml-text);
    cursor: pointer;
    transition: border-color var(--ml-transition-fast);
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236e7781' d='M2.5 4.5l3.5 3.5 3.5-3.5'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    padding-right: 28px;
}

@media (prefers-color-scheme: dark) {
    .overlay-controls select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%237d8590' d='M2.5 4.5l3.5 3.5 3.5-3.5'/%3E%3C/svg%3E");
    }
}

.overlay-controls select:focus {
    outline: none;
    border-color: var(--ml-primary);
    box-shadow: var(--ml-focus-ring);
}

/* 展开版工具栏 */
.overlay-toolbar {
    padding: clamp(10px, 1.5vw, 14px) clamp(12px, 2vw, 20px);
    border-bottom: 1px solid var(--ml-border-muted);
    display: flex;
    gap: clamp(6px, 1vw, 10px);
    flex-wrap: wrap;
    align-items: center;
    background: var(--ml-bg);
}

.overlay-toolbar .selection-count {
    font-size: 13px;
    color: var(--ml-text-secondary);
    margin-left: auto;
    padding: 4px 10px;
    background: var(--ml-bg-secondary);
    border-radius: var(--ml-radius-sm);
}

/* 展开版内容区域 */
.media-overlay-content {
    flex: 1;
    overflow: hidden;
    padding: 0 clamp(12px, 2vw, 20px);
    display: flex;
    flex-direction: column;
    background: var(--ml-bg-secondary);
}

/* 展开版响应式网格 - 最多5列布局 */
#expanded-media-grid {
    flex: 1;
    overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
    padding: 16px 4px 16px 0;
    align-content: start;
    scroll-behavior: smooth;
    scrollbar-width: thin;
    scrollbar-color: var(--ml-border) transparent;
}

/* 响应式断点 - 调整列数 */
/* 超大屏幕: 5列 */
@media (max-width: 1200px) {
    #expanded-media-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

/* 中等屏幕: 4列 */
@media (max-width: 900px) {
    #expanded-media-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
    }
}

/* 小屏幕: 3列 */
@media (max-width: 640px) {
    #expanded-media-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
}

/* 超小屏幕: 2列 */
@media (max-width: 400px) {
    #expanded-media-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
}

/* 滚动条样式 */
#expanded-media-grid::-webkit-scrollbar {
    width: 10px;
}

#expanded-media-grid::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 5px;
}

#expanded-media-grid::-webkit-scrollbar-thumb {
    background: var(--ml-border);
    border-radius: 5px;
    border: 2px solid var(--ml-bg-secondary);
}

#expanded-media-grid::-webkit-scrollbar-thumb:hover {
    background: var(--ml-text-muted);
}

/* 展开版媒体项 */
#expanded-media-grid .expanded-media-item {
    background: var(--ml-bg);
    border: 1px solid var(--ml-border-muted);
    border-radius: var(--ml-radius-md);
    padding: clamp(8px, 1.5vw, 12px);
    cursor: pointer;
    transition: all var(--ml-transition);
    animation: mlFadeInUp var(--ml-transition-slow) ease backwards;
    position: relative;
}

#expanded-media-grid .expanded-media-item:hover {
    border-color: var(--ml-border);
    box-shadow: var(--ml-shadow-md);
    transform: translateY(-3px);
}

#expanded-media-grid .expanded-media-item:focus-visible {
    outline: none;
    box-shadow: var(--ml-focus-ring);
}

#expanded-media-grid .expanded-media-item.selected {
    border-color: var(--ml-primary);
    box-shadow: 0 0 0 3px var(--ml-primary-bg);
    background: var(--ml-primary-bg);
}

#expanded-media-grid .expanded-media-item .media-preview {
    width: 100%;
    aspect-ratio: 1;
    min-height: 100px;
    background: var(--ml-bg-secondary);
    border-radius: var(--ml-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}

#expanded-media-grid .expanded-media-item .media-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform var(--ml-transition);
}

#expanded-media-grid .expanded-media-item:hover .media-preview img {
    transform: scale(1.05);
}

#expanded-media-grid .expanded-media-item .file-icon {
    font-size: clamp(18px, 3vw, 28px);
    color: var(--ml-text-secondary);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: -0.5px;
}

#expanded-media-grid .expanded-media-item .media-title {
    margin-top: 10px;
    font-size: clamp(11px, 1.2vw, 13px);
    font-weight: 500;
    color: var(--ml-text);
    text-align: center;
    max-height: 36px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-height: 1.4;
    word-break: break-all;
}

#expanded-media-grid .expanded-media-item .media-meta {
    margin-top: 6px;
    font-size: clamp(10px, 1vw, 12px);
    color: var(--ml-text-muted);
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 选择复选框 */
#expanded-media-grid .expanded-media-item .media-checkbox {
    position: absolute;
    top: 8px;
    left: 8px;
    width: 22px;
    height: 22px;
    border-radius: var(--ml-radius-sm);
    border: 2px solid rgba(255, 255, 255, 0.85);
    background: rgba(0, 0, 0, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all var(--ml-transition);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}

#expanded-media-grid .expanded-media-item:hover .media-checkbox,
#expanded-media-grid .expanded-media-item.selected .media-checkbox {
    opacity: 1;
}

#expanded-media-grid .expanded-media-item.selected .media-checkbox {
    background: var(--ml-primary);
    border-color: var(--ml-primary);
}

#expanded-media-grid .expanded-media-item.selected .media-checkbox::after {
    content: '✓';
    color: #fff;
    font-size: 13px;
    font-weight: bold;
    line-height: 1;
}

/* 加载和空状态 */
#expanded-media-grid .loading,
#expanded-media-grid .empty-state {
    grid-column: 1 / -1;
    text-align: center;
    color: var(--ml-text-muted);
    padding: clamp(40px, 8vw, 80px) 20px;
    font-size: 15px;
}

#expanded-media-grid .end-indicator {
    grid-column: 1 / -1;
    text-align: center;
    color: var(--ml-text-muted);
    font-size: 13px;
    padding: 20px 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

#expanded-media-grid .end-indicator::before,
#expanded-media-grid .end-indicator::after {
    content: '';
    width: 60px;
    height: 1px;
    background: var(--ml-border-subtle);
}

/* 展开版分页 */
.overlay-pagination {
    padding: clamp(12px, 2vw, 18px) clamp(12px, 2vw, 20px);
    border-top: 1px solid var(--ml-border-muted);
    display: flex;
    justify-content: center;
    align-items: center;
    gap: clamp(10px, 1.5vw, 16px);
    background: var(--ml-bg);
}

.overlay-pagination .pager-btn {
    width: 36px;
    height: 36px;
    border-radius: var(--ml-radius);
    border: 1px solid var(--ml-border);
    background: var(--ml-bg);
    color: var(--ml-text);
    font-size: 16px;
    cursor: pointer;
    transition: all var(--ml-transition-fast);
    display: flex;
    align-items: center;
    justify-content: center;
}

.overlay-pagination .pager-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.overlay-pagination .pager-btn:not(:disabled):hover {
    background: var(--ml-btn-hover-bg);
    border-color: var(--ml-primary-border);
}

.overlay-pagination .page-indicator {
    font-size: 14px;
    color: var(--ml-text-secondary);
    padding: 6px 14px;
    background: var(--ml-bg-secondary);
    border-radius: var(--ml-radius);
    min-width: 100px;
    text-align: center;
}

/* 关闭按钮 */
#expanded-close-btn {
    width: 36px;
    height: 36px;
    padding: 0;
    font-size: 22px;
    line-height: 1;
    border-radius: var(--ml-radius);
    display: flex;
    align-items: center;
    justify-content: center;
}

/* 动画关键帧 */
@keyframes mlFadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes mlOverlayFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes mlPanelSlideIn {
    from {
        opacity: 0;
        transform: scale(0.96) translateY(-12px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes mlModalScale {
    from {
        opacity: 0;
        transform: translateY(-10px) scale(0.97);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes mlSpinnerRotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Overlay 动画 */
.media-library-overlay {
    animation: mlOverlayFadeIn var(--ml-transition-slow) ease;
}

.media-library-overlay.closing {
    animation: mlOverlayFadeIn var(--ml-transition) ease reverse;
}

.media-library-overlay.closing .media-overlay-panel {
    animation: mlPanelSlideIn var(--ml-transition) ease reverse;
}

/* 删除确认对话框 */
.ml-confirm-dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--ml-bg-overlay);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10001;
    animation: mlOverlayFadeIn var(--ml-transition) ease;
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}

.ml-confirm-dialog .confirm-box {
    background: var(--ml-bg);
    border-radius: var(--ml-radius-lg);
    border: 1px solid var(--ml-border);
    padding: clamp(20px, 4vw, 28px);
    max-width: 420px;
    width: calc(100% - 32px);
    box-shadow: var(--ml-shadow-xl);
    animation: mlModalScale var(--ml-transition-slow) ease;
}

.ml-confirm-dialog .confirm-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--ml-text);
    margin-bottom: 12px;
}

.ml-confirm-dialog .confirm-message {
    font-size: 14px;
    color: var(--ml-text-secondary);
    margin-bottom: 24px;
    line-height: 1.6;
}

.ml-confirm-dialog .confirm-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.ml-confirm-dialog .confirm-actions .btn {
    padding: 8px 18px;
    font-size: 14px;
    font-weight: 500;
    border-radius: var(--ml-radius);
    cursor: pointer;
    transition: all var(--ml-transition-fast);
}

.ml-confirm-dialog .btn-cancel {
    background: var(--ml-btn-bg);
    border: 1px solid var(--ml-border);
    color: var(--ml-text);
}

.ml-confirm-dialog .btn-cancel:hover {
    background: var(--ml-btn-hover-bg);
}

/* 加载动画 */
.loading::before {
    content: '';
    display: inline-block;
    width: 18px;
    height: 18px;
    border: 2px solid var(--ml-border);
    border-top-color: var(--ml-primary);
    border-radius: 50%;
    animation: mlSpinnerRotate 0.7s linear infinite;
    margin-right: 10px;
    vertical-align: middle;
}

/* Toast 提示 */
.ml-editor-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--ml-text);
    color: var(--ml-bg);
    padding: 10px 18px;
    border-radius: var(--ml-radius);
    box-shadow: var(--ml-shadow-lg);
    opacity: 0;
    transform: translateY(-10px);
    transition: all var(--ml-transition);
    z-index: 10002;
    font-size: 14px;
    font-weight: 500;
    max-width: 320px;
}

.ml-editor-toast.show {
    opacity: 1;
    transform: translateY(0);
}

/* ========================================
   响应式设计 - 移动端优化
   ======================================== */
@media (max-width: 768px) {
    .media-library-overlay {
        padding: 0;
    }

    .media-overlay-panel {
        width: 100%;
        height: 100%;
        max-height: 100vh;
        min-height: 100vh;
        border-radius: 0;
    }

    .overlay-header {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
        padding: 12px 14px;
    }

    .overlay-title {
        font-size: 16px;
    }

    .overlay-controls {
        width: 100%;
        justify-content: stretch;
        gap: 8px;
    }

    .overlay-controls input[type="text"] {
        width: 100%;
        flex: 1;
        min-width: 0;
    }

    .overlay-controls select {
        flex: 1;
        min-width: 0;
    }

    .overlay-toolbar {
        flex-wrap: wrap;
        padding: 10px 14px;
    }

    .overlay-toolbar .btn {
        flex: 1;
        min-width: 70px;
        justify-content: center;
        padding: 6px 10px;
        font-size: 13px;
    }

    .overlay-toolbar .selection-count {
        width: 100%;
        margin-left: 0;
        margin-top: 8px;
        text-align: center;
    }

    .media-overlay-content {
        padding: 0 12px;
    }

    #expanded-media-grid {
        padding: 12px 0;
    }

    #expanded-media-grid .expanded-media-item .media-preview {
        min-height: 70px;
    }

    .overlay-pagination {
        padding: 10px 14px;
    }
}

/* 超小屏幕 */
@media (max-width: 480px) {
    #media-library-container .media-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    #media-library-container .media-toolbar > div:first-child {
        display: flex;
        gap: 8px;
    }

    .media-toolbar .toolbar-actions {
        justify-content: flex-end;
    }

    .media-pagination {
        justify-content: center;
    }

    .overlay-header {
        padding: 10px 12px;
    }

    .overlay-controls {
        flex-direction: column;
    }

    .overlay-controls input[type="text"],
    .overlay-controls select {
        width: 100%;
    }

    .overlay-toolbar .btn {
        font-size: 12px;
        padding: 5px 8px;
    }

    #expanded-media-grid .expanded-media-item {
        padding: 8px;
    }

    #expanded-media-grid .expanded-media-item .media-title {
        font-size: 11px;
        margin-top: 6px;
    }

    #expanded-media-grid .expanded-media-item .media-meta {
        font-size: 10px;
    }
}

/* 大屏幕优化 */
@media (min-width: 1400px) {
    .media-overlay-panel {
        max-width: 1300px;
    }

    .overlay-controls input[type="text"] {
        width: 240px;
    }
}

/* 高对比度模式支持 */
@media (prefers-contrast: high) {
    #media-library-container,
    .media-library-overlay,
    .ml-modal,
    .ml-confirm-dialog {
        --ml-border: currentColor;
        --ml-border-muted: currentColor;
    }
}

/* 减少动画偏好 */
@media (prefers-reduced-motion: reduce) {
    #media-library-container,
    .media-library-overlay,
    .ml-modal,
    .ml-confirm-dialog {
        --ml-transition-fast: 0ms;
        --ml-transition: 0ms;
        --ml-transition-slow: 0ms;
    }

    #expanded-media-grid .expanded-media-item,
    .media-overlay-panel,
    .ml-modal-dialog {
        animation: none !important;
    }
}
</style>

<div class="media-library-editor">
    <div class="media-toolbar">
        <div>
            <button type="button" class="btn btn-primary" id="editor-upload-btn">上传文件</button>
            <button type="button" class="btn" id="editor-copy-markdown" style="display:none;">复制</button>
        </div>
        <div class="toolbar-actions">
            <button type="button" class="btn btn-icon" id="editor-expand-btn" title="展开媒体库">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M5 9V5h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M19 15v4h-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M5 5l5 5M19 19l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
    </div>
    
    <div class="media-grid editor-media-grid">
        <!-- 动态加载内容 -->
        <div class="loading">加载中...</div>
    </div>

    <div class="media-pagination">
        <button type="button" class="pager-btn" id="editor-page-prev" title="上一页">&lsaquo;</button>
        <button type="button" class="pager-btn" id="editor-page-next" title="下一页">&rsaquo;</button>
    </div>
</div>

<div class="ml-modal" id="editor-upload-modal">
    <div class="ml-modal-dialog">
        <div class="ml-modal-header">
            <h3>上传文件</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <div class="ml-upload-storage-control">
                <div>选择存储位置</div>
                <div class="ml-storage-options">
                    <?php foreach ($uploadStorageOptions as $storageOption): ?>
                        <label class="ml-storage-pill <?php echo empty($storageOption['available']) ? 'disabled' : ''; ?>">
                            <input type="radio"
                                name="editor-upload-storage"
                                value="<?php echo $storageOption['value']; ?>"
                                data-label="<?php echo htmlspecialchars($storageOption['label']); ?>"
                                <?php if ($storageOption['value'] === $defaultUploadStorage): ?>checked<?php endif; ?>
                                <?php if (empty($storageOption['available'])): ?>disabled<?php endif; ?>>
                            <div class="storage-pill-text">
                                <span class="storage-pill-name"><?php echo htmlspecialchars($storageOption['label']); ?></span>
                                <?php if (!empty($storageOption['description'])): ?>
                                    <span class="storage-pill-desc"><?php echo htmlspecialchars($storageOption['description']); ?></span>
                                <?php endif; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="ml-upload-storage-hint">当前上传至：<strong id="editor-upload-storage-label"><?php echo $defaultUploadStorage === 'webdav' ? 'WebDAV' : '本地存储'; ?></strong></div>
            </div>

            <div id="editor-upload-area" class="ml-upload-area">
                <div class="upload-hint">拖拽文件到此处或点击按钮选择</div>
                <a href="#" class="btn btn-primary" id="editor-upload-file-btn">选择文件</a>
            </div>
<ul id="editor-file-list"></ul>
        </div>
    </div>
</div>

<div class="media-library-overlay" id="editor-expanded-overlay">
    <div class="media-overlay-panel">
        <div class="overlay-header">
            <div class="overlay-title">媒体库</div>
            <div class="overlay-controls">
                <input type="text" id="expanded-search-input" placeholder="搜索文件...">
                <select id="expanded-type-filter">
                    <?php foreach ($typeFilterOptions as $option): ?>
                        <option value="<?php echo $option['value']; ?>"><?php echo $option['label']; ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="expanded-storage-filter">
                    <?php foreach ($storageFilterOptions as $option): ?>
                        <option value="<?php echo $option['value']; ?>"><?php echo $option['label']; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn" id="expanded-refresh-btn">刷新</button>
                <button type="button" class="btn btn-icon" id="expanded-close-btn" title="关闭">&times;</button>
            </div>
        </div>
        <div class="overlay-toolbar">
            <button type="button" class="btn btn-primary" id="expanded-upload-btn">上传文件</button>
            <button type="button" class="btn btn-danger" id="expanded-delete-btn" disabled>删除选中</button>
            <button type="button" class="btn" id="expanded-copy-btn" disabled>复制</button>
            <span class="selection-count" id="expanded-selection-count"></span>
        </div>
        <div class="media-overlay-content">
            <div class="media-grid" id="expanded-media-grid">
                <div class="loading">加载中...</div>
            </div>
        </div>
        <div class="overlay-pagination">
            <button type="button" class="pager-btn" id="expanded-prev-page">&lsaquo;</button>
            <span class="page-indicator" id="expanded-page-indicator">第 1 页</span>
            <button type="button" class="pager-btn" id="expanded-next-page">&rsaquo;</button>
        </div>
    </div>
</div>

<script src="<?php $options->adminStaticUrl('js', 'moxie.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'plupload.js'); ?>"></script>
<script>
(function(init) {
    function run() {
        if (window.__MediaLibraryEditorInitialized__) {
            return;
        }
        if (!window.jQuery) {
            return;
        }
        window.__MediaLibraryEditorInitialized__ = true;
        init(window.jQuery);
    }

    if (window.jQuery) {
        run();
    } else {
        var attempts = 0;
        var timer = setInterval(function() {
            if (window.jQuery) {
                clearInterval(timer);
                run();
            } else if (++attempts > 200) {
                clearInterval(timer);
                console.error('MediaLibrary: jQuery 未加载，组件初始化失败');
            }
        }, 50);

        window.addEventListener('load', function() {
            if (window.jQuery) {
                clearInterval(timer);
                run();
            }
        });
    }
})(function($) {
    var listUrl = '<?php echo addslashes($editorMediaAjaxUrl); ?>';
    var uploadBaseUrl = '<?php echo addslashes($adminAjaxBase); ?>';
    var allowedTypes = '<?php echo $allowedTypes; ?>';
    var maxFileSize = '<?php echo $phpMaxFilesize; ?>';
    var adminStaticUrl = '<?php echo addslashes($options->adminStaticUrl); ?>';
    var $grid = $('.editor-media-grid');
    var $copyBtn = $('#editor-copy-markdown');
    var $pagerPrev = $('#editor-page-prev');
    var $pagerNext = $('#editor-page-next');
    var defaultPerPage = 20;
    var editorState = {
        perPage: defaultPerPage,
        currentPage: 1,
        maxLoadedPage: 0,
        totalPages: 0,
        hasMore: true,
        loading: false,
        filters: {
            keywords: '',
            type: 'all',
            storage: 'all'
        },
        loadedPages: {},
        selection: {}
    };

    function resetSelection(skipDomUpdate) {
        editorState.selection = {};
        if (!skipDomUpdate) {
            $grid.find('.editor-media-item.selected').removeClass('selected');
        }
        updateCopyButton();
    }

    function updateCopyButton() {
        var selected = Object.keys(editorState.selection).length;
        if (selected > 0) {
            $copyBtn.show().text('复制 (' + selected + ')');
        } else {
            $copyBtn.hide();
        }
    }

    function showToast(message) {
        var toast = $('<div class="ml-editor-toast"></div>').text(message || '操作完成');
        $('body').append(toast);
        setTimeout(function() {
            toast.addClass('show');
        }, 10);
        setTimeout(function() {
            toast.removeClass('show');
            setTimeout(function() {
                toast.remove();
            }, 200);
        }, 2500);
    }

    function escapeMarkdown(text) {
        return (text || '').replace(/\\/g, '\\\\')
            .replace(/\[/g, '\\[')
            .replace(/\]/g, '\\]')
            .replace(/\*/g, '\\*')
            .replace(/_/g, '\\_');
    }

    function buildMarkdownSnippet(title, url, isImage) {
        if (!url) {
            return '';
        }
        var safeTitle = escapeMarkdown(title || url);
        return isImage
            ? '![' + safeTitle + '](' + url + ')'
            : '[' + safeTitle + '](' + url + ')';
    }

    function copyTextToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function(resolve, reject) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            try {
                var successful = document.execCommand('copy');
                document.body.removeChild(textarea);
                if (successful) {
                    resolve();
                } else {
                    reject();
                }
            } catch (err) {
                document.body.removeChild(textarea);
                reject(err);
            }
        });
    }

    function makeItemKey(item) {
        if (item.cid) {
            return 'cid-' + item.cid;
        }
        if (item.webdav_path) {
            return 'webdav-' + item.webdav_path;
        }
        if (item.object_storage_path) {
            return 'object-' + item.object_storage_path;
        }
        if (item.path) {
            return 'path-' + item.path;
        }
        if (item.url) {
            return 'url-' + item.url;
        }
        return 'file-' + (item.filename || item.title || Date.now());
    }

    function getFileIconLabel(item) {
        var mime = (item.mime || '').toLowerCase();
        var ext = (item.extension || '').toLowerCase();
        if (mime.indexOf('video/') === 0) return 'VIDEO';
        if (mime.indexOf('audio/') === 0) return 'AUDIO';
        if (mime.indexOf('application/pdf') === 0 || ext === 'pdf') return 'PDF';
        if (mime.indexOf('text/') === 0 || ext === 'txt') return 'TEXT';
        if (ext === 'zip' || ext === 'rar' || mime.indexOf('zip') >= 0) return 'ZIP';
        if (ext === 'doc' || ext === 'docx') return 'DOC';
        if (ext === 'xls' || ext === 'xlsx') return 'XLS';
        if (ext === 'ppt' || ext === 'pptx') return 'PPT';
        return 'FILE';
    }

    function buildMediaCard(item) {
        var key = makeItemKey(item);
        var title = item.title || item.filename || '未命名文件';
        var meta = {
            key: key,
            title: title,
            url: item.url || '',
            isImage: !!item.is_image
        };

        var $item = $('<div class="editor-media-item" tabindex="0"></div>');
        $item.attr({
            'data-item-key': key,
            'data-url': meta.url,
            'data-title': title,
            'data-is-image': item.is_image ? 1 : 0,
            'data-storage': item.storage || 'local'
        });
        $item.data('meta', meta);

        if (editorState.selection[key]) {
            $item.addClass('selected');
        }

        var $preview = $('<div class="media-preview"></div>');
        var previewUrl = item.thumbnail || (item.is_image && item.has_url ? item.url : '');
        if (previewUrl) {
            $('<img>').attr({
                src: previewUrl,
                alt: title
            }).appendTo($preview);
        } else {
            $('<div class="file-icon"></div>').text(getFileIconLabel(item)).appendTo($preview);
        }
        $item.append($preview);

        $('<div class="media-title"></div>').text(title).appendTo($item);

        var metaParts = [];
        if (item.storage_label) {
            metaParts.push(item.storage_label);
        }
        if (item.size) {
            metaParts.push(item.size);
        }
        $('<div class="media-meta"></div>').text(metaParts.join(' · ')).appendTo($item);

        return $item;
    }

    function showGridLoading(replaceContent) {
        if (replaceContent || !$grid.children().length) {
            $grid.html('<div class="loading editor-grid-loading">加载中...</div>');
        } else if (!$grid.find('.editor-grid-loading').length) {
            $grid.append('<div class="loading editor-grid-loading">加载中...</div>');
        }
    }

    function hideGridLoading() {
        $grid.find('.editor-grid-loading').remove();
    }

    function clearEndIndicator() {
        $grid.find('.media-end-indicator').remove();
    }

    function appendEndIndicator() {
        if (!$grid.find('.media-end-indicator').length) {
            $grid.append('<div class="media-end-indicator">已经到底啦</div>');
        }
    }

    function renderMediaPage(page, items, options) {
        var opts = $.extend({ replaceAll: false }, options || {});
        if (opts.replaceAll) {
            $grid.empty();
        }

        var $group = $('<div class="media-page-group" data-page="' + page + '"></div>');
        $('<div class="media-page-label"></div>').text('第 ' + page + ' 页').appendTo($group);

        var $itemsWrapper = $('<div class="media-page-items"></div>');
        if (!items.length) {
            var emptyText = page === 1 ? '没有媒体文件，请上传' : '本页没有文件';
            $('<div class="empty-state"></div>').text(emptyText).appendTo($itemsWrapper);
        } else {
            items.forEach(function(item) {
                $itemsWrapper.append(buildMediaCard(item));
            });
        }

        $group.append($itemsWrapper);

        var $existing = $grid.find('.media-page-group[data-page="' + page + '"]');
        if ($existing.length) {
            $existing.replaceWith($group);
        } else {
            $grid.append($group);
        }
    }

    function scrollToPage(page, instant) {
        var $target = $grid.find('.media-page-group[data-page="' + page + '"]');
        if (!$target.length) {
            return;
        }
        var top = $target[0].offsetTop;
        if (instant) {
            $grid.scrollTop(top);
        } else {
            $grid.stop().animate({ scrollTop: top }, 200);
        }
        editorState.currentPage = page;
        updatePagerButtons();
    }

    function updateCurrentPageByScroll() {
        var scrollTop = $grid.scrollTop();
        var activePage = editorState.currentPage;
        $grid.find('.media-page-group').each(function() {
            var page = parseInt($(this).data('page'), 10) || 1;
            if (scrollTop >= this.offsetTop - 20) {
                activePage = page;
            }
        });
        if (activePage !== editorState.currentPage) {
            editorState.currentPage = activePage;
            updatePagerButtons();
        }
    }

    function updatePagerButtons() {
        var current = editorState.currentPage || 1;
        var hasPrev = current > 1;
        var hasNext = editorState.hasMore || (editorState.maxLoadedPage > current);
        if (!hasNext && editorState.totalPages) {
            hasNext = current < editorState.totalPages;
        }
        $pagerPrev.prop('disabled', !hasPrev);
        $pagerNext.prop('disabled', !hasNext);
    }

    function requestPage(page, options) {
        var opts = $.extend({
            replaceAll: false,
            scrollIntoView: false
        }, options || {});

        if (editorState.loading) {
            return;
        }

        if (page < 1) {
            page = 1;
        }

        if (opts.replaceAll) {
            editorState.currentPage = 1;
            editorState.maxLoadedPage = 0;
            editorState.totalPages = 0;
            editorState.hasMore = true;
            editorState.loadedPages = {};
            clearEndIndicator();
            resetSelection(true);
        }

        editorState.loading = true;
        showGridLoading(opts.replaceAll);

        var params = $.extend({}, editorState.filters, {
            page: page,
            per_page: editorState.perPage
        });

        $.getJSON(listUrl, params).done(function(response) {
            if (!response || response.success === false) {
                showToast(response && response.message ? response.message : '媒体列表加载失败');
                return;
            }

            var items = Array.isArray(response.items) ? response.items : [];
            editorState.hasMore = !!response.has_more;
            editorState.totalPages = response.page_count || 0;
            editorState.loadedPages[page] = items;
            editorState.maxLoadedPage = Math.max(editorState.maxLoadedPage, page);
            editorState.currentPage = page;

            renderMediaPage(page, items, { replaceAll: opts.replaceAll });

            if (opts.scrollIntoView || opts.replaceAll) {
                scrollToPage(page, opts.replaceAll);
            }

            if (!editorState.hasMore) {
                appendEndIndicator();
            }
        }).fail(function() {
            showToast('媒体列表加载失败，请刷新页面');
            if (opts.replaceAll) {
                $grid.html('<div class="empty-state">媒体列表加载失败，请稍后重试</div>');
            }
        }).always(function() {
            editorState.loading = false;
            hideGridLoading();
            updatePagerButtons();
            updateCopyButton();
        });
    }

    function reloadMediaList() {
        requestPage(1, { replaceAll: true, scrollIntoView: true });
    }

    function copySelectedItems() {
        var keys = Object.keys(editorState.selection);
        if (!keys.length) {
            showToast('请选择文件');
            return;
        }

        var snippets = [];
        keys.forEach(function(key) {
            var meta = editorState.selection[key];
            if (!meta) {
                return;
            }
            var snippet = buildMarkdownSnippet(meta.title, meta.url, meta.isImage);
            if (snippet) {
                snippets.push(snippet);
            }
        });

        if (!snippets.length) {
            showToast('所选文件不可复制');
            return;
        }

        copyTextToClipboard(snippets.join('\n')).then(function() {
            showToast('Markdown 已复制到剪贴板');
            resetSelection();
        }).catch(function() {
            showToast('复制失败，请检查浏览器权限');
        });
    }

    function bindSelectionEvents() {
        $grid.on('click', '.editor-media-item', function() {
            var $item = $(this);
            var meta = $item.data('meta');
            if (!meta) {
                return;
            }
            var key = meta.key;
            if ($item.hasClass('selected')) {
                $item.removeClass('selected');
                delete editorState.selection[key];
            } else {
                $item.addClass('selected');
                editorState.selection[key] = meta;
            }
            updateCopyButton();
        });
    }

    function bindCopyEvent() {
        $copyBtn.on('click', function() {
            copySelectedItems();
        });
    }

    function bindPaginationEvents() {
        $pagerPrev.on('click', function() {
            if (editorState.currentPage <= 1) {
                return;
            }
            var target = editorState.currentPage - 1;
            if (editorState.loadedPages[target]) {
                scrollToPage(target, false);
            } else {
                requestPage(target, { replaceAll: false, scrollIntoView: true });
            }
        });

        $pagerNext.on('click', function() {
            var target = editorState.currentPage + 1;
            if (editorState.loadedPages[target]) {
                scrollToPage(target, false);
            } else if (editorState.hasMore) {
                requestPage(target, { replaceAll: false, scrollIntoView: true });
            }
        });
    }

    function bindInfiniteScroll() {
        $grid.on('scroll', function() {
            updateCurrentPageByScroll();
            if (!editorState.hasMore || editorState.loading) {
                return;
            }
            var el = this;
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 40) {
                requestPage(editorState.maxLoadedPage + 1, { replaceAll: false });
            }
        });
    }

    function initUploadModal() {
        var $modal = $('#editor-upload-modal');
        var $fileList = $('#editor-file-list');
        var $storageInputs = $('input[name="editor-upload-storage"]');
        var $storageHint = $('#editor-upload-storage-label');
        var currentStorage = $storageInputs.filter(':checked').val() || 'local';
        var uploadArea = document.getElementById('editor-upload-area');
        var uploader;

        function getStorageLabel(value) {
            var input = $storageInputs.filter('[value="' + value + '"]');
            var customLabel = input.data('label');
            if (customLabel) {
                return customLabel;
            }
            if (value === 'webdav') return 'WebDAV';
            if (value === 'object_storage') return '对象存储';
            return '本地存储';
        }

        function buildUploadUrl(storage) {
            var target = storage || currentStorage || 'local';
            return uploadBaseUrl + '&action=upload&storage=' + target;
        }

        function openModal() {
            $modal.addClass('active');
        }

        function closeModal() {
            $modal.removeClass('active');
            setTimeout(function() {
                $fileList.empty();
            }, 200);
        }

        $('#editor-upload-btn').on('click', function(e) {
            e.preventDefault();
            openModal();
        });

        $modal.on('click', function(e) {
            if ($(e.target).is('.ml-modal')) {
                closeModal();
            }
        });

        $modal.find('.ml-modal-close').on('click', function() {
            closeModal();
        });

        if ($storageHint.length) {
            $storageHint.text(getStorageLabel(currentStorage));
        }

        if (uploadArea) {
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            uploadArea.addEventListener('drop', function() {
                this.classList.remove('dragover');
            });
        }

        if (typeof plupload === 'undefined') {
            console.warn('plupload 未加载，上传功能不可用');
            return;
        }

        uploader = new plupload.Uploader({
            browse_button: 'editor-upload-file-btn',
            url: buildUploadUrl(currentStorage),
            runtimes: 'html5,flash,html4',
            flash_swf_url: adminStaticUrl + 'Moxie.swf',
            drop_element: 'editor-upload-area',
            multi_selection: true,
            filters: {
                max_file_size: maxFileSize || '2mb',
                mime_types: [{
                    title: '允许上传的文件',
                    extensions: allowedTypes
                }],
                prevent_duplicates: true
            },
            init: {
                FilesAdded: function(up, files) {
                    openModal();
                    $fileList.empty();
                    plupload.each(files, function(file) {
                        var li = document.createElement('li');
                        li.id = file.id;
                        li.innerHTML = ''
                            + '<div><span class="file-name">' + file.name + '</span>'
                            + '<span class="file-size"> (' + plupload.formatSize(file.size) + ')</span></div>'
                            + '<div class="progress-bar"><div class="progress-fill"></div></div>'
                            + '<div class="status">等待上传...</div>';
                        $fileList.append(li);
                    });
                    uploader.start();
                },
                UploadProgress: function(up, file) {
                    var li = document.getElementById(file.id);
                    if (!li) return;
                    var fill = li.querySelector('.progress-fill');
                    var status = li.querySelector('.status');
                    if (fill) fill.style.width = file.percent + '%';
                    if (status) status.textContent = '上传中... ' + file.percent + '%';
                },
                FileUploaded: function(up, file, result) {
                    var li = document.getElementById(file.id);
                    if (!li) return;
                    var status = li.querySelector('.status');
                    var fill = li.querySelector('.progress-fill');
                    if (200 === result.status) {
                        var parsedData;
                        var uploadSuccess = false;
                        var uploadMessage = '';
                        try {
                            parsedData = JSON.parse(result.response);
                            if (Array.isArray(parsedData)) {
                                uploadSuccess = true;
                            } else if (parsedData && typeof parsedData === 'object') {
                                if (typeof parsedData.success === 'boolean') {
                                    uploadSuccess = parsedData.success;
                                } else if (parsedData.count || parsedData.data) {
                                    uploadSuccess = true;
                                }
                                if (parsedData.message) {
                                    uploadMessage = parsedData.message;
                                }
                            }
                        } catch (e) {
                            uploadSuccess = false;
                            uploadMessage = '上传失败：响应解析错误';
                        }

                        if (uploadSuccess) {
                            li.classList.add('success');
                            if (status) status.textContent = uploadMessage || '上传成功';
                            if (fill) fill.style.background = '#22c55e';
                        } else {
                            li.classList.add('error');
                            if (status) status.textContent = uploadMessage || '上传失败';
                            if (fill) fill.style.background = '#ef4444';
                        }
                    } else {
                        li.classList.add('error');
                        if (status) status.textContent = '上传失败：HTTP ' + result.status;
                        if (fill) fill.style.background = '#ef4444';
                    }
                    uploader.removeFile(file);
                },
                UploadComplete: function() {
                    closeModal();
                    reloadMediaList();
                    refreshExpandedIfOpen();
                    showToast('上传完成');
                },
                Error: function(up, error) {
                    var li = document.createElement('li');
                    li.className = 'error';
                    var message = '上传出现错误';
                    if (error.code === plupload.FILE_SIZE_ERROR) {
                        message = '文件大小超过限制';
                    } else if (error.code === plupload.FILE_EXTENSION_ERROR) {
                        message = '文件扩展名不被支持';
                    } else if (error.code === plupload.FILE_DUPLICATE_ERROR) {
                        message = '文件已经在队列中';
                    } else if (error.code === plupload.HTTP_ERROR) {
                        message = '网络错误，请检查连接';
                    }
                    li.innerHTML = '<div><span class="file-name">' + (error.file ? error.file.name : '未知文件') + '</span></div>'
                        + '<div class="status">' + message + '</div>';
                    $fileList.append(li);
                    if (error.file) {
                        up.removeFile(error.file);
                    }
                }
            }
        });

        $storageInputs.not(':disabled').on('change', function() {
            if (this.checked) {
                currentStorage = this.value;
                $storageHint.text(getStorageLabel(currentStorage));
                if (uploader && typeof uploader.setOption === 'function') {
                    uploader.setOption('url', buildUploadUrl(currentStorage));
                } else if (uploader && uploader.settings) {
                    uploader.settings.url = buildUploadUrl(currentStorage);
                }
            }
        });

        uploader.init();
    }

    // ========================================
    // 展开版 Overlay 功能
    // ========================================
    var $overlay = $('#editor-expanded-overlay');
    var $expandedGrid = $('#expanded-media-grid');
    var $expandedSearchInput = $('#expanded-search-input');
    var $expandedTypeFilter = $('#expanded-type-filter');
    var $expandedStorageFilter = $('#expanded-storage-filter');
    var $expandedRefreshBtn = $('#expanded-refresh-btn');
    var $expandedCloseBtn = $('#expanded-close-btn');
    var $expandedUploadBtn = $('#expanded-upload-btn');
    var $expandedDeleteBtn = $('#expanded-delete-btn');
    var $expandedCopyBtn = $('#expanded-copy-btn');
    var $expandedPrevPage = $('#expanded-prev-page');
    var $expandedNextPage = $('#expanded-next-page');
    var $expandedPageIndicator = $('#expanded-page-indicator');

    var expandedState = {
        page: 1,
        perPage: 30,
        totalPages: 0,
        total: 0,
        hasMore: true,
        loading: false,
        filters: {
            keywords: '',
            type: 'all',
            storage: 'all'
        },
        selection: {},
        items: []
    };

    function openOverlay() {
        expandedState.page = 1;
        expandedState.selection = {};
        expandedState.items = [];
        $expandedSearchInput.val(expandedState.filters.keywords);
        $expandedTypeFilter.val(expandedState.filters.type);
        $expandedStorageFilter.val(expandedState.filters.storage);
        $overlay.addClass('active').css('display', 'flex');
        loadExpandedMedia(true);
        $('body').css('overflow', 'hidden');
    }

    function closeOverlay() {
        $overlay.addClass('closing');
        setTimeout(function() {
            $overlay.removeClass('active closing').css('display', 'none');
            $('body').css('overflow', '');
        }, 150);
    }

    function loadExpandedMedia(replaceAll) {
        if (expandedState.loading) return;
        expandedState.loading = true;

        if (replaceAll) {
            $expandedGrid.html('<div class="loading">加载中...</div>');
            expandedState.items = [];
        } else {
            $expandedGrid.find('.end-indicator').remove();
            $expandedGrid.append('<div class="loading">加载更多...</div>');
        }

        var params = $.extend({}, expandedState.filters, {
            page: expandedState.page,
            per_page: expandedState.perPage
        });

        $.getJSON(listUrl, params).done(function(response) {
            if (!response || response.success === false) {
                showToast(response && response.message ? response.message : '加载失败');
                return;
            }

            var items = Array.isArray(response.items) ? response.items : [];
            expandedState.hasMore = !!response.has_more;
            expandedState.totalPages = response.page_count || 0;
            expandedState.total = response.total || 0;

            if (replaceAll) {
                expandedState.items = items;
                $expandedGrid.empty();
            } else {
                expandedState.items = expandedState.items.concat(items);
            }

            if (items.length === 0 && replaceAll) {
                $expandedGrid.html('<div class="empty-state">没有找到媒体文件</div>');
            } else {
                items.forEach(function(item, index) {
                    var $card = buildExpandedCard(item);
                    $card.css('animation-delay', (index * 0.03) + 's');
                    $expandedGrid.append($card);
                });

                if (!expandedState.hasMore) {
                    $expandedGrid.append('<div class="end-indicator">已经到底啦</div>');
                }
            }

            updateExpandedPagination();
            updateExpandedToolbar();
        }).fail(function() {
            showToast('加载失败，请重试');
            if (replaceAll) {
                $expandedGrid.html('<div class="empty-state">加载失败，请重试</div>');
            }
        }).always(function() {
            expandedState.loading = false;
            $expandedGrid.find('.loading').remove();
        });
    }

    function buildExpandedCard(item) {
        var key = makeItemKey(item);
        var title = item.title || item.filename || '未命名文件';
        var meta = {
            key: key,
            cid: item.cid,
            title: title,
            url: item.url || '',
            isImage: !!item.is_image,
            storage: item.storage || 'local',
            webdavPath: item.webdav_path || '',
            objectStoragePath: item.object_storage_path || ''
        };

        var $card = $('<div class="expanded-media-item" tabindex="0"></div>');
        $card.attr({
            'data-item-key': key,
            'data-cid': item.cid || 0,
            'data-url': meta.url,
            'data-title': title,
            'data-is-image': item.is_image ? 1 : 0,
            'data-storage': item.storage || 'local',
            'data-webdav-path': item.webdav_path || '',
            'data-object-storage-path': item.object_storage_path || ''
        });
        $card.data('meta', meta);

        if (expandedState.selection[key]) {
            $card.addClass('selected');
        }

        var $preview = $('<div class="media-preview"></div>');
        $preview.append('<div class="media-checkbox"></div>');

        var previewUrl = item.thumbnail || (item.is_image && item.has_url ? item.url : '');
        if (previewUrl) {
            $('<img>').attr({ src: previewUrl, alt: title }).appendTo($preview);
        } else {
            $('<div class="file-icon"></div>').text(getFileIconLabel(item)).appendTo($preview);
        }
        $card.append($preview);

        $('<div class="media-title"></div>').text(title).attr('title', title).appendTo($card);

        var metaParts = [];
        if (item.storage_label) metaParts.push(item.storage_label);
        if (item.size) metaParts.push(item.size);
        $('<div class="media-meta"></div>').text(metaParts.join(' · ')).appendTo($card);

        return $card;
    }

    function updateExpandedPagination() {
        var current = expandedState.page;
        var total = expandedState.totalPages || 1;
        $expandedPageIndicator.text('第 ' + current + ' / ' + total + ' 页');
        $expandedPrevPage.prop('disabled', current <= 1);
        $expandedNextPage.prop('disabled', !expandedState.hasMore && current >= total);
    }

    function updateExpandedToolbar() {
        var count = Object.keys(expandedState.selection).length;
        var $selectionCount = $('#expanded-selection-count');

        $expandedDeleteBtn.prop('disabled', count === 0);
        $expandedCopyBtn.prop('disabled', count === 0);

        if (count > 0) {
            $expandedDeleteBtn.text('删除选中 (' + count + ')');
            $expandedCopyBtn.text('复制 (' + count + ')');
            $selectionCount.text('已选中 ' + count + ' 个文件');
        } else {
            $expandedDeleteBtn.text('删除选中');
            $expandedCopyBtn.text('复制');
            $selectionCount.text('');
        }
    }

    function bindExpandedSelectionEvents() {
        $expandedGrid.on('click', '.expanded-media-item', function(e) {
            var $card = $(this);
            var meta = $card.data('meta');
            if (!meta) return;

            var key = meta.key;
            if ($card.hasClass('selected')) {
                $card.removeClass('selected');
                delete expandedState.selection[key];
            } else {
                $card.addClass('selected');
                expandedState.selection[key] = meta;
            }
            updateExpandedToolbar();
        });
    }

    function expandedCopySelected() {
        var keys = Object.keys(expandedState.selection);
        if (!keys.length) {
            showToast('请选择文件');
            return;
        }

        var snippets = [];
        keys.forEach(function(key) {
            var meta = expandedState.selection[key];
            if (!meta || !meta.url) return;
            var snippet = buildMarkdownSnippet(meta.title, meta.url, meta.isImage);
            if (snippet) snippets.push(snippet);
        });

        if (!snippets.length) {
            showToast('所选文件不可复制');
            return;
        }

        copyTextToClipboard(snippets.join('\n')).then(function() {
            showToast('Markdown 已复制到剪贴板');
            expandedState.selection = {};
            $expandedGrid.find('.expanded-media-item.selected').removeClass('selected');
            updateExpandedToolbar();
        }).catch(function() {
            showToast('复制失败，请检查浏览器权限');
        });
    }

    function showConfirmDialog(title, message, onConfirm) {
        var $dialog = $('<div class="ml-confirm-dialog"></div>');
        var $box = $('<div class="confirm-box"></div>');
        $box.append('<div class="confirm-title">' + title + '</div>');
        $box.append('<div class="confirm-message">' + message + '</div>');

        var $actions = $('<div class="confirm-actions"></div>');
        var $cancelBtn = $('<button class="btn btn-cancel">取消</button>');
        var $confirmBtn = $('<button class="btn btn-confirm-danger">确认删除</button>');

        $cancelBtn.on('click', function() {
            $dialog.remove();
        });

        $confirmBtn.on('click', function() {
            $dialog.remove();
            if (typeof onConfirm === 'function') onConfirm();
        });

        $actions.append($cancelBtn).append($confirmBtn);
        $box.append($actions);
        $dialog.append($box);
        $('body').append($dialog);

        $dialog.on('click', function(e) {
            if ($(e.target).is('.ml-confirm-dialog')) {
                $dialog.remove();
            }
        });
    }

    function expandedDeleteSelected() {
        var keys = Object.keys(expandedState.selection);
        if (!keys.length) {
            showToast('请选择要删除的文件');
            return;
        }

        var cids = [];
        var webdavPaths = [];
        var objectStoragePaths = [];

        keys.forEach(function(key) {
            var meta = expandedState.selection[key];
            if (!meta) return;
            if (meta.cid && meta.cid !== 0) {
                cids.push(meta.cid);
            }
            if (meta.webdavPath) {
                webdavPaths.push(meta.webdavPath);
            }
            if (meta.objectStoragePath) {
                objectStoragePaths.push(meta.objectStoragePath);
            }
        });

        if (cids.length === 0 && webdavPaths.length === 0) {
            showToast('没有可删除的文件');
            return;
        }

        showConfirmDialog(
            '确认删除',
            '确定要删除选中的 ' + keys.length + ' 个文件吗？此操作不可恢复！',
            function() {
                performDelete(cids, webdavPaths, objectStoragePaths);
            }
        );
    }

    function performDelete(cids, webdavPaths, objectStoragePaths) {
        $expandedDeleteBtn.prop('disabled', true).text('删除中...');

        var params = { action: 'delete' };
        if (cids.length > 0) {
            params['cids[]'] = cids;
        }
        if (webdavPaths.length > 0) {
            params['webdav_paths[]'] = webdavPaths;
        }

        $.ajax({
            url: uploadBaseUrl,
            type: 'POST',
            data: $.param(params, true),
            dataType: 'json'
        }).done(function(response) {
            if (response && response.success) {
                showToast(response.message || '删除成功');
                expandedState.selection = {};
                expandedState.page = 1;
                loadExpandedMedia(true);
                reloadMediaList();
            } else {
                showToast('删除失败: ' + (response && response.message ? response.message : '未知错误'));
            }
        }).fail(function() {
            showToast('删除失败，请检查网络连接');
        }).always(function() {
            updateExpandedToolbar();
        });
    }

    function bindExpandedFilterEvents() {
        var searchTimeout;
        $expandedSearchInput.on('input', function() {
            clearTimeout(searchTimeout);
            var value = $(this).val();
            searchTimeout = setTimeout(function() {
                expandedState.filters.keywords = value;
                expandedState.page = 1;
                loadExpandedMedia(true);
            }, 300);
        });

        $expandedSearchInput.on('keypress', function(e) {
            if (e.which === 13) {
                clearTimeout(searchTimeout);
                expandedState.filters.keywords = $(this).val();
                expandedState.page = 1;
                loadExpandedMedia(true);
            }
        });

        $expandedTypeFilter.on('change', function() {
            expandedState.filters.type = $(this).val();
            expandedState.page = 1;
            loadExpandedMedia(true);
        });

        $expandedStorageFilter.on('change', function() {
            expandedState.filters.storage = $(this).val();
            expandedState.page = 1;
            loadExpandedMedia(true);
        });

        $expandedRefreshBtn.on('click', function() {
            expandedState.page = 1;
            loadExpandedMedia(true);
        });
    }

    function bindExpandedPaginationEvents() {
        $expandedPrevPage.on('click', function() {
            if (expandedState.page > 1) {
                expandedState.page--;
                loadExpandedMedia(true);
            }
        });

        $expandedNextPage.on('click', function() {
            if (expandedState.hasMore || expandedState.page < expandedState.totalPages) {
                expandedState.page++;
                loadExpandedMedia(true);
            }
        });
    }

    function bindExpandedInfiniteScroll() {
        $expandedGrid.on('scroll', function() {
            if (!expandedState.hasMore || expandedState.loading) return;
            var el = this;
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 100) {
                expandedState.page++;
                loadExpandedMedia(false);
            }
        });
    }

    function bindExpandedToolbarEvents() {
        $expandedCopyBtn.on('click', function() {
            expandedCopySelected();
        });

        $expandedDeleteBtn.on('click', function() {
            expandedDeleteSelected();
        });

        $expandedUploadBtn.on('click', function() {
            $('#editor-upload-modal').addClass('active');
        });
    }

    function refreshExpandedIfOpen() {
        if ($overlay.hasClass('active')) {
            expandedState.page = 1;
            loadExpandedMedia(true);
        }
    }

    function bindOverlayEvents() {
        $('#editor-expand-btn').on('click', function() {
            openOverlay();
        });

        $expandedCloseBtn.on('click', function() {
            closeOverlay();
        });

        $overlay.on('click', function(e) {
            if ($(e.target).is('.media-library-overlay')) {
                closeOverlay();
            }
        });

        $(document).on('keydown', function(e) {
            if (!$overlay.hasClass('active')) return;

            if (e.key === 'Escape') {
                closeOverlay();
            }

            // Ctrl/Cmd + A 全选
            if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                e.preventDefault();
                selectAllExpanded();
            }
        });
    }

    function selectAllExpanded() {
        var allSelected = Object.keys(expandedState.selection).length === expandedState.items.length && expandedState.items.length > 0;

        if (allSelected) {
            // 取消全选
            expandedState.selection = {};
            $expandedGrid.find('.expanded-media-item').removeClass('selected');
        } else {
            // 全选
            expandedState.items.forEach(function(item) {
                var key = makeItemKey(item);
                var title = item.title || item.filename || '未命名文件';
                expandedState.selection[key] = {
                    key: key,
                    cid: item.cid,
                    title: title,
                    url: item.url || '',
                    isImage: !!item.is_image,
                    storage: item.storage || 'local',
                    webdavPath: item.webdav_path || '',
                    objectStoragePath: item.object_storage_path || ''
                };
            });
            $expandedGrid.find('.expanded-media-item').addClass('selected');
        }
        updateExpandedToolbar();
    }

    function initExpandedOverlay() {
        bindOverlayEvents();
        bindExpandedSelectionEvents();
        bindExpandedFilterEvents();
        bindExpandedPaginationEvents();
        bindExpandedInfiniteScroll();
        bindExpandedToolbarEvents();
    }

    $(function() {
        reloadMediaList();
        bindSelectionEvents();
        bindCopyEvent();
        bindPaginationEvents();
        bindInfiniteScroll();
        initUploadModal();
        initExpandedOverlay();
    });
});
</script>
