<?php
$statusClass = $webdavStatus['connected'] ? 'ok' : ($webdavStatus['configured'] ? 'warn' : 'info');
?>
<div class="webdav-panel" id="webdav-panel" data-configured="<?php echo $webdavStatus['configured'] ? '1' : '0'; ?>">
    <div class="webdav-header">
        <div class="webdav-title">
            <h3>WebDAV 文件管理</h3>
            <p class="webdav-status webdav-status-<?php echo $statusClass; ?>">
                <?php echo htmlspecialchars($webdavStatus['message']); ?>
            </p>
        </div>
        <?php if ($webdavStatus['configured']): ?>
            <div class="webdav-actions">
                <button type="button" class="btn ghost" id="webdav-refresh">刷新</button>
                <button type="button" class="btn ghost" id="webdav-up">上一级</button>
                <button type="button" class="btn ghost" id="webdav-new-folder">新建文件夹</button>
                <label class="btn ghost webdav-upload-label" for="webdav-upload-input">
                    上传文件
                    <input type="file" id="webdav-upload-input" multiple>
                </label>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($webdavStatus['configured']): ?>
        <!-- 同步控制面板 -->
        <?php if ($configOptions['webdavSyncEnabled']): ?>
            <div class="webdav-sync-panel">
                <div class="webdav-sync-header">
                    <span class="webdav-sync-title">📤 本地到 WebDAV 同步</span>
                    <span class="webdav-sync-mode">模式：<?php
                        $modes = [
                            'manual' => '手动同步',
                            'onupload' => '上传时自动',
                            'scheduled' => '定时同步'
                        ];
                        echo $modes[$configOptions['webdavSyncMode']] ?? '未知';
                    ?></span>
                </div>
                <div class="webdav-sync-actions">
                    <button type="button" class="btn btn-s" id="webdav-sync-all">批量同步所有文件</button>
                    <span class="webdav-sync-tip">同步目标：<?php echo htmlspecialchars($configOptions['webdavSyncPath']); ?></span>
                </div>
                <div id="webdav-sync-progress" class="webdav-sync-progress" style="display:none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="webdav-sync-progress-fill" style="width:0%"></div>
                    </div>
                    <div class="progress-text" id="webdav-sync-progress-text">准备同步...</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="webdav-meta">
            <span>当前路径：<strong id="webdav-current-path" data-root="<?php echo htmlspecialchars($webdavStatus['root']); ?>">/</strong></span>
            <span id="webdav-feedback" class="webdav-feedback"></span>
        </div>
        <div class="webdav-list" id="webdav-list">
            <div class="webdav-empty">正在连接 WebDAV ...</div>
        </div>
    <?php else: ?>
        <div class="webdav-empty">
            <p>尚未完成 WebDAV 配置，请在插件设置中填写服务器地址和凭证。</p>
        </div>
    <?php endif; ?>
</div>
