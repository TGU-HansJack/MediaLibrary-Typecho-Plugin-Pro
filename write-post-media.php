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
   ======================================== */

/* CSS 变量定义 */
#media-library-container {
    --ml-bg: #ffffff;
    --ml-bg-secondary: #f6f8fa;
    --ml-border: #d0d7de;
    --ml-border-muted: #d8dee4;
    --ml-text: #24292f;
    --ml-text-secondary: #656d76;
    --ml-text-muted: #8b949e;
    --ml-primary: #0969da;
    --ml-primary-hover: #0860ca;
    --ml-primary-bg: #ddf4ff;
    --ml-success: #1a7f37;
    --ml-danger: #d1242f;
    --ml-shadow: 0 1px 0 rgba(31, 35, 40, 0.04);
    --ml-shadow-md: 0 3px 6px rgba(140, 149, 159, 0.15);
    --ml-radius: 6px;
    --ml-radius-md: 8px;
    --ml-transition: 0.2s cubic-bezier(0.3, 0, 0.5, 1);
}

/* 深色模式变量 */
@media (prefers-color-scheme: dark) {
    #media-library-container {
        --ml-bg: #0d1117;
        --ml-bg-secondary: #161b22;
        --ml-border: #30363d;
        --ml-border-muted: #21262d;
        --ml-text: #c9d1d9;
        --ml-text-secondary: #8b949e;
        --ml-text-muted: #6e7681;
        --ml-primary: #58a6ff;
        --ml-primary-hover: #79c0ff;
        --ml-primary-bg: #1c2d41;
        --ml-success: #3fb950;
        --ml-danger: #f85149;
        --ml-shadow: 0 1px 0 rgba(1, 4, 9, 0.1);
        --ml-shadow-md: 0 3px 6px rgba(1, 4, 9, 0.3);
    }
}

/* 主容器 */
#media-library-container .media-library-editor {
    background: var(--ml-bg);
    border: 1px solid var(--ml-border);
    border-radius: var(--ml-radius-md);
    padding: 12px;
    margin-top: 12px;
    box-shadow: var(--ml-shadow);
}

/* 工具栏 */
#media-library-container .media-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--ml-border-muted);
}

#media-library-container .media-toolbar .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 5px 12px;
    font-size: 12px;
    font-weight: 500;
    line-height: 20px;
    color: var(--ml-text);
    background: var(--ml-bg);
    border: 1px solid var(--ml-border);
    border-radius: var(--ml-radius);
    cursor: pointer;
    transition: background var(--ml-transition), border-color var(--ml-transition);
    white-space: nowrap;
}

#media-library-container .media-toolbar .btn:hover {
    background: var(--ml-bg-secondary);
    border-color: var(--ml-border);
}

#media-library-container .media-toolbar .btn-primary {
    color: #ffffff;
    background: var(--ml-success);
    border-color: rgba(31, 35, 40, 0.15);
}

#media-library-container .media-toolbar .btn-primary:hover {
    background: #1a7f37;
}

@media (prefers-color-scheme: dark) {
    #media-library-container .media-toolbar .btn-primary {
        background: #238636;
        border-color: rgba(240, 246, 252, 0.1);
    }
    #media-library-container .media-toolbar .btn-primary:hover {
        background: #2ea043;
    }
}

/* 媒体网格容器 - 带滚动 */
#media-library-container .media-grid {
    max-height: 240px;
    min-height: 80px;
    overflow-y: auto;
    overflow-x: hidden;
    border: 1px solid var(--ml-border-muted);
    border-radius: var(--ml-radius);
    padding: 8px;
    display: block;
    background: var(--ml-bg-secondary);
    scrollbar-width: thin;
    scrollbar-color: var(--ml-border) transparent;
    position: relative;
}

#media-library-container .media-page-group {
    margin-bottom: 12px;
}

#media-library-container .media-page-group:last-child {
    margin-bottom: 0;
}

#media-library-container .media-page-label {
    font-size: 11px;
    color: var(--ml-text-muted);
    margin: 4px 0 6px;
}

