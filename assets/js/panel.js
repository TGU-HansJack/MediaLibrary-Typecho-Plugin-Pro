// 鍏ㄥ眬鍙橀噺
var currentUrl = window.mediaLibraryCurrentUrl;
var currentKeywords = window.mediaLibraryKeywords || '';
var currentType = window.mediaLibraryType || 'all';
var currentView = window.mediaLibraryView || 'grid';
var currentStorageFilter = window.mediaLibraryStorage || 'all';
var currentStorage = currentStorageFilter === 'all' ? 'local' : currentStorageFilter;
var config = window.mediaLibraryConfig || {};

// 淇鍒嗛〉璺宠浆鍑芥暟 - 闃叉鎵撳紑鏂版爣绛鹃〉
function goToPage(page, event) {
    // 闃绘榛樿琛屼负
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
    // 浣跨敤 location.href 鑰屼笉鏄?window.open
    window.location.href = url;
    return false; // 闃叉榛樿琛屼负
}


// 涓昏鍔熻兘瀵硅薄
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
        
        // 绫诲瀷閫夋嫨鍙樺寲
        var typeSelect = document.getElementById('type-select');
        if (typeSelect) {
            typeSelect.addEventListener('change', function() {
                self.updateUrl({type: this.value, page: 1});
            });
        }
        
        // 鎼滅储
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
        
        // 瑙嗗浘鍒囨崲
        document.querySelectorAll('.view-switch a').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var view = this.getAttribute('data-view');
                self.updateUrl({view: view});
            });
        });
        
        // 鍏ㄩ€?
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
        
        // 鍗曢€?
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
        
        // 鍒犻櫎閫変腑
        var deleteSelectedBtn = document.getElementById('delete-selected');
        if (deleteSelectedBtn) {
            deleteSelectedBtn.addEventListener('click', function() {
                self.deleteSelected();
            });
        }
        
        // 鍒嗗紑鐨勫帇缂╂寜閽?
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
        
        // 闅愮妫€娴嬫寜閽?
        var privacyBtn = document.getElementById('privacy-btn');
        if (privacyBtn) {
            privacyBtn.addEventListener('click', function() {
                self.checkPrivacy();
            });
        }
        
        // 鍒犻櫎鍗曚釜
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('media-delete-btn')) {
                e.preventDefault();
                e.stopPropagation();
                var cid = e.target.getAttribute('data-cid');
                self.deleteFiles([cid]);
            }
        });
        
        // 鏌ョ湅璇︽儏
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('media-info-btn')) {
                e.preventDefault();
                e.stopPropagation();
                var cid = e.target.getAttribute('data-cid');
                self.showFileInfo(cid);
            }
        });
        
        // 鐐瑰嚮鏂囦欢鍗＄墖棰勮
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
        
        // 妯℃€佹鍏抽棴
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
        
        // 涓婁紶鎸夐挳
        document.addEventListener('click', function(e) {
            if (e.target.id === 'upload-btn' || e.target.id === 'upload-btn-empty') {
                var uploadModal = document.getElementById('upload-modal');
                if (uploadModal) {
                    uploadModal.style.display = 'flex';
                }
            }
        });

        // 鍘嬬缉鐩稿叧浜嬩欢
        this.bindCompressEvents();
    },
    
    bindCompressEvents: function() {
        var self = this;
        
        // 鍥剧墖鍘嬬缉璐ㄩ噺婊戝潡
        var imageQualitySlider = document.getElementById('image-quality-slider');
        var imageQualityValue = document.getElementById('image-quality-value');
        if (imageQualitySlider && imageQualityValue) {
            imageQualitySlider.addEventListener('input', function() {
                imageQualityValue.textContent = this.value + '%';
            });
        }
        
        // 鏅鸿兘寤鸿鐩稿叧浜嬩欢
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
        
        // 瑙嗛鍘嬬缉璐ㄩ噺婊戝潡
        var videoQualitySlider = document.getElementById('video-quality-slider');
        var videoQualityValue = document.getElementById('video-quality-value');
        if (videoQualitySlider && videoQualityValue) {
            videoQualitySlider.addEventListener('input', function() {
                videoQualityValue.textContent = this.value;
            });
        }
        
        // 鍥剧墖鏇挎崲妯″紡鍒囨崲
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
        
        // 瑙嗛鏇挎崲妯″紡鍒囨崲
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
        
        // 寮€濮嬪浘鐗囧帇缂?
        var startImageCompressBtn = document.getElementById('start-image-compress');
        if (startImageCompressBtn) {
            startImageCompressBtn.addEventListener('click', function() {
                self.startImageCompress();
            });
        }
        
        // 寮€濮嬭棰戝帇缂?
        var startVideoCompressBtn = document.getElementById('start-video-compress');
        if (startVideoCompressBtn) {
            startVideoCompressBtn.addEventListener('click', function() {
                self.startVideoCompress();
            });
        }
        
        // 鍙栨秷鍥剧墖鍘嬬缉
        var cancelImageCompressBtn = document.getElementById('cancel-image-compress');
        if (cancelImageCompressBtn) {
            cancelImageCompressBtn.addEventListener('click', function() {
                document.getElementById('image-compress-modal').style.display = 'none';
            });
        }
        
        // 鍙栨秷瑙嗛鍘嬬缉
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
        var newStorage = params.storage !== undefined ? params.storage : currentStorageFilter;
        
        if (newPage > 1) urlParams.push('page=' + newPage);
        if (newKeywords) urlParams.push('keywords=' + encodeURIComponent(newKeywords));
        if (newType !== 'all') urlParams.push('type=' + newType);
        if (newView !== 'grid') urlParams.push('view=' + newView);
        if (newStorage && newStorage !== 'all') urlParams.push('storage=' + newStorage);
        
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
                deleteBtn.textContent = '鍒犻櫎閫変腑 (' + count + ')';
            } else {
                deleteBtn.style.display = 'none';
            }
        }
        
        if (selectionIndicator) {
            if (count > 0) {
                selectionIndicator.textContent = '宸查€変腑 ' + count + ' 涓枃浠?;
                selectionIndicator.classList.add('active');
            } else {
                selectionIndicator.textContent = '鏈€夋嫨鏂囦欢';
                selectionIndicator.classList.remove('active');
            }
        }
        
        // 鏇存柊閫変腑椤圭洰鍒楄〃
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
    var cropImagesBtn = document.getElementById('crop-images-btn'); // 娣诲姞杩欒
    var addWatermarkBtn = document.getElementById('add-watermark-btn'); // 娣诲姞杩欒
    
    var selectedImages = this.selectedItems.filter(function(item) {
        return item.isImage;
    });
    
    var selectedVideos = this.selectedItems.filter(function(item) {
        return item.isVideo;
    });
    
    // 鍥剧墖鍘嬬缉鎸夐挳
    if (compressImagesBtn && (config.enableGD || config.enableImageMagick || config.enableFFmpeg)) {
        if (selectedImages.length > 0) {
            compressImagesBtn.style.display = 'inline-block';
            compressImagesBtn.disabled = false;
            compressImagesBtn.textContent = '鍘嬬缉鍥剧墖 (' + selectedImages.length + ')';
        } else {
            compressImagesBtn.style.display = 'none';
            compressImagesBtn.disabled = true;
        }
    }
    
    // 瑙嗛鍘嬬缉鎸夐挳
    if (compressVideosBtn && config.enableVideoCompress && config.enableFFmpeg) {
        if (selectedVideos.length > 0) {
            compressVideosBtn.style.display = 'inline-block';
            compressVideosBtn.disabled = false;
            compressVideosBtn.textContent = '鍘嬬缉瑙嗛 (' + selectedVideos.length + ')';
        } else {
            compressVideosBtn.style.display = 'none';
            compressVideosBtn.disabled = true;
        }
    }
    
    // 闅愮妫€娴嬫寜閽?- 妫€娴嬮渶瑕丒XIF鎵╁睍鎴朎xifTool锛屼絾娓呴櫎鍙渶瑕丒xifTool
    if (privacyBtn && config.enableExif && (config.hasExifTool || config.hasPhpExif)) {
        if (selectedImages.length > 0) {
            privacyBtn.style.display = 'inline-block';
            privacyBtn.disabled = false;
            privacyBtn.textContent = '闅愮妫€娴?(' + selectedImages.length + ')';
        } else {
            privacyBtn.style.display = 'none';
            privacyBtn.disabled = true;
        }
    }
    
    // 娣诲姞瑁佸壀鍥剧墖鎸夐挳澶勭悊
    if (cropImagesBtn && (config.enableGD || config.enableImageMagick)) {
        if (selectedImages.length === 1) {
            cropImagesBtn.style.display = 'inline-block';
            cropImagesBtn.disabled = false;
            cropImagesBtn.textContent = '瑁佸壀鍥剧墖';
        } else {
            cropImagesBtn.style.display = 'none';
            cropImagesBtn.disabled = true;
        }
    }
    
    // 娣诲姞姘村嵃鎸夐挳澶勭悊
    if (addWatermarkBtn && (config.enableGD || config.enableImageMagick)) {
        if (selectedImages.length === 1) {
            addWatermarkBtn.style.display = 'inline-block';
            addWatermarkBtn.disabled = false;
            addWatermarkBtn.textContent = '娣诲姞姘村嵃';
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
            alert('璇烽€夋嫨瑕佸垹闄ょ殑鏂囦欢');
            return;
        }
        
        this.deleteFiles(cids);
    },
    
    deleteFiles: function(cids) {
        if (!confirm('纭畾瑕佸垹闄よ繖浜涙枃浠跺悧锛熸鎿嶄綔涓嶅彲鎭㈠锛?)) {
            return;
        }
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', currentUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        var params = 'action=delete&' + cids.map(function(cid) {
            return 'cids[]=' + encodeURIComponent(cid);
        }).join('&');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                location.reload();
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
                        html += '<tr><td>鏂囦欢鍚?/td><td>' + data.title + '</td></tr>';
                        html += '<tr><td>鏂囦欢绫诲瀷</td><td>' + data.mime + '</td></tr>';
                        html += '<tr><td>鏂囦欢澶у皬</td><td>' + data.size + '</td></tr>';
                        html += '<tr><td>涓婁紶鏃堕棿</td><td>' + data.created + '</td></tr>';
                        html += '<tr><td>鏂囦欢璺緞</td><td>' + data.path + '</td></tr>';
                        html += '<tr><td>璁块棶鍦板潃</td><td><input type="text" value="' + data.url + '" readonly onclick="this.select()" style="width:100%;"></td></tr>';
                        
                        html += '<tr><td>鎵€灞炴枃绔?/td><td>';
                        if (data.parent_post.status === 'archived') {
                            html += '<div class="parent-post">';
                            html += '<a href="' + currentUrl.replace('extending.php?panel=MediaLibrary%2Fpanel.php', 'write-' + (data.parent_post.post.type.indexOf('post') === 0 ? 'post' : 'page') + '.php?cid=' + data.parent_post.post.cid) + '" target="_blank">' + data.parent_post.post.title + '</a>';
                            html += '</div>';
                        } else {
                            html += '<span style="color: #999;">鏈綊妗?/span>';
                        }
                        html += '</td></tr>';
                        html += '</table>';
                        
                        if (data.detailed_info && Object.keys(data.detailed_info).length > 0) {
                            html += '<div class="detailed-info">';
                            html += '<h4>璇︾粏淇℃伅</h4>';
                            html += '<table>';
                            
                            var info = data.detailed_info;
                            if (info.format) html += '<tr><td>鏍煎紡</td><td>' + info.format + '</td></tr>';
                            if (info.dimensions) html += '<tr><td>灏哄</td><td>' + info.dimensions + '</td></tr>';
                            if (info.duration) html += '<tr><td>鏃堕暱</td><td>' + info.duration + '</td></tr>';
                            if (info.bitrate) html += '<tr><td>姣旂壒鐜?/td><td>' + info.bitrate + '</td></tr>';
                            if (info.channels) html += '<tr><td>澹伴亾</td><td>' + info.channels + '</td></tr>';
                            if (info.sample_rate) html += '<tr><td>閲囨牱鐜?/td><td>' + info.sample_rate + '</td></tr>';
                            if (info.permissions) html += '<tr><td>鏉冮檺</td><td>' + info.permissions + '</td></tr>';
                            if (info.modified) html += '<tr><td>淇敼鏃堕棿</td><td>' + new Date(info.modified * 1000).toLocaleString() + '</td></tr>';
                            
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
                        alert('鑾峰彇鏂囦欢淇℃伅澶辫触锛? + response.message);
                    }
                } catch (e) {
                    alert('鑾峰彇鏂囦欢淇℃伅澶辫触锛岃閲嶈瘯');
                }
            }
        };
        
        xhr.send();
    },
    
    // 鏅鸿兘灏哄閫傞厤棰勮鍔熻兘 - 浼樺寲鐗堟湰
    showPreview: function(url, type, title) {
        var self = this;
        var modal = document.getElementById('preview-modal');
        var modalDialog = modal.querySelector('.modal-dialog');
        var modalBody = modal.querySelector('.modal-body');
        var modalTitle = modal.querySelector('.modal-header h3');
        
        if (!modal || !modalDialog || !modalBody) return;
        
        // 璁剧疆鏍囬
        if (modalTitle) {
            modalTitle.textContent = title || '棰勮';
        }
        
        // 娓呯┖鍐呭
        modalBody.innerHTML = '';
        
        // 閲嶇疆鏍峰紡
        modalDialog.className = 'modal-dialog';
        modalBody.style = '';
        
        // 鏍规嵁绫诲瀷璁剧疆棰勮鍐呭
        if (type.indexOf('image/') === 0) {
            // 鍥剧墖棰勮 - 鑷€傚簲灏哄
            modalDialog.classList.add('image-preview');
            
            var img = new Image();
            img.onload = function() {
                modalBody.appendChild(img);
                modal.style.display = 'flex';
            };
            img.onerror = function() {
                modalBody.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">鍥剧墖鍔犺浇澶辫触</p>';
                modal.style.display = 'flex';
            };
            img.src = url;
            img.alt = title || '';
            
        } else if (type.indexOf('video/') === 0) {
            // 瑙嗛棰勮
            modalDialog.classList.add('video-preview');
            
            var video = document.createElement('video');
            video.controls = true;
            video.autoplay = false;
            video.src = url;
            
            modalBody.appendChild(video);
            modal.style.display = 'flex';
            
        } else if (type.indexOf('audio/') === 0) {
            // 闊抽棰勮
            modalDialog.classList.add('audio-preview');
            
            var audio = document.createElement('audio');
            audio.controls = true;
            audio.src = url;
            
            var icon = document.createElement('div');
            icon.style.fontSize = '48px';
            icon.style.marginBottom = '20px';
            icon.textContent = '馃幍';
            
            modalBody.appendChild(icon);
            modalBody.appendChild(audio);
            modal.style.display = 'flex';
            
        } else if (type === 'application/pdf') {
            // PDF棰勮
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
            // 鏂囨湰鏂囦欢棰勮
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
            // 鍏朵粬鏂囦欢绫诲瀷
            modalDialog.style.width = '500px';
            
            var content = '<div style="text-align: center; padding: 40px;">';
            content += '<div style="font-size: 48px; margin-bottom: 20px;">馃搫</div>';
            content += '<p style="color: #666; margin-bottom: 20px;">鏃犳硶棰勮姝ゆ枃浠剁被鍨?/p>';
            content += '<a href="' + url + '" target="_blank" class="btn btn-primary">涓嬭浇鏂囦欢</a>';
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
        alert('璇烽€夋嫨鍥剧墖鏂囦欢杩涜鍘嬬缉');
        return;
    }
    
    var imageCompressModal = document.getElementById('image-compress-modal');
    if (imageCompressModal) {
        // 閲嶇疆缁撴灉鏄剧ず
        var resultDiv = document.getElementById('image-compress-result');
        if (resultDiv) {
            resultDiv.style.display = 'none';
            resultDiv.innerHTML = '';
        }
        
        // 鏄剧ず鏅鸿兘寤鸿鍖哄煙
        var suggestionArea = document.getElementById('smart-suggestion-area');
        if (suggestionArea) {
            suggestionArea.style.display = 'block';
        }
        
        // 閲嶇疆鏅鸿兘寤鸿鍐呭
        var suggestionContent = document.getElementById('suggestion-content');
        if (suggestionContent) {
            suggestionContent.innerHTML = '<p style="color: #666; text-align: center; padding: 20px;">鐐瑰嚮"鑾峰彇鏅鸿兘寤鸿"鎸夐挳鏉ヨ幏鍙栭拡瀵规墍閫夊浘鐗囩殑鍘嬬缉寤鸿</p>';
        }
        
        // 閲嶇疆璐ㄩ噺婊戝潡涓洪厤缃殑榛樿鍊?
        var qualitySlider = document.getElementById('image-quality-slider');
        var qualityValue = document.getElementById('image-quality-value');
        if (qualitySlider && qualityValue) {
            qualitySlider.value = config.gdQuality || 80;
            qualityValue.textContent = (config.gdQuality || 80) + '%';
        }
        
        // 閲嶇疆杈撳嚭鏍煎紡
        var formatSelect = document.getElementById('image-output-format');
        if (formatSelect) {
            formatSelect.value = 'original';
        }
        
        // 閲嶇疆鍘嬬缉鏂规硶
        var methodSelect = document.getElementById('image-compress-method');
        if (methodSelect) {
            methodSelect.value = config.enableGD ? 'gd' : (config.enableImageMagick ? 'imagick' : 'ffmpeg');
        }
        
        // 鏄剧ず閫変腑鏂囦欢淇℃伅
        var fileList = document.getElementById('image-compress-files');
        if (fileList) {
            var html = '<p>宸查€夋嫨 ' + selectedImages.length + ' 涓浘鐗囨枃浠讹細</p>';
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
            alert('璇烽€夋嫨瑕佸帇缂╃殑瑙嗛');
            return;
        }
        
        var modal = document.getElementById('video-compress-modal');
        var fileList = document.getElementById('video-compress-files');
        
        if (fileList) {
            var html = '<p>宸查€夋嫨 ' + selectedVideos.length + ' 涓棰戞枃浠?/p>';
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
            alert('璇烽€夋嫨鍥剧墖鏂囦欢');
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
                        alert('鑾峰彇寤鸿澶辫触锛? + response.message);
                    }
                } catch (e) {
                    alert('鑾峰彇寤鸿澶辫触锛岃閲嶈瘯');
                }
            }
        };
        
        xhr.send(params);
    },
    
    displaySmartSuggestion: function(suggestions) {
        var suggestionContent = document.getElementById('suggestion-content');
        if (!suggestionContent) return;
        
        var html = '<div style="max-height: 200px; overflow-y: auto;">';
        
        // 璁＄畻骞冲潎寤鸿
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
        html += '<strong>馃搳 缁煎悎寤鸿锛?/strong><br>';
        html += '鎺ㄨ崘璐ㄩ噺: ' + avgQuality + '%<br>';
        html += '鎺ㄨ崘鏍煎紡: ' + (recommendedFormat === 'original' ? '淇濇寔鍘熸牸寮? : recommendedFormat.toUpperCase()) + '<br>';
        html += '鎺ㄨ崘鏂规硶: ' + recommendedMethod.toUpperCase();
        html += '</div>';
        
        suggestions.forEach(function(item) {
            html += '<div style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px;">';
            html += '<div style="font-weight: bold;">' + item.filename + ' (' + item.size + ')</div>';
            html += '<div style="font-size: 12px; color: #666;">';
            html += '寤鸿璐ㄩ噺: ' + item.suggestion.quality + '% | ';
            html += '寤鸿鏍煎紡: ' + (item.suggestion.format === 'original' ? '淇濇寔鍘熸牸寮? : item.suggestion.format.toUpperCase()) + '<br>';
            html += '鍘熷洜: ' + item.suggestion.reason;
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        
        suggestionContent.innerHTML = html;
        
        // 瀛樺偍寤鸿鏁版嵁
        this.currentSuggestion = {
            quality: avgQuality,
            format: recommendedFormat,
            method: recommendedMethod
        };
    },
    
        applySmartSuggestion: function() {
        if (!this.currentSuggestion) {
            alert('璇峰厛鑾峰彇鏅鸿兘寤鸿');
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
        
        alert('宸插簲鐢ㄦ櫤鑳藉缓璁缃紒');
    },
    
    
    
    
    showVideoCompressModal: function() {
        var selectedVideos = this.selectedItems.filter(function(item) {
            return item.isVideo;
        });
        
        if (selectedVideos.length === 0) {
            alert('璇烽€夋嫨瑙嗛鏂囦欢杩涜鍘嬬缉');
            return;
        }
        
        var videoCompressModal = document.getElementById('video-compress-modal');
        if (videoCompressModal) {
            // 閲嶇疆缁撴灉鏄剧ず
            var resultDiv = document.getElementById('video-compress-result');
            if (resultDiv) {
                resultDiv.style.display = 'none';
                resultDiv.innerHTML = '';
            }
            
            // 閲嶇疆璐ㄩ噺婊戝潡涓洪厤缃殑榛樿鍊?
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
        alert('璇烽€夋嫨鍥剧墖鏂囦欢');
        return;
    }
    
    var cids = selectedImages.map(function(item) { return item.cid; });
    var quality = document.getElementById('image-quality-slider').value;
    var outputFormat = document.getElementById('image-output-format').value;
    var compressMethod = document.getElementById('image-compress-method').value;
    var replaceOriginal = document.querySelector('input[name="image-replace-mode"]:checked').value === 'replace';
    var customName = document.getElementById('image-custom-name').value;
    
    // 鏄剧ず杩涘害
    var resultDiv = document.getElementById('image-compress-result');
    if (resultDiv) {
        resultDiv.innerHTML = '<div style="text-align: center; padding: 20px;"><div>姝ｅ湪鍘嬬缉鍥剧墖锛岃绋嶅€?..</div><div style="margin-top: 10px;"><div class="spinner"></div></div></div>';
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
                    var html = '<h4>鍘嬬缉缁撴灉</h4>';
                    html += '<div style="max-height: 200px; overflow-y: auto;">';
                    
                    response.results.forEach(function(result) {
                        if (result.success) {
                            html += '<div style="padding: 10px; margin-bottom: 10px; background: #f0f8ff; border-left: 3px solid #46b450;">';
                            html += '<div style="color: #46b450; font-weight: bold;">鉁?鍘嬬缉鎴愬姛 (CID: ' + result.cid + ')</div>';
                            html += '<div>鍘熷澶у皬: ' + result.original_size + ' 鈫?鍘嬬缉鍚? ' + result.compressed_size + '</div>';
                            html += '<div>鑺傜渷绌洪棿: ' + result.savings + ' | 鏂规硶: ' + result.method + ' | 鏍煎紡: ' + result.format + '</div>';
                            html += '</div>';
                        } else {
                            html += '<div style="padding: 10px; margin-bottom: 10px; background: #fff2f2; border-left: 3px solid #dc3232;">';
                            html += '<div style="color: #dc3232; font-weight: bold;">鉁?鍘嬬缉澶辫触 (CID: ' + result.cid + ')</div>';
                            html += '<div>' + result.message + '</div>';
                            html += '</div>';
                        }
                    });
                    
                    html += '</div>';
                    html += '<div style="margin-top: 15px; text-align: center;">';
                    html += '<button class="btn btn-primary" onclick="location.reload()">鍒锋柊椤甸潰</button>';
                    html += '</div>';
                    
                    if (resultDiv) {
                        resultDiv.innerHTML = html;
                    }
                } else {
                    if (resultDiv) {
                        resultDiv.innerHTML = '<div style="color: red;">鉁?鎵归噺鍘嬬缉澶辫触: ' + response.message + '</div>';
                    }
                }
            } catch (e) {
                if (resultDiv) {
                    resultDiv.innerHTML = '<div style="color: red;">鉁?鍘嬬缉澶辫触锛岃閲嶈瘯</div>';
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
            alert('璇烽€夋嫨瑕佸帇缂╃殑瑙嗛');
            return;
        }
        
        var quality = document.getElementById('video-quality-slider').value;
        var codec = document.getElementById('video-codec').value;
        var replaceMode = document.querySelector('input[name="video-replace-mode"]:checked').value;
        var customName = '';
        
        if (replaceMode === 'keep') {
            customName = document.getElementById('video-custom-name').value;
            if (!customName) {
                alert('璇疯緭鍏ヨ嚜瀹氫箟鏂囦欢鍚嶅悗缂€');
                return;
            }
        }
        
        if (!confirm('纭畾瑕佸帇缂╅€変腑鐨?' + selectedVideos.length + ' 涓棰戝悧锛熻棰戝帇缂╁彲鑳介渶瑕佽緝闀挎椂闂淬€?)) {
            return;
        }
        
        // 鏄剧ず杩涘害
        var modal = document.getElementById('video-compress-modal');
        var progressDiv = document.getElementById('video-compress-progress');
        if (progressDiv) {
            progressDiv.style.display = 'block';
            progressDiv.innerHTML = '<p>姝ｅ湪鍘嬬缉瑙嗛锛岃鑰愬績绛夊緟...</p><div class="progress-bar"><div class="progress-fill" style="width: 0%"></div></div>';
        }
        
        // 绂佺敤鎸夐挳
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
                                progressDiv.innerHTML = '<p style="color: #34a853;">鉁?鍘嬬缉瀹屾垚锛?/p>';
                            }
                            setTimeout(function() {
                                modal.style.display = 'none';
                                location.reload();
                            }, 1500);
                        } else {
                            if (progressDiv) {
                                progressDiv.innerHTML = '<p style="color: #ea4335;">鍘嬬缉澶辫触锛? + response.message + '</p>';
                            }
                        }
                    } catch (e) {
                        if (progressDiv) {
                            progressDiv.innerHTML = '<p style="color: #ea4335;">鍘嬬缉澶辫触锛岃閲嶈瘯</p>';
                        }
                    }
                } else {
                    if (progressDiv) {
                        progressDiv.innerHTML = '<p style="color: #ea4335;">鍘嬬缉澶辫触锛岃閲嶈瘯</p>';
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
        alert('璇烽€夋嫨鍥剧墖鏂囦欢杩涜闅愮妫€娴?);
        return;
    }
    
    var cids = selectedImages.map(function(item) { return item.cid; });
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', currentUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    var params = 'action=check_privacy&' + cids.map(function(cid) {
        return 'cids[]=' + encodeURIComponent(cid);
    }).join('&');
    
    // 娣诲姞瓒呮椂璁剧疆
    xhr.timeout = 30000; // 30绉掕秴鏃?
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    var privacyContent = document.getElementById('privacy-content');
                    var privacyModal = document.getElementById('privacy-modal');
                    
                    if (response.success) {
                        var html = '<h4>闅愮妫€娴嬬粨鏋?/h4>';
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
                                    html += '<div><strong>鍙戠幇鐨勯殣绉佷俊鎭?</strong></div>';
                                    html += '<ul style="margin: 10px 0; padding-left: 20px;">';
                                    
                                    for (var key in result.privacy_info) {
                                        html += '<li style="margin-bottom: 5px;">' + key + ': ' + result.privacy_info[key] + '</li>';
                                    }
                                    
                                    html += '</ul>';
                                    
                                    // 鍙湪鏈塃xifTool鏃舵樉绀烘竻闄ゆ寜閽?
                                    if (config.hasExifTool) {
                                        html += '<div style="margin-top: 10px;">';
                                        html += '<button class="btn btn-warning btn-small" onclick="MediaLibrary.removeExif(\'' + result.cid + '\')">娓呴櫎EXIF淇℃伅</button>';
                                        html += '</div>';
                                    } else {
                                        html += '<div style="margin-top: 10px; color: #999; font-size: 12px;">';
                                        html += '闇€瑕佸畨瑁?ExifTool 搴撴墠鑳芥竻闄XIF淇℃伅';
                                        html += '</div>';
                                    }
                                    
                                    // GPS鍦板浘鏁版嵁鏀堕泦淇濇寔涓嶅彉
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
                                html += '<div style="color: #dc3232; font-weight: bold;">妫€娴嬪け璐?(CID: ' + result.cid + ')</div>';
                                html += '<div>' + result.message + '</div>';
                                html += '</div>';
                            }
                        });
                        
                        html += '</div>';
                        
                        // 濡傛灉鏈塆PS鏁版嵁锛屾樉绀哄湴鍥炬寜閽?
                        if (gpsImages.length > 0) {
                            html += '<div style="text-align: center; margin: 20px 0; padding: 15px; background: #e8f4fd; border-radius: 4px;">';
                            html += '<div style="margin-bottom: 10px; font-weight: bold; color: #1976d2;">鍙戠幇 ' + gpsImages.length + ' 寮犲浘鐗囧寘鍚獹PS浣嶇疆淇℃伅</div>';
                            html += '<button class="btn btn-primary" onclick="MediaLibrary.showGPSMap(' + JSON.stringify(gpsImages).replace(/"/g, '&quot;') + ')">鍦ㄥ湴鍥句笂鏌ョ湅浣嶇疆</button>';
                            html += '</div>';
                        }
                        
                        html += '<div style="color: #d63638; font-size: 12px; margin-top: 15px; text-align: center;">';
                        html += '鈿狅笍 寤鸿鍦ㄥ彂甯冨墠娓呴櫎鍖呭惈闅愮淇℃伅鐨勫浘鐗囩殑EXIF鏁版嵁';
                        html += '</div>';
                        
                        privacyContent.innerHTML = html;
                        privacyModal.style.display = 'flex';
                    } else {
                        alert('闅愮妫€娴嬪け璐ワ細' + response.message);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text:', xhr.responseText);
                    alert('闅愮妫€娴嬪け璐ワ紝鏈嶅姟鍣ㄥ搷搴旀牸寮忛敊璇?);
                }
            } else if (xhr.status === 500) {
                alert('鏈嶅姟鍣ㄥ唴閮ㄩ敊璇紙500锛夛紝璇锋鏌ユ湇鍔″櫒鏃ュ織');
                console.error('Server error 500, response:', xhr.responseText);
            } else {
                alert('闅愮妫€娴嬪け璐ワ紝HTTP鐘舵€佺爜锛? + xhr.status);
                console.error('HTTP error:', xhr.status, xhr.responseText);
            }
        }
    };
    
    xhr.ontimeout = function() {
        alert('璇锋眰瓒呮椂锛岃閲嶈瘯');
    };
    
    xhr.onerror = function() {
        alert('缃戠粶閿欒锛岃妫€鏌ョ綉缁滆繛鎺?);
    };
    
    xhr.send(params);
},

    
// 鍦?checkPrivacy 鏂规硶鍚庢坊鍔犱互涓嬫柟娉曪細

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
                html += '<p><strong>GPS浣嶇疆锛?/strong>绾害 ' + result.gps.latitude.toFixed(6) + ', 缁忓害 ' + result.gps.longitude.toFixed(6) + '</p>';
            }
            if (result.has_camera_info) {
                html += '<p><strong>鐩告満淇℃伅锛?/strong>' + result.camera_info + '</p>';
            }
            if (result.has_datetime) {
                html += '<p><strong>鎷嶆憚鏃堕棿锛?/strong>' + result.datetime + '</p>';
            }
            
            html += '<div class="privacy-actions">';
            html += '<button type="button" class="btn-warning" onclick="MediaLibrary.removeExif(\'' + result.cid + '\')">娓呴櫎EXIF淇℃伅</button>';
            html += '</div>';
        } else {
            html += '<p style="color: #34a853;">鉁?鏈娴嬪埌闅愮淇℃伅</p>';
        }
        
        html += '</div>';
        html += '</div>';
    });
    
    resultsDiv.innerHTML = html;
},

