// 全局变量
var currentUrl = window.mediaLibraryCurrentUrl;
var currentKeywords = window.mediaLibraryKeywords || '';
var currentType = window.mediaLibraryType || 'all';
var currentView = window.mediaLibraryView || 'grid';
var currentStorageFilter = window.mediaLibraryStorage || 'all';
var currentStorage = currentStorageFilter === 'all' ? 'local' : currentStorageFilter;
var config = window.mediaLibraryConfig || {};

// 修复分页跳转函数 - 防止打开新标签页
function goToPage(page, event) {
    // 阻止默认行为
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    var params = [];
    if (page > 1) params.push('page=' + page);
    if (currentKeywords) params.push('keywords=' + encodeURIComponent(currentKeywords));
    if (currentType !== 'all') params.push('type=' + currentType);
    if (currentView !== 'grid') params.push('view=' + currentView);
    if (currentStorageFilter && currentStorageFilter !== 'all') params.push('storage=' + currentStorageFilter);

    var url = currentUrl + (params.length ? '&' + params.join('&') : '');
    // 使用 location.href 而不是 window.open
    window.location.href = url;
    return false; // 防止默认行为
}


// 主要功能对象
var MediaLibrary = {
    selectedItems: [],
    
    init: function() {
        this.bindEvents();
        this.initUpload();
        this.hideAllModals();
    },
    
    hideAllModals: function() {
        var modals = document.querySelectorAll('.modal');
        modals.forEach(function(modal) {
            modal.style.display = 'none';
        });
    },
    
    bindEvents: function() {
        var self = this;
        
        // 类型选择变化
        var typeSelect = document.getElementById('type-select');
        if (typeSelect) {
            typeSelect.addEventListener('change', function() {
                self.updateUrl({type: this.value, page: 1});
            });
        }
        
        // 搜索
        var searchBtn = document.getElementById('search-btn');
        if (searchBtn) {
            searchBtn.addEventListener('click', function() {
                var keywords = document.getElementById('keywords-input').value;
                self.updateUrl({keywords: keywords, page: 1});
            });
        }
        
        var keywordsInput = document.getElementById('keywords-input');
        if (keywordsInput) {
            keywordsInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    var keywords = this.value;
                    self.updateUrl({keywords: keywords, page: 1});
                }
            });
        }
        
        // 视图切换
        document.querySelectorAll('.view-switch a').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var view = this.getAttribute('data-view');
                self.updateUrl({view: view});
            });
        });
        
        // 全选
        var selectAllCheckbox = document.querySelector('.select-all');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                var checked = this.checked;
                var checkboxes = document.querySelectorAll('input[type="checkbox"][value]');
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = checked;
                    var item = checkbox.closest('.media-item, tr[data-cid]');
                    if (item) {
                        if (checked) {
                            item.classList.add('selected');
                        } else {
                            item.classList.remove('selected');
                        }
                    }
                });
                self.updateSelectedCount();
                self.updateToolbarButtons();
            });
        }
        
        // 单选
        document.addEventListener('change', function(e) {
            if (e.target.type === 'checkbox' && e.target.value) {
                var item = e.target.closest('.media-item, tr[data-cid]');
                if (item) {
                    if (e.target.checked) {
                        item.classList.add('selected');
                    } else {
                        item.classList.remove('selected');
                    }
                }
                self.updateSelectedCount();
                self.updateToolbarButtons();
            }
        });
        
        // 删除选中
        var deleteSelectedBtn = document.getElementById('delete-selected');
        if (deleteSelectedBtn) {
            deleteSelectedBtn.addEventListener('click', function() {
                self.deleteSelected();
            });
        }
        
        // 分开的压缩按钮
        var compressImagesBtn = document.getElementById('compress-images-btn');
        if (compressImagesBtn) {
            compressImagesBtn.addEventListener('click', function() {
                self.showImageCompressModal();
            });
        }
        
        var compressVideosBtn = document.getElementById('compress-videos-btn');
        if (compressVideosBtn) {
            compressVideosBtn.addEventListener('click', function() {
                self.showVideoCompressModal();
            });
        }
        
        // 隐私检测按钮
        var privacyBtn = document.getElementById('privacy-btn');
        if (privacyBtn) {
            privacyBtn.addEventListener('click', function() {
                self.checkPrivacy();
            });
        }

        // 缓存刷新按钮
        var cacheRefreshBtn = document.getElementById('cache-refresh-btn');
        if (cacheRefreshBtn) {
            cacheRefreshBtn.addEventListener('click', function() {
                self.refreshCache();
            });
        }

        // 删除单个
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('media-delete-btn')) {
                e.preventDefault();
                e.stopPropagation();
                var cid = e.target.getAttribute('data-cid');
                self.deleteFiles([cid]);
            }
        });
        
        // 查看详情
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('media-info-btn')) {
                e.preventDefault();
                e.stopPropagation();
                var cid = e.target.getAttribute('data-cid');
                self.showFileInfo(cid);
            }
        });
        
        // 点击文件卡片预览
        document.addEventListener('click', function(e) {
            if (e.target.closest('.media-actions') || 
                e.target.closest('.media-checkbox') || 
                e.target.type === 'checkbox' ||
                e.target.classList.contains('media-delete-btn') ||
                e.target.classList.contains('media-info-btn')) {
                return;
            }
            
            var item = e.target.closest('.media-item');
            var thumb = e.target.classList.contains('media-thumb') ? e.target : null;
            
            if (item || thumb) {
                var element = item || thumb.closest('tr[data-cid]');
                if (element) {
                    var url = element.getAttribute('data-url');
                    var type = element.getAttribute('data-type');
                    var title = element.getAttribute('data-title');
                    var hasUrl = element.getAttribute('data-has-url');
                    
                    if (url && url.trim() !== '' && hasUrl === '1') {
                        self.showPreview(url, type, title);
                    }
                }
            }
        });
        
        // 模态框关闭
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-close')) {
                var modal = e.target.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            } else if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
        
        // 上传按钮
        document.addEventListener('click', function(e) {
            if (e.target.id === 'upload-btn' || e.target.id === 'upload-btn-empty') {
                var uploadModal = document.getElementById('upload-modal');
                if (uploadModal) {
                    uploadModal.style.display = 'flex';
                }
            }
        });

        // 压缩相关事件
        this.bindCompressEvents();
    },
    
    bindCompressEvents: function() {
        var self = this;
        
        // 图片压缩质量滑块
        var imageQualitySlider = document.getElementById('image-quality-slider');
        var imageQualityValue = document.getElementById('image-quality-value');
        if (imageQualitySlider && imageQualityValue) {
            imageQualitySlider.addEventListener('input', function() {
                imageQualityValue.textContent = this.value + '%';
            });
        }
        
        // 智能建议相关事件
        var getSmartSuggestionBtn = document.getElementById('get-smart-suggestion');
        if (getSmartSuggestionBtn) {
            getSmartSuggestionBtn.addEventListener('click', function() {
                self.getSmartSuggestion();
            });
        }
        
        var applySmartSuggestionBtn = document.getElementById('apply-smart-suggestion');
        if (applySmartSuggestionBtn) {
            applySmartSuggestionBtn.addEventListener('click', function() {
                self.applySmartSuggestion();
            });
        }
        
        // 视频压缩质量滑块
        var videoQualitySlider = document.getElementById('video-quality-slider');
        var videoQualityValue = document.getElementById('video-quality-value');
        if (videoQualitySlider && videoQualityValue) {
            videoQualitySlider.addEventListener('input', function() {
                videoQualityValue.textContent = this.value;
            });
        }
        
        // 图片替换模式切换
        document.addEventListener('change', function(e) {
            if (e.target.name === 'image-replace-mode') {
                var customNameGroup = document.getElementById('image-custom-name-group');
                if (customNameGroup) {
                    if (e.target.value === 'keep') {
                        customNameGroup.style.display = 'block';
                    } else {
                        customNameGroup.style.display = 'none';
                    }
                }
            }
        });
        
        // 视频替换模式切换
        document.addEventListener('change', function(e) {
            if (e.target.name === 'video-replace-mode') {
                var customNameGroup = document.getElementById('video-custom-name-group');
                if (customNameGroup) {
                    if (e.target.value === 'keep') {
                        customNameGroup.style.display = 'block';
                    } else {
                        customNameGroup.style.display = 'none';
                    }
                }
            }
        });
        
        // 开始图片压缩
        var startImageCompressBtn = document.getElementById('start-image-compress');
        if (startImageCompressBtn) {
            startImageCompressBtn.addEventListener('click', function() {
                self.startImageCompress();
            });
        }
        
        // 开始视频压缩
        var startVideoCompressBtn = document.getElementById('start-video-compress');
        if (startVideoCompressBtn) {
            startVideoCompressBtn.addEventListener('click', function() {
                self.startVideoCompress();
            });
        }
        
        // 取消图片压缩
        var cancelImageCompressBtn = document.getElementById('cancel-image-compress');
        if (cancelImageCompressBtn) {
            cancelImageCompressBtn.addEventListener('click', function() {
                document.getElementById('image-compress-modal').style.display = 'none';
            });
        }
        
        // 取消视频压缩
        var cancelVideoCompressBtn = document.getElementById('cancel-video-compress');
        if (cancelVideoCompressBtn) {
            cancelVideoCompressBtn.addEventListener('click', function() {
                document.getElementById('video-compress-modal').style.display = 'none';
            });
        }
    },
    
    updateUrl: function(params) {
        var urlParams = [];
        
        var newKeywords = params.keywords !== undefined ? params.keywords : currentKeywords;
        var newType = params.type !== undefined ? params.type : currentType;
        var newView = params.view !== undefined ? params.view : currentView;
        var newPage = params.page !== undefined ? params.page : 1;
        
        if (newPage > 1) urlParams.push('page=' + newPage);
        if (newKeywords) urlParams.push('keywords=' + encodeURIComponent(newKeywords));
        if (newType !== 'all') urlParams.push('type=' + newType);
        if (newView !== 'grid') urlParams.push('view=' + newView);
        
        var url = currentUrl + (urlParams.length ? '&' + urlParams.join('&') : '');
        window.location.href = url;
    },
    
    updateSelectedCount: function() {
        var checkboxes = document.querySelectorAll('input[type="checkbox"][value]:checked');
        var count = checkboxes.length;
        var deleteBtn = document.getElementById('delete-selected');
        var selectionIndicator = document.getElementById('selection-indicator');
        
        if (deleteBtn) {
            if (count > 0) {
                deleteBtn.style.display = 'inline-block';
                deleteBtn.textContent = '删除选中 (' + count + ')';
            } else {
                deleteBtn.style.display = 'none';
            }
        }
        
        if (selectionIndicator) {
            if (count > 0) {
                selectionIndicator.textContent = '已选中 ' + count + ' 个文件';
                selectionIndicator.classList.add('active');
            } else {
                selectionIndicator.textContent = '未选择文件';
                selectionIndicator.classList.remove('active');
            }
        }
        
        // 更新选中项目列表
        this.selectedItems = [];
        checkboxes.forEach(function(checkbox) {
            var item = checkbox.closest('.media-item, tr[data-cid]');
            if (item) {
                var type = item.getAttribute('data-type') || '';
                this.selectedItems.push({
                    cid: checkbox.value,
                    isImage: item.getAttribute('data-is-image') === '1',
                    isVideo: item.getAttribute('data-is-video') === '1' || type.indexOf('video/') === 0,
                    type: type
                });
            }
        }.bind(this));
    },
    
