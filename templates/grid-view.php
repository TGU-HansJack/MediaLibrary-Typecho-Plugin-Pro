<?php if (!empty($attachments)): ?>
    <div class="media-grid">
        <?php foreach ($attachments as $attachment): ?>
            <div class="media-item" data-cid="<?php echo $attachment['cid']; ?>" 
                 data-url="<?php echo htmlspecialchars($attachment['url']); ?>" 
                 data-type="<?php echo htmlspecialchars($attachment['mime']); ?>"
                 data-title="<?php echo htmlspecialchars($attachment['title']); ?>"
                 data-has-url="<?php echo $attachment['hasValidUrl'] ? '1' : '0'; ?>"
                 data-is-image="<?php echo $attachment['isImage'] ? '1' : '0'; ?>"
                 data-is-video="<?php echo $attachment['isVideo'] ? '1' : '0'; ?>">
                <div class="media-checkbox">
                    <input type="checkbox" value="<?php echo $attachment['cid']; ?>">
                </div>
                
                <?php if ($attachment['isImage'] && $attachment['hasValidUrl']): ?>
                    <div class="media-preview">
                        <img src="<?php echo $attachment['url']; ?>" alt="<?php echo htmlspecialchars($attachment['title']); ?>">
                    </div>
                <?php else: ?>
                    <div class="media-preview">
                        <div class="file-icon">
                            <?php
                            $mime = $attachment['mime'];
                            if (strpos($mime, 'video/') === 0) echo 'VIDEO';
                            elseif (strpos($mime, 'audio/') === 0) echo 'AUDIO';
                            elseif (strpos($mime, 'application/pdf') === 0) echo 'PDF';
                            elseif (strpos($mime, 'text/') === 0) echo 'TEXT';
                            elseif (strpos($mime, 'application/zip') === 0 || strpos($mime, 'application/x-rar') === 0) echo 'ZIP';
                            elseif (strpos($mime, 'application/msword') === 0 || strpos($mime, 'application/vnd.openxmlformats-officedocument.wordprocessingml') === 0) echo 'DOC';
                            elseif (strpos($mime, 'application/vnd.ms-excel') === 0 || strpos($mime, 'application/vnd.openxmlformats-officedocument.spreadsheetml') === 0) echo 'XLS';
                            elseif (strpos($mime, 'application/vnd.ms-powerpoint') === 0 || strpos($mime, 'application/vnd.openxmlformats-officedocument.presentationml') === 0) echo 'PPT';
                            else echo 'FILE';
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="media-actions">
                    <button class="btn btn-small media-info-btn" data-cid="<?php echo $attachment['cid']; ?>" title="详情">详情</button>
                    <button class="btn btn-small btn-danger media-delete-btn" data-cid="<?php echo $attachment['cid']; ?>" title="删除">删除</button>
                </div>
                
                <div class="media-info">
                    <div class="media-title" title="<?php echo htmlspecialchars($attachment['title']); ?>">
                        <?php echo htmlspecialchars($attachment['title']); ?>
                    </div>
                    <div class="media-meta">
                        <?php echo $attachment['size']; ?> • <?php echo isset($attachment['modified']) ? date('m/d', $attachment['modified']) : ''; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <h3>没有找到文件</h3>
        <p>尝试上传一些文件或调整搜索条件</p>
        <button class="btn btn-primary" id="upload-btn-empty">上传文件</button>
    </div>
<?php endif; ?>