removeExif: function(cid) {
    if (!confirm('纭畾瑕佹竻闄よ繖涓浘鐗囩殑EXIF淇℃伅鍚楋紵')) {
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
                        alert('EXIF淇℃伅宸叉竻闄?);
                        // 寤惰繜2绉掑悗閲嶆柊妫€娴嬶紝纭繚鏂囦欢绯荤粺鏇存柊
                        setTimeout(function() {
                            MediaLibrary.checkPrivacy();
                        }, 2000);
                    } else {
                        alert('娓呴櫎澶辫触锛? + response.message);
                    }
                } catch (e) {
                    alert('娓呴櫎澶辫触锛岃閲嶈瘯');
                }
            } else {
                alert('娓呴櫎澶辫触锛岃閲嶈瘯');
            }
        }
    };
    
    xhr.send('action=remove_exif&cid=' + cid);
},


showGPSMap: function(gpsImages) {
    var gpsMapModal = document.getElementById('gps-map-modal');
    var gpsMapContainer = document.getElementById('gps-map-container');
    
    if (!gpsMapModal || !gpsMapContainer) {
        alert('鍦板浘缁勪欢鏈壘鍒?);
        return;
    }
    
    // 鏄剧ず妯℃€佹
    gpsMapModal.style.display = 'flex';
    
    // 鍔犺浇鍦板浘
    this.initGPSMap(gpsMapContainer, gpsImages);
},

initGPSMap: function(container, gpsImages) {
    // 妫€鏌Charts鏄惁宸插姞杞?
    if (typeof echarts === 'undefined') {
        alert('ECharts鏈姞杞斤紝鏃犳硶鏄剧ず鍦板浘');
        return;
    }
    
    var myChart = echarts.init(container);
    
    // 鍔犺浇涓浗鍦板浘鏁版嵁
    var geoJsonUrl = config.pluginUrl + '/assets/geo/china.json';
    
    fetch(geoJsonUrl)
        .then(function(response) { return response.json(); })
        .then(function(geoJson) {
            echarts.registerMap('china', geoJson);
            
            // 璁＄畻鍦板浘涓績鐐?
            var centerLng = 0, centerLat = 0;
            gpsImages.forEach(function(item) {
                centerLng += item.coords[0];
                centerLat += item.coords[1];
            });
            centerLng /= gpsImages.length;
            centerLat /= gpsImages.length;
            
            var option = {
                title: {
                    text: '鍥剧墖GPS浣嶇疆鍒嗗竷',
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
                            html += '<div style="font-size: 12px; color: #666;">缁忓害: ' + data.coords[0].toFixed(6) + '</div>';
                            html += '<div style="font-size: 12px; color: #666;">绾害: ' + data.coords[1].toFixed(6) + '</div>';
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
                    name: 'GPS浣嶇疆',
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
            
            // 绐楀彛澶у皬鏀瑰彉鏃堕噸鏂拌皟鏁村浘琛?
            window.addEventListener('resize', function() {
                myChart.resize();
            });
        })
        .catch(function(error) {
            console.error('鍔犺浇鍦板浘鏁版嵁澶辫触:', error);
            container.innerHTML = '<div style="text-align: center; padding: 50px; color: #999;">鍦板浘鏁版嵁鍔犺浇澶辫触</div>';
        });
},

// HTML 杞箟
escapeHtml: function(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
},

initUpload: function() {
    var self = this;
    function buildUploadUrl(targetStorage) {
        var storageKey = targetStorage || currentStorage || 'local';
        return currentUrl + '&action=upload&storage=' + storageKey;
    }

    var uploader = new plupload.Uploader({
        browse_button: 'upload-file-btn',
        url: buildUploadUrl(),
        runtimes: 'html5,flash,html4',
        flash_swf_url: config.adminStaticUrl + 'Moxie.swf',
        drop_element: 'upload-area',
        filters: {
            max_file_size: config.phpMaxFilesize || '2mb',
            mime_types: [{
                'title': '鍏佽涓婁紶鐨勬枃浠?,
                'extensions': config.allowedTypes || 'jpg,jpeg,png,gif,bmp,webp,svg,mp4,avi,mov,wmv,flv,mp3,wav,ogg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,avif'
            }],
            prevent_duplicates: true
        },
        multi_selection: true,

        init: {
            FilesAdded: function(up, files) {
                // 鑷姩鏄剧ず涓婁紶妯℃€佹
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
                        '<div class="status">绛夊緟涓婁紶...</div>' +
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
                        status.textContent = '涓婁紶涓?.. ' + file.percent + '%';
                    }
                }
            },

            FileUploaded: function(up, file, result) {
                var li = document.getElementById(file.id);
                if (li) {
                    var status = li.querySelector('.status');
                    var progressFill = li.querySelector('.progress-fill');
                    
                    if (200 == result.status) {
                        try {
                            var data = JSON.parse(result.response);
                            if (data && data.length >= 2) {
                                li.className = 'success';
                                if (status) status.textContent = '涓婁紶鎴愬姛';
                                if (progressFill) progressFill.style.background = '#46b450';
                            } else {
                                li.className = 'error';
                                if (status) status.textContent = '涓婁紶澶辫触: 鏈嶅姟鍣ㄥ搷搴斿紓甯?;
                                if (progressFill) progressFill.style.background = '#dc3232';
                            }
                        } catch (e) {
                            li.className = 'error';
                            if (status) status.textContent = '涓婁紶澶辫触: 鍝嶅簲瑙ｆ瀽閿欒';
                            if (progressFill) progressFill.style.background = '#dc3232';
                        }
                    } else {
                        li.className = 'error';
                        if (status) status.textContent = '涓婁紶澶辫触: HTTP ' + result.status;
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
                        // 鍒涘缓骞舵樉绀哄脊骞?
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
                                <span style="font-size: 16px;">鉁?/span>
                                <span>涓婁紶瀹屾垚锛佹垚鍔熶笂浼?${successCount} 涓枃浠?/span>
                            </div>
                        `;
                        
                        document.body.appendChild(toast);
                        
                        // 鏄剧ず鍔ㄧ敾
                        setTimeout(function() {
                            toast.style.opacity = '1';
                            toast.style.transform = 'translateX(0)';
                        }, 100);
                        
                        // 鑷姩娑堝け骞跺埛鏂伴〉闈?
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
                        word = '鏂囦欢澶у皬瓒呰繃闄愬埗';
                        break;
                    case plupload.FILE_EXTENSION_ERROR:
                        word = '鏂囦欢鎵╁睍鍚嶄笉琚敮鎸?;
                        break;
                    case plupload.FILE_DUPLICATE_ERROR:
                        word = '鏂囦欢宸茬粡涓婁紶杩?;
                        break;
                    case plupload.HTTP_ERROR:
                    default:
                        word = '涓婁紶鍑虹幇閿欒';
                        break;
                }
                
                li.innerHTML = '<div class="file-info">' +
                    '<div class="file-name">' + (error.file ? error.file.name : '鏈煡鏂囦欢') + '</div>' +
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
    
    var storageInputs = document.querySelectorAll("input[name=\"upload-storage\"]");
    var storagePills = document.querySelectorAll('.storage-pill');
    var storageLabel = document.getElementById('upload-storage-current-label');

    function updateStorageLabel(storageKey, customLabel) {
        if (!storageLabel) {
            return;
        }
        var label = customLabel;
        if (!label) {
            label = storageKey === 'webdav' ? 'WebDAV' : '本地存储';
        }
        storageLabel.textContent = label;
    }

    function updateStoragePillState(activeValue) {
        if (!storagePills.length) {
            return;
        }
        storagePills.forEach(function(pill) {
            var input = pill.querySelector('input[name="upload-storage"]');
            if (!input) {
                return;
            }
            if (input.value === activeValue && input.checked) {
                pill.classList.add('active');
            } else {
                pill.classList.remove('active');
            }
        });
    }

    function changeUploadStorage(storageKey, label) {
        currentStorage = storageKey || 'local';
        uploader.setOption('url', buildUploadUrl(currentStorage));
        updateStorageLabel(currentStorage, label);
        updateStoragePillState(currentStorage);
    }

    if (storageInputs.length) {
        storageInputs.forEach(function(input) {
            input.addEventListener('change', function() {
                if (this.checked) {
                    changeUploadStorage(this.value, this.getAttribute('data-label'));
                }
            });
        });

        var checked = document.querySelector('input[name="upload-storage"]:checked');
        if (checked) {
            changeUploadStorage(checked.value, checked.getAttribute('data-label'));
        } else {
            changeUploadStorage(currentStorage);
        }
    } else {
        updateStorageLabel(currentStorage);
    }
    
    // 鍏ㄩ〉闈㈡嫋鎷界洃鍚?
    var dragCounter = 0;
    var dragOverlay = null;
    
    // 鍒涘缓鎷栨嫿瑕嗙洊灞?
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
                <div style="font-size: 48px; margin-bottom: 16px;">馃搧</div>
                <div style="font-size: 24px; font-weight: bold; margin-bottom: 8px;">鎷栨嫿鏂囦欢鍒版澶?/div>
                <div style="font-size: 14px; opacity: 0.8;">鏉惧紑榧犳爣寮€濮嬩笂浼?/div>
            </div>
        `;
        
        document.body.appendChild(dragOverlay);
        return dragOverlay;
    }
    
    // 鏄剧ず鎷栨嫿瑕嗙洊灞?
    function showDragOverlay() {
        var overlay = createDragOverlay();
        overlay.style.pointerEvents = 'auto';
        setTimeout(function() {
            overlay.style.opacity = '1';
        }, 10);
    }
    
    // 闅愯棌鎷栨嫿瑕嗙洊灞?
    function hideDragOverlay() {
        if (dragOverlay) {
            dragOverlay.style.opacity = '0';
            dragOverlay.style.pointerEvents = 'none';
        }
    }
    
    // 鍏ㄩ〉闈㈡嫋鎷戒簨浠?
    document.addEventListener('dragenter', function(e) {
        e.preventDefault();
        dragCounter++;
        
        // 妫€鏌ユ槸鍚︽槸鏂囦欢鎷栨嫿
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
        
        // 妫€鏌ユ槸鍚﹀湪涓婁紶鍖哄煙澶栨嫋鎷?
        var uploadArea = document.getElementById('upload-area');
        var isInUploadArea = uploadArea && uploadArea.contains(e.target);
        
        if (!isInUploadArea && e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            // 鍦ㄩ〉闈㈠叾浠栧湴鏂规嫋鎷芥枃浠讹紝娣诲姞鍒颁笂浼犻槦鍒?
            var files = Array.from(e.dataTransfer.files);
            
            // 楠岃瘉鏂囦欢绫诲瀷鍜屽ぇ灏?
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
                // 娣诲姞鏂囦欢鍒颁笂浼犻槦鍒?
                validFiles.forEach(function(file) {
                    uploader.addFile(file);
                });
            } else {
                // 鏄剧ず閿欒鎻愮ず
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
                        <span style="font-size: 16px;">鉂?/span>
                        <span>鏂囦欢绫诲瀷涓嶆敮鎸佹垨鏂囦欢杩囧ぇ</span>
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
    
    // 鎷栨嫿鍖哄煙浜嬩欢锛堜繚鎸佸師鏈夊姛鑳斤級
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

// 娣诲姞杈呭姪鍑芥暟瑙ｆ瀽鏂囦欢澶у皬
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
    
    return 2 * 1024 * 1024; // 榛樿2MB
}

};


var WebDAVManager = {
    enabled: !!(config.enableWebDAV),
    container: null,
    listElement: null,
    pathLabel: null,
    feedbackEl: null,
    currentPath: '/',
    loading: false,

    init: function() {
        this.container = document.getElementById('webdav-panel');
        if (!this.enabled || !this.container) {
            return;
        }
        this.listElement = document.getElementById('webdav-list');
        this.pathLabel = document.getElementById('webdav-current-path');
        this.feedbackEl = document.getElementById('webdav-feedback');
        this.currentPath = config.webdavRoot || '/';
        this.bindEvents();

        if (config.webdavConfigured && this.listElement) {
            this.loadList(this.currentPath);
        }
    },

    bindEvents: function() {
        var self = this;
        var refreshBtn = document.getElementById('webdav-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                self.refresh();
            });
        }

        var upBtn = document.getElementById('webdav-up');
        if (upBtn) {
            upBtn.addEventListener('click', function() {
                var parentPath = self.getParentPath(self.currentPath);
                if (parentPath) {
                    self.loadList(parentPath);
                }
            });
        }

        var newFolderBtn = document.getElementById('webdav-new-folder');
        if (newFolderBtn) {
            newFolderBtn.addEventListener('click', function() {
                self.createFolder();
            });
        }

        var uploadInput = document.getElementById('webdav-upload-input');
        if (uploadInput) {
            uploadInput.addEventListener('change', function() {
                if (this.files && this.files.length) {
                    self.uploadFiles(this.files);
                    this.value = '';
                }
            });
        }

        if (this.listElement) {
            this.listElement.addEventListener('click', function(e) {
                var deleteBtn = e.target.closest('.webdav-delete');
                if (deleteBtn) {
                    var row = deleteBtn.closest('tr[data-path]');
                    if (row) {
                        var target = row.getAttribute('data-path');
                        if (target && confirm('纭畾瑕佸垹闄よ鏉＄洰鍚楋紵')) {
                            self.deleteEntry(target);
                        }
                    }
                    return;
                }

                var nameEl = e.target.closest('.webdav-entry-name');
                if (nameEl) {
                    var row = nameEl.closest('tr[data-path]');
                    if (!row) {
                        return;
                    }
                    var isDir = row.getAttribute('data-dir') === '1';
                    var path = row.getAttribute('data-path');
                    if (isDir) {
                        self.loadList(path);
                    } else {
                        var fileUrl = row.getAttribute('data-url');
                        if (fileUrl) {
                            window.open(fileUrl, '_blank');
                        }
                    }
                }
            });
        }
    },

    refresh: function() {
        if (!this.listElement) {
            return;
        }
        this.loadList(this.currentPath);
    },

    loadList: function(targetPath) {
        var self = this;
        if (!this.listElement || !config.webdavConfigured) {
            return;
        }
        var path = typeof targetPath === 'string' ? targetPath : this.currentPath;
        this.loading = true;
        this.showLoading('姝ｅ湪鍔犺浇 WebDAV 鐩綍...');
        this.request('webdav_list', { path: path })
            .then(function(response) {
                if (!response.success) {
                    throw new Error(response.message || '鍔犺浇澶辫触');
                }
                var data = response.data || {};
                self.currentPath = data.current_path || '/';
                self.renderList(data.items || []);
                self.updatePathLabel();
                self.showFeedback('鐩綍宸叉洿鏂?, 'success');
                self.loading = false;
            }, function(error) {
                self.loading = false;
                self.showFeedback(error && error.message ? error.message : 'WebDAV 鎿嶄綔澶辫触', 'error');
            });
    },

    renderList: function(items) {
        if (!this.listElement) {
            return;
        }
        if (!items.length) {
            this.listElement.innerHTML = '<div class="webdav-empty">褰撳墠鐩綍涓虹┖</div>';
            return;
        }
        var self = this;

        var rows = items.map(function(item) {
            var isDir = !!item.is_dir;
            var icon = isDir ? '馃搧' : '馃搫';
            var safeName = self.escapeHtml(item.name || '鏈懡鍚?);
            var size = isDir ? '-' : (item.size_human || '-');
            var modified = item.modified || '-';
            var publicUrl = item.public_url ? self.escapeHtml(item.public_url) : '';

            var actions = '<div class="webdav-entry-actions">';
            if (!isDir && publicUrl) {
                actions += '<a href="' + publicUrl + '" target="_blank" rel="noreferrer">涓嬭浇</a>';
            }
            actions += '<button type="button" class="btn ghost webdav-delete">鍒犻櫎</button>';
            actions += '</div>';

            return '<tr data-path="' + self.escapeHtml(item.path) + '" data-dir="' + (isDir ? '1' : '0') + '" data-url="' + publicUrl + '">'
                + '<td class="webdav-entry-name"><span class="webdav-entry-icon">' + icon + '</span><span>' + safeName + '</span></td>'
                + '<td>' + size + '</td>'
                + '<td>' + modified + '</td>'
                + '<td>' + actions + '</td>'
                + '</tr>';
        }).join('');

        var table = '<table class="webdav-table">'
            + '<thead><tr><th>鍚嶇О</th><th>澶у皬</th><th>淇敼鏃堕棿</th><th>鎿嶄綔</th></tr></thead>'
            + '<tbody>' + rows + '</tbody></table>';
        this.listElement.innerHTML = table;
    },

    updatePathLabel: function() {
        if (this.pathLabel) {
            this.pathLabel.textContent = this.currentPath;
        }
    },

    showLoading: function(message) {
        if (this.listElement) {
            this.listElement.innerHTML = '<div class="webdav-empty">' + (message || '鍔犺浇涓?..') + '</div>';
        }
    },

    showFeedback: function(message, type) {
        if (!this.feedbackEl) {
            return;
        }
        this.feedbackEl.textContent = message || '';
        this.feedbackEl.className = 'webdav-feedback' + (type ? ' ' + type : '');
    },

    deleteEntry: function(path) {
        var self = this;
        this.request('webdav_delete', { target: path })
            .then(function(response) {
                if (!response.success) {
                    throw new Error(response.message || '鍒犻櫎澶辫触');
                }
                self.showFeedback('鍒犻櫎鎴愬姛', 'success');
                self.loadList(self.currentPath);
            }, function(error) {
                self.showFeedback(error && error.message ? error.message : '鍒犻櫎澶辫触', 'error');
            });
    },

    createFolder: function() {
        var name = prompt('璇疯緭鍏ユ枃浠跺す鍚嶇О');
        if (!name) {
            return;
        }
        name = name.trim();
        if (!name) {
            return;
        }
        if (/[\\/]/.test(name)) {
            alert('鏂囦欢澶瑰悕绉颁笉鑳藉寘鍚?/ 鎴?\\');
            return;
        }
        var self = this;
        this.request('webdav_create_folder', { path: this.currentPath, name: name })
            .then(function(response) {
                if (!response.success) {
                    throw new Error(response.message || '鍒涘缓澶辫触');
                }
                self.showFeedback('鐩綍鍒涘缓鎴愬姛', 'success');
                self.loadList(self.currentPath);
            }, function(error) {
                self.showFeedback(error && error.message ? error.message : '鍒涘缓澶辫触', 'error');
            });
    },

    uploadFiles: function(fileList) {
        var files = Array.prototype.slice.call(fileList || []);
        if (!files.length) {
            return;
        }
        var self = this;
        var uploadNext = function(index) {
            if (index >= files.length) {
                self.showFeedback('涓婁紶瀹屾垚', 'success');
                self.loadList(self.currentPath);
                return;
            }
            var file = files[index];
            var formData = new FormData();
            formData.append('path', self.currentPath);
            formData.append('file', file);
            self.showFeedback('姝ｅ湪涓婁紶 ' + file.name + '...', 'info');
            self.request('webdav_upload', formData, true)
                .then(function(response) {
                    if (!response.success) {
                        throw new Error(response.message || '涓婁紶澶辫触');
                    }
                    uploadNext(index + 1);
                }, function(error) {
                    self.showFeedback(error && error.message ? error.message : '涓婁紶澶辫触', 'error');
                });
        };

        uploadNext(0);
    },

    getParentPath: function(path) {
        if (!path || path === '/') {
            return null;
        }
        var segments = path.split('/').filter(function(item) {
            return item !== '';
        });
        segments.pop();
        return segments.length ? '/' + segments.join('/') : '/';
    },

    escapeHtml: function(str) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
        return (str || '').replace(/[&<>"']/g, function(ch) {
            return map[ch] || ch;
        });
    },

    request: function(action, data, isFormData) {
        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', currentUrl, true);
            if (!isFormData) {
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            }
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            resolve(response);
                        } catch (err) {
                            reject(err);
                        }
                    } else {
                        reject(new Error('璇锋眰澶辫触锛岀姸鎬佺爜 ' + xhr.status));
                    }
                }
            };

            if (isFormData) {
                data.append('action', action);
                xhr.send(data);
            } else {
                var payload = 'action=' + encodeURIComponent(action);
                if (data) {
                    var pairs = [];
                    for (var key in data) {
                        if (data.hasOwnProperty(key)) {
                            pairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
                        }
                    }
                    if (pairs.length) {
                        payload += '&' + pairs.join('&');
                    }
                }
                xhr.send(payload);
            }
        });
    },

    focus: function() {
        if (!this.container) {
            return;
        }
        this.container.classList.add('webdav-highlight');
        this.container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(function() {
            WebDAVManager.container.classList.remove('webdav-highlight');
        }, 600);
    }
};

window.WebDAVManager = WebDAVManager;



// 鍔犺浇鍥惧儚缂栬緫鍣ㄨ剼鏈?
(function() {
    var script = document.createElement('script');
    script.src = window.mediaLibraryConfig.pluginUrl + '/assets/js/image-editor.js';
    script.type = 'text/javascript';
    document.getElementsByTagName('head')[0].appendChild(script);
})();


// 鍒濆鍖?
document.addEventListener('DOMContentLoaded', function() {
    MediaLibrary.init();
    if (window.WebDAVManager) {
        WebDAVManager.init();
    }

    // 鍒濆鍖栦晶鏍忓姛鑳?
    initSidebar();
});

// 渚ф爮鍔熻兘鍒濆鍖?
function initSidebar() {
    // 绉诲姩绔晶鏍忔姌鍙犲姛鑳?
    if (window.innerWidth <= 768) {
        var sidebarSections = document.querySelectorAll('.sidebar-section');

        sidebarSections.forEach(function(section, index) {
            var title = section.querySelector('.sidebar-title');
            var content = section.querySelector('.sidebar-content');

            if (title && content) {
                // 榛樿灞曞紑鏂囦欢绫诲瀷绛涢€夛紝鍏朵粬鎶樺彔
                if (index !== 2) { // 绗笁涓猻ection鏄枃浠剁被鍨嬬瓫閫?
                    content.style.display = 'none';
                    title.classList.add('collapsed');
                }

                // 娣诲姞鐐瑰嚮浜嬩欢
                title.addEventListener('click', function() {
                    if (content.style.display === 'none') {
                        content.style.display = 'block';
                        title.classList.remove('collapsed');
                    } else {
                        content.style.display = 'none';
                        title.classList.add('collapsed');
                    }
                });

                // 娣诲姞鎶樺彔鎸囩ず鍣?
                if (!title.querySelector('.toggle-icon')) {
                    var icon = document.createElement('span');
                    icon.className = 'toggle-icon';
                    icon.innerHTML = '鈻?;
                    title.appendChild(icon);
                }
            }
        });
    }

    // 鐩戝惉绐楀彛澶у皬鍙樺寲
    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // 妗岄潰绔椂閲嶇疆鎵€鏈夊唴瀹逛负鍙
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

    // 楂樹寒褰撳墠绛涢€夐」
    highlightActiveFilter();
}

// 楂樹寒褰撳墠婵€娲荤殑绛涢€夐」
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

// 瀵煎嚭鍒板叏灞€
window.MediaLibrary = MediaLibrary;

