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