updateToolbarButtons: function() {
    var compressImagesBtn = document.getElementById('compress-images-btn');
    var compressVideosBtn = document.getElementById('compress-videos-btn');
    var privacyBtn = document.getElementById('privacy-btn');
    var cropImagesBtn = document.getElementById('crop-images-btn'); // 添加这行
    var addWatermarkBtn = document.getElementById('add-watermark-btn'); // 添加这行
    
    var selectedImages = this.selectedItems.filter(function(item) {
        return item.isImage;
    });
    
    var selectedVideos = this.selectedItems.filter(function(item) {
        return item.isVideo;
    });
    
    // 图片压缩按钮
    if (compressImagesBtn && (config.enableGD || config.enableImageMagick || config.enableFFmpeg)) {
        if (selectedImages.length > 0) {
            compressImagesBtn.style.display = 'inline-block';
            compressImagesBtn.disabled = false;
            compressImagesBtn.textContent = '压缩图片 (' + selectedImages.length + ')';
        } else {
            compressImagesBtn.style.display = 'none';
            compressImagesBtn.disabled = true;
        }
    }
    
    // 视频压缩按钮
    if (compressVideosBtn && config.enableVideoCompress && config.enableFFmpeg) {
        if (selectedVideos.length > 0) {
            compressVideosBtn.style.display = 'inline-block';
            compressVideosBtn.disabled = false;
            compressVideosBtn.textContent = '压缩视频 (' + selectedVideos.length + ')';
        } else {
            compressVideosBtn.style.display = 'none';
            compressVideosBtn.disabled = true;
        }
    }
    
    // 隐私检测按钮 - 检测需要EXIF扩展或ExifTool，但清除只需要ExifTool
    if (privacyBtn && config.enableExif && (config.hasExifTool || config.hasPhpExif)) {
        if (selectedImages.length > 0) {
            privacyBtn.style.display = 'inline-block';
            privacyBtn.disabled = false;
            privacyBtn.textContent = '隐私检测 (' + selectedImages.length + ')';
        } else {
            privacyBtn.style.display = 'none';
            privacyBtn.disabled = true;
        }
    }
    
    // 添加裁剪图片按钮处理
    if (cropImagesBtn && (config.enableGD || config.enableImageMagick)) {
        if (selectedImages.length === 1) {
            cropImagesBtn.style.display = 'inline-block';
            cropImagesBtn.disabled = false;
            cropImagesBtn.textContent = '裁剪图片';
        } else {
            cropImagesBtn.style.display = 'none';
            cropImagesBtn.disabled = true;
        }
    }
    
    // 添加水印按钮处理
    if (addWatermarkBtn && (config.enableGD || config.enableImageMagick)) {
        if (selectedImages.length === 1) {
            addWatermarkBtn.style.display = 'inline-block';
            addWatermarkBtn.disabled = false;
            addWatermarkBtn.textContent = '添加水印';
        } else {
            addWatermarkBtn.style.display = 'none';
            addWatermarkBtn.disabled = true;
        }
    }
},


    
    deleteSelected: function() {
        var cids = [];
        var checkboxes = document.querySelectorAll('input[type="checkbox"][value]:checked');
        checkboxes.forEach(function(checkbox) {
            cids.push(checkbox.value);
        });
        
        if (cids.length === 0) {
            alert('请选择要删除的文件');
            return;
        }
        
        this.deleteFiles(cids);
    },
    
    deleteFiles: function(cids) {
        if (!confirm('确定要删除这些文件吗？此操作不可恢复！')) {
            return;
        }
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', currentUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        var params = 'action=delete&' + cids.map(function(cid) {
            return 'cids[]=' + encodeURIComponent(cid);
        }).join('&');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            alert(response.message || '删除成功');
                            location.reload();
                        } else {
                            alert('删除失败: ' + (response.message || '未知错误'));
                        }
                    } catch (e) {
                        alert('删除操作响应解析失败');
                        console.error('Response parse error:', e, xhr.responseText);
                    }
                } else {
                    alert('删除操作失败，服务器返回错误 (HTTP ' + xhr.status + ')');
                }
            }
        };
        
        xhr.send(params);
    },
    
    showFileInfo: function(cid) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', currentUrl + '&action=get_info&cid=' + cid, true);
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        var data = response.data;
                        var html = '<table class="file-info-table">';
                        html += '<tr><td>文件名</td><td>' + data.title + '</td></tr>';
                        html += '<tr><td>文件类型</td><td>' + data.mime + '</td></tr>';
                        html += '<tr><td>文件大小</td><td>' + data.size + '</td></tr>';
                        html += '<tr><td>上传时间</td><td>' + data.created + '</td></tr>';
                        html += '<tr><td>文件路径</td><td>' + data.path + '</td></tr>';
                        html += '<tr><td>访问地址</td><td><input type="text" value="' + data.url + '" readonly onclick="this.select()" style="width:100%;"></td></tr>';
                        
                        html += '<tr><td>所属文章</td><td>';
                        if (data.parent_post.status === 'archived') {
                            html += '<div class="parent-post">';
                            html += '<a href="' + currentUrl.replace('extending.php?panel=MediaLibrary%2Fpanel.php', 'write-' + (data.parent_post.post.type.indexOf('post') === 0 ? 'post' : 'page') + '.php?cid=' + data.parent_post.post.cid) + '" target="_blank">' + data.parent_post.post.title + '</a>';
                            html += '</div>';
                        } else {
                            html += '<span style="color: #999;">未归档</span>';
                        }
                        html += '</td></tr>';
                        html += '</table>';
                        
                        if (data.detailed_info && Object.keys(data.detailed_info).length > 0) {
                            html += '<div class="detailed-info">';
                            html += '<h4>详细信息</h4>';
                            html += '<table>';
                            
                            var info = data.detailed_info;
                            if (info.format) html += '<tr><td>格式</td><td>' + info.format + '</td></tr>';
                            if (info.dimensions) html += '<tr><td>尺寸</td><td>' + info.dimensions + '</td></tr>';
                            if (info.duration) html += '<tr><td>时长</td><td>' + info.duration + '</td></tr>';
                            if (info.bitrate) html += '<tr><td>比特率</td><td>' + info.bitrate + '</td></tr>';
                            if (info.channels) html += '<tr><td>声道</td><td>' + info.channels + '</td></tr>';
                            if (info.sample_rate) html += '<tr><td>采样率</td><td>' + info.sample_rate + '</td></tr>';
                            if (info.permissions) html += '<tr><td>权限</td><td>' + info.permissions + '</td></tr>';
                            if (info.modified) html += '<tr><td>修改时间</td><td>' + new Date(info.modified * 1000).toLocaleString() + '</td></tr>';
                            
                            html += '</table>';
                            html += '</div>';
                        }
                        
                        var infoContent = document.getElementById('file-info-content');
                        var infoModal = document.getElementById('info-modal');
                        if (infoContent && infoModal) {
                            infoContent.innerHTML = html;
                            infoModal.style.display = 'flex';
                        }
                    } else {
                        alert('获取文件信息失败：' + response.message);
                    }
                } catch (e) {
                    alert('获取文件信息失败，请重试');
                }
            }
        };
        
        xhr.send();
    },
    
    // 智能尺寸适配预览功能 - 优化版本
    showPreview: function(url, type, title) {
        var self = this;
        var modal = document.getElementById('preview-modal');
        var modalDialog = modal.querySelector('.modal-dialog');
        var modalBody = modal.querySelector('.modal-body');
        var modalTitle = modal.querySelector('.modal-header h3');
        
        if (!modal || !modalDialog || !modalBody) return;
        
        // 设置标题
        if (modalTitle) {
            modalTitle.textContent = title || '预览';
        }
        
        // 清空内容
        modalBody.innerHTML = '';
        
        // 重置样式
        modalDialog.className = 'modal-dialog';
        modalBody.style = '';
        
        // 根据类型设置预览内容
        if (type.indexOf('image/') === 0) {
            // 图片预览 - 自适应尺寸
            modalDialog.classList.add('image-preview');
            
            var img = new Image();
            img.onload = function() {
                modalBody.appendChild(img);
                modal.style.display = 'flex';
            };
            img.onerror = function() {
                modalBody.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">图片加载失败</p>';
                modal.style.display = 'flex';
            };
            img.src = url;
            img.alt = title || '';
            
        } else if (type.indexOf('video/') === 0) {
            // 视频预览
            modalDialog.classList.add('video-preview');
            
            var video = document.createElement('video');
            video.controls = true;
            video.autoplay = false;
            video.src = url;
            
            modalBody.appendChild(video);
            modal.style.display = 'flex';
            
        } else if (type.indexOf('audio/') === 0) {
            // 音频预览
            modalDialog.classList.add('audio-preview');
            
            var audio = document.createElement('audio');
            audio.controls = true;
            audio.src = url;
            
            var icon = document.createElement('div');
            icon.style.fontSize = '48px';
            icon.style.marginBottom = '20px';
            icon.textContent = '🎵';
            
            modalBody.appendChild(icon);
            modalBody.appendChild(audio);
            modal.style.display = 'flex';
            
        } else if (type === 'application/pdf') {
            // PDF预览
            modalDialog.classList.add('document-preview');
            
            var iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = 'none';
            
            modalBody.appendChild(iframe);
            modal.style.display = 'flex';
            
        } else if (type.indexOf('text/') === 0 || 
                   type === 'application/json' || 
                   type === 'application/xml' ||
                   type === 'application/javascript') {
            // 文本文件预览
            modalDialog.classList.add('document-preview');
            
            var iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = 'none';
            iframe.style.background = 'white';
            
            modalBody.appendChild(iframe);
            modal.style.display = 'flex';
            
        } else {
            // 其他文件类型
            modalDialog.style.width = '500px';
            
            var content = '<div style="text-align: center; padding: 40px;">';
            content += '<div style="font-size: 48px; margin-bottom: 20px;">📄</div>';
            content += '<p style="color: #666; margin-bottom: 20px;">无法预览此文件类型</p>';
            content += '<a href="' + url + '" target="_blank" class="btn btn-primary">下载文件</a>';
            content += '</div>';
            
            modalBody.innerHTML = content;
            modal.style.display = 'flex';
        }
    },
    
