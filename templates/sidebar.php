<?php
// 侧栏组件
?>
<div class="media-sidebar">
    <!-- 存储类型 -->
    <div class="sidebar-section">
        <h3 class="sidebar-title">存储类型</h3>
        <div class="sidebar-content">
            <ul class="storage-list">
                <li class="storage-item active">
                    <span class="storage-icon">📁</span>
                    <span class="storage-name">本地存储</span>
                    <span class="storage-badge">活跃</span>
                </li>
                <?php
                // 检查是否启用了其他存储类型（如果有相关配置）
                // 这里可以根据实际插件配置扩展
                ?>
                <li class="storage-item disabled">
                    <span class="storage-icon">☁️</span>
                    <span class="storage-name">WebDAV</span>
                    <span class="storage-badge">未配置</span>
                </li>
                <li class="storage-item disabled">
                    <span class="storage-icon">🌐</span>
                    <span class="storage-name">对象存储</span>
                    <span class="storage-badge">未配置</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- 文件类型筛选 -->
    <div class="sidebar-section">
        <h3 class="sidebar-title">文件类型</h3>
        <div class="sidebar-content">
            <ul class="filter-list">
                <li class="filter-item <?php echo $type === 'all' ? 'active' : ''; ?>">
                    <a href="<?php echo $currentUrl; ?>&type=all&page=1" class="filter-link">
                        <span class="filter-icon">📄</span>
                        <span class="filter-name">所有文件</span>
                        <span class="filter-count"><?php echo number_format($total); ?></span>
                    </a>
                </li>
                <li class="filter-item <?php echo $type === 'image' ? 'active' : ''; ?>">
                    <a href="<?php echo $currentUrl; ?>&type=image&page=1" class="filter-link">
                        <span class="filter-icon">🖼️</span>
                        <span class="filter-name">图片</span>
                        <span class="filter-count">-</span>
                    </a>
                </li>
                <li class="filter-item <?php echo $type === 'video' ? 'active' : ''; ?>">
                    <a href="<?php echo $currentUrl; ?>&type=video&page=1" class="filter-link">
                        <span class="filter-icon">🎬</span>
                        <span class="filter-name">视频</span>
                        <span class="filter-count">-</span>
                    </a>
                </li>
                <li class="filter-item <?php echo $type === 'audio' ? 'active' : ''; ?>">
                    <a href="<?php echo $currentUrl; ?>&type=audio&page=1" class="filter-link">
                        <span class="filter-icon">🎵</span>
                        <span class="filter-name">音频</span>
                        <span class="filter-count">-</span>
                    </a>
                </li>
                <li class="filter-item <?php echo $type === 'document' ? 'active' : ''; ?>">
                    <a href="<?php echo $currentUrl; ?>&type=document&page=1" class="filter-link">
                        <span class="filter-icon">📝</span>
                        <span class="filter-name">文档</span>
                        <span class="filter-count">-</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- 统计信息 -->
    <div class="sidebar-section">
        <h3 class="sidebar-title">当前页统计</h3>
        <div class="sidebar-content">
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-label">图片</span>
                    <span class="stat-value"><?php echo $imagesCount; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">视频</span>
                    <span class="stat-value"><?php echo $videosCount; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">音频</span>
                    <span class="stat-value"><?php echo $audioCount; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">文档</span>
                    <span class="stat-value"><?php echo $documentsCount; ?></span>
                </div>
            </div>
            <div class="storage-info">
                <span class="storage-label">本页容量</span>
                <span class="storage-value"><?php echo $currentPageFootprint; ?></span>
            </div>
        </div>
    </div>
</div>
