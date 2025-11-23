<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$options = Helper::options();
$pluginUrl = $options->pluginUrl . '/MediaLibrary';
?>

<div class="media-library-editor">
    <div class="media-toolbar">
        <button class="btn btn-primary" id="editor-upload-btn">上传文件</button>
        <button class="btn" id="editor-insert-selected" style="display:none;">插入选中</button>
    </div>
    
    <div class="media-grid editor-media-grid">
        <!-- 动态加载内容 -->
        <div class="loading">加载中...</div>
    </div>
</div>

<script>
(function($) {
    // 加载媒体库
    $.get('<?php echo $options->adminUrl; ?>extending.php?panel=MediaLibrary/editor-media-ajax.php', function(data) {
        $('.editor-media-grid').html(data);
    });
    
    // 上传按钮
    $('#editor-upload-btn').on('click', function() {
        // 显示上传弹窗
    });
    
    // 选择和插入功能
    $(document).on('click', '.editor-media-item', function() {
        $(this).toggleClass('selected');
        updateInsertButton();
    });
    
    // 更新插入按钮状态
    function updateInsertButton() {
        var selected = $('.editor-media-item.selected').length;
        $('#editor-insert-selected').toggle(selected > 0);
    }
    
    // 插入到编辑器
    $('#editor-insert-selected').on('click', function() {
        $('.editor-media-item.selected').each(function() {
            var url = $(this).data('url');
            var isImage = $(this).data('is-image') === 1;
            
            if (isImage) {
                insertContentToEditor('<img src="' + url + '" alt="" />');
            } else {
                insertContentToEditor('<a href="' + url + '">' + $(this).data('title') + '</a>');
            }
        });
        
        // 清除选择
        $('.editor-media-item.selected').removeClass('selected');
        updateInsertButton();
    });
    
    // 插入内容到编辑器
    function insertContentToEditor(content) {
        // 检测是否有wangEditor
        if (window.editor && window.editor.txt) {
            window.editor.txt.append(content);
            return;
        }
        
        // 检测是否有Markdown编辑器
        if (window.$('#text').length > 0) {
            var textarea = $('#text');
            var caretPos = textarea[0].selectionStart;
            var textAreaTxt = textarea.val();
            var txtToAdd = content;
            textarea.val(textAreaTxt.substring(0, caretPos) + txtToAdd + textAreaTxt.substring(caretPos));
            return;
        }
    }
})(jQuery);
</script>
