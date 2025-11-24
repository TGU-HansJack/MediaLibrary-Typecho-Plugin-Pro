<!-- 上传模态框 -->
<div class="modal" id="upload-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3>上传文件</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <?php
                // 上传弹窗默认存储位置固定为 local，不受页面筛选条件影响
                $defaultUploadStorage = 'local';
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
                ?>
                <div class="upload-storage-control">
                    <div class="upload-storage-label">选择存储位置</div>
                    <div class="upload-storage-options">
                        <?php foreach ($uploadStorageOptions as $storageOption): ?>
                            <label class="storage-pill <?php echo empty($storageOption['available']) ? 'disabled' : ''; ?>">
                                <input type="radio"
                                    name="upload-storage"
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
                    <div class="upload-storage-hint">当前上传至：<strong id="upload-storage-current-label"><?php echo $defaultUploadStorage === 'webdav' ? 'WebDAV' : '本地存储'; ?></strong></div>
                </div>
                <div id="upload-area" class="upload-area">
                    <p>拖拽文件到此处或点击选择文件</p>
                    <a href="#" id="upload-file-btn" class="btn btn-primary">选择文件</a>
                </div>
                <ul id="file-list" style="margin-top: 20px;"></ul>
            </div>
        </div>
    </div>
</div>

<!-- 文件详情模态框 -->
<div class="modal" id="info-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3>文件详情</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body" id="file-info-content">
                <!-- 动态加载内容 -->
            </div>
        </div>
    </div>
</div>

<!-- 文件预览模态框 -->
<div class="modal preview-modal" id="preview-modal">
    <div class="modal-dialog" id="preview-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="preview-modal-title">文件预览</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body" id="preview-content">
                <!-- 动态加载内容 -->
            </div>
        </div>
    </div>
</div>

<!-- 修改图片压缩模态框 -->
<div class="modal" id="image-compress-modal">
    <div class="modal-dialog" style="max-width: 700px;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>批量压缩图片</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <!-- 智能建议区域 -->
                <div id="smart-suggestion-area" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; display: none;">
                    <h4 style="margin: 0 0 10px 0; color: #495057;">🤖 智能压缩建议</h4>
                    <div id="suggestion-content"></div>
                    <div style="margin-top: 10px;">
                        <button class="btn btn-success btn-small" id="apply-smart-suggestion">应用建议设置</button>
                        <button class="btn btn-secondary btn-small" id="get-smart-suggestion">获取建议</button>
                    </div>
                </div>
                
                <div class="compress-settings">
                    <div style="margin-bottom: 15px;">
                        <label>压缩方法:</label>
                        <select id="image-compress-method" style="width: 100%; margin-top: 5px;">
                            <?php if ($enableGD && extension_loaded('gd')): ?>
                                <option value="gd">GD 库</option>
                            <?php endif; ?>
                            <?php if ($enableImageMagick && extension_loaded('imagick')): ?>
                                <option value="imagick">ImageMagick</option>
                            <?php endif; ?>
                            <?php if ($enableFFmpeg): ?>
                                <option value="ffmpeg">FFmpeg</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label>输出格式:</label>
                        <select id="image-output-format" style="width: 100%; margin-top: 5px;">
                            <option value="original">保持原格式</option>
                            <option value="jpeg">JPEG</option>
                            <option value="png">PNG</option>
                            <option value="webp">WebP</option>
                            <option value="avif">AVIF</option>
                        </select>
                        <small style="color: #666;">注意：格式转换时，替换原文件会保持相同链接</small>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label>压缩质量: <span id="image-quality-value"><?php echo $gdQuality; ?>%</span></label>
                        <input type="range" id="image-quality-slider" min="10" max="100" value="<?php echo $gdQuality; ?>" style="width: 100%; margin-top: 5px;">
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                            <span style="float: left;">高压缩</span>
                            <span style="float: right;">高质量</span>
                            <div style="clear: both;"></div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label>
                            <input type="radio" name="image-replace-mode" value="replace" checked> 
                            替换原文件（保持链接不变）
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="image-replace-mode" value="keep"> 
                            保留原文件（创建新文件）
                        </label>
                        <div id="image-custom-name-group" style="margin-top: 10px; display: none;">
                            <input type="text" id="image-custom-name" placeholder="自定义文件名前缀（可选）" style="width: 100%;">
                            <small style="color: #666;">留空则使用默认命名规则</small>
                        </div>
                    </div>
                </div>
                
                <div class="compress-actions" style="margin-top: 20px;">
                    <button class="btn btn-primary" id="start-image-compress">开始压缩</button>
                    <button class="btn" id="cancel-image-compress">取消</button>
                </div>
                
                <div id="image-compress-result" style="display: none; margin-top: 20px; max-height: 300px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