showImageCompressModal: function() {
    var selectedImages = this.selectedItems.filter(function(item) {
        return item.isImage;
    });
    
    if (selectedImages.length === 0) {
        alert('请选择图片文件进行压缩');
        return;
    }
    
    var imageCompressModal = document.getElementById('image-compress-modal');
    if (imageCompressModal) {
        // 重置结果显示
        var resultDiv = document.getElementById('image-compress-result');
        if (resultDiv) {
            resultDiv.style.display = 'none';
            resultDiv.innerHTML = '';
        }
        
        // 显示智能建议区域
        var suggestionArea = document.getElementById('smart-suggestion-area');
        if (suggestionArea) {
            suggestionArea.style.display = 'block';
        }
        
        // 重置智能建议内容
        var suggestionContent = document.getElementById('suggestion-content');
        if (suggestionContent) {
            suggestionContent.innerHTML = '<p style="color: #666; text-align: center; padding: 20px;">点击"获取智能建议"按钮来获取针对所选图片的压缩建议</p>';
        }
        
        // 重置质量滑块为配置的默认值
        var qualitySlider = document.getElementById('image-quality-slider');
        var qualityValue = document.getElementById('image-quality-value');
        if (qualitySlider && qualityValue) {
            qualitySlider.value = config.gdQuality || 80;
            qualityValue.textContent = (config.gdQuality || 80) + '%';
        }
        
        // 重置输出格式
        var formatSelect = document.getElementById('image-output-format');
        if (formatSelect) {
            formatSelect.value = 'original';
        }
        
        // 重置压缩方法
        var methodSelect = document.getElementById('image-compress-method');
        if (methodSelect) {
            methodSelect.value = config.enableGD ? 'gd' : (config.enableImageMagick ? 'imagick' : 'ffmpeg');
        }
        
        // 显示选中文件信息
        var fileList = document.getElementById('image-compress-files');
        if (fileList) {
            var html = '<p>已选择 ' + selectedImages.length + ' 个图片文件：</p>';
            html += '<ul style="max-height: 100px; overflow-y: auto; margin: 10px 0; padding-left: 20px;">';
            selectedImages.forEach(function(item) {
                var element = document.querySelector('[data-cid="' + item.cid + '"]');
                var filename = element ? element.getAttribute('data-title') || 'Unknown' : 'Unknown';
                html += '<li>' + filename + '</li>';
            });
            html += '</ul>';
            fileList.innerHTML = html;
        }
        
        imageCompressModal.style.display = 'flex';
    }
},

    
    showVideoCompressModal: function() {
        var selectedVideos = this.selectedItems.filter(function(item) {
            return item.isVideo;
        });
        
        if (selectedVideos.length === 0) {
            alert('请选择要压缩的视频');
            return;
        }
        
        var modal = document.getElementById('video-compress-modal');
        var fileList = document.getElementById('video-compress-files');
        
        if (fileList) {
            var html = '<p>已选择 ' + selectedVideos.length + ' 个视频文件</p>';
            fileList.innerHTML = html;
        }
        
        if (modal) {
            modal.style.display = 'flex';
        }
    },
    
    
      getSmartSuggestion: function() {
        var selectedImages = this.selectedItems.filter(function(item) {
            return item.isImage;
        });
        
        if (selectedImages.length === 0) {
            alert('请选择图片文件');
            return;
        }
        
        var cids = selectedImages.map(function(item) { return item.cid; });
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', currentUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        var params = 'action=get_smart_suggestion&' + cids.map(function(cid) {
            return 'cids[]=' + encodeURIComponent(cid);
        }).join('&');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        MediaLibrary.displaySmartSuggestion(response.suggestions);
                    } else {
                        alert('获取建议失败：' + response.message);
                    }
                } catch (e) {
                    alert('获取建议失败，请重试');
                }
            }
        };
        
        xhr.send(params);
    },
    
    displaySmartSuggestion: function(suggestions) {
        var suggestionContent = document.getElementById('suggestion-content');
        if (!suggestionContent) return;
        
        var html = '<div style="max-height: 200px; overflow-y: auto;">';
        
        // 计算平均建议
        var avgQuality = 0;
        var formatCounts = {};
        var methodCounts = {};
        
        suggestions.forEach(function(item) {
            avgQuality += item.suggestion.quality;
            formatCounts[item.suggestion.format] = (formatCounts[item.suggestion.format] || 0) + 1;
            methodCounts[item.suggestion.method] = (methodCounts[item.suggestion.method] || 0) + 1;
        });
        
        avgQuality = Math.round(avgQuality / suggestions.length);
        
        var recommendedFormat = Object.keys(formatCounts).reduce(function(a, b) {
            return formatCounts[a] > formatCounts[b] ? a : b;
        });
        
        var recommendedMethod = Object.keys(methodCounts).reduce(function(a, b) {
            return methodCounts[a] > methodCounts[b] ? a : b;
        });
        
        html += '<div style="padding: 10px; background: #e8f5e8; border-radius: 4px; margin-bottom: 10px;">';
        html += '<strong>📊 综合建议：</strong><br>';
        html += '推荐质量: ' + avgQuality + '%<br>';
        html += '推荐格式: ' + (recommendedFormat === 'original' ? '保持原格式' : recommendedFormat.toUpperCase()) + '<br>';
        html += '推荐方法: ' + recommendedMethod.toUpperCase();
        html += '</div>';
        
        suggestions.forEach(function(item) {
            html += '<div style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px;">';
            html += '<div style="font-weight: bold;">' + item.filename + ' (' + item.size + ')</div>';
            html += '<div style="font-size: 12px; color: #666;">';
            html += '建议质量: ' + item.suggestion.quality + '% | ';
            html += '建议格式: ' + (item.suggestion.format === 'original' ? '保持原格式' : item.suggestion.format.toUpperCase()) + '<br>';
            html += '原因: ' + item.suggestion.reason;
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        
        suggestionContent.innerHTML = html;
        
        // 存储建议数据
        this.currentSuggestion = {
            quality: avgQuality,
            format: recommendedFormat,
            method: recommendedMethod
        };
    },
    
        applySmartSuggestion: function() {
        if (!this.currentSuggestion) {
            alert('请先获取智能建议');
            return;
        }
        
        var qualitySlider = document.getElementById('image-quality-slider');
        var qualityValue = document.getElementById('image-quality-value');
        var formatSelect = document.getElementById('image-output-format');
        var methodSelect = document.getElementById('image-compress-method');
        
        if (qualitySlider && qualityValue) {
            qualitySlider.value = this.currentSuggestion.quality;
            qualityValue.textContent = this.currentSuggestion.quality + '%';
        }
        
        if (formatSelect) {
            formatSelect.value = this.currentSuggestion.format;
        }
        
        if (methodSelect) {
            methodSelect.value = this.currentSuggestion.method;
        }
        
        alert('已应用智能建议设置！');
    },
    
    
    
    
    showVideoCompressModal: function() {
        var selectedVideos = this.selectedItems.filter(function(item) {
            return item.isVideo;
        });
        
        if (selectedVideos.length === 0) {
            alert('请选择视频文件进行压缩');
            return;
        }
        
        var videoCompressModal = document.getElementById('video-compress-modal');
        if (videoCompressModal) {
            // 重置结果显示
            var resultDiv = document.getElementById('video-compress-result');
            if (resultDiv) {
                resultDiv.style.display = 'none';
                resultDiv.innerHTML = '';
            }
            
            // 重置质量滑块为配置的默认值
            var qualitySlider = document.getElementById('video-quality-slider');
            var qualityValue = document.getElementById('video-quality-value');
            if (qualitySlider && qualityValue) {
                qualitySlider.value = config.videoQuality || 23;
                qualityValue.textContent = config.videoQuality || 23;
            }
            
            videoCompressModal.style.display = 'flex';
        }
    },
    
    
 startImageCompress: function() {
    var selectedImages = this.selectedItems.filter(function(item) {
        return item.isImage;
    });
    
    if (selectedImages.length === 0) {
        alert('请选择图片文件');
        return;
    }
    
    var cids = selectedImages.map(function(item) { return item.cid; });
    var quality = document.getElementById('image-quality-slider').value;
    var outputFormat = document.getElementById('image-output-format').value;
    var compressMethod = document.getElementById('image-compress-method').value;
    var replaceOriginal = document.querySelector('input[name="image-replace-mode"]:checked').value === 'replace';
    var customName = document.getElementById('image-custom-name').value;
    
    // 显示进度
    var resultDiv = document.getElementById('image-compress-result');
    if (resultDiv) {
        resultDiv.innerHTML = '<div style="text-align: center; padding: 20px;"><div>正在压缩图片，请稍候...</div><div style="margin-top: 10px;"><div class="spinner"></div></div></div>';
        resultDiv.style.display = 'block';
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', currentUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    var params = 'action=compress_images';
    params += '&quality=' + quality;
    params += '&output_format=' + outputFormat;
    params += '&compress_method=' + compressMethod;
    params += '&replace_original=' + (replaceOriginal ? '1' : '0');
    params += '&custom_name=' + encodeURIComponent(customName);
    params += '&' + cids.map(function(cid) {
        return 'cids[]=' + encodeURIComponent(cid);
    }).join('&');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    var html = '<h4>压缩结果</h4>';
                    html += '<div style="max-height: 200px; overflow-y: auto;">';
                    
                    response.results.forEach(function(result) {
                        if (result.success) {
                            html += '<div style="padding: 10px; margin-bottom: 10px; background: #f0f8ff; border-left: 3px solid #46b450;">';
                            html += '<div style="color: #46b450; font-weight: bold;">✓ 压缩成功 (CID: ' + result.cid + ')</div>';
                            html += '<div>原始大小: ' + result.original_size + ' → 压缩后: ' + result.compressed_size + '</div>';
                            html += '<div>节省空间: ' + result.savings + ' | 方法: ' + result.method + ' | 格式: ' + result.format + '</div>';
                            html += '</div>';
                        } else {
                            html += '<div style="padding: 10px; margin-bottom: 10px; background: #fff2f2; border-left: 3px solid #dc3232;">';
                            html += '<div style="color: #dc3232; font-weight: bold;">✗ 压缩失败 (CID: ' + result.cid + ')</div>';
                            html += '<div>' + result.message + '</div>';
                            html += '</div>';
                        }
                    });
                    
                    html += '</div>';
                    html += '<div style="margin-top: 15px; text-align: center;">';
                    html += '<button class="btn btn-primary" onclick="location.reload()">刷新页面</button>';
                    html += '</div>';
                    
                    if (resultDiv) {
                        resultDiv.innerHTML = html;
                    }
                } else {
                    if (resultDiv) {
                        resultDiv.innerHTML = '<div style="color: red;">✗ 批量压缩失败: ' + response.message + '</div>';
                    }
                }
            } catch (e) {
                if (resultDiv) {
                    resultDiv.innerHTML = '<div style="color: red;">✗ 压缩失败，请重试</div>';
                }
            }
        }
    };
    
    xhr.send(params);
},

    
    startVideoCompress: function() {
        var self = this;
        var selectedVideos = this.selectedItems.filter(function(item) {
            return item.isVideo;
        });
        
        if (selectedVideos.length === 0) {
            alert('请选择要压缩的视频');
            return;
        }
        
        var quality = document.getElementById('video-quality-slider').value;
        var codec = document.getElementById('video-codec').value;
        var replaceMode = document.querySelector('input[name="video-replace-mode"]:checked').value;
        var customName = '';
        
        if (replaceMode === 'keep') {
            customName = document.getElementById('video-custom-name').value;
            if (!customName) {
                alert('请输入自定义文件名后缀');
                return;
            }
        }
        
        if (!confirm('确定要压缩选中的 ' + selectedVideos.length + ' 个视频吗？视频压缩可能需要较长时间。')) {
            return;
        }
        
        // 显示进度
        var modal = document.getElementById('video-compress-modal');
        var progressDiv = document.getElementById('video-compress-progress');
        if (progressDiv) {
            progressDiv.style.display = 'block';
            progressDiv.innerHTML = '<p>正在压缩视频，请耐心等待...</p><div class="progress-bar"><div class="progress-fill" style="width: 0%"></div></div>';
        }
        
        // 禁用按钮
        document.getElementById('start-video-compress').disabled = true;
        document.getElementById('cancel-video-compress').disabled = true;
        
        var cids = selectedVideos.map(function(item) { return item.cid; });
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', currentUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        var params = 'action=compress_videos';
        params += '&quality=' + quality;
        params += '&codec=' + codec;
        params += '&replace_original=' + (replaceMode === 'replace' ? '1' : '0');
        params += '&custom_name=' + encodeURIComponent(customName);
        params += '&' + cids.map(function(cid) {
            return 'cids[]=' + encodeURIComponent(cid);
        }).join('&');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                document.getElementById('start-video-compress').disabled = false;
                document.getElementById('cancel-video-compress').disabled = false;
                
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            if (progressDiv) {
                                progressDiv.innerHTML = '<p style="color: #34a853;">✓ 压缩完成！</p>';
                            }
                            setTimeout(function() {
                                modal.style.display = 'none';
                                location.reload();
                            }, 1500);
                        } else {
                            if (progressDiv) {
                                progressDiv.innerHTML = '<p style="color: #ea4335;">压缩失败：' + response.message + '</p>';
                            }
                        }
                    } catch (e) {
                        if (progressDiv) {
                            progressDiv.innerHTML = '<p style="color: #ea4335;">压缩失败，请重试</p>';
                        }
                    }
                } else {
                    if (progressDiv) {
                        progressDiv.innerHTML = '<p style="color: #ea4335;">压缩失败，请重试</p>';
                    }
                }
            }
        };
        
        xhr.send(params);
    },
    