#media-library-container .media-page-items {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
    gap: 8px;
    background: var(--ml-bg-secondary);
}

#media-library-container .media-end-indicator {
    text-align: center;
    color: var(--ml-text-muted);
    font-size: 11px;
    padding: 6px 0;
}

#media-library-container .media-page-items .loading,
#media-library-container .media-page-items .empty-state {
    grid-column: 1 / -1;
}

#media-library-container .media-grid::-webkit-scrollbar {
    width: 6px;
}

#media-library-container .media-grid::-webkit-scrollbar-track {
    background: transparent;
}

#media-library-container .media-grid::-webkit-scrollbar-thumb {
    background: var(--ml-border);
    border-radius: 3px;
}

#media-library-container .media-grid::-webkit-scrollbar-thumb:hover {
    background: var(--ml-text-muted);
}

#media-library-container .media-grid .loading,
#media-library-container .media-grid .empty-state {
    grid-column: 1 / -1;
    text-align: center;
    color: var(--ml-text-muted);
    padding: 20px 0;
    font-size: 13px;
}

/* 媒体项 */
#media-library-container .editor-media-item {
    background: var(--ml-bg);
    border: 1px solid var(--ml-border-muted);
    border-radius: var(--ml-radius);
    padding: 6px;
    cursor: pointer;
    transition: border-color var(--ml-transition), box-shadow var(--ml-transition), transform var(--ml-transition);
}

#media-library-container .editor-media-item:hover {
    border-color: var(--ml-border);
    box-shadow: var(--ml-shadow-md);
    transform: translateY(-1px);
}

#media-library-container .editor-media-item.selected {
    border-color: var(--ml-primary);
    box-shadow: 0 0 0 2px var(--ml-primary-bg);
}

#media-library-container .editor-media-item .media-preview {
    width: 100%;
    height: 64px;
    background: var(--ml-bg-secondary);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

#media-library-container .editor-media-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

#media-library-container .editor-media-item .file-icon {
    font-size: 20px;
    color: var(--ml-text-secondary);
    font-weight: 500;
}

#media-library-container .editor-media-item .media-title {
    margin-top: 4px;
    font-size: 11px;
    color: var(--ml-text-secondary);
    text-align: center;
    max-height: 28px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-height: 1.3;
}

#media-library-container .editor-media-item .media-meta {
    margin-top: 2px;
    font-size: 10px;
    color: var(--ml-text-muted);
    text-align: center;
}

/* 上传模态框 */
.ml-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(27, 31, 36, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 16px;
    opacity: 0;
    pointer-events: none;
    visibility: hidden;
    transition: opacity 0.2s ease;
}

.ml-modal.active {
    opacity: 1;
    pointer-events: auto;
    visibility: visible;
}

@media (prefers-color-scheme: dark) {
    .ml-modal {
        background: rgba(1, 4, 9, 0.8);
    }
}

.ml-modal-dialog {
    background: var(--ml-bg, #ffffff);
    width: 400px;
    max-width: 100%;
    border-radius: var(--ml-radius-md, 8px);
    border: 1px solid var(--ml-border, #d0d7de);
    box-shadow: 0 8px 24px rgba(140, 149, 159, 0.2);
    padding: 16px;
    animation: mlModalScale 0.2s cubic-bezier(0.33, 1, 0.68, 1);
}

@media (prefers-color-scheme: dark) {
    .ml-modal-dialog {
        background: #161b22;
        border-color: #30363d;
        box-shadow: 0 8px 24px rgba(1, 4, 9, 0.4);
    }
}

.ml-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--ml-border-muted, #d8dee4);
}

@media (prefers-color-scheme: dark) {
    .ml-modal-header {
        border-bottom-color: #21262d;
    }
}

.ml-modal-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--ml-text, #24292f);
}

@media (prefers-color-scheme: dark) {
    .ml-modal-header h3 {
        color: #c9d1d9;
    }
}

