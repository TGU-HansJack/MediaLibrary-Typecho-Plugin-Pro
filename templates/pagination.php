<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="#" onclick="return goToPage(<?php echo $page - 1; ?>, event)">« 上一页</a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <?php if ($i === $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="#" onclick="return goToPage(<?php echo $i; ?>, event)"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
            <a href="#" onclick="return goToPage(<?php echo $page + 1; ?>, event)">下一页 »</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