checkPrivacy: function() {
    var selectedImages = this.selectedItems.filter(function(item) {
        return item.isImage;
    });
    
    if (selectedImages.length === 0) {
        alert('请选择图片文件进行隐私检测');
        return;
    }
    
    var cids = selectedImages.map(function(item) { return item.cid; });
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', currentUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    var params = 'action=check_privacy&' + cids.map(function(cid) {
        return 'cids[]=' + encodeURIComponent(cid);
    }).join('&');
    
    // 添加超时设置
    xhr.timeout = 30000; // 30秒超时
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    var privacyContent = document.getElementById('privacy-content');
                    var privacyModal = document.getElementById('privacy-modal');
                    
                    if (response.success) {
                        var html = '<h4>隐私检测结果</h4>';
                        html += '<div style="max-height: 400px; overflow-y: auto;">';
                        
                        var gpsImages = [];
                        
                        response.results.forEach(function(result) {
                            if (result.success) {
                                html += '<div style="padding: 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;">';
                                html += '<div style="font-weight: bold; margin-bottom: 10px;">' + result.filename + '</div>';
                                html += '<div style="margin-bottom: 10px;">';
                                html += '<span style="color: ' + (result.has_privacy ? '#dc3232' : '#46b450') + ';">';
                                html += result.message;
                                html += '</span>';
                                html += '</div>';
                                
                                if (result.has_privacy && result.privacy_info) {
                                    html += '<div><strong>发现的隐私信息:</strong></div>';
                                    html += '<ul style="margin: 10px 0; padding-left: 20px;">';
                                    
                                    for (var key in result.privacy_info) {
                                        html += '<li style="margin-bottom: 5px;">' + key + ': ' + result.privacy_info[key] + '</li>';
                                    }
                                    
                                    html += '</ul>';
                                    
                                    // 只在有ExifTool时显示清除按钮
                                    if (config.hasExifTool) {
                                        html += '<div style="margin-top: 10px;">';
                                        html += '<button class="btn btn-warning btn-small" onclick="MediaLibrary.removeExif(\'' + result.cid + '\')">清除EXIF信息</button>';
                                        html += '</div>';
                                    } else {
                                        html += '<div style="margin-top: 10px; color: #999; font-size: 12px;">';
                                        html += '需要安装 ExifTool 库才能清除EXIF信息';
                                        html += '</div>';
                                    }
                                    
                                    // GPS地图数据收集保持不变
                                    if (result.gps_coords && result.image_url) {
                                        gpsImages.push({
                                            cid: result.cid,
                                            title: result.filename,
                                            coords: result.gps_coords,
                                            image: result.image_url
                                        });
                                    }
                                }
                                html += '</div>';
                            } else {
                                html += '<div style="padding: 15px; margin-bottom: 15px; border: 1px solid #dc3232; border-radius: 4px; background: #fff2f2;">';
                                html += '<div style="color: #dc3232; font-weight: bold;">检测失败 (CID: ' + result.cid + ')</div>';
                                html += '<div>' + result.message + '</div>';
                                html += '</div>';
                            }
                        });
                        
                        html += '</div>';
                        
                        // 如果有GPS数据，显示地图按钮
                        if (gpsImages.length > 0) {
                            html += '<div style="text-align: center; margin: 20px 0; padding: 15px; background: #e8f4fd; border-radius: 4px;">';
                            html += '<div style="margin-bottom: 10px; font-weight: bold; color: #1976d2;">发现 ' + gpsImages.length + ' 张图片包含GPS位置信息</div>';
                            html += '<button class="btn btn-primary" onclick="MediaLibrary.showGPSMap(' + JSON.stringify(gpsImages).replace(/"/g, '&quot;') + ')">在地图上查看位置</button>';
                            html += '</div>';
                        }
                        
                        html += '<div style="color: #d63638; font-size: 12px; margin-top: 15px; text-align: center;">';
                        html += '⚠️ 建议在发布前清除包含隐私信息的图片的EXIF数据';
                        html += '</div>';
                        
                        privacyContent.innerHTML = html;
                        privacyModal.style.display = 'flex';
                    } else {
                        alert('隐私检测失败：' + response.message);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text:', xhr.responseText);
                    alert('隐私检测失败，服务器响应格式错误');
                }
            } else if (xhr.status === 500) {
                alert('服务器内部错误（500），请检查服务器日志');
                console.error('Server error 500, response:', xhr.responseText);
            } else {
                alert('隐私检测失败，HTTP状态码：' + xhr.status);
                console.error('HTTP error:', xhr.status, xhr.responseText);
            }
        }
    };
    
    xhr.ontimeout = function() {
        alert('请求超时，请重试');
    };
    
    xhr.onerror = function() {
        alert('网络错误，请检查网络连接');
    };
    
    xhr.send(params);
},

