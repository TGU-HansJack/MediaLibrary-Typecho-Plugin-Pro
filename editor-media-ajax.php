<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 获取数据库实例
$db = Typecho_Db::get();
$options = Typecho_Widget::widget('Widget_Options');

// 获取最近20个附件
$attachments = $db->fetchAll($db->select()->from('table.contents')
    ->where('table.contents.type = ?', 'attachment')
    ->where('table.contents.status = ?', 'publish')
    ->order('table.contents.created', Typecho_Db::SORT_DESC)
    ->limit(20));

// 处理附件数据
$mediaItems = [];
foreach ($attachments as $attachment) {
    $textData = isset($attachment['text']) ? $attachment['text'] : '';
    
    $attachmentData = [];
    if (!empty($textData)) {
        $unserialized = @unserialize($textData);
        if (is_array($unserialized)) {
            $attachmentData = $unserialized;
        }
    }
    
    $isImage = isset($attachmentData['mime']) && strpos($attachmentData['mime'], 'image/') === 0;
    $url = '';
    
    if (isset($attachmentData['path'])) {
        $url = Typecho_Common::url($attachmentData['path'], $options->siteUrl);
    }
    
    $mediaItems[] = [
        'cid' => $attachment['cid'],
        'title' => isset($attachment['title']) ? $attachment['title'] : '未命名文件',
        'url' => $url,
        'isImage' => $isImage,
        'mime' => isset($attachmentData['mime']) ? $attachmentData['mime'] : 'unknown'
    ];
}

// 输出HTML
if (empty($mediaItems)) {
    echo '<div class="empty-state">没有媒体文件，请上传</div>';
} else {
    foreach ($mediaItems as $item) {
        echo '<div class="editor-media-item" data-cid="' . $item['cid'] . '" data-url="' . htmlspecialchars($item['url']) . '" data-is-image="' . ($item['isImage'] ? 1 : 0) . '" data-title="' . htmlspecialchars($item['title']) . '">';
        
        if ($item['isImage']) {
            echo '<div class="media-preview"><img src="' . $item['url'] . '" alt=""></div>';
        } else {
            echo '<div class="media-preview"><div class="file-icon">' . strtoupper(pathinfo($item['title'], PATHINFO_EXTENSION)) . '</div></div>';
        }
        
        echo '<div class="media-title">' . htmlspecialchars($item['title']) . '</div>';
        echo '</div>';
    }
}
?>