<!-- 视频压缩模态框 -->
<div class="modal" id="video-compress-modal">
    <div class="modal-dialog" style="max-width: 600px;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>批量压缩视频</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="compress-settings">
                    <div style="margin-bottom: 15px;">
                        <label>视频编码器:</label>
                        <select id="video-codec" style="width: 100%; margin-top: 5px;">
                            <option value="libx264" <?php echo $videoCodec === 'libx264' ? 'selected' : ''; ?>>H.264 (兼容性好)</option>
                            <option value="libx265" <?php echo $videoCodec === 'libx265' ? 'selected' : ''; ?>>H.265 (压缩率高)</option>
                            <option value="libvpx-vp9" <?php echo $videoCodec === 'libvpx-vp9' ? 'selected' : ''; ?>>VP9 (开源)</option>
                            <option value="libaom-av1" <?php echo $videoCodec === 'libaom-av1' ? 'selected' : ''; ?>>AV1 (最新标准)</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label>压缩质量: <span id="video-quality-value"><?php echo $videoQuality; ?></span></label>
                        <input type="range" id="video-quality-slider" min="18" max="35" value="<?php echo $videoQuality; ?>" style="width: 100%; margin-top: 5px;">
                        <small style="color: #666;">数值越小质量越高，推荐18-28</small>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label>
                            <input type="radio" name="video-replace-mode" value="replace" checked> 
                            替换原文件
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="video-replace-mode" value="keep"> 
                            保留原文件
                        </label>
                        <div id="video-custom-name-group" style="margin-top: 10px; display: none;">
                            <input type="text" id="video-custom-name" placeholder="自定义文件名前缀（可选）" style="width: 100%;">
                            <small style="color: #666;">留空则使用默认命名规则</small>
                        </div>
                    </div>
                </div>
                
                <div class="compress-actions" style="margin-top: 20px;">
                    <button class="btn btn-primary" id="start-video-compress">开始压缩</button>
                    <button class="btn" id="cancel-video-compress">取消</button>
                </div>
                
                <div id="video-compress-result" style="display: none; margin-top: 20px; max-height: 300px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

<!-- 隐私检测模态框 -->
<div class="modal" id="privacy-modal">
    <div class="modal-dialog" style="max-width: 800px;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>批量隐私检测</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body" id="privacy-content">
                <!-- 动态加载内容 -->
            </div>
        </div>
    </div>
</div>

<!-- GPS地图模态框 -->
<div class="modal" id="gps-map-modal" style="z-index: 1002;">
    <div class="modal-dialog" style="max-width: 90vw; max-height: 90vh;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>GPS位置地图</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body" style="padding: 0;">
                <div id="gps-map-container" style="width: 100%; height: 70vh; min-height: 500px;"></div>
            </div>
        </div>
    </div>
</div>