// 缓存刷新方法
refreshCache: function() {
    var self = this;
    var btn = document.getElementById('cache-refresh-btn');

    if (!confirm('确定要刷新所有缓存吗？\n\n这将重新生成类型统计、文件详情、EXIF隐私检测和智能压缩建议等缓存数据。\n\n首次刷新可能需要较长时间，具体取决于媒体库文件数量。')) {
        return;
    }

    // 禁用按钮
    if (btn) {
        btn.disabled = true;
        btn.textContent = '刷新中...';
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', currentUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.timeout = 300000; // 5分钟超时

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<svg style="width:14px;height:14px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24"><path fill="currentColor" d="M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z" /></svg>刷新缓存';
            }

            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        self.showFeedback('缓存刷新成功！已刷新: ' + (response.refreshed ? response.refreshed.join(', ') : '所有缓存'), 'success');

                        // 刷新页面以使用新缓存
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        self.showFeedback('缓存刷新失败: ' + response.message, 'error');
                    }
                } catch (e) {
                    self.showFeedback('缓存刷新失败: 无法解析服务器响应', 'error');
                }
            } else {
                self.showFeedback('缓存刷新失败: 网络错误 (HTTP ' + xhr.status + ')', 'error');
            }
        }
    };

    xhr.ontimeout = function() {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg style="width:14px;height:14px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24"><path fill="currentColor" d="M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z" /></svg>刷新缓存';
        }
        self.showFeedback('缓存刷新超时，请稍后重试', 'error');
    };

    xhr.onerror = function() {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg style="width:14px;height:14px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24"><path fill="currentColor" d="M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z" /></svg>刷新缓存';
        }
        self.showFeedback('网络错误，请检查网络连接', 'error');
    };

    xhr.send('action=cache_refresh&type=all');
},


// 在 checkPrivacy 方法后添加以下方法：

displayPrivacyResults: function(results) {
    var resultsDiv = document.getElementById('privacy-results');
    if (!resultsDiv) return;
    
    var html = '';
    
    results.forEach(function(result) {
        var hasPrivacy = result.has_gps || result.has_camera_info || result.has_datetime;
        
        html += '<div class="privacy-item' + (hasPrivacy ? ' has-privacy' : '') + '">';
        html += '<h5>' + result.filename + '</h5>';
        html += '<div class="privacy-info">';
        
        if (hasPrivacy) {
            if (result.has_gps) {
                html += '<p><strong>GPS位置：</strong>纬度 ' + result.gps.latitude.toFixed(6) + ', 经度 ' + result.gps.longitude.toFixed(6) + '</p>';
            }
            if (result.has_camera_info) {
                html += '<p><strong>相机信息：</strong>' + result.camera_info + '</p>';
            }
            if (result.has_datetime) {
                html += '<p><strong>拍摄时间：</strong>' + result.datetime + '</p>';
            }
            
            html += '<div class="privacy-actions">';
            html += '<button type="button" class="btn-warning" onclick="MediaLibrary.removeExif(\'' + result.cid + '\')">清除EXIF信息</button>';
            html += '</div>';
        } else {
            html += '<p style="color: #34a853;">✓ 未检测到隐私信息</p>';
        }
        
        html += '</div>';
        html += '</div>';
    });
    
    resultsDiv.innerHTML = html;
},

removeExif: function(cid) {
    if (!confirm('确定要清除这个图片的EXIF信息吗？')) {
        return;
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', currentUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('EXIF信息已清除');
                        // 延迟2秒后重新检测，确保文件系统更新
                        setTimeout(function() {
                            MediaLibrary.checkPrivacy();
                        }, 2000);
                    } else {
                        alert('清除失败：' + response.message);
                    }
                } catch (e) {
                    alert('清除失败，请重试');
                }
            } else {
                alert('清除失败，请重试');
            }
        }
    };
    
    xhr.send('action=remove_exif&cid=' + cid);
},


showGPSMap: function(gpsImages) {
    var gpsMapModal = document.getElementById('gps-map-modal');
    var gpsMapContainer = document.getElementById('gps-map-container');
    
    if (!gpsMapModal || !gpsMapContainer) {
        alert('地图组件未找到');
        return;
    }
    
    // 显示模态框
    gpsMapModal.style.display = 'flex';
    
    // 加载地图
    this.initGPSMap(gpsMapContainer, gpsImages);
},

initGPSMap: function(container, gpsImages) {
    // 检查ECharts是否已加载
    if (typeof echarts === 'undefined') {
        alert('ECharts未加载，无法显示地图');
        return;
    }
    
    var myChart = echarts.init(container);
    
    // 加载中国地图数据
    var geoJsonUrl = config.pluginUrl + '/assets/geo/china.json';
    
    fetch(geoJsonUrl)
        .then(function(response) { return response.json(); })
        .then(function(geoJson) {
            echarts.registerMap('china', geoJson);
            
            // 计算地图中心点
            var centerLng = 0, centerLat = 0;
            gpsImages.forEach(function(item) {
                centerLng += item.coords[0];
                centerLat += item.coords[1];
            });
            centerLng /= gpsImages.length;
            centerLat /= gpsImages.length;
            
            var option = {
                title: {
                    text: '图片GPS位置分布',
                    left: 'center',
                    textStyle: {
                        color: '#333',
                        fontSize: 18
                    }
                },
                tooltip: {
                    trigger: 'item',
                    formatter: function(params) {
                        var data = params.data;
                        if (data && data.title) {
                            var html = '<div style="max-width: 300px;">';
                            if (data.image) {
                                html += '<img src="' + data.image + '" style="width: 100%; max-width: 200px; border-radius: 4px; margin-bottom: 8px;">';
                            }
                            html += '<div style="font-weight: bold; margin-bottom: 4px;">' + data.title + '</div>';
                            html += '<div style="font-size: 12px; color: #666;">经度: ' + data.coords[0].toFixed(6) + '</div>';
                            html += '<div style="font-size: 12px; color: #666;">纬度: ' + data.coords[1].toFixed(6) + '</div>';
                            html += '</div>';
                            return html;
                        }
                        return params.name;
                    }
                },
                geo: {
                    map: 'china',
                    roam: true,
                    center: [centerLng, centerLat],
                    zoom: gpsImages.length === 1 ? 8 : 5,
                    scaleLimit: {
                        min: 1,
                        max: 20
                    },
                    itemStyle: {
                        areaColor: '#f0f0f0',
                        borderColor: '#999'
                    },
                    emphasis: {
                        itemStyle: {
                            areaColor: '#e0e0e0'
                        }
                    }
                },
                series: [{
                    name: 'GPS位置',
                    type: 'scatter',
                    coordinateSystem: 'geo',
                    data: gpsImages.map(function(item) {
                        return {
                            name: item.title,
                            value: item.coords,
                            title: item.title,
                            coords: item.coords,
                            image: item.image,
                            cid: item.cid
                        };
                    }),
                    symbolSize: 20,
                    itemStyle: {
                        color: '#ff4444',
                        shadowBlur: 10,
                        shadowColor: 'rgba(255, 68, 68, 0.5)'
                    },
                    emphasis: {
                        itemStyle: {
                            color: '#ff0000',
                            shadowBlur: 20
                        }
                    }
                }]
            };
            
            myChart.setOption(option);
            
            // 窗口大小改变时重新调整图表
            window.addEventListener('resize', function() {
                myChart.resize();
            });
        })
        .catch(function(error) {
            console.error('加载地图数据失败:', error);
            container.innerHTML = '<div style="text-align: center; padding: 50px; color: #999;">地图数据加载失败</div>';
        });
},