.ml-modal-close {
    cursor: pointer;
    font-size: 18px;
    color: var(--ml-text-muted, #8b949e);
    padding: 4px;
    border-radius: 4px;
    transition: background var(--ml-transition, 0.2s), color var(--ml-transition, 0.2s);
}

.ml-modal-close:hover {
    color: var(--ml-text, #24292f);
    background: var(--ml-bg-secondary, #f6f8fa);
}

@media (prefers-color-scheme: dark) {
    .ml-modal-close:hover {
        color: #c9d1d9;
        background: #21262d;
    }
}

.ml-modal-body {
    font-size: 13px;
    color: var(--ml-text-secondary, #656d76);
}

@media (prefers-color-scheme: dark) {
    .ml-modal-body {
        color: #8b949e;
    }
}

/* 存储选择控件 */
.ml-upload-storage-control {
    margin-bottom: 12px;
}

.ml-storage-options {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.ml-storage-pill {
    border: 1px solid var(--ml-border, #d0d7de);
    border-radius: var(--ml-radius, 6px);
    padding: 8px 10px;
    font-size: 12px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    min-width: 100px;
    background: var(--ml-bg, #ffffff);
    transition: border-color var(--ml-transition, 0.2s), background var(--ml-transition, 0.2s);
}

@media (prefers-color-scheme: dark) {
    .ml-storage-pill {
        background: #0d1117;
        border-color: #30363d;
    }
}

.ml-storage-pill:hover:not(.disabled) {
    border-color: var(--ml-primary, #0969da);
}

.ml-storage-pill input {
    display: none;
}

.ml-storage-pill .storage-pill-name {
    font-weight: 600;
    color: var(--ml-text, #24292f);
    font-size: 12px;
}

@media (prefers-color-scheme: dark) {
    .ml-storage-pill .storage-pill-name {
        color: #c9d1d9;
    }
}

.ml-storage-pill .storage-pill-desc {
    color: var(--ml-text-muted, #8b949e);
    font-size: 11px;
    margin-top: 2px;
}

.ml-storage-pill input:checked + .storage-pill-text .storage-pill-name {
    color: var(--ml-primary, #0969da);
}

@media (prefers-color-scheme: dark) {
    .ml-storage-pill input:checked + .storage-pill-text .storage-pill-name {
        color: #58a6ff;
    }
}

.ml-storage-pill.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.ml-upload-storage-hint {
    margin-top: 6px;
    font-size: 12px;
    color: var(--ml-text-secondary, #656d76);
}

@media (prefers-color-scheme: dark) {
    .ml-upload-storage-hint {
        color: #8b949e;
    }
}

/* 上传区域 */
.ml-upload-area {
    border: 2px dashed var(--ml-border, #d0d7de);
    border-radius: var(--ml-radius, 6px);
    padding: 20px;
    text-align: center;
    background: var(--ml-bg-secondary, #f6f8fa);
    transition: border-color var(--ml-transition, 0.2s), background var(--ml-transition, 0.2s);
}

@media (prefers-color-scheme: dark) {
    .ml-upload-area {
        background: #0d1117;
        border-color: #30363d;
    }
}

.ml-upload-area:hover {
    border-color: var(--ml-primary, #0969da);
}

.ml-upload-area.dragover {
    border-color: var(--ml-primary, #0969da);
    background: var(--ml-primary-bg, #ddf4ff);
}

@media (prefers-color-scheme: dark) {
    .ml-upload-area.dragover {
        background: #1c2d41;
    }
}

.ml-upload-area .upload-hint {
    color: var(--ml-text-muted, #8b949e);
    margin-bottom: 8px;
    font-size: 13px;
}

/* 文件列表 */
#editor-file-list {
    list-style: none;
    margin: 12px 0 0;
    padding: 0;
    max-height: 180px;
    overflow-y: auto;
}

#editor-file-list li {
    border: 1px solid var(--ml-border, #d0d7de);
    border-left: 3px solid var(--ml-border, #d0d7de);
    border-radius: var(--ml-radius, 6px);
    padding: 8px 10px;
    margin-bottom: 8px;
    font-size: 12px;
    color: var(--ml-text-secondary, #656d76);
    background: var(--ml-bg, #ffffff);
}

@media (prefers-color-scheme: dark) {
    #editor-file-list li {
        background: #0d1117;
        border-color: #30363d;
        color: #8b949e;
    }
}

#editor-file-list li.success {
    border-left-color: var(--ml-success, #1a7f37);
}

@media (prefers-color-scheme: dark) {
    #editor-file-list li.success {
        border-left-color: #3fb950;
    }
}

#editor-file-list li.error {
    border-left-color: var(--ml-danger, #d1242f);
    color: var(--ml-danger, #d1242f);
}

@media (prefers-color-scheme: dark) {
    #editor-file-list li.error {
        border-left-color: #f85149;
        color: #f85149;
    }
}

#editor-file-list .file-name {
    font-weight: 600;
    display: inline-block;
    max-width: 60%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--ml-text, #24292f);
}

@media (prefers-color-scheme: dark) {
    #editor-file-list .file-name {
        color: #c9d1d9;
    }
}

#editor-file-list .file-size {
    color: var(--ml-text-muted, #8b949e);
    margin-left: 6px;
}

#editor-file-list .progress-bar {
    width: 100%;
    height: 4px;
    background: var(--ml-border-muted, #d8dee4);
    border-radius: 2px;
    margin-top: 6px;
    overflow: hidden;
}

@media (prefers-color-scheme: dark) {
    #editor-file-list .progress-bar {
        background: #21262d;
    }
}

#editor-file-list .progress-fill {
    width: 0%;
    height: 100%;
    border-radius: 2px;
    background: linear-gradient(90deg, var(--ml-primary, #0969da), #54aeff);
    transition: width 0.2s ease;
}

@media (prefers-color-scheme: dark) {
    #editor-file-list .progress-fill {
        background: linear-gradient(90deg, #1f6feb, #58a6ff);
    }
}

#editor-file-list .status {
    margin-top: 4px;
    font-size: 11px;
}

.media-toolbar .toolbar-actions {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 6px;
}

.media-toolbar .btn-icon {
    width: 28px;
    height: 28px;
    padding: 0;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    background: var(--ml-bg);
}

.media-toolbar .btn-icon svg {
    width: 14px;
    height: 14px;
}

.media-pagination {
    margin-top: 8px;
    display: flex;
    justify-content: flex-end;
    gap: 6px;
}

.media-pagination .pager-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1px solid var(--ml-border);
    background: var(--ml-bg);
    color: var(--ml-text);
    cursor: pointer;
    transition: background var(--ml-transition);
}

.media-pagination .pager-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.media-pagination .pager-btn:not(:disabled):hover {
    background: var(--ml-bg-secondary);
}

.media-library-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(13, 17, 23, 0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 20px;
}

.media-library-overlay.active {
    display: flex;
}

.media-overlay-panel {
    width: 90vw;
    height: 90vh;
    background: #fff;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 12px 40px rgba(15, 23, 42, 0.35);
    animation: mlModalScale 0.25s ease;
    overflow: hidden;
}

@media (prefers-color-scheme: dark) {
    .media-overlay-panel {
        background: #161b22;
        color: #c9d1d9;
    }
}

.overlay-header {
    padding: 18px 20px 10px;
    border-bottom: 1px solid var(--ml-border-muted);
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
}

.overlay-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--ml-text);
}

.overlay-controls {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.overlay-controls input[type="text"],
.overlay-controls select {
    border: 1px solid var(--ml-border);
    border-radius: 6px;
    padding: 5px 8px;
    font-size: 13px;
    background: var(--ml-bg);
    color: var(--ml-text);
}

.overlay-toolbar {
    padding: 12px 20px;
    border-bottom: 1px solid var(--ml-border-muted);
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.overlay-toolbar .btn {
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid var(--ml-border);
    background: var(--ml-bg);
    cursor: pointer;
}

.overlay-toolbar .btn-primary {
    background: var(--ml-primary);
    color: #fff;
    border-color: var(--ml-primary);
}

.overlay-toolbar .btn-danger {
    background: var(--ml-danger);
    border-color: var(--ml-danger);
    color: #fff;
}

.media-overlay-content {
    flex: 1;
    overflow: hidden;
    padding: 0 20px;
    display: flex;
    flex-direction: column;
}

#expanded-media-grid {
    flex: 1;
    overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px;
    padding: 16px 0;
}

.expanded-media-item {
    border: 1px solid var(--ml-border);
    border-radius: 8px;
    background: var(--ml-bg);
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    cursor: pointer;
    transition: border-color var(--ml-transition), box-shadow var(--ml-transition);
}

.expanded-media-item.selected {
    border-color: var(--ml-primary);
    box-shadow: 0 0 0 2px var(--ml-primary-bg);
}

.expanded-media-item .media-preview {
    height: 120px;
}

.expanded-media-item .media-meta {
    font-size: 12px;
    color: var(--ml-text-secondary);
}

.overlay-pagination {
    padding: 12px 20px 20px;
    border-top: 1px solid var(--ml-border-muted);
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
}

.overlay-pagination .page-indicator {
    font-size: 13px;
    color: var(--ml-text-secondary);
}

/* 展开版网格样式 */
#expanded-media-grid {
    flex: 1;
    overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
    padding: 16px 0;
    align-content: start;
}

#expanded-media-grid .expanded-media-item {
    background: var(--ml-bg);
    border: 1px solid var(--ml-border-muted);
    border-radius: var(--ml-radius-md);
    padding: 10px;
    cursor: pointer;
    transition: all var(--ml-transition);
    animation: mlFadeInUp 0.25s ease backwards;
}

#expanded-media-grid .expanded-media-item:hover {
    border-color: var(--ml-border);
    box-shadow: var(--ml-shadow-md);
    transform: translateY(-2px);
}

#expanded-media-grid .expanded-media-item.selected {
    border-color: var(--ml-primary);
    box-shadow: 0 0 0 3px var(--ml-primary-bg);
}

#expanded-media-grid .expanded-media-item .media-preview {
    width: 100%;
    height: 100px;
    background: var(--ml-bg-secondary);
    border-radius: 6px;
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
}

#expanded-media-grid .expanded-media-item .file-icon {
    font-size: 24px;
    color: var(--ml-text-secondary);
    font-weight: 600;
}

#expanded-media-grid .expanded-media-item .media-title {
    margin-top: 8px;
    font-size: 12px;
    font-weight: 500;
    color: var(--ml-text);
    text-align: center;
    max-height: 32px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-height: 1.35;
}

#expanded-media-grid .expanded-media-item .media-meta {
    margin-top: 4px;
    font-size: 11px;
    color: var(--ml-text-muted);
    text-align: center;
}

#expanded-media-grid .expanded-media-item .media-checkbox {
    position: absolute;
    top: 6px;
    left: 6px;
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid rgba(255, 255, 255, 0.8);
    background: rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity var(--ml-transition);
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
    font-size: 11px;
    font-weight: bold;
}

/* 展开版加载和空状态 */
#expanded-media-grid .loading,
#expanded-media-grid .empty-state {
    grid-column: 1 / -1;
    text-align: center;
    color: var(--ml-text-muted);
    padding: 60px 20px;
    font-size: 14px;
}

#expanded-media-grid .end-indicator {
    grid-column: 1 / -1;
    text-align: center;
    color: var(--ml-text-muted);
    font-size: 12px;
    padding: 16px 0;
}

/* 展开版 Header 样式优化 */
.overlay-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--ml-border-muted);
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    background: var(--ml-bg);
}

