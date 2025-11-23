<div class="media-toolbar">
    <div class="toolbar-row">
        <button class="btn btn-primary" id="upload-btn">上传文件</button>
        <button class="btn btn-danger" id="delete-selected" style="display:none;">删除选中</button>
        
        <select class="form-control" id="type-select">
            <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>所有文件</option>
            <option value="image" <?php echo $type === 'image' ? 'selected' : ''; ?>>图片</option>
            <option value="video" <?php echo $type === 'video' ? 'selected' : ''; ?>>视频</option>
            <option value="audio" <?php echo $type === 'audio' ? 'selected' : ''; ?>>音频</option>
            <option value="document" <?php echo $type === 'document' ? 'selected' : ''; ?>>文档</option>
        </select>
        
        <input type="text" class="form-control" id="keywords-input" placeholder="搜索文件名..." 
               value="<?php echo htmlspecialchars($keywords); ?>" style="width: 200px;">
        <button class="btn" id="search-btn">搜索</button>
        
        <!-- 分开的压缩按钮 -->
        <?php if (($enableGD && extension_loaded('gd')) || ($enableImageMagick && extension_loaded('imagick')) || $enableFFmpeg): ?>
            <button class="btn" id="compress-images-btn" style="display:none;" disabled>压缩图片</button>
        <?php endif; ?>
        
        <?php if ($enableVideoCompress && $enableFFmpeg): ?>
            <button class="btn" id="compress-videos-btn" style="display:none;" disabled>压缩视频</button>
        <?php endif; ?>
        
        <?php if (extension_loaded('gd') || extension_loaded('imagick')): ?>
            <button class="btn" id="crop-images-btn" style="display:none;">裁剪图片</button>
            <button class="btn" id="add-watermark-btn" style="display:none;">添加水印</button>
        <?php endif; ?>

        
        
        <?php 
        // 检查是否有可用的 EXIF 工具
        $hasExifTool = MediaLibrary_ExifPrivacy::isExifToolAvailable();
        $hasPhpExif = extension_loaded('exif');
        if ($enableExif && ($hasExifTool || $hasPhpExif)): 
        ?>
            <button class="btn" id="privacy-btn" style="display:none;" disabled>隐私检测</button>
        <?php endif; ?>
        
        <div class="view-switch">
            <a href="#" data-view="grid" class="<?php echo $view === 'grid' ? 'active' : ''; ?>">网格</a>
            <a href="#" data-view="list" class="<?php echo $view === 'list' ? 'active' : ''; ?>">列表</a>
        </div>
    </div>
</div>