// HTML 转义
escapeHtml: function(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
},

    initUpload: function() {
        var self = this;
        var storageInputs = Array.prototype.slice.call(document.querySelectorAll('input[name="upload-storage"]'));
        var storageHint = document.getElementById('upload-storage-current-label');

        var getStorageLabel = function(input, fallbackValue) {
            if (input && input.getAttribute) {
                var customLabel = input.getAttribute('data-label');
                if (customLabel) {
                    return customLabel;
                }
            }
            var value = fallbackValue || (input ? input.value : '');
            return value === 'webdav' ? 'WebDAV' : '本地存储';
        };

        var initialCheckedInput = document.querySelector('input[name="upload-storage"]:checked:not(:disabled)');
        if (!initialCheckedInput) {
            for (var i = 0; i < storageInputs.length; i++) {
                if (!storageInputs[i].disabled) {
                    initialCheckedInput = storageInputs[i];
                    initialCheckedInput.checked = true;
                    break;
                }
            }
        }

        if (initialCheckedInput) {
            currentStorage = initialCheckedInput.value;
        } else {
            currentStorage = currentStorage || 'local';
        }

        if (storageHint) {
            storageHint.textContent = getStorageLabel(initialCheckedInput, currentStorage);
        }

        var buildUploadUrl = function(targetStorage) {
            var value = targetStorage || 'local';
            return currentUrl + '&action=upload&storage=' + value;
        };

        var uploader = new plupload.Uploader({
            browse_button: 'upload-file-btn',
            url: buildUploadUrl(currentStorage),
            runtimes: 'html5,flash,html4',
            flash_swf_url: config.adminStaticUrl + 'Moxie.swf',
            drop_element: 'upload-area',
        filters: {
            max_file_size: config.phpMaxFilesize || '2mb',
            mime_types: [{
                'title': '允许上传的文件',
                'extensions': config.allowedTypes || 'jpg,jpeg,png,gif,bmp,webp,svg,mp4,avi,mov,wmv,flv,mp3,wav,ogg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,avif'
            }],
            prevent_duplicates: true
        },
        multi_selection: true,

        init: {
            FilesAdded: function(up, files) {
                // 自动显示上传模态框
                var uploadModal = document.getElementById('upload-modal');
                if (uploadModal) {
                    uploadModal.style.display = 'flex';
                }
                
                var fileList = document.getElementById('file-list');
                if (fileList) {
                    fileList.innerHTML = '';
                }
                
                plupload.each(files, function(file) {
                    var li = document.createElement('li');
                    li.id = file.id;
                    li.className = 'loading';
                    li.style.padding = '10px';
                    li.style.borderBottom = '1px solid #eee';
                    li.style.position = 'relative';
                    
                    li.innerHTML = '<div class="file-info">' +
                        '<div class="file-name">' + file.name + '</div>' +
                        '<div class="file-size">(' + plupload.formatSize(file.size) + ')</div>' +
                        '<div class="progress-bar" style="width: 100%; height: 4px; background: #f0f0f0; border-radius: 2px; margin-top: 5px;">' +
                        '<div class="progress-fill" style="width: 0%; height: 100%; background: #007cba; border-radius: 2px; transition: width 0.3s;"></div>' +
                        '</div>' +
                        '<div class="status">等待上传...</div>' +
                        '</div>';
                    
                    if (fileList) {
                        fileList.appendChild(li);
                    }
                });

                uploader.start();
            },

            UploadProgress: function(up, file) {
                var li = document.getElementById(file.id);
                if (li) {
                    var progressFill = li.querySelector('.progress-fill');
                    var status = li.querySelector('.status');
                    if (progressFill) {
                        progressFill.style.width = file.percent + '%';
                    }
                    if (status) {
                        status.textContent = '上传中... ' + file.percent + '%';
                    }
                }
            },

            FileUploaded: function(up, file, result) {
                var li = document.getElementById(file.id);
                if (li) {
                    var status = li.querySelector('.status');
                    var progressFill = li.querySelector('.progress-fill');
                    
                    if (200 === result.status) {
                        var parsedData;
                        var parsedSuccessfully = true;
                        try {
                            parsedData = JSON.parse(result.response);
                        } catch (error) {
                            parsedSuccessfully = false;
                            li.className = 'error';
                            if (status) status.textContent = '上传失败: 响应解析错误';
                            if (progressFill) progressFill.style.background = '#dc3232';
                        }

                        if (parsedSuccessfully) {
                            var uploadSuccess = false;
                            var uploadMessage = '';

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

                            if (uploadSuccess) {
                                li.className = 'success';
                                if (status) status.textContent = uploadMessage || '上传成功';
                                if (progressFill) progressFill.style.background = '#46b450';
                            } else {
                                li.className = 'error';
                                if (status) status.textContent = uploadMessage || '上传失败: 服务器响应异常';
                                if (progressFill) progressFill.style.background = '#dc3232';
                            }
                        }
                    } else {
                        li.className = 'error';
                        if (status) status.textContent = '上传失败: HTTP ' + result.status;
                        if (progressFill) progressFill.style.background = '#dc3232';
                    }
                }
                
                uploader.removeFile(file);
            },

            UploadComplete: function(up, files) {
                setTimeout(function() {
                    var uploadModal = document.getElementById('upload-modal');
                    if (uploadModal) {
                        uploadModal.style.display = 'none';
                    }
                    
                    var successCount = document.querySelectorAll('#file-list .success').length;
                    if (successCount > 0) {
                        // 创建并显示弹幕
                        var toast = document.createElement('div');
                        toast.style.cssText = `
                            position: fixed;
                            top: 20px;
                            right: 20px;
                            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
                            color: white;
                            padding: 15px 25px;
                            border-radius: 8px;
                            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
                            font-size: 14px;
                            font-weight: 500;
                            z-index: 10000;
                            opacity: 0;
                            transform: translateX(100%);
                            transition: all 0.3s ease-in-out;
                            max-width: 300px;
                        `;
                        
                        toast.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 16px;">✅</span>
                                <span>上传完成！成功上传 ${successCount} 个文件</span>
                            </div>
                        `;
                        
                        document.body.appendChild(toast);
                        
                        // 显示动画
                        setTimeout(function() {
                            toast.style.opacity = '1';
                            toast.style.transform = 'translateX(0)';
                        }, 100);
                        
                        // 自动消失并刷新页面
                        setTimeout(function() {
                            toast.style.opacity = '0';
                            toast.style.transform = 'translateX(100%)';
                            
                            setTimeout(function() {
                                if (toast.parentNode) {
                                    toast.parentNode.removeChild(toast);
                                }
                                location.reload();
                            }, 300);
                        }, 800);
                    }
                }, 1000);
            },

            Error: function(up, error) {
                var fileList = document.getElementById('file-list');
                var li = document.createElement('li');
                li.className = 'error';
                li.style.padding = '10px';
                li.style.borderBottom = '1px solid #eee';
                li.style.color = 'red';
                
                var word = '';
                switch (error.code) {
                    case plupload.FILE_SIZE_ERROR:
                        word = '文件大小超过限制';
                        break;
                    case plupload.FILE_EXTENSION_ERROR:
                        word = '文件扩展名不被支持';
                        break;
                    case plupload.FILE_DUPLICATE_ERROR:
                        word = '文件已经上传过';
                        break;
                    case plupload.HTTP_ERROR:
                    default:
                        word = '上传出现错误';
                        break;
                }
                
                li.innerHTML = '<div class="file-info">' +
                    '<div class="file-name">' + (error.file ? error.file.name : '未知文件') + '</div>' +
                    '<div class="status">' + word + '</div>' +
                    '</div>';
                
                if (fileList) {
                    fileList.appendChild(li);
                }
                
                if (error.file) {
                    up.removeFile(error.file);
                }
            }
        }
    });

    uploader.init();

    var syncUploadStorage = function(targetInput) {
        var storageValue = targetInput ? targetInput.value : 'local';
        var labelText = getStorageLabel(targetInput, storageValue);
        currentStorage = storageValue;

        if (typeof uploader.setOption === 'function') {
            uploader.setOption('url', buildUploadUrl(storageValue));
        } else if (uploader.settings) {
            uploader.settings.url = buildUploadUrl(storageValue);
        }

        if (storageHint) {
            storageHint.textContent = labelText;
        }
    };

    if (initialCheckedInput) {
        syncUploadStorage(initialCheckedInput);
    } else {
        syncUploadStorage(null);
    }

    storageInputs.forEach(function(input) {
        if (input.disabled) {
            return;
        }
        input.addEventListener('change', function() {
            if (this.checked) {
                syncUploadStorage(this);
            }
        });
    });
    
    // 全页面拖拽监听
    var dragCounter = 0;
    var dragOverlay = null;
    
    // 创建拖拽覆盖层
    function createDragOverlay() {
        if (dragOverlay) return dragOverlay;
        
        dragOverlay = document.createElement('div');
        dragOverlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 123, 186, 0.1);
            backdrop-filter: blur(2px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
            pointer-events: none;
        `;
        
        dragOverlay.innerHTML = `
            <div style="
                background: rgba(0, 123, 186, 0.9);
                color: white;
                padding: 40px 60px;
                border-radius: 12px;
                text-align: center;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                border: 3px dashed rgba(255, 255, 255, 0.5);
            ">
                <div style="font-size: 48px; margin-bottom: 16px;">📁</div>
                <div style="font-size: 24px; font-weight: bold; margin-bottom: 8px;">拖拽文件到此处</div>
                <div style="font-size: 14px; opacity: 0.8;">松开鼠标开始上传</div>
            </div>
        `;
        
        document.body.appendChild(dragOverlay);
        return dragOverlay;
    }
    
    // 显示拖拽覆盖层
    function showDragOverlay() {
        var overlay = createDragOverlay();
        overlay.style.pointerEvents = 'auto';
        setTimeout(function() {
            overlay.style.opacity = '1';
        }, 10);
    }
    
    // 隐藏拖拽覆盖层
    function hideDragOverlay() {
        if (dragOverlay) {
            dragOverlay.style.opacity = '0';
            dragOverlay.style.pointerEvents = 'none';
        }
    }
    
    // 全页面拖拽事件
    document.addEventListener('dragenter', function(e) {
        e.preventDefault();
        dragCounter++;
        
        // 检查是否是文件拖拽
        if (e.dataTransfer && e.dataTransfer.types) {
            var hasFiles = false;
            for (var i = 0; i < e.dataTransfer.types.length; i++) {
                if (e.dataTransfer.types[i] === 'Files') {
                    hasFiles = true;
                    break;
                }
            }
            
            if (hasFiles && dragCounter === 1) {
                showDragOverlay();
            }
        }
    });
    
    document.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    });
    
    document.addEventListener('dragleave', function(e) {
        e.preventDefault();
        dragCounter--;
        
        if (dragCounter === 0) {
            hideDragOverlay();
        }
    });
    
    document.addEventListener('drop', function(e) {
        e.preventDefault();
        dragCounter = 0;
        hideDragOverlay();
        
        // 检查是否在上传区域外拖拽
        var uploadArea = document.getElementById('upload-area');
        var isInUploadArea = uploadArea && uploadArea.contains(e.target);
        
        if (!isInUploadArea && e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            // 在页面其他地方拖拽文件，添加到上传队列
            var files = Array.from(e.dataTransfer.files);
            
            // 验证文件类型和大小
            var validFiles = [];
            var allowedExtensions = (config.allowedTypes || 'jpg,jpeg,png,gif,bmp,webp,svg,mp4,avi,mov,wmv,flv,mp3,wav,ogg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,avif').split(',');
            var maxSize = self.parseSize(config.phpMaxFilesize || '2mb');
            
            files.forEach(function(file) {
                var ext = file.name.split('.').pop().toLowerCase();
                if (allowedExtensions.indexOf(ext) !== -1 && file.size <= maxSize) {
                    validFiles.push(file);
                }
            });
            
            if (validFiles.length > 0) {
                // 添加文件到上传队列
                validFiles.forEach(function(file) {
                    uploader.addFile(file);
                });
            } else {
                // 显示错误提示
                var errorToast = document.createElement('div');
                errorToast.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
                    color: white;
                    padding: 15px 25px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(244, 67, 54, 0.3);
                    font-size: 14px;
                    font-weight: 500;
                    z-index: 10000;
                    opacity: 0;
                    transform: translateX(100%);
                    transition: all 0.3s ease-in-out;
                    max-width: 300px;
                `;
                
                errorToast.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 16px;">❌</span>
                        <span>文件类型不支持或文件过大</span>
                    </div>
                `;
                
                document.body.appendChild(errorToast);
                
                setTimeout(function() {
                    errorToast.style.opacity = '1';
                    errorToast.style.transform = 'translateX(0)';
                }, 100);
                
                setTimeout(function() {
                    errorToast.style.opacity = '0';
                    errorToast.style.transform = 'translateX(100%)';
                    
                    setTimeout(function() {
                        if (errorToast.parentNode) {
                            errorToast.parentNode.removeChild(errorToast);
                        }
                    }, 300);
                }, 3000);
            }
        }
    });
    
    // 拖拽区域事件（保持原有功能）
    var uploadArea = document.getElementById('upload-area');
    if (uploadArea) {
        uploadArea.addEventListener('dragenter', function(e) {
            e.stopPropagation();
            this.classList.add('dragover');
        });

        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('dragover');
        });

        uploadArea.addEventListener('drop', function(e) {
            e.stopPropagation();
            this.classList.remove('dragover');
        });

        uploadArea.addEventListener('dragend', function(e) {
            e.stopPropagation();
            this.classList.remove('dragover');
        });

        uploadArea.addEventListener('dragleave', function(e) {
            e.stopPropagation();
            this.classList.remove('dragover');
        });
    }
},

// 添加辅助函数解析文件大小
parseSize: function(size) {
    if (typeof size === 'number') return size;
    
    var units = {
        'b': 1,
        'kb': 1024,
        'mb': 1024 * 1024,
        'gb': 1024 * 1024 * 1024
    };
    
    var match = size.toString().toLowerCase().match(/^(\d+(?:\.\d+)?)\s*([kmg]?b)$/);
    if (match) {
        return parseFloat(match[1]) * (units[match[2]] || 1);
    }
    
    return 2 * 1024 * 1024; // 默认2MB
}

};

// 加载图像编辑器脚本
(function() {
    var script = document.createElement('script');
    script.src = window.mediaLibraryConfig.pluginUrl + '/assets/js/image-editor.js';
    script.type = 'text/javascript';
    document.getElementsByTagName('head')[0].appendChild(script);
})();


// 初始化
document.addEventListener('DOMContentLoaded', function() {
    MediaLibrary.init();
    if (window.WebDAVManager) {
        WebDAVManager.init();
    }

    // 初始化侧栏功能
    initSidebar();
});

// 侧栏功能初始化
function initSidebar() {
    // 移动端侧栏折叠功能
    if (window.innerWidth <= 768) {
        var sidebarSections = document.querySelectorAll('.sidebar-section');

        sidebarSections.forEach(function(section, index) {
            var title = section.querySelector('.sidebar-title');
            var content = section.querySelector('.sidebar-content');

            if (title && content) {
                // 默认展开文件类型筛选，其他折叠
                if (index !== 2) { // 第三个section是文件类型筛选
                    content.style.display = 'none';
                    title.classList.add('collapsed');
                }

                // 添加点击事件
                title.addEventListener('click', function() {
                    if (content.style.display === 'none') {
                        content.style.display = 'block';
                        title.classList.remove('collapsed');
                    } else {
                        content.style.display = 'none';
                        title.classList.add('collapsed');
                    }
                });

                // 添加折叠指示器
                if (!title.querySelector('.toggle-icon')) {
                    var icon = document.createElement('span');
                    icon.className = 'toggle-icon';
                    icon.innerHTML = '▼';
                    title.appendChild(icon);
                }
            }
        });
    }

    // 监听窗口大小变化
    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // 桌面端时重置所有内容为可见
            if (window.innerWidth > 768) {
                var contents = document.querySelectorAll('.sidebar-content');
                contents.forEach(function(content) {
                    content.style.display = 'block';
                });

                var titles = document.querySelectorAll('.sidebar-title');
                titles.forEach(function(title) {
                    title.classList.remove('collapsed');
                });
            }
        }, 250);
    });

    // 高亮当前筛选项
    highlightActiveFilter();
}