.overlay-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--ml-text);
    display: flex;
    align-items: center;
    gap: 8px;
}

.overlay-controls {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.overlay-controls input[type="text"] {
    width: 180px;
    border: 1px solid var(--ml-border);
    border-radius: var(--ml-radius);
    padding: 6px 10px;
    font-size: 13px;
    background: var(--ml-bg);
    color: var(--ml-text);
    transition: border-color var(--ml-transition);
}

.overlay-controls input[type="text"]:focus {
    outline: none;
    border-color: var(--ml-primary);
    box-shadow: 0 0 0 2px var(--ml-primary-bg);
}

.overlay-controls select {
    border: 1px solid var(--ml-border);
    border-radius: var(--ml-radius);
    padding: 6px 10px;
    font-size: 13px;
    background: var(--ml-bg);
    color: var(--ml-text);
    cursor: pointer;
}

/* 展开版工具栏优化 */
.overlay-toolbar {
    padding: 12px 20px;
    border-bottom: 1px solid var(--ml-border-muted);
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    background: var(--ml-bg-secondary);
}

.overlay-toolbar .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.4;
    border-radius: var(--ml-radius);
    border: 1px solid var(--ml-border);
    background: var(--ml-bg);
    color: var(--ml-text);
    cursor: pointer;
    transition: all var(--ml-transition);
}