<!-- 图片裁剪模态框 -->
<div class="modal crop-modal" id="crop-modal">
    <div class="modal-dialog" style="max-width: 90vw; max-height: 90vh;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>图片裁剪</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="crop-container" style="display: flex; flex-wrap: wrap;">
                    <div class="crop-preview-container" style="flex: 2; min-width: 300px; max-width: 800px;">
                        <div id="crop-image-container" style="position: relative; margin: 0 auto; max-width: 100%; overflow: hidden;">
                            <img id="crop-image" src="" alt="裁剪图片" style="display: block; max-width: 100%;">
                            <div id="crop-box" style="position: absolute; top: 0; left: 0; border: 1px dashed #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.5); cursor: move;">
                                <div class="resize-handle top-left" style="position: absolute; top: -5px; left: -5px; width: 10px; height: 10px; background: #fff; border: 1px solid #333; cursor: nwse-resize;"></div>
                                <div class="resize-handle top-right" style="position: absolute; top: -5px; right: -5px; width: 10px; height: 10px; background: #fff; border: 1px solid #333; cursor: nesw-resize;"></div>
                                <div class="resize-handle bottom-left" style="position: absolute; bottom: -5px; left: -5px; width: 10px; height: 10px; background: #fff; border: 1px solid #333; cursor: nesw-resize;"></div>
                                <div class="resize-handle bottom-right" style="position: absolute; bottom: -5px; right: -5px; width: 10px; height: 10px; background: #fff; border: 1px solid #333; cursor: nwse-resize;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="crop-settings" style="flex: 1; min-width: 250px; padding-left: 20px;">
                        <div style="margin-bottom: 15px;">
                            <label>裁剪尺寸预设:</label>
                            <select id="crop-ratio-preset" style="width: 100%; margin-top: 5px;">
                                <option value="free">自由裁剪</option>
                                <option value="original">原图比例</option>
                                <option value="1:1">1:1 方形</option>
                                <option value="2:3">2:3 单反相机（竖）</option>
                                <option value="3:2">3:2 单反相机（横）</option>
                                <option value="3:4">3:4 电商主图</option>
                                <option value="4:3">4:3 媒体主图</option>
                                <option value="9:16">9:16 视频封面（竖）</option>
                                <option value="16:9">16:9 视频封面（横）</option>
                                <option value="1:2">1:2 手机壁纸</option>
                                <option value="custom">自定义尺寸</option>
                            </select>
                        </div>
                        
                        <div id="custom-ratio-group" style="margin-bottom: 15px; display: none;">
                            <div style="display: flex; gap: 10px;">
                                <div style="flex: 1;">
                                    <label>宽度 (px):</label>
                                    <input type="number" id="custom-width" style="width: 100%; margin-top: 5px;" placeholder="宽度" min="1">
                                </div>
                                <div style="flex: 1;">
                                    <label>高度 (px):</label>
                                    <input type="number" id="custom-height" style="width: 100%; margin-top: 5px;" placeholder="高度" min="1">
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label>图像处理库:</label>
                            <select id="crop-use-library" style="width: 100%; margin-top: 5px;">
                                <?php if (extension_loaded('gd')): ?>
                                    <option value="gd">GD 库</option>
                                <?php endif; ?>
                                <?php if (extension_loaded('imagick')): ?>
                                    <option value="imagick">ImageMagick</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label>
                                <input type="radio" name="crop-replace-mode" value="replace" checked> 
                                替换原文件（保持链接不变）
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="crop-replace-mode" value="keep"> 
                                保留原文件（创建新文件）
                            </label>
                            <div id="crop-custom-name-group" style="margin-top: 10px; display: none;">
                                <input type="text" id="crop-custom-name" placeholder="自定义文件名前缀（可选）" style="width: 100%;">
                                <small style="color: #666;">留空则使用默认命名规则</small>
                            </div>
                        </div>
                        
                        <div style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px;">
                            <p>当前裁剪框信息：</p>
                            <ul style="font-size: 13px; margin: 10px 0; padding-left: 20px;">
                                <li>左上角: X: <span id="crop-info-x">0</span>, Y: <span id="crop-info-y">0</span></li>
                                <li>尺寸: <span id="crop-info-width">0</span> × <span id="crop-info-height">0</span> 像素</li>
                                <li>比例: <span id="crop-info-ratio">自由</span></li>
                            </ul>
                        </div>
                        
                        <div class="crop-actions" style="margin-top: 20px;">
                            <button class="btn btn-primary" id="apply-crop">应用裁剪</button>
                            <button class="btn" id="cancel-crop">取消</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 水印添加模态框 -->