// 高亮当前激活的筛选项
function highlightActiveFilter() {
    var currentType = window.mediaLibraryType || 'all';
    var filterItems = document.querySelectorAll('.filter-item');

    filterItems.forEach(function(item) {
        var link = item.querySelector('.filter-link');
        if (link && link.href.indexOf('type=' + currentType) > -1) {
            item.classList.add('active');
        }
    });
}

// WebDAV 管理器
var WebDAVManager = {
    currentPath: '/',
    isLoading: false,

    init: function() {
        if (!window.mediaLibraryConfig.enableWebDAV) {
            return;
        }

        this.bindEvents();

        // 如果已配置，自动加载根目录
        if (window.mediaLibraryConfig.webdavConfigured) {
            this.loadDirectory('/');
        }
    },

    bindEvents: function() {
        var self = this;

        // 刷新按钮
        jQuery('#webdav-refresh').on('click', function() {
            self.loadDirectory(self.currentPath);
        });

        // 上一级按钮
        jQuery('#webdav-up').on('click', function() {
            self.goUp();
        });

        // 新建文件夹按钮
        jQuery('#webdav-new-folder').on('click', function() {
            self.showCreateFolderDialog();
        });

        // 上传文件
        jQuery('#webdav-upload-input').on('change', function(e) {
            var files = e.target.files;
            if (files.length > 0) {
                self.uploadFiles(files);
            }
        });

        // 批量同步按钮（主面板）
        jQuery('#webdav-sync-all').on('click', function() {
            self.syncAllToWebDAV();
        });

        // 批量同步按钮（侧边栏）
        jQuery('#webdav-sync-all-btn').on('click', function() {
            self.syncAllToWebDAV();
        });

        // 测试连接按钮（侧边栏）
        jQuery('#webdav-test-connection-btn').on('click', function() {
            self.testConnection();
        });
    },

    loadDirectory: function(path) {
        var self = this;

        if (this.isLoading) {
            return;
        }

        this.isLoading = true;
        this.showFeedback('正在加载...', 'info');

        jQuery.ajax({
            url: window.location.href,
            type: 'GET',
            data: {
                action: 'webdav_list',
                path: path
            },
            dataType: 'json',
            success: function(response) {
                self.isLoading = false;
                if (response.success) {
                    self.currentPath = response.data.current_path;
                    self.renderFileList(response.data.items);
                    self.updatePathDisplay();
                    self.showFeedback('');
                } else {
                    self.showFeedback(response.message || '加载失败', 'error');
                }
            },
            error: function() {
                self.isLoading = false;
                self.showFeedback('网络错误', 'error');
            }
        });
    },

    renderFileList: function(items) {
        var listEl = jQuery('#webdav-list');

        if (!items || items.length === 0) {
            listEl.html('<div class="webdav-empty">此目录为空</div>');
            return;
        }

        var html = '<table class="webdav-table"><thead><tr>' +
            '<th>名称</th>' +
            '<th>大小</th>' +
            '<th>修改时间</th>' +
            '<th>操作</th>' +
            '</tr></thead><tbody>';

        items.forEach(function(item) {
            var icon = item.is_dir ? '📁' : '📄';
            var nameHtml = item.is_dir
                ? '<a href="#" class="webdav-dir-link" data-path="' + item.path + '">' + icon + ' ' + item.name + '</a>'
                : '<span>' + icon + ' ' + item.name + '</span>';

            html += '<tr>' +
                '<td class="webdav-name">' + nameHtml + '</td>' +
                '<td>' + item.size_human + '</td>' +
                '<td>' + (item.modified || '-') + '</td>' +
                '<td class="webdav-actions">';

            if (!item.is_dir) {
                html += '<button class="btn btn-xs webdav-btn-sync" data-path="' + item.path + '" data-name="' + item.name + '" title="同步到本地">同步</button>';
                if (item.public_url) {
                    html += '<button class="btn btn-xs webdav-btn-copy-url" data-url="' + item.public_url + '" title="复制链接">复制链接</button>';
                }
            }

            html += '<button class="btn btn-xs webdav-btn-delete" data-path="' + item.path + '" data-name="' + item.name + '" title="删除">删除</button>' +
                '</td></tr>';
        });

        html += '</tbody></table>';
        listEl.html(html);

        // 绑定目录点击事件
        listEl.find('.webdav-dir-link').on('click', function(e) {
            e.preventDefault();
            var path = jQuery(this).data('path');
            WebDAVManager.loadDirectory(path);
        });

        // 绑定删除按钮
        listEl.find('.webdav-btn-delete').on('click', function() {
            var path = jQuery(this).data('path');
            var name = jQuery(this).data('name');
            WebDAVManager.deleteItem(path, name);
        });

        // 绑定复制链接按钮
        listEl.find('.webdav-btn-copy-url').on('click', function() {
            var url = jQuery(this).data('url');
            WebDAVManager.copyToClipboard(url);
        });

        // 绑定同步按钮
        listEl.find('.webdav-btn-sync').on('click', function() {
            var path = jQuery(this).data('path');
            var name = jQuery(this).data('name');
            WebDAVManager.syncToLocal(path, name);
        });
    },

    updatePathDisplay: function() {
        jQuery('#webdav-current-path').text(this.currentPath);
    },

    goUp: function() {
        if (this.currentPath === '/') {
            this.showFeedback('已在根目录', 'info');
            return;
        }

        var segments = this.currentPath.split('/').filter(function(s) { return s !== ''; });
        segments.pop();
        var newPath = segments.length > 0 ? '/' + segments.join('/') : '/';
        this.loadDirectory(newPath);
    },

    showCreateFolderDialog: function() {
        var name = prompt('请输入文件夹名称：');
        if (name && name.trim() !== '') {
            this.createFolder(name.trim());
        }
    },

    createFolder: function(name) {
        var self = this;

        this.showFeedback('正在创建...', 'info');

        jQuery.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'webdav_create_folder',
                path: self.currentPath,
                name: name
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    self.showFeedback('创建成功', 'success');
                    self.loadDirectory(self.currentPath);
                } else {
                    self.showFeedback(response.message || '创建失败', 'error');
                }
            },
            error: function() {
                self.showFeedback('网络错误', 'error');
            }
        });
    },

    deleteItem: function(path, name) {
        if (!confirm('确定要删除 "' + name + '" 吗？')) {
            return;
        }

        var self = this;
        this.showFeedback('正在删除...', 'info');

        jQuery.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'webdav_delete',
                target: path
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    self.showFeedback('删除成功', 'success');
                    self.loadDirectory(self.currentPath);
                } else {
                    self.showFeedback(response.message || '删除失败', 'error');
                }
            },
            error: function() {
                self.showFeedback('网络错误', 'error');
            }
        });
    },

    uploadFiles: function(files) {
        var self = this;
        var formData = new FormData();

        for (var i = 0; i < files.length; i++) {
            formData.append('file[]', files[i]);
        }

        formData.append('action', 'webdav_upload');
        formData.append('path', this.currentPath);

        this.showFeedback('正在上传...', 'info');

        jQuery.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    self.showFeedback('上传成功', 'success');
                    self.loadDirectory(self.currentPath);
                    // 清空文件选择
                    jQuery('#webdav-upload-input').val('');
                } else {
                    self.showFeedback(response.message || '上传失败', 'error');
                }
            },
            error: function() {
                self.showFeedback('网络错误', 'error');
            }
        });
    },

    copyToClipboard: function(text) {
        var self = this;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                self.showFeedback('链接已复制到剪贴板', 'success');
            }).catch(function() {
                self.fallbackCopy(text);
            });
        } else {
            self.fallbackCopy(text);
        }
    },

    fallbackCopy: function(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            this.showFeedback('链接已复制到剪贴板', 'success');
        } catch (err) {
            this.showFeedback('复制失败，请手动复制', 'error');
        }

        document.body.removeChild(textarea);
    },

    showFeedback: function(message, type) {
        var feedbackEl = jQuery('#webdav-feedback');

        if (!message) {
            feedbackEl.text('').removeClass('webdav-feedback-success webdav-feedback-error webdav-feedback-info');
            return;
        }

        feedbackEl.text(message)
            .removeClass('webdav-feedback-success webdav-feedback-error webdav-feedback-info');

        if (type === 'success') {
            feedbackEl.addClass('webdav-feedback-success');
        } else if (type === 'error') {
            feedbackEl.addClass('webdav-feedback-error');
        } else {
            feedbackEl.addClass('webdav-feedback-info');
        }

        // 成功和信息类消息3秒后自动消失
        if (type === 'success' || type === 'info') {
            setTimeout(function() {
                if (feedbackEl.text() === message) {
                    feedbackEl.text('').removeClass('webdav-feedback-success webdav-feedback-error webdav-feedback-info');
                }
            }, 3000);
        }
    },

    normalizeSyncStats: function(rawStats) {
        rawStats = rawStats || {};

        var toNumber = function(value, fallback) {
            if (typeof value === 'number' && !isNaN(value)) {
                return value;
            }
            if (typeof value === 'string' && value.trim() !== '') {
                var parsed = parseInt(value, 10);
                if (!isNaN(parsed)) {
                    return parsed;
                }
            }
            return fallback || 0;
        };

        var stats = {
            synced: toNumber(rawStats.synced, toNumber(rawStats.success, 0)),
            failed: toNumber(rawStats.failed, 0),
            skipped: toNumber(rawStats.skipped, toNumber(rawStats.skip, toNumber(rawStats.skippedCount, 0))),
            renamed: toNumber(rawStats.renamed, toNumber(rawStats.rename, 0))
        };

        var total = toNumber(rawStats.total, stats.synced + stats.failed + stats.skipped);
        stats.total = total;

        return stats;
    },

    syncToLocal: function(path, name) {
        if (!confirm('确定要将 "' + name + '" 同步到本地媒体库吗？')) {
            return;
        }

        var self = this;
        this.showFeedback('正在同步...', 'info');

        jQuery.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'webdav_sync_to_local',
                path: path
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    self.showFeedback('同步成功！文件已添加到媒体库', 'success');
                    // 3秒后刷新页面以显示新添加的文件
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    self.showFeedback(response.message || '同步失败', 'error');
                }
            },
            error: function() {
                self.showFeedback('网络错误', 'error');
            }
        });
    },

    syncAllToWebDAV: function() {
        if (!confirm('确定要将所有本地文件同步到 WebDAV 吗？\n\n这可能需要一些时间，请耐心等待。')) {
            return;
        }

        var self = this;
        var progressBar = jQuery('#webdav-sync-progress');
        var progressFill = jQuery('#webdav-sync-progress-fill');
        var progressText = jQuery('#webdav-sync-progress-text');

        // 侧边栏进度元素
        var sidebarProgress = jQuery('#webdav-sync-progress');
        var sidebarProgressBar = sidebarProgress.find('.progress-bar');
        var sidebarProgressCount = sidebarProgress.find('.progress-count');
        var sidebarProgressDetail = sidebarProgress.find('.progress-detail');
        var sidebarResult = jQuery('#webdav-sync-result');
        var syncButton = jQuery('#webdav-sync-all-btn');

        // 显示进度条
        if (progressBar.length) {
            progressBar.show();
            progressFill.css('width', '0%');
            progressText.text('开始同步...');
        }

        // 显示侧边栏进度
        if (sidebarProgress.length) {
            sidebarProgress.show();
            sidebarResult.hide();
            sidebarProgressBar.css('width', '0%');
            sidebarProgressCount.text('0/0');
            sidebarProgressDetail.text('准备同步...');
            syncButton.prop('disabled', true).find('.btn-text').text('同步中...');
        }

        jQuery.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'webdav_sync_all_local'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var stats = self.normalizeSyncStats(response.stats);

                    // 更新主进度条
                    if (progressBar.length) {
                        progressFill.css('width', '100%');
                        progressText.text(response.message);
                    }

                    // 更新侧边栏进度
                    if (sidebarProgress.length) {
                        sidebarProgressBar.css('width', '100%');
                        var completedTotal = stats.synced + stats.failed + stats.skipped;
                        var totalCount = stats.total > 0 ? stats.total : completedTotal;
                        var displayValue = totalCount || completedTotal || 0;
                        sidebarProgressCount.text(displayValue + '/' + displayValue);
                        sidebarProgressDetail.text('同步完成');

                        // 显示结果
                        setTimeout(function() {
                            sidebarProgress.hide();
                            sidebarResult.removeClass('success error').addClass('success').show();
                            sidebarResult.find('.result-icon').text('✓');
                            sidebarResult.find('.result-title').text('同步成功');

                            var sidebarDetails = '已同步 ' + stats.synced + ' | ' +
                                                '跳过: ' + stats.skipped;
                            if (stats.renamed) {
                                sidebarDetails += ' | 重命名 ' + stats.renamed;
                            }
                            if (stats.failed) {
                                sidebarDetails += ' | 失败: ' + stats.failed;
                            }

                            sidebarResult.find('.result-details').text(sidebarDetails);

                            syncButton.prop('disabled', false).find('.btn-text').text('批量同步');

                            // 5秒后隐藏结果
                            setTimeout(function() {
                                sidebarResult.fadeOut();
                            }, 5000);
                        }, 500);
                    }

                    self.showFeedback('同步完成', 'success');

                    // 显示详细结果
                    if (response.stats) {
                        var details = '同步结果：\n' +
                            '成功：' + stats.synced + ' 个\n' +
                            '失败：' + stats.failed + ' 个\n' +
                            '跳过：' + stats.skipped + ' 个\n';

                        if (stats.renamed) {
                            details += '重命名：' + stats.renamed + ' 个\n';
                        }

                        details += '总计：' + stats.total + ' 个';

                        if (response.errors && response.errors.length > 0) {
                            details += '\n\n失败详情：\n';
                            response.errors.slice(0, 5).forEach(function(error) {
                                details += 'ID ' + error.cid + ': ' + error.error + '\n';
                            });
                            if (response.errors.length > 5) {
                                details += '... 还有 ' + (response.errors.length - 5) + ' 个错误';
                            }
                        }

                        alert(details);
                    }
                    // 3秒后隐藏主进度条
                    if (progressBar.length) {
                        setTimeout(function() {
                            progressBar.fadeOut();
                        }, 3000);
                    }

                } else {
                    // 失败处理
                    if (progressBar.length) {
                        progressText.text('同步失败');
                    }

                    if (sidebarProgress.length) {
                        sidebarProgress.hide();
                        sidebarResult.removeClass('success error').addClass('error').show();
                        sidebarResult.find('.result-icon').text('❌');
                        sidebarResult.find('.result-title').text('同步失败');
                        sidebarResult.find('.result-details').text(response.message || '未知错误');
                        syncButton.prop('disabled', false).find('.btn-text').text('批量同步');

                        setTimeout(function() {
                            sidebarResult.fadeOut();
                        }, 5000);
                    }

                    self.showFeedback(response.message || '同步失败', 'error');
                }
            },
            error: function() {
                if (progressBar.length) {
                    progressText.text('网络错误');
                }

                if (sidebarProgress.length) {
                    sidebarProgress.hide();
                    sidebarResult.removeClass('success error').addClass('error').show();
                    sidebarResult.find('.result-icon').text('❌');
                    sidebarResult.find('.result-title').text('网络错误');
                    sidebarResult.find('.result-details').text('无法连接到服务器');
                    syncButton.prop('disabled', false).find('.btn-text').text('批量同步');

                    setTimeout(function() {
                        sidebarResult.fadeOut();
                    }, 5000);
                }

                self.showFeedback('网络错误', 'error');
            }
        });
    },

    testConnection: function() {
        var self = this;
        var testButton = jQuery('#webdav-test-connection-btn');
        var sidebarResult = jQuery('#webdav-sync-result');

        // 禁用按钮
        testButton.prop('disabled', true).find('.btn-text').text('测试中...');

        jQuery.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'webdav_test'
            },
            dataType: 'json',
            success: function(response) {
                testButton.prop('disabled', false).find('.btn-text').text('测试连接');

                sidebarResult.removeClass('success error')
                    .addClass(response.success ? 'success' : 'error')
                    .show();

                sidebarResult.find('.result-icon').text(response.success ? '✅' : '❌');
                sidebarResult.find('.result-title').text(response.success ? '连接成功' : '连接失败');

                var details = '';
                if (response.local) {
                    details += '本地: ' + (response.local.success ? '✓' : '✗') + ' | ';
                }
                if (response.remote) {
                    details += '远程: ' + (response.remote.success ? '✓' : '✗');
                }

                sidebarResult.find('.result-details').text(details || response.message);

                // 5秒后隐藏
                setTimeout(function() {
                    sidebarResult.fadeOut();
                }, 5000);

                self.showFeedback(response.message, response.success ? 'success' : 'error');
            },
            error: function() {
                testButton.prop('disabled', false).find('.btn-text').text('测试连接');

                sidebarResult.removeClass('success error').addClass('error').show();
                sidebarResult.find('.result-icon').text('❌');
                sidebarResult.find('.result-title').text('测试失败');
                sidebarResult.find('.result-details').text('网络错误');

                setTimeout(function() {
                    sidebarResult.fadeOut();
                }, 5000);

                self.showFeedback('网络错误', 'error');
            }
        });
    }
};

// 导出到全局
window.MediaLibrary = MediaLibrary;
