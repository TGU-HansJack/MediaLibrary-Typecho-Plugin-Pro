<div class="media-toolbar">
    <div class="toolbar-primary">
        <div class="primary-actions">
            <button class="btn btn-primary" id="upload-btn">上传文件</button>
            <button class="btn btn-secondary" id="scan-folder-btn">扫描文件夹</button>
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
        </div>
    </div>
</div>