<div class="modal watermark-modal" id="watermark-modal">
    <div class="modal-dialog" style="max-width: 90vw; max-height: 90vh;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>添加水印</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="watermark-container" style="display: flex; flex-wrap: wrap;">
                    <div class="watermark-preview-container" style="flex: 2; min-width: 300px; max-width: 800px;">
                        <div id="watermark-image-container" style="position: relative; margin: 0 auto; max-width: 100%; overflow: hidden;">
                            <img id="watermark-preview-image" src="" alt="预览图片" style="display: block; max-width: 100%;">
                            <div id="watermark-overlay" style="position: absolute; top: 10px; left: 10px; cursor: move; user-select: none;">
                                <!-- 水印预览 - 动态内容 -->
                            </div>
                        </div>
                        <p style="margin: 10px 0; font-size: 13px; color: #666;">提示：可拖动调整水印位置，使用鼠标滚轮调整水印大小</p>
                    </div>
                    
                    <div class="watermark-settings" style="flex: 1; min-width: 250px; padding-left: 20px;">
                        <div style="margin-bottom: 15px;">
                            <label>水印类型:</label>
                            <select id="watermark-type" style="width: 100%; margin-top: 5px;">
                                <option value="text">文本水印</option>
                                <option value="image">图片水印</option>
                            </select>
                        </div>
                        
                        <div id="text-watermark-options">
                            <div style="margin-bottom: 15px;">
                                <label>预设水印:</label>
                                <select id="watermark-preset" style="width: 100%; margin-top: 5px;">
                                    <option value="">自定义文本</option>
                                    <option value="ai-generated">AI生成图像标识</option>
                                    <option value="copyright">版权声明</option>
                                </select>
                            </div>
                            
                            <div id="custom-text-group" style="margin-bottom: 15px;">
                                <label>水印文本:</label>
                                <input type="text" id="watermark-text" style="width: 100%; margin-top: 5px;" placeholder="输入水印文本" value="水印文本">
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label>字体选择:</label>
                                <select id="watermark-font" style="width: 100%; margin-top: 5px;">
                                    <option value="msyh.ttf">微软雅黑</option>
                                    <option value="simhei.ttf">黑体</option>
                                    <option value="simsun.ttc">宋体</option>
                                    <option value="simkai.ttf">楷体</option>
                                </select>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label>字体大小: <span id="font-size-value">24</span>px</label>
                                <input type="range" id="watermark-font-size" min="10" max="72" value="24" style="width: 100%; margin-top: 5px;">
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label>字体颜色:</label>
                                <input type="color" id="watermark-color" value="#ffffff" style="width: 100%; margin-top: 5px;">
                            </div>
                        </div>
                        
                        <div id="image-watermark-options" style="display: none;">
                            <div style="margin-bottom: 15px;">
                                <label>选择水印图片:</label>
                                <select id="watermark-image" style="width: 100%; margin-top: 5px;">
                                    <option value="logo.png">默认Logo</option>
                                    <option value="watermark.png">通用水印</option>
                                    <option value="copyright.png">版权图标</option>
                                </select>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label>缩放比例: <span id="scale-value">1.0</span>x</label>
                                <input type="range" id="watermark-scale" min="0.1" max="2" step="0.1" value="1" style="width: 100%; margin-top: 5px;">
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label>水印位置:</label>
                            <select id="watermark-position" style="width: 100%; margin-top: 5px;">
                                <option value="custom">自定义位置</option>
                                <option value="top-left">左上角</option>
                                <option value="top-center">顶部居中</option>
                                <option value="top-right">右上角</option>
                                <option value="middle-left">左侧居中</option>
                                <option value="middle-center">正中心</option>
                                <option value="middle-right">右侧居中</option>
                                <option value="bottom-left">左下角</option>
                                <option value="bottom-center">底部居中</option>
                                <option value="bottom-right">右下角</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label>透明度: <span id="opacity-value">70</span>%</label>
                            <input type="range" id="watermark-opacity" min="10" max="100" value="70" style="width: 100%; margin-top: 5px;">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label>图像处理库:</label>
                            <select id="watermark-use-library" style="width: 100%; margin-top: 5px;">
                                <?php if (extension_loaded('gd')): ?>
                                    <option value="gd">GD 库</option>
                                <?php endif; ?>
                                <?php if (extension_loaded('imagick')): ?>
                                    <option value="imagick">ImageMagick</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label>
                                <input type="radio" name="watermark-replace-mode" value="replace" checked> 
                                替换原文件（保持链接不变）
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="watermark-replace-mode" value="keep"> 
                                保留原文件（创建新文件）
                            </label>
                            <div id="watermark-custom-name-group" style="margin-top: 10px; display: none;">
                                <input type="text" id="watermark-custom-name" placeholder="自定义文件名前缀（可选）" style="width: 100%;">
                                <small style="color: #666;">留空则使用默认命名规则</small>
                            </div>
                        </div>

                        <div class="watermark-actions" style="margin-top: 20px;">
                            <button class="btn btn-primary" id="apply-watermark">应用水印</button>
                            <button class="btn" id="cancel-watermark">取消</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 文件夹扫描结果模态框 -->
