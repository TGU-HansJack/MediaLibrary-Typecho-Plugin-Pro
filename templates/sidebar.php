<?php
// 侧栏组件
?>
<div class="media-sidebar">
    <!-- 存储类型 -->
    <div class="sidebar-section">
        <h3 class="sidebar-title">存储类型</h3>
        <div class="sidebar-content">
            <?php if (!empty($storageStatusList)): ?>
                <ul class="storage-list">
                    <?php
                    $currentStorage = $request->get('storage', 'all');
                    foreach ($storageStatusList as $storageItem):
                        $isActive = ($currentStorage === $storageItem['key']) || ($currentStorage === 'all' && $storageItem['key'] === 'local');
                        $storageUrl = $currentUrl . '&storage=' . $storageItem['key'] . '&page=1';
                        if ($keywords) $storageUrl .= '&keywords=' . urlencode($keywords);
                        if ($type !== 'all') $storageUrl .= '&type=' . $type;
                        if ($view !== 'grid') $storageUrl .= '&view=' . $view;
                    ?>
                        <li class="storage-item <?php echo htmlspecialchars($storageItem['class']); ?> <?php echo $isActive ? 'storage-active' : ''; ?>">
                            <a href="<?php echo $storageUrl; ?>" class="storage-link" title="<?php echo htmlspecialchars($storageItem['description']); ?>">
                                <span class="storage-icon"><?php echo $storageItem['icon']; ?></span>
                                <div class="storage-text">
                                    <span class="storage-name"><?php echo htmlspecialchars($storageItem['name']); ?></span>
                                </div>
                                <span class="storage-status-dot"></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="storage-empty">暂无存储状态信息</p>
            <?php endif; ?>
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
                        <span class="filter-count"><?php echo number_format($typeStatistics['image']); ?></span>
                    </a>
                </li>
                <li class="filter-item <?php echo $type === 'video' ? 'active' : ''; ?>">
                    <a href="<?php echo $currentUrl; ?>&type=video&page=1" class="filter-link">
                        <span class="filter-icon">🎬</span>
                        <span class="filter-name">视频</span>
                        <span class="filter-count"><?php echo number_format($typeStatistics['video']); ?></span>
                    </a>
                </li>
                <li class="filter-item <?php echo $type === 'audio' ? 'active' : ''; ?>">
                    <a href="<?php echo $currentUrl; ?>&type=audio&page=1" class="filter-link">
                        <span class="filter-icon">🎵</span>
                        <span class="filter-name">音频</span>
                        <span class="filter-count"><?php echo number_format($typeStatistics['audio']); ?></span>
                    </a>
                </li>
                <li class="filter-item <?php echo $type === 'document' ? 'active' : ''; ?>">
                    <a href="<?php echo $currentUrl; ?>&type=document&page=1" class="filter-link">
                        <span class="filter-icon">📝</span>
                        <span class="filter-name">文档</span>
                        <span class="filter-count"><?php echo number_format($typeStatistics['document']); ?></span>
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
