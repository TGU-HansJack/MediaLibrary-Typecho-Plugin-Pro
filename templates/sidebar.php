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
                        <i class="filter-icon fas fa-file"></i>
                        <span class="filter-name">所有文件</span>
                        <span class="filter-count"><?php echo number_format($total); ?></span>
                    </a>
                </li>
                <li class="filter-item <?php echo $type === 'image' ? 'active' : ''; ?>">
                    <a href="<?php echo $currentUrl; ?>&type=image&page=1" class="filter-link">
                        <i class="filter-icon fas fa-image"></i>
                        <span class="filter-name">图片</span>
                        <span class="filter-count"><?php echo number_format($typeStatistics['image']); ?></span>
                    </a>
                </li>
                <li class="filter-item <?php echo $type === 'video' ? 'active' : ''; ?>">
                    <a href="<?php echo $currentUrl; ?>&type=video&page=1" class="filter-link">
                        <i class="filter-icon fas fa-video"></i>
                        <span class="filter-name">视频</span>
                        <span class="filter-count"><?php echo number_format($typeStatistics['video']); ?></span>
                    </a>
                </li>
                <li class="filter-item <?php echo $type === 'audio' ? 'active' : ''; ?>">
                    <a href="<?php echo $currentUrl; ?>&type=audio&page=1" class="filter-link">
                        <i class="filter-icon fas fa-music"></i>
                        <span class="filter-name">音频</span>
                        <span class="filter-count"><?php echo number_format($typeStatistics['audio']); ?></span>
                    </a>
                </li>
                <li class="filter-item <?php echo $type === 'document' ? 'active' : ''; ?>">
                    <a href="<?php echo $currentUrl; ?>&type=document&page=1" class="filter-link">
                        <i class="filter-icon fas fa-file-alt"></i>
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

    <!-- WebDAV 同步管理 -->
    <?php if (!empty($configOptions['enableWebDAV']) && $configOptions['enableWebDAV']): ?>
    <div class="sidebar-section webdav-manager-section">
        <h3 class="sidebar-title">
            WebDAV 同步
            <span class="webdav-status-indicator <?php echo !empty($webdavStatus['success']) ? 'status-ok' : 'status-error'; ?>"
                  title="<?php echo !empty($webdavStatus['message']) ? htmlspecialchars($webdavStatus['message']) : '未连接'; ?>">
            </span>
        </h3>
        <div class="sidebar-content">
            <!-- 同步状态 -->
            <div class="webdav-sync-status">
                <div class="status-item">
                    <span class="status-label">同步模式</span>
                    <span class="status-value">
                        <?php
                        $syncMode = isset($configOptions['webdavSyncMode']) ? $configOptions['webdavSyncMode'] : 'manual';
                        $syncModeLabels = [
                            'manual' => '手动',
                            'onupload' => '自动',
                            'scheduled' => '定时'
                        ];
                        echo $syncModeLabels[$syncMode];
                        ?>
                    </span>
                </div>
                <?php if (!empty($configOptions['webdavSyncEnabled'])): ?>
                <div class="status-item">
                    <span class="status-label">自动同步</span>
                    <span class="status-value status-enabled">已启用</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- 同步操作按钮 -->
            <div class="webdav-actions">
                <button type="button" id="webdav-sync-all-btn" class="webdav-action-btn primary-btn" title="同步所有本地文件到 WebDAV">
                    <i class="btn-icon fas fa-sync"></i>
                    <span class="btn-text">批量同步</span>
                </button>

                <button type="button" id="webdav-test-connection-btn" class="webdav-action-btn secondary-btn" title="测试 WebDAV 连接">
                    <i class="btn-icon fas fa-plug"></i>
                    <span class="btn-text">测试连接</span>
                </button>
            </div>

            <!-- 同步进度 -->
            <div id="webdav-sync-progress" class="webdav-sync-progress" style="display: none;">
                <div class="progress-header">
                    <span class="progress-title">同步中...</span>
                    <span class="progress-count">0/0</span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: 0%;"></div>
                </div>
                <div class="progress-details">
                    <span class="progress-detail">准备同步...</span>
                </div>
            </div>

            <!-- 同步结果 -->
            <div id="webdav-sync-result" class="webdav-sync-result" style="display: none;">
                <div class="result-header">
                    <span class="result-icon"></span>
                    <span class="result-title"></span>
                </div>
                <div class="result-details"></div>
            </div>

            <!-- 路径信息 -->
            <div class="webdav-info">
                <div class="info-item">
                    <span class="info-label">本地路径</span>
                    <span class="info-value" title="<?php echo htmlspecialchars($configOptions['webdavLocalPath']); ?>">
                        <?php echo htmlspecialchars(basename($configOptions['webdavLocalPath'])); ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">远程服务器</span>
                    <span class="info-value" title="<?php echo htmlspecialchars($configOptions['webdavEndpoint']); ?>">
                        <?php
                        $endpoint = parse_url($configOptions['webdavEndpoint']);
                        echo htmlspecialchars($endpoint['host'] ?? '未配置');
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
