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
#media-library-container .media-library-editor {
    background: #fff;
    border: 1px solid #e3e8f0;
    border-radius: 6px;
    padding: 16px;
    margin-top: 15px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

#media-library-container .media-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

#media-library-container .media-toolbar .btn {
    margin-right: 8px;
}

#media-library-container .media-grid {
    min-height: 120px;
    border: 1px dashed #dbe1ee;
    border-radius: 6px;
    padding: 12px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
    background: #f9fbff;
}

#media-library-container .media-grid .loading,
#media-library-container .media-grid .empty-state {
    grid-column: 1 / -1;
    text-align: center;
    color: #94a3b8;
    padding: 30px 0;
}

#media-library-container .editor-media-item {
    background: #fff;
    border: 1px solid #e3e8f0;
    border-radius: 6px;
    padding: 8px;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

#media-library-container .editor-media-item.selected {
    border-color: #3b82f6;
    box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.3);
}

#media-library-container .editor-media-item .media-preview {
    width: 100%;
    height: 90px;
    background: #f5f6fa;
    border-radius: 5px;
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
    font-size: 24px;
    color: #475569;
    font-weight: 600;
}

#media-library-container .editor-media-item .media-title {
    margin-top: 6px;
    font-size: 12px;
    color: #475569;
    text-align: center;
    max-height: 32px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.ml-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.45);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
}

.ml-modal-dialog {
    background: #fff;
    width: 420px;
    border-radius: 12px;
    box-shadow: 0 25px 50px rgba(15, 23, 42, 0.15);
    padding: 20px;
    animation: mlModalScale 0.18s ease;
}

.ml-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.ml-modal-header h3 {
    margin: 0;
    font-size: 18px;
    color: #0f172a;
}

.ml-modal-close {
    cursor: pointer;
    font-size: 20px;
    color: #94a3b8;
}

.ml-modal-close:hover {
    color: #0f172a;
}

.ml-modal-body {
    font-size: 13px;
    color: #475569;
}

.ml-upload-storage-control {
    margin-bottom: 16px;
}

.ml-storage-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.ml-storage-pill {
    border: 1px solid #dbe1ee;
    border-radius: 30px;
    padding: 8px 12px;
    font-size: 12px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    min-width: 120px;
}

.ml-storage-pill input {
    display: none;
}

.ml-storage-pill .storage-pill-name {
    font-weight: 600;
    color: #0f172a;
}

.ml-storage-pill .storage-pill-desc {
    color: #94a3b8;
    font-size: 11px;
    margin-top: 4px;
}

.ml-storage-pill input:checked + .storage-pill-text,
.ml-storage-pill input:checked + .storage-pill-text .storage-pill-name {
    color: #1d4ed8;
}

.ml-storage-pill.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.ml-upload-storage-hint {
    margin-top: 6px;
    font-size: 12px;
    color: #475569;
}

.ml-upload-area {
    border: 1px dashed #cbd5f5;
    border-radius: 8px;
    padding: 24px;
    text-align: center;
    background: #f8faff;
    transition: border-color 0.15s ease, background 0.15s ease;
}

.ml-upload-area.dragover {
    border-color: #2563eb;
    background: #eef2ff;
}

.ml-upload-area .upload-hint {
    color: #94a3b8;
    margin-bottom: 8px;
}

#editor-file-list {
    list-style: none;
    margin: 16px 0 0;
    padding: 0;
    max-height: 200px;
    overflow-y: auto;
}

#editor-file-list li {
    border: 1px solid #e5e7eb;
    border-left: 4px solid #cbd5f5;
    border-radius: 4px;
    padding: 10px 12px;
    margin-bottom: 10px;
    font-size: 12px;
    color: #475569;
}

#editor-file-list li.success {
    border-left-color: #22c55e;
}

#editor-file-list li.error {
    border-left-color: #ef4444;
    color: #dc2626;
}

#editor-file-list .file-name {
    font-weight: 600;
    display: inline-block;
    max-width: 60%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

#editor-file-list .file-size {
    color: #94a3b8;
    margin-left: 6px;
}

#editor-file-list .progress-bar {
    width: 100%;
    height: 4px;
    background: #e2e8f0;
    border-radius: 2px;
    margin-top: 8px;
}

#editor-file-list .progress-fill {
    width: 0%;
    height: 100%;
    border-radius: 2px;
    background: #2563eb;
    transition: width 0.2s ease;
}

#editor-file-list .status {
    margin-top: 6px;
}

.ml-editor-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #2563eb;
    color: #fff;
    padding: 10px 16px;
    border-radius: 6px;
    box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
    opacity: 0;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    z-index: 10000;
    font-size: 13px;
}

.ml-editor-toast.show {
    opacity: 1;
    transform: translateY(0);
}

@keyframes mlModalScale {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.98);
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
            <button class="btn btn-primary" id="editor-upload-btn">上传文件</button>
            <button class="btn" id="editor-insert-selected" style="display:none;">插入选中</button>
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
(function($) {
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
})(jQuery);
</script>
