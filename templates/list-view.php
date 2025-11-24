<table class="media-list-table">
    <thead>
        <tr>
            <th width="40"><input type="checkbox" class="select-all"></th>
            <th width="80">预览</th>
            <th>文件名</th>
            <th width="100">大小</th>
            <th width="150">修改时间</th>
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
                    <td data-label="预览">
                        <?php if ($attachment['isImage'] && $attachment['hasValidUrl']): ?>
                            <img src="<?php echo $attachment['url']; ?>" class="media-thumb" alt="">
                        <?php else: ?>
                            <div style="font-size: 12px; color: #666;">FILE</div>
                        <?php endif; ?>
                    </td>
                    <td data-label="文件名"><?php echo htmlspecialchars($attachment['title']); ?></td>
                    <td data-label="大小"><?php echo $attachment['size']; ?></td>
                    <td data-label="修改时间"><?php echo isset($attachment['modified']) ? date('Y-m-d H:i', $attachment['modified']) : ''; ?></td>
                    <td data-label="操作" class="media-list-actions">
                        <button class="btn btn-small media-info-btn" data-cid="<?php echo $attachment['cid']; ?>">详情</button>
                        <button class="btn btn-small btn-danger media-delete-btn" data-cid="<?php echo $attachment['cid']; ?>">删除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" class="empty-state">
                    <h3>没有找到文件</h3>
                    <p>尝试上传一些文件或调整搜索条件</p>
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
