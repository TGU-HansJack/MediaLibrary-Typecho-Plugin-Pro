/**
 * 媒体库图像编辑器 - 裁剪和水印功能
 */
(function($) {
    'use strict';

    // 全局变量
    var currentImageCid = null;
    var originalImageWidth = 0;
    var originalImageHeight = 0;
    var cropStartX = 0;
    var cropStartY = 0;
    var isMovingCrop = false;
    var activeHandle = null;
    var cropRatio = 0; // 0表示自由比例
    var cropCustomWidth = 0;
    var cropCustomHeight = 0;
    
    // 水印相关变量
    var watermarkStartX = 0;
    var watermarkStartY = 0;
    var isMovingWatermark = false;
    var watermarkScale = 1.0;
    
    /**
     * 裁剪功能初始化
     */
    function initCropFeature() {
        // 显示裁剪按钮
        $('#crop-images-btn').on('click', function() {
            var selectedItems = $('.media-item.selected, .media-list-table tr.selected');
            if (selectedItems.length !== 1) {
                alert('请选择一个图片进行裁剪');
                return;
            }
            
            var item = selectedItems.first();
            var isImage = item.data('is-image') == 1;
            if (!isImage) {
                alert('只能裁剪图片文件');
                return;
            }
            
            currentImageCid = item.data('cid');
            var imageUrl = item.data('url');
            
            // 重置裁剪框和预览
            resetCropInterface();
            
            // 加载图片
            $('#crop-image').attr('src', imageUrl).on('load', function() {
                originalImageWidth = this.naturalWidth;
                originalImageHeight = this.naturalHeight;
                
                // 设置裁剪框初始大小（默认为图片的80%）
                var cropBoxWidth = Math.round(originalImageWidth * 0.8);
                var cropBoxHeight = Math.round(originalImageHeight * 0.8);
                
                // 居中裁剪框
                var cropBoxLeft = Math.round((originalImageWidth - cropBoxWidth) / 2);
                var cropBoxTop = Math.round((originalImageHeight - cropBoxTop) / 2);
                
                // 调整裁剪容器尺寸
                var containerWidth = $('#crop-image-container').width();
                var scale = containerWidth / originalImageWidth;
                
                // 应用裁剪框
                $('#crop-box').css({
                    width: cropBoxWidth * scale + 'px',
                    height: cropBoxHeight * scale + 'px',
                    left: cropBoxLeft * scale + 'px',
                    top: cropBoxTop * scale + 'px'
                });
                
                // 更新裁剪信息
                updateCropInfo(cropBoxLeft, cropBoxTop, cropBoxWidth, cropBoxHeight);
                
                // 显示模态框
                $('#crop-modal').show();
            });
        });
        
        // 处理裁剪框拖动
        $('#crop-box').on('mousedown', function(e) {
            if ($(e.target).hasClass('resize-handle')) {
                // 调整大小操作
                activeHandle = $(e.target);
                isMovingCrop = false;
            } else {
                // 移动操作
                isMovingCrop = true;
                activeHandle = null;
            }
            
            cropStartX = e.clientX;
            cropStartY = e.clientY;
            e.preventDefault();
        });
        
        // 处理拖动和调整大小
        $(document).on('mousemove', function(e) {
            if (!isMovingCrop && !activeHandle) return;
            
            var cropBox = $('#crop-box');
            var cropContainer = $('#crop-image-container');
            var containerOffset = cropContainer.offset();
            var scale = cropContainer.width() / originalImageWidth;
            
            if (isMovingCrop) {
                // 移动裁剪框
                var deltaX = e.clientX - cropStartX;
                var deltaY = e.clientY - cropStartY;
                
                var newLeft = cropBox.position().left + deltaX;
                var newTop = cropBox.position().top + deltaY;
                
                // 限制在图片范围内
                newLeft = Math.max(0, Math.min(newLeft, cropContainer.width() - cropBox.width()));
                newTop = Math.max(0, Math.min(newTop, cropContainer.height() - cropBox.height()));
                
                cropBox.css({
                    left: newLeft + 'px',
                    top: newTop + 'px'
                });
                
                // 更新裁剪信息
                updateCropInfo(
                    Math.round(newLeft / scale),
                    Math.round(newTop / scale),
                    Math.round(cropBox.width() / scale),
                    Math.round(cropBox.height() / scale)
                );
                
            } else if (activeHandle) {
                // 调整裁剪框大小
                var deltaX = e.clientX - cropStartX;
                var deltaY = e.clientY - cropStartY;
                
                var newWidth = cropBox.width();
                var newHeight = cropBox.height();
                var newLeft = cropBox.position().left;
                var newTop = cropBox.position().top;
                
                if (activeHandle.hasClass('top-left')) {
                    newLeft += deltaX;
                    newTop += deltaY;
                    newWidth -= deltaX;
                    newHeight -= deltaY;
                } else if (activeHandle.hasClass('top-right')) {
                    newTop += deltaY;
                    newWidth += deltaX;
                    newHeight -= deltaY;
                } else if (activeHandle.hasClass('bottom-left')) {
                    newLeft += deltaX;
                    newWidth -= deltaX;
                    newHeight += deltaY;
                } else if (activeHandle.hasClass('bottom-right')) {
                    newWidth += deltaX;
                    newHeight += deltaY;
                }
                
                // 保持宽高比（如果设置了）
                if (cropRatio > 0) {
                    if (activeHandle.hasClass('top-left') || activeHandle.hasClass('bottom-right')) {
                        newHeight = newWidth / cropRatio;
                    } else {
                        newWidth = newHeight * cropRatio;
                    }
                }
                
                // 确保尺寸合理
                newWidth = Math.max(20, newWidth);
                newHeight = Math.max(20, newHeight);
                
                // 确保裁剪框在图片内
                if (newLeft < 0) {
                    newWidth += newLeft;
                    newLeft = 0;
                }
                
                if (newTop < 0) {
                    newHeight += newTop;
                    newTop = 0;
                }
                
                if (newLeft + newWidth > cropContainer.width()) {
                    newWidth = cropContainer.width() - newLeft;
                }
                
                if (newTop + newHeight > cropContainer.height()) {
                    newHeight = cropContainer.height() - newTop;
                }
                
                // 再次应用宽高比（如果设置了）
                if (cropRatio > 0) {
                    if (activeHandle.hasClass('top-left') || activeHandle.hasClass('bottom-right')) {
                        newHeight = newWidth / cropRatio;
                    } else {
                        newWidth = newHeight * cropRatio;
                    }
                    
                    // 再次确保在图片范围内
                    if (newLeft + newWidth > cropContainer.width()) {
                        newWidth = cropContainer.width() - newLeft;
                        newHeight = newWidth / cropRatio;
                    }
                    
                    if (newTop + newHeight > cropContainer.height()) {
                        newHeight = cropContainer.height() - newTop;
                        newWidth = newHeight * cropRatio;
                    }
                }
                
                // 应用调整后的裁剪框
                cropBox.css({
                    width: newWidth + 'px',
                    height: newHeight + 'px',
                    left: newLeft + 'px',
                    top: newTop + 'px'
                });
                
                // 更新裁剪信息
                updateCropInfo(
                    Math.round(newLeft / scale),
                    Math.round(newTop / scale),
                    Math.round(newWidth / scale),
                    Math.round(newHeight / scale)
                );
            }
            
            cropStartX = e.clientX;
            cropStartY = e.clientY;
        });
        
        // 结束拖动或调整大小
        $(document).on('mouseup', function() {
            isMovingCrop = false;
            activeHandle = null;
        });
        
        // 监听裁剪比例预设改变
        $('#crop-ratio-preset').on('change', function() {
            var preset = $(this).val();
            
            // 重置自定义尺寸选项
            $('#custom-ratio-group').hide();
            cropCustomWidth = 0;
            cropCustomHeight = 0;
            
            // 应用选择的比例
            if (preset === 'free') {
                cropRatio = 0;
                $('#crop-info-ratio').text('自由');
            } else if (preset === 'original') {
                cropRatio = originalImageWidth / originalImageHeight;
                $('#crop-info-ratio').text(originalImageWidth + ':' + originalImageHeight);
            } else if (preset === 'custom') {
                $('#custom-ratio-group').show();
                $('#crop-info-ratio').text('自定义');
            } else {
                // 解析比例 (如 "16:9" => 16/9)
                var ratioParts = preset.split(':');
                cropRatio = parseFloat(ratioParts[0]) / parseFloat(ratioParts[1]);
                $('#crop-info-ratio').text(preset);
            }
            
            // 应用新比例到现有裁剪框
            if (cropRatio > 0) {
                var cropBox = $('#crop-box');
                var currentWidth = cropBox.width();
                var newHeight = currentWidth / cropRatio;
                
                var cropContainer = $('#crop-image-container');
                var scale = cropContainer.width() / originalImageWidth;
                
                // 确保高度在合理范围内
                if (newHeight > cropContainer.height()) {
                    newHeight = cropContainer.height();
                    currentWidth = newHeight * cropRatio;
                }
                
                cropBox.css({
                    width: currentWidth + 'px',
                    height: newHeight + 'px'
                });
                
                // 更新裁剪信息
                updateCropInfo(
                    Math.round(cropBox.position().left / scale),
                    Math.round(cropBox.position().top / scale),
                    Math.round(currentWidth / scale),
                    Math.round(newHeight / scale)
                );
            }
        });
        
        // 监听自定义尺寸变化
        $('#custom-width, #custom-height').on('input', function() {
            var customWidth = parseInt($('#custom-width').val()) || 0;
            var customHeight = parseInt($('#custom-height').val()) || 0;
            
            if (customWidth > 0 && customHeight > 0) {
                cropCustomWidth = customWidth;
                cropCustomHeight = customHeight;
                cropRatio = customWidth / customHeight;
                $('#crop-info-ratio').text(customWidth + 'x' + customHeight + 'px');
                
                // 应用自定义尺寸
                var cropBox = $('#crop-box');
                var cropContainer = $('#crop-image-container');
                var scale = cropContainer.width() / originalImageWidth;
                
                var currentWidth = cropBox.width();
                var newHeight = currentWidth / cropRatio;
                
                // 确保高度在合理范围内
                if (newHeight > cropContainer.height()) {
                    newHeight = cropContainer.height();
                    currentWidth = newHeight * cropRatio;
                }
                
                cropBox.css({
                    width: currentWidth + 'px',
                    height: newHeight + 'px'
                });
                
                // 更新裁剪信息
                updateCropInfo(
                    Math.round(cropBox.position().left / scale),
                    Math.round(cropBox.position().top / scale),
                    Math.round(currentWidth / scale),
                    Math.round(newHeight / scale)
                );
            }
        });
        
        // 监听替换模式变化
        $('input[name="crop-replace-mode"]').on('change', function() {
            if ($(this).val() === 'keep') {
                $('#crop-custom-name-group').show();
            } else {
                $('#crop-custom-name-group').hide();
            }
        });
        
        // 裁剪取消按钮
        $('#cancel-crop').on('click', function() {
            $('#crop-modal').hide();
        });
        
        // 关闭裁剪模态框
        $('#crop-modal .modal-close').on('click', function() {
            $('#crop-modal').hide();
        });
        
        // 应用裁剪按钮
        $('#apply-crop').on('click', function() {
            var cropBox = $('#crop-box');
            var cropContainer = $('#crop-image-container');
            var scale = cropContainer.width() / originalImageWidth;
            
            // 获取裁剪参数
            var cropX = Math.round(cropBox.position().left / scale);
            var cropY = Math.round(cropBox.position().top / scale);
            var cropWidth = Math.round(cropBox.width() / scale);
            var cropHeight = Math.round(cropBox.height() / scale);
            
            // 如果设置了自定义尺寸
            if ($('#crop-ratio-preset').val() === 'custom' && cropCustomWidth > 0 && cropCustomHeight > 0) {
                // 使用自定义尺寸，但保持选定区域的位置
                cropWidth = Math.min(cropCustomWidth, originalImageWidth - cropX);
                cropHeight = Math.min(cropCustomHeight, originalImageHeight - cropY);
            }
            
            // 确保裁剪区域在图片范围内
            if (cropX + cropWidth > originalImageWidth) {
                cropWidth = originalImageWidth - cropX;
            }
            
            if (cropY + cropHeight > originalImageHeight) {
                cropHeight = originalImageHeight - cropY;
            }
            
            // 检查裁剪区域有效性
            if (cropWidth <= 0 || cropHeight <= 0) {
                alert('裁剪区域无效，请重新选择');
                return;
            }
            
            // 获取其他选项
            var useLibrary = $('#crop-use-library').val();
            var replaceOriginal = $('input[name="crop-replace-mode"]:checked').val() === 'replace';
            var customName = $('#crop-custom-name').val();
            
            // 显示loading状态
            var applyBtn = $(this);
            applyBtn.prop('disabled', true).text('处理中...');
            
            // 发送裁剪请求
            $.ajax({
                url: window.mediaLibraryCurrentUrl + '&action=crop_image',
                type: 'POST',
                data: {
                    cid: currentImageCid,
                    x: cropX,
                    y: cropY,
                    width: cropWidth,
                    height: cropHeight,
                    use_library: useLibrary,
                    replace_original: replaceOriginal ? '1' : '0',
                    custom_name: customName
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload(); // 刷新页面显示裁剪后的图片
                    } else {
                        alert('裁剪失败: ' + response.message);
                    }
                    $('#crop-modal').hide();
                },
                error: function() {
                    alert('裁剪请求发送失败');
                },
                complete: function() {
                    applyBtn.prop('disabled', false).text('应用裁剪');
                }
            });
        });
    }
    
    /**
     * 重置裁剪界面
     */
    function resetCropInterface() {
        $('#crop-image').attr('src', '');
        $('#crop-box').css({
            width: '0',
            height: '0',
            left: '0',
            top: '0'
        });
        $('#crop-ratio-preset').val('free');
        $('#custom-ratio-group').hide();
        $('#custom-width').val('');
        $('#custom-height').val('');
        $('input[name="crop-replace-mode"][value="replace"]').prop('checked', true);
        $('#crop-custom-name-group').hide();
        $('#crop-custom-name').val('');
        cropRatio = 0;
        cropCustomWidth = 0;
        cropCustomHeight = 0;
        $('#crop-info-x').text('0');
        $('#crop-info-y').text('0');
        $('#crop-info-width').text('0');
        $('#crop-info-height').text('0');
        $('#crop-info-ratio').text('自由');
    }
    
    /**
     * 更新裁剪信息
     */
    function updateCropInfo(x, y, width, height) {
        $('#crop-info-x').text(x);
        $('#crop-info-y').text(y);
        $('#crop-info-width').text(width);
        $('#crop-info-height').text(height);
    }
    
    /**
     * 水印功能初始化
     */
    function initWatermarkFeature() {
        // 显示水印按钮
        $('#add-watermark-btn').on('click', function() {
            var selectedItems = $('.media-item.selected, .media-list-table tr.selected');
            if (selectedItems.length !== 1) {
                alert('请选择一个图片添加水印');
                return;
            }
            
            var item = selectedItems.first();
            var isImage = item.data('is-image') == 1;
            if (!isImage) {
                alert('只能为图片添加水印');
                return;
            }
            
            currentImageCid = item.data('cid');
            var imageUrl = item.data('url');
            
            // 重置水印界面
            resetWatermarkInterface();
            
            // 加载图片
            $('#watermark-preview-image').attr('src', imageUrl).on('load', function() {
                originalImageWidth = this.naturalWidth;
                originalImageHeight = this.naturalHeight;
                
                // 预设初始水印位置（右下角）
                updateWatermarkPosition('bottom-right');
                
                // 更新水印预览
                updateWatermarkPreview();
                
                // 显示模态框
                $('#watermark-modal').show();
            });
        });
        
        // 监听水印类型变化
        $('#watermark-type').on('change', function() {
            var type = $(this).val();
            if (type === 'text') {
                $('#text-watermark-options').show();
                $('#image-watermark-options').hide();
            } else {
                $('#text-watermark-options').hide();
                $('#image-watermark-options').show();
            }
            updateWatermarkPreview();
        });
        
        // 监听预设水印变化
        $('#watermark-preset').on('change', function() {
            var preset = $(this).val();
            if (preset === '') {
                $('#custom-text-group').show();
            } else {
                $('#custom-text-group').hide();
                if (preset === 'ai-generated') {
                    $('#watermark-text').val('AI生成图像 - ' + new Date().toISOString().split('T')[0]);
                } else if (preset === 'copyright') {
                    $('#watermark-text').val('© ' + new Date().getFullYear() + ' - 版权所有');
                }
            }
            updateWatermarkPreview();
        });
        
        // 监听水印文本变化
        $('#watermark-text').on('input', function() {
            updateWatermarkPreview();
        });
        
        // 监听字体选择变化
        $('#watermark-font').on('change', function() {
            updateWatermarkPreview();
        });
        
        // 监听字体大小变化
        $('#watermark-font-size').on('input', function() {
            var size = $(this).val();
            $('#font-size-value').text(size);
            updateWatermarkPreview();
        });
        
        // 监听字体颜色变化
        $('#watermark-color').on('input', function() {
            updateWatermarkPreview();
        });
        
        // 监听水印图片选择变化
        $('#watermark-image').on('change', function() {
            updateWatermarkPreview();
        });
        
        // 监听缩放比例变化
        $('#watermark-scale').on('input', function() {
            watermarkScale = parseFloat($(this).val());
            $('#scale-value').text(watermarkScale.toFixed(1));
            updateWatermarkPreview();
        });
        
        // 监听水印位置预设变化
        $('#watermark-position').on('change', function() {
            var position = $(this).val();
            if (position !== 'custom') {
                updateWatermarkPosition(position);
            }
            updateWatermarkPreview();
        });
        
        // 监听透明度变化
        $('#watermark-opacity').on('input', function() {
            var opacity = $(this).val();
            $('#opacity-value').text(opacity);
            $('#watermark-overlay').css('opacity', opacity / 100);
        });
        
        // 监听替换模式变化
        $('input[name="watermark-replace-mode"]').on('change', function() {
            if ($(this).val() === 'keep') {
                $('#watermark-custom-name-group').show();
            } else {
                $('#watermark-custom-name-group').hide();
            }
        });
        
        // 处理水印拖动
        $('#watermark-overlay').on('mousedown', function(e) {
            isMovingWatermark = true;
            watermarkStartX = e.clientX;
            watermarkStartY = e.clientY;
            e.preventDefault();
        });
        
        // 处理水印拖动
        $(document).on('mousemove', function(e) {
            if (!isMovingWatermark) return;
            
            var deltaX = e.clientX - watermarkStartX;
            var deltaY = e.clientY - watermarkStartY;
            
            var watermarkOverlay = $('#watermark-overlay');
            var newLeft = watermarkOverlay.position().left + deltaX;
            var newTop = watermarkOverlay.position().top + deltaY;
            
            var container = $('#watermark-image-container');
            
            // 限制在图片范围内
            newLeft = Math.max(0, Math.min(newLeft, container.width() - watermarkOverlay.width()));
            newTop = Math.max(0, Math.min(newTop, container.height() - watermarkOverlay.height()));
            
            watermarkOverlay.css({
                left: newLeft + 'px',
                top: newTop + 'px'
            });
            
            // 设置为自定义位置
            $('#watermark-position').val('custom');
            
            watermarkStartX = e.clientX;
            watermarkStartY = e.clientY;
        });
        
        // 结束水印拖动
        $(document).on('mouseup', function() {
            isMovingWatermark = false;
        });
        
        // 水印缩放 - 鼠标滚轮
        $('#watermark-overlay').on('wheel', function(e) {
            e.preventDefault();
            
            // 防止事件传播
            e.stopPropagation();
            
            // 获取滚轮方向
            var delta = e.originalEvent.deltaY || -e.originalEvent.wheelDelta;
            
            // 调整缩放比例
            if (delta > 0) {
                // 缩小
                watermarkScale = Math.max(0.1, watermarkScale - 0.1);
            } else {
                // 放大
                watermarkScale = Math.min(2.0, watermarkScale + 0.1);
            }
            
            // 更新滑块值
            $('#watermark-scale').val(watermarkScale);
            $('#scale-value').text(watermarkScale.toFixed(1));
            
            // 更新预览
            updateWatermarkPreview();
            
            return false;
        });
        
        // 水印取消按钮
        $('#cancel-watermark').on('click', function() {
            $('#watermark-modal').hide();
        });
        
        // 关闭水印模态框
        $('#watermark-modal .modal-close').on('click', function() {
            $('#watermark-modal').hide();
        });
        
        // 应用水印按钮
        $('#apply-watermark').on('click', function() {
            // 获取水印参数
            var watermarkType = $('#watermark-type').val();
            var watermarkPosition = $('#watermark-position').val();
            var watermarkOpacity = $('#watermark-opacity').val();
            var useLibrary = $('#watermark-use-library').val();
            var replaceOriginal = $('input[name="watermark-replace-mode"]:checked').val() === 'replace';
            var customName = $('#watermark-custom-name').val();
            
            // 获取水印位置坐标
            var watermarkOverlay = $('#watermark-overlay');
            var container = $('#watermark-image-container');
            var scale = container.width() / originalImageWidth;
            
            var watermarkX = Math.round(watermarkOverlay.position().left / scale);
            var watermarkY = Math.round(watermarkOverlay.position().top / scale);
            
            // 构建请求数据
            var requestData = {
                cid: currentImageCid,
                watermark_type: watermarkType,
                watermark_position: watermarkPosition,
                watermark_x: watermarkX,
                watermark_y: watermarkY,
                watermark_opacity: watermarkOpacity,
                use_library: useLibrary,
                replace_original: replaceOriginal ? '1' : '0',
                custom_name: customName
            };
            
            // 根据水印类型添加特定参数
            if (watermarkType === 'text') {
                requestData.watermark_text = $('#watermark-text').val();
                requestData.watermark_font_size = $('#watermark-font-size').val();
                requestData.watermark_color = $('#watermark-color').val();
                requestData.watermark_font = $('#watermark-font').val();
                requestData.watermark_preset = $('#watermark-preset').val();
            } else {
                requestData.watermark_image = $('#watermark-image').val();
                requestData.watermark_scale = watermarkScale;
            }
            
            // 显示loading状态
            var applyBtn = $(this);
            applyBtn.prop('disabled', true).text('处理中...');
            
            // 发送水印请求
            $.ajax({
                url: window.mediaLibraryCurrentUrl + '&action=add_watermark',
                type: 'POST',
                data: requestData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload(); // 刷新页面显示添加水印后的图片
                    } else {
                        alert('添加水印失败: ' + response.message);
                    }
                    $('#watermark-modal').hide();
                },
                error: function() {
                    alert('水印请求发送失败');
                },
                complete: function() {
                    applyBtn.prop('disabled', false).text('应用水印');
                }
            });
        });
    }
    
    /**
     * 重置水印界面
     */
    function resetWatermarkInterface() {
        $('#watermark-preview-image').attr('src', '');
        $('#watermark-overlay').html('').css({
            left: '10px',
            top: '10px'
        });
        $('#watermark-type').val('text');
        $('#text-watermark-options').show();
        $('#image-watermark-options').hide();
        $('#watermark-preset').val('');
        $('#custom-text-group').show();
        $('#watermark-text').val('水印文本');
        $('#watermark-font').val('msyh.ttf');
        $('#watermark-font-size').val('24');
        $('#font-size-value').text('24');
        $('#watermark-color').val('#ffffff');
        $('#watermark-image').val('logo.png');
        $('#watermark-scale').val('1');
        $('#scale-value').text('1.0');
        watermarkScale = 1.0;
        $('#watermark-position').val('bottom-right');
        $('#watermark-opacity').val('70');
        $('#opacity-value').text('70');
        $('#watermark-use-library').val('gd');
        $('input[name="watermark-replace-mode"][value="replace"]').prop('checked', true);
        $('#watermark-custom-name-group').hide();
        $('#watermark-custom-name').val('');
    }
    
    /**
     * 更新水印预览
     */
    function updateWatermarkPreview() {
        var watermarkType = $('#watermark-type').val();
        var watermarkOverlay = $('#watermark-overlay');
        
        // 清除现有预览
        watermarkOverlay.empty();
        
        // 文本水印预览
        if (watermarkType === 'text') {
            var text = $('#watermark-text').val() || '水印文本';
            var fontSize = $('#watermark-font-size').val();
            var color = $('#watermark-color').val();
            
            watermarkOverlay.html('<div style="font-size:' + fontSize + 'px; color:' + color + '; text-shadow: 1px 1px 2px rgba(0,0,0,0.7); white-space: nowrap;">' + text + '</div>');
        }
        // 图片水印预览
        else {
            var imageName = $('#watermark-image').val();
            var imageUrl = window.mediaLibraryConfig.pluginUrl + '/assets/images/' + imageName;
            var img = $('<img>').attr('src', imageUrl).css({
                'max-width': '150px',
                'max-height': '150px',
                'transform': 'scale(' + watermarkScale + ')',
                'transform-origin': 'top left'
            });
            watermarkOverlay.append(img);
        }
    }
    
    /**
     * 根据预设位置更新水印位置
     */
    function updateWatermarkPosition(position) {
        var watermarkOverlay = $('#watermark-overlay');
        var container = $('#watermark-image-container');
        
        // 延迟执行以确保水印内容已渲染
        setTimeout(function() {
            var watermarkWidth = watermarkOverlay.width();
            var watermarkHeight = watermarkOverlay.height();
            var containerWidth = container.width();
            var containerHeight = container.height();
            
            var left = 10;
            var top = 10;
            
            switch (position) {
                case 'top-center':
                    left = (containerWidth - watermarkWidth) / 2;
                    top = 10;
                    break;
                case 'top-right':
                    left = containerWidth - watermarkWidth - 10;
                    top = 10;
                    break;
                case 'middle-left':
                    left = 10;
                    top = (containerHeight - watermarkHeight) / 2;
                    break;
                case 'middle-center':
                    left = (containerWidth - watermarkWidth) / 2;
                    top = (containerHeight - watermarkHeight) / 2;
                    break;
                case 'middle-right':
                    left = containerWidth - watermarkWidth - 10;
                    top = (containerHeight - watermarkHeight) / 2;
                    break;
                case 'bottom-left':
                    left = 10;
                    top = containerHeight - watermarkHeight - 10;
                    break;
                case 'bottom-center':
                    left = (containerWidth - watermarkWidth) / 2;
                    top = containerHeight - watermarkHeight - 10;
                    break;
                case 'bottom-right':
                    left = containerWidth - watermarkWidth - 10;
                    top = containerHeight - watermarkHeight - 10;
                    break;
            }
            
            watermarkOverlay.css({
                left: left + 'px',
                top: top + 'px'
            });
        }, 100);
    }
    
    // 在文档就绪后初始化功能
    $(document).ready(function() {
        // 在选择变化时显示/隐藏裁剪和水印按钮
        $(document).on('change', '.media-item input[type="checkbox"], .media-list-table input[type="checkbox"]', function() {
            var selectedItems = $('.media-item input[type="checkbox"]:checked, .media-list-table input[type="checkbox"]:checked');
            var selectedCount = selectedItems.length;
            var allImages = true;
            
            // 检查所有选中项是否都是图片
            selectedItems.each(function() {
                var item = $(this).closest('.media-item, tr');
                allImages = allImages && (item.data('is-image') == 1);
            });
            
            $('#crop-images-btn').toggle(selectedCount === 1 && allImages);
            $('#add-watermark-btn').toggle(selectedCount === 1 && allImages);
            
            // 处理全选复选框的选中状态
            if (selectedCount > 0) {
                $('#delete-selected').show();
                $('#compress-images-btn').toggle(allImages && selectedCount > 0);
                $('#compress-videos-btn').toggle(!allImages && selectedCount > 0);
                $('#privacy-btn').toggle(allImages && selectedCount > 0);
            } else {
                $('#delete-selected, #compress-images-btn, #compress-videos-btn, #privacy-btn').hide();
            }
        });
        
        // 初始化裁剪功能
        initCropFeature();
        
        // 初始化水印功能
        initWatermarkFeature();
    });
    
})(jQuery);
