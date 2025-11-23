var currentMediaData = null;
var allAttachments = window.mediaAttachments || [];

// 媒体库项目点击事件 - 处理复制和预览功能
function selectMedia(element) {
    var url = element.getAttribute('data-url');
    var mime = element.getAttribute('data-mime') || '';
    var title = element.getAttribute('data-title');
    var isImage = element.getAttribute('data-is-image') === '1';
    
    currentMediaData = {
        cid: element.getAttribute('data-cid'),
        url: url,
        title: title,
        mime: mime,
        isImage: isImage
    };
    
    // 检查是否为文档类型，如果是则显示全屏预览
    if (isDocumentType(mime)) {
        showFullscreenPreview(url, mime, title);
    } else {
        // 否则显示复制模态框
        showMediaCopyModal();
    }
}

// 判断是否为文档类型
function isDocumentType(mime) {
    var documentTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/html',
        'text/css',
        'text/javascript',
        'application/json',
        'application/xml'
    ];
    
    return documentTypes.some(function(type) {
        return mime.indexOf(type) === 0;
    });
}

// 显示全屏预览 - 修复版本
function showFullscreenPreview(url, mime, title) {
    // 创建模态框HTML
    var modalHtml = `
        <div class="fullscreen-preview-modal" id="fullscreen-preview-modal">
            <div class="fullscreen-preview-overlay" onclick="closeFullscreenPreview()"></div>
            <div class="fullscreen-preview-dialog ${mime.indexOf('image/') === 0 ? 'image-preview' : 'document-preview'}">
                ${mime.indexOf('image/') === 0 ? '' : `
                <div class="fullscreen-preview-header">
                    <h3 id="fullscreen-preview-title">${title}</h3>
                    <span class="fullscreen-preview-close" onclick="closeFullscreenPreview()">&times;</span>
                </div>
                `}
                <div class="fullscreen-preview-content" id="fullscreen-preview-content">
                    <!-- 动态内容 -->
                </div>
            </div>
        </div>
    `;
    
    // 如果模态框不存在，创建它
    var existingModal = document.getElementById('fullscreen-preview-modal');
    if (!existingModal) {
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
    
    var modal = document.getElementById('fullscreen-preview-modal');
    var content = document.getElementById('fullscreen-preview-content');
    var dialog = modal.querySelector('.fullscreen-preview-dialog');
    
    // 根据类型设置内容
    var html = '';
    
    if (mime.indexOf('image/') === 0) {
        // 图片预览 - 自适应尺寸
        dialog.className = 'fullscreen-preview-dialog image-preview';
        html = '<img src="' + url + '" alt="' + title + '">';
    } else if (mime === 'application/pdf') {
        html = '<iframe src="' + url + '" style="width: 100%; height: 100%; border: none;"></iframe>';
    } else if (mime.indexOf('text/') === 0 || mime === 'application/json' || mime === 'application/xml') {
        html = '<iframe src="' + url + '" style="width: 100%; height: 100%; border: none; background: white;"></iframe>';
    } else if (mime.indexOf('video/') === 0) {
        html = '<video controls style="width: 100%; height: 100%; object-fit: contain;"><source src="' + url + '" type="' + mime + '">您的浏览器不支持视频播放。</video>';
    } else if (mime.indexOf('audio/') === 0) {
        html = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; background: #f5f5f5;"><audio controls style="width: 80%;"><source src="' + url + '" type="' + mime + '">您的浏览器不支持音频播放。</audio></div>';
    } else {
        html = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; flex-direction: column; color: #666;"><div style="font-size: 48px; margin-bottom: 20px;">📄</div><p>无法预览此文件类型</p><a href="' + url + '" target="_blank" style="color: #1a73e8; text-decoration: none;">点击下载文件</a></div>';
    }
    
    content.innerHTML = html;
    modal.style.display = 'flex';
}

// 关闭全屏预览
function closeFullscreenPreview() {
    var modal = document.getElementById('fullscreen-preview-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// 搜索功能
function searchMedia() {
    var searchValue = document.getElementById('media-search-input').value.trim();
    var currentUrl = window.location.href.split('?')[0];
    var params = [];
    
    if (searchValue) {
        params.push('search=' + encodeURIComponent(searchValue));
    }
    
    var newUrl = currentUrl + (params.length ? '?' + params.join('&') : '');
    window.location.href = newUrl;
}

// 清除搜索
function clearSearch() {
    var currentUrl = window.location.href.split('?')[0];
    window.location.href = currentUrl;
}

// 实时搜索功能
document.getElementById('media-search-input').addEventListener('input', function() {
    var keyword = this.value.toLowerCase();
    var grid = document.getElementById('media-library-grid');
    
    if (!grid) return;
    
    // 如果搜索框为空，显示所有项目
    if (keyword === '') {
        var items = grid.querySelectorAll('.media-item');
        items.forEach(function(item) {
            item.style.display = '';
        });
        return;
    }
    
    // 过滤显示匹配的项目
    var items = grid.querySelectorAll('.media-item');
    items.forEach(function(item) {
        var title = item.getAttribute('data-title') || '';
        if (title.toLowerCase().indexOf(keyword) !== -1) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});

// 回车搜索
document.getElementById('media-search-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchMedia();
    }
});

// 打开媒体库管理新窗口
function openMediaManageWindow() {
    var url = window.mediaLibraryUrl;
    var windowFeatures = 'width=1200,height=800,scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no,status=no';
    window.open(url, 'MediaLibraryManager', windowFeatures);
}

function showMediaCopyModal() {
    if (!currentMediaData) return;
    
    var preview = document.getElementById('media-copy-preview');
    var altInput = document.getElementById('media-alt');
    var sizeSelect = document.getElementById('media-size');
    
    // 设置预览
    if (currentMediaData.isImage) {
        preview.innerHTML = '<img src="' + currentMediaData.url + '" alt="' + currentMediaData.title + '">';
        sizeSelect.value = 'image';
        // 显示图片选项
        var imageOption = sizeSelect.querySelector('option[value="image"]');
        if (imageOption) {
            imageOption.style.display = '';
        }
    } else {
        preview.innerHTML = '<div style="padding: 20px; background: #f8f9fa; border-radius: 4px;"><div style="font-size: 32px; margin-bottom: 10px;">📄</div><p style="margin: 0; color: #5f6368; font-size: 13px;">' + currentMediaData.title + '</p></div>';
        sizeSelect.value = 'link';
        // 隐藏图片选项
        var imageOption = sizeSelect.querySelector('option[value="image"]');
        if (imageOption) {
            imageOption.style.display = 'none';
        }
    }
    
    altInput.value = currentMediaData.title;
    updateCopyCode();
    
    document.getElementById('media-copy-modal').style.display = 'flex';
}

function closeMediaCopyModal() {
    document.getElementById('media-copy-modal').style.display = 'none';
    currentMediaData = null;
}

function updateCopyCode() {
    if (!currentMediaData) return;
    
    var altText = document.getElementById('media-alt').value || currentMediaData.title;
    var insertType = document.getElementById('media-size').value;
    var codeTextarea = document.getElementById('media-code');
    
    var code = '';
    
    switch (insertType) {
        case 'link':
            code = '<a href="' + currentMediaData.url + '" target="_blank">' + altText + '</a>';
            break;
        case 'image':
            code = '<img src="' + currentMediaData.url + '" alt="' + altText + '" />';
            break;
        case 'markdown':
            if (currentMediaData.isImage) {
                code = '![' + altText + '](' + currentMediaData.url + ')';
            } else {
                code = '[' + altText + '](' + currentMediaData.url + ')';
            }
            break;
    }
    
    codeTextarea.value = code;
}

function copyMediaCode() {
    var codeTextarea = document.getElementById('media-code');
    codeTextarea.select();
    codeTextarea.setSelectionRange(0, 99999);
    
    try {
        document.execCommand('copy');
        var status = document.getElementById('copy-status');
        status.classList.add('show');
        setTimeout(function() {
            status.classList.remove('show');
        }, 2000);
    } catch (err) {
        alert('复制失败，请手动复制');
    }
}

// Alt 文本变化时更新代码
document.getElementById('media-alt').addEventListener('input', updateCopyCode);

// 模态框关闭事件
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('media-copy-close') || 
        (e.target.classList.contains('media-copy-modal') && e.target === document.getElementById('media-copy-modal'))) {
        closeMediaCopyModal();
    }
});

// ESC键关闭模态框
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMediaCopyModal();
        closeFullscreenPreview();
    }
});

// 初始化时确保全屏预览模态框存在
document.addEventListener('DOMContentLoaded', function() {
    // 创建全屏预览模态框（如果不存在）
    if (!document.getElementById('fullscreen-preview-modal')) {
        var modalHtml = `
            <div class="fullscreen-preview-modal" id="fullscreen-preview-modal">
                <div class="fullscreen-preview-overlay" onclick="closeFullscreenPreview()"></div>
                <div class="fullscreen-preview-dialog">
                    <div class="fullscreen-preview-header">
                        <h3 id="fullscreen-preview-title">预览</h3>
                        <span class="fullscreen-preview-close" onclick="closeFullscreenPreview()">&times;</span>
                    </div>
                    <div class="fullscreen-preview-content" id="fullscreen-preview-content">
                        <!-- 动态内容 -->
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
});