.overlay-toolbar .btn:hover:not(:disabled) {
    background: var(--ml-bg-secondary);
}

.overlay-toolbar .btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.overlay-toolbar .btn-primary {
    background: var(--ml-success);
    border-color: transparent;
    color: #fff;
}

.overlay-toolbar .btn-primary:hover:not(:disabled) {
    background: #1a7f37;
}

@media (prefers-color-scheme: dark) {
    .overlay-toolbar .btn-primary {
        background: #238636;
    }
    .overlay-toolbar .btn-primary:hover:not(:disabled) {
        background: #2ea043;
    }
}

.overlay-toolbar .btn-danger {
    background: var(--ml-danger);
    border-color: transparent;
    color: #fff;
}

.overlay-toolbar .btn-danger:hover:not(:disabled) {
    background: #b91c1c;
}

.overlay-toolbar .selection-count {
    font-size: 13px;
    color: var(--ml-text-secondary);
    margin-left: auto;
}

/* 展开版分页优化 */
.overlay-pagination {
    padding: 14px 20px;
    border-top: 1px solid var(--ml-border-muted);
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    background: var(--ml-bg);
}

.overlay-pagination .pager-btn {
    width: 32px;
    height: 32px;
    border-radius: var(--ml-radius);
    border: 1px solid var(--ml-border);
    background: var(--ml-bg);
    color: var(--ml-text);
    font-size: 14px;
    cursor: pointer;
    transition: all var(--ml-transition);
    display: flex;
    align-items: center;
    justify-content: center;
}

