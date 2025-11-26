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
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
    gap: 8px;
    background: var(--ml-bg-secondary);
    scrollbar-width: thin;
    scrollbar-color: var(--ml-border) transparent;
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

/* 上传模态框 */
.ml-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(27, 31, 36, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 16px;
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
            <button type="button" class="btn" id="editor-insert-selected" style="display:none;">插入选中</button>
        </div>
    </div>
    
    <div class="media-grid editor-media-grid">
        <!-- 动态加载内容 -->
        <div class="loading">加载中...</div>
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
    var $insertBtn = $('#editor-insert-selected');

    function loadMediaList() {
        $grid.html('<div class="loading">加载中...</div>');
        $.get(listUrl, function(data) {
            $grid.html(data || '<div class="empty-state">没有媒体文件，请上传</div>');
            updateInsertButton();
        }).fail(function() {
            $grid.html('<div class="empty-state">媒体列表加载失败，请刷新页面</div>');
        });
    }

    function updateInsertButton() {
        var selected = $('.editor-media-item.selected').length;
        $insertBtn.toggle(selected > 0);
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

    function insertContentToEditor(content) {
        if (window.editor && window.editor.txt) {
            window.editor.txt.append(content);
            return;
        }

        var textarea = $('#text');
        if (textarea.length > 0 && textarea[0]) {
            var caretPos = textarea[0].selectionStart || 0;
            var textAreaTxt = textarea.val();
            textarea.val(textAreaTxt.substring(0, caretPos) + content + textAreaTxt.substring(caretPos));
        }
    }

    function bindSelectionEvents() {
        $(document).on('click', '.editor-media-item', function() {
            $(this).toggleClass('selected');
            updateInsertButton();
        });
    }

    function bindInsertEvent() {
        $insertBtn.on('click', function() {
            $('.editor-media-item.selected').each(function() {
                var url = $(this).data('url');
                var isImage = $(this).data('is-image') === 1 || $(this).data('is-image') === '1';
                if (!url) {
                    return;
                }
                if (isImage) {
                    insertContentToEditor('<img src="' + url + '" alt="" />');
                } else {
                    var title = $(this).data('title') || url;
                    insertContentToEditor('<a href="' + url + '">' + title + '</a>');
                }
            });

            $('.editor-media-item.selected').removeClass('selected');
            updateInsertButton();
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
            $modal.fadeIn(120);
        }

        function closeModal() {
            $modal.fadeOut(120, function() {
                $fileList.empty();
            });
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
                    loadMediaList();
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

    $(function() {
        loadMediaList();
        bindSelectionEvents();
        bindInsertEvent();
        initUploadModal();
    });
});
</script>
