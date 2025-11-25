<div class="media-toolbar">
    <div class="toolbar-primary">
        <div class="primary-actions">
            <button class="btn btn-primary" id="upload-btn">上传文件</button>
            <button class="btn btn-danger" id="delete-selected" style="display:none;">删除选中</button>
        </div>
        <div class="toolbar-meta">
            <span class="selection-pill" id="selection-indicator">未选择文件</span>
            <div class="view-switch">
                <a href="#" data-view="grid" class="<?php echo $view === 'grid' ? 'active' : ''; ?>">网格</a>
                <a href="#" data-view="list" class="<?php echo $view === 'list' ? 'active' : ''; ?>">列表</a>
            </div>
        </div>
    </div>

    <div class="toolbar-secondary">
        <div class="filter-control search-control">
            <label for="keywords-input">快速搜索</label>
            <div class="search-input">
                <input type="text" class="form-control" id="keywords-input" placeholder="搜索文件名..."
                    value="<?php echo htmlspecialchars($keywords); ?>">
                <button class="btn ghost" id="search-btn" type="button">搜索</button>
            </div>
        </div>

        <div class="utility-actions">
            <!-- 分开的压缩按钮 -->
            <?php if (($enableGD && extension_loaded('gd')) || ($enableImageMagick && extension_loaded('imagick')) || $enableFFmpeg): ?>
                <button class="btn subtle" id="compress-images-btn" style="display:none;" disabled>压缩图片</button>
            <?php endif; ?>

            <?php if ($enableVideoCompress && $enableFFmpeg): ?>
                <button class="btn subtle" id="compress-videos-btn" style="display:none;" disabled>压缩视频</button>
            <?php endif; ?>

            <?php if (extension_loaded('gd') || extension_loaded('imagick')): ?>
                <button class="btn subtle" id="crop-images-btn" style="display:none;">裁剪图片</button>
                <button class="btn subtle" id="add-watermark-btn" style="display:none;">添加水印</button>
            <?php endif; ?>

            <?php
            // 检查是否有可用的 EXIF 工具
            $hasExifTool = MediaLibrary_ExifPrivacy::isExifToolAvailable();
            $hasPhpExif = extension_loaded('exif');
            if ($enableExif && ($hasExifTool || $hasPhpExif)):
            ?>
                <button class="btn subtle" id="privacy-btn" style="display:none;" disabled>隐私检测</button>
            <?php endif; ?>

            <!-- 缓存管理按钮 -->
            <button class="btn subtle" id="cache-refresh-btn" title="刷新缓存以加速页面加载">
                <svg style="width:14px;height:14px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z" />
                </svg>
                刷新缓存
            </button>
        </div>
    </div>
</div>