.overlay-pagination .pager-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.overlay-pagination .pager-btn:not(:disabled):hover {
    background: var(--ml-bg-secondary);
    border-color: var(--ml-primary);
}

/* 关闭按钮样式 */
#expanded-close-btn {
    width: 32px;
    height: 32px;
    padding: 0;
    font-size: 20px;
    line-height: 1;
    border-radius: var(--ml-radius);
}

/* 动画 */
@keyframes mlFadeInUp {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes mlOverlayFadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes mlPanelSlideIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.media-library-overlay {
    animation: mlOverlayFadeIn 0.2s ease;
}

.media-library-overlay.closing {
    animation: mlOverlayFadeIn 0.15s ease reverse;
}

.media-overlay-panel {
    animation: mlPanelSlideIn 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.media-library-overlay.closing .media-overlay-panel {
    animation: mlPanelSlideIn 0.15s ease reverse;
}

/* 删除确认对话框 */
.ml-confirm-dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(13, 17, 23, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10001;
    animation: mlOverlayFadeIn 0.15s ease;
}

.ml-confirm-dialog .confirm-box {
    background: var(--ml-bg, #fff);
    border-radius: var(--ml-radius-md, 8px);
    padding: 24px;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
    animation: mlPanelSlideIn 0.2s ease;
}

@media (prefers-color-scheme: dark) {
    .ml-confirm-dialog .confirm-box {
        background: #161b22;
    }
}

.ml-confirm-dialog .confirm-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--ml-text, #24292f);
    margin-bottom: 12px;
}

@media (prefers-color-scheme: dark) {
    .ml-confirm-dialog .confirm-title {
        color: #c9d1d9;
    }
}

.ml-confirm-dialog .confirm-message {
    font-size: 14px;
    color: var(--ml-text-secondary, #656d76);
    margin-bottom: 20px;
    line-height: 1.5;
}

@media (prefers-color-scheme: dark) {
    .ml-confirm-dialog .confirm-message {
        color: #8b949e;
    }
}

.ml-confirm-dialog .confirm-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.ml-confirm-dialog .confirm-actions .btn {
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 500;
    border-radius: var(--ml-radius, 6px);
    cursor: pointer;
    transition: all 0.15s ease;
}

.ml-confirm-dialog .btn-cancel {
    background: var(--ml-bg-secondary, #f6f8fa);
    border: 1px solid var(--ml-border, #d0d7de);
    color: var(--ml-text, #24292f);
}

@media (prefers-color-scheme: dark) {
    .ml-confirm-dialog .btn-cancel {
        background: #21262d;
        border-color: #30363d;
        color: #c9d1d9;
    }
}

.ml-confirm-dialog .btn-cancel:hover {
    background: var(--ml-border-muted, #d8dee4);
}

@media (prefers-color-scheme: dark) {
    .ml-confirm-dialog .btn-cancel:hover {
        background: #30363d;
    }
}

.ml-confirm-dialog .btn-confirm-danger {
    background: var(--ml-danger, #d1242f);
    border: 1px solid transparent;
    color: #fff;
}

.ml-confirm-dialog .btn-confirm-danger:hover {
    background: #b91c1c;
}

/* 加载动画 */
@keyframes mlSpinnerRotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading::before {
    content: '';
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid var(--ml-border, #d0d7de);
    border-top-color: var(--ml-primary, #0969da);
    border-radius: 50%;
    animation: mlSpinnerRotate 0.8s linear infinite;
    margin-right: 8px;
    vertical-align: middle;
}

/* 响应式设计 */
@media (max-width: 768px) {
    .media-library-overlay {
        padding: 10px;
    }

    .media-overlay-panel {
        width: 100%;
        height: 100%;
        border-radius: 0;
    }

    .overlay-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .overlay-controls {
        width: 100%;
        justify-content: space-between;
    }

    .overlay-controls input[type="text"] {
        width: 100%;
        flex: 1;
    }

    .overlay-toolbar {
        flex-wrap: wrap;
    }

    .overlay-toolbar .selection-count {
        width: 100%;
        margin-left: 0;
        margin-top: 8px;
        text-align: center;
    }

    #expanded-media-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 8px;
    }

    #expanded-media-grid .expanded-media-item .media-preview {
        height: 80px;
    }
}

/* 深色模式下的加载动画 */
@media (prefers-color-scheme: dark) {
    .loading::before {
        border-color: #30363d;
        border-top-color: #58a6ff;
    }
}
/* Toast 提示 */
.ml-editor-toast {
    position: fixed;
    top: 16px;
    right: 16px;
    background: var(--ml-text, #24292f);
    color: #ffffff;
    padding: 8px 14px;
    border-radius: var(--ml-radius, 6px);
    box-shadow: 0 8px 24px rgba(140, 149, 159, 0.2);
    opacity: 0;
    transform: translateY(-8px);
    transition: all 0.2s ease;
    z-index: 10000;
    font-size: 13px;
}

@media (prefers-color-scheme: dark) {
    .ml-editor-toast {
        background: #c9d1d9;
        color: #0d1117;
    }
}

.ml-editor-toast.show {
    opacity: 1;
    transform: translateY(0);
}

@keyframes mlModalScale {
    from {
        opacity: 0;
        transform: translateY(-8px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>

<div class="media-library-editor">
    <div class="media-toolbar">
        <div>
            <button type="button" class="btn btn-primary" id="editor-upload-btn">上传文件</button>
            <button type="button" class="btn" id="editor-copy-markdown" style="display:none;">复制 Markdown</button>
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
            <button type="button" class="btn" id="expanded-copy-btn" disabled>复制 Markdown</button>
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
            $copyBtn.show().text('复制 Markdown (' + selected + ')');
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
            $expandedCopyBtn.text('复制 Markdown (' + count + ')');
            $selectionCount.text('已选中 ' + count + ' 个文件');
        } else {
            $expandedDeleteBtn.text('删除选中');
            $expandedCopyBtn.text('复制 Markdown');
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
