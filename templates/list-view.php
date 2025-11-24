<table class="media-list-table">
    <thead>
        <tr>
            <th width="40"><input type="checkbox" class="select-all"></th>
            <th width="80">预览</th>
            <th>文件名</th>
            <th width="100">大小</th>
            <th width="100">文件来源</th>
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
                    <td data-label="预览">
                        <?php if ($attachment['isImage'] && $attachment['hasValidUrl']): ?>
                            <img src="<?php echo $attachment['url']; ?>" class="media-thumb" alt="">
                        <?php else: ?>
                            <div style="font-size: 12px; color: #666;">FILE</div>
                        <?php endif; ?>
                    </td>
                    <td data-label="文件名"><?php echo htmlspecialchars($attachment['title']); ?></td>
                    <td data-label="大小"><?php echo $attachment['size']; ?></td>
                    <td data-label="文件来源">
                        <span class="media-source-badge" data-source="<?php echo $attachment['source']; ?>">
                            <?php echo $attachment['sourceLabel']; ?>
                        </span>
                    </td>
                    <td data-label="所属文章">
                        <?php if ($attachment['parent_post']['status'] === 'archived'): ?>
                            <a href="<?php echo $options->adminUrl('write-' . (0 === strpos($attachment['parent_post']['post']['type'], 'post') ? 'post' : 'page') . '.php?cid=' . $attachment['parent_post']['post']['cid']); ?>" style="color: #0073aa;"><?php echo htmlspecialchars($attachment['parent_post']['post']['title']); ?></a>
                        <?php else: ?>
                            <span style="color: #999;">未归档</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="上传时间"><?php echo isset($attachment['created']) ? date('Y-m-d H:i', $attachment['created']) : ''; ?></td>
                    <td data-label="操作" class="media-list-actions">
                        <button class="btn btn-small media-info-btn" data-cid="<?php echo $attachment['cid']; ?>">详情</button>
                        <button class="btn btn-small btn-danger media-delete-btn" data-cid="<?php echo $attachment['cid']; ?>">删除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" class="empty-state">
                    <h3>没有找到文件</h3>
                    <p>尝试上传一些文件或调整搜索条件</p>
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
