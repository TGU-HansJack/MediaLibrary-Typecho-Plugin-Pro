<table class="media-list-table">
    <thead>
        <tr>
            <th width="40"><input type="checkbox" class="select-all"></th>
            <th width="60">预览</th>
            <th>文件名</th>
            <th width="100">大小</th>
            <th width="100">所属文章</th>
            <th width="150">上传时间</th>
            <th width="100">操作</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($attachments)): ?>
            <?php foreach ($attachments as $attachment): ?>
                <tr data-cid="<?php echo $attachment['cid']; ?>"
                    data-url="<?php echo htmlspecialchars($attachment['url']); ?>"
                    data-type="<?php echo htmlspecialchars($attachment['mime']); ?>"
                    data-title="<?php echo htmlspecialchars($attachment['title']); ?>"
                    data-has-url="<?php echo $attachment['hasValidUrl'] ? '1' : '0'; ?>"
                    data-is-image="<?php echo $attachment['isImage'] ? '1' : '0'; ?>"
                    data-is-video="<?php echo $attachment['isVideo'] ? '1' : '0'; ?>">
                    <td data-label="选择"><input type="checkbox" value="<?php echo $attachment['cid']; ?>"></td>
                    <td data-label="预览" class="file-preview-cell">
                        <?php if ($attachment['isImage'] && $attachment['hasValidUrl']): ?>
                            <img src="<?php echo $attachment['url']; ?>" class="media-thumb" alt="">
                        <?php else: ?>
                            <?php
                            // 根据文件类型显示不同图标
                            $mimeType = $attachment['mime'];
                            $iconClass = 'fa-file';
                            if (strpos($mimeType, 'video/') === 0) {
                                $iconClass = 'fa-file-video';
                            } elseif (strpos($mimeType, 'audio/') === 0) {
                                $iconClass = 'fa-file-audio';
                            } elseif (strpos($mimeType, 'pdf') !== false) {
                                $iconClass = 'fa-file-pdf';
                            } elseif (strpos($mimeType, 'word') !== false || strpos($mimeType, 'document') !== false) {
                                $iconClass = 'fa-file-word';
                            } elseif (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'sheet') !== false) {
                                $iconClass = 'fa-file-excel';
                            } elseif (strpos($mimeType, 'powerpoint') !== false || strpos($mimeType, 'presentation') !== false) {
                                $iconClass = 'fa-file-powerpoint';
                            } elseif (strpos($mimeType, 'zip') !== false || strpos($mimeType, 'rar') !== false || strpos($mimeType, 'compressed') !== false) {
                                $iconClass = 'fa-file-archive';
                            } elseif (strpos($mimeType, 'text/') === 0) {
                                $iconClass = 'fa-file-alt';
                            }
                            ?>
                            <i class="file-type-icon fas <?php echo $iconClass; ?>"></i>
                        <?php endif; ?>
                    </td>
                    <td data-label="文件名" class="file-name-cell">
                        <span class="file-name-text"><?php echo htmlspecialchars($attachment['title']); ?></span>
                    </td>
                    <td data-label="大小" class="file-size-cell"><?php echo $attachment['size']; ?></td>
                    <td data-label="所属文章">
                        <?php if ($attachment['parent_post']['status'] === 'archived'): ?>
                            <a href="<?php echo $options->adminUrl('write-' . (0 === strpos($attachment['parent_post']['post']['type'], 'post') ? 'post' : 'page') . '.php?cid=' . $attachment['parent_post']['post']['cid']); ?>" class="post-link"><?php echo htmlspecialchars($attachment['parent_post']['post']['title']); ?></a>
                        <?php else: ?>
                            <span class="no-post">未归档</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="上传时间" class="file-time-cell"><?php echo isset($attachment['created']) ? date('Y-m-d H:i', $attachment['created']) : ''; ?></td>
                    <td data-label="操作" class="media-list-actions">
                        <button class="btn btn-xs media-info-btn" data-cid="<?php echo $attachment['cid']; ?>">详情</button>
                        <button class="btn btn-xs btn-danger media-delete-btn" data-cid="<?php echo $attachment['cid']; ?>">删除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <h3>没有找到文件</h3>
                    <p>尝试上传一些文件或调整搜索条件</p>
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