<div class="modal" id="scan-result-modal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3>文件夹扫描结果</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div id="scan-result-content">
                    <div class="scan-loading" style="text-align: center; padding: 40px;">
                        <p>正在扫描文件夹...</p>
                    </div>
                </div>

                <div id="scan-result-data" style="display: none;">
                    <div class="scan-summary">
                        <h4>扫描摘要</h4>
                        <div class="scan-stats">
                            <div class="scan-stat-item">
                                <span class="scan-stat-label">扫描路径:</span>
                                <span class="scan-stat-value" id="scan-path"></span>
                            </div>
                            <div class="scan-stat-item">
                                <span class="scan-stat-label">文件系统中的文件:</span>
                                <span class="scan-stat-value" id="total-files-system"></span>
                            </div>
                            <div class="scan-stat-item">
                                <span class="scan-stat-label">数据库中的文件:</span>
                                <span class="scan-stat-value" id="total-files-db"></span>
                            </div>
                            <div class="scan-stat-item">
                                <span class="scan-stat-label">未录入的文件:</span>
                                <span class="scan-stat-value" id="orphaned-count"></span>
                            </div>
                        </div>
                    </div>

                    <div class="scan-files-section" id="orphaned-files-section" style="display: none;">
                        <div class="scan-files-header">
                            <h4>未录入的文件</h4>
                            <div class="scan-files-actions">
                                <button class="btn btn-xs" id="select-all-orphaned">全选</button>
                                <button class="btn btn-xs" id="deselect-all-orphaned">取消全选</button>
                                <button class="btn btn-xs btn-primary" id="import-selected-files">导入选中文件</button>
                            </div>
                        </div>
                        <div class="scan-files-list" id="orphaned-files-list">
                            <!-- 文件列表将通过 JavaScript 动态填充 -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn" id="close-scan-modal">关闭</button>
            </div>
        </div>
    </div>
</div>

<style>
.scan-description {
    font-size: 12px;
    color: #666;
    margin-bottom: 10px;
    line-height: 1.5;
}

.scan-folder-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.scan-folder-btn .btn-icon {
    font-size: 14px;
}

.modal-lg {
    max-width: 900px;
}

.scan-summary {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.scan-summary h4 {
    margin: 0 0 15px 0;
    font-size: 16px;
    color: #333;
}

.scan-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.scan-stat-item {
    display: flex;
    justify-content: space-between;
    padding: 8px;
    background: white;
    border-radius: 3px;
}

.scan-stat-label {
    font-weight: 500;
    color: #666;
}

.scan-stat-value {
    color: #0073aa;
    font-weight: 600;
}

.scan-files-section {
    margin-top: 20px;
}

.scan-files-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.scan-files-header h4 {
    margin: 0;
    font-size: 16px;
}

.scan-files-actions {
    display: flex;
    gap: 5px;
}

.scan-files-list {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
}

.scan-file-item {
    display: flex;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}

.scan-file-item:hover {
    background: #f9f9f9;
}

.scan-file-item:last-child {
    border-bottom: none;
}

.scan-file-checkbox {
    margin-right: 10px;
}

.scan-file-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f0f0f0;
    border-radius: 4px;
    margin-right: 10px;
    font-size: 20px;
}

.scan-file-info {
    flex: 1;
}

.scan-file-name {
    font-weight: 500;
    color: #333;
    display: block;
    margin-bottom: 3px;
}

.scan-file-meta {
    font-size: 12px;
    color: #999;
}

.scan-file-path {
    font-size: 11px;
    color: #aaa;
    font-family: monospace;
    margin-top: 3px;
}

.scan-loading {
    text-align: center;
    padding: 40px;
}

.scan-error {
    background: #ffebee;
    color: #c62828;
    padding: 15px;
    border-radius: 4px;
    text-align: center;
}
</style>
