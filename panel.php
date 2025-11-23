<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

include 'header.php';
include 'menu.php';

// 在文件开头添加错误处理
error_reporting(E_ALL);
ini_set('display_errors', 0); // 生产环境关闭错误显示
ini_set('log_errors', 1);

// 引入必要的组件
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/FileOperations.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/PanelHelper.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/AjaxHandler.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/ImageProcessing.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/VideoProcessing.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/ExifPrivacy.php';

// 获取数据库实例
$db = Typecho_Db::get();

// 获取参数
$page = max(1, intval($request->get('page', 1)));
$keywords = trim($request->get('keywords', ''));
$type = $request->get('type', 'all');
$view = $request->get('view', 'grid');

// 处理 AJAX 请求
if ($request->get('action')) {
    MediaLibrary_AjaxHandler::handleRequest($request, $db, $options, $user);
    exit;
}

// 获取插件配置
$configOptions = MediaLibrary_PanelHelper::getPluginConfig();
extract($configOptions);

// 获取系统上传限制
$phpMaxFilesize = function_exists('ini_get') ? trim(ini_get('upload_max_filesize')) : '2M';
if (preg_match("/^([0-9]+)([a-z]{1,2})$/i", $phpMaxFilesize, $matches)) {
    $phpMaxFilesize = strtolower($matches[1] . $matches[2] . (1 == strlen($matches[2]) ? 'b' : ''));
}

// 固定每页显示数量
$pageSize = 20;

// 获取媒体列表
$mediaListData = MediaLibrary_PanelHelper::getMediaList($db, $page, $pageSize, $keywords, $type);
$attachments = $mediaListData['attachments'];
$total = $mediaListData['total'];

// 计算分页
$totalPages = $total > 0 ? ceil($total / $pageSize) : 1;

$currentUrl = $options->adminUrl . 'extending.php?panel=MediaLibrary%2Fpanel.php';
?>

<link rel="stylesheet" href="<?php echo Helper::options()->pluginUrl; ?>/MediaLibrary/assets/css/panel.css">

<div class="main">
    <div class="body container">
        <div class="colgroup">
            <div class="col-mb-12">
                <div class="media-library-container">
                    <div class="typecho-page-title">
                        <h2>媒体库管理</h2>
                        <p>管理您的媒体文件 - 共 <?php echo $total; ?> 个文件</p>
                    </div>
                    
                    <?php include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/templates/toolbar.php'; ?>
                    
                    <?php if ($view === 'grid'): ?>
                        <?php include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/templates/grid-view.php'; ?>
                    <?php else: ?>
                        <?php include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/templates/list-view.php'; ?>
                    <?php endif; ?>
                    
                    <?php include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/templates/pagination.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/templates/modals.php'; ?>

<!-- 引入 plupload -->
<script src="<?php $options->adminStaticUrl('js', 'moxie.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'plupload.js'); ?>"></script>

<!-- 引入 jQuery -->
<script src="<?php $options->adminStaticUrl('js', 'jquery.js'); ?>"></script>

<!-- 引入ECharts -->
<script src="<?php echo Helper::options()->pluginUrl; ?>/MediaLibrary/assets/js/echarts.min.js"></script>
<script src="<?php echo Helper::options()->pluginUrl; ?>/MediaLibrary/assets/js/image-editor.js"></script>

<script>
window.mediaLibraryCurrentUrl = '<?php echo $currentUrl; ?>';
window.mediaLibraryKeywords = '<?php echo addslashes($keywords); ?>';
window.mediaLibraryType = '<?php echo $type; ?>';
window.mediaLibraryView = '<?php echo $view; ?>';
window.mediaLibraryConfig = {
    enableGetID3: <?php echo $enableGetID3 ? 'true' : 'false'; ?>,
    enableExif: <?php echo $enableExif ? 'true' : 'false'; ?>,
    enableGD: <?php echo $enableGD ? 'true' : 'false'; ?>,
    enableImageMagick: <?php echo $enableImageMagick ? 'true' : 'false'; ?>,
    enableFFmpeg: <?php echo $enableFFmpeg ? 'true' : 'false'; ?>,
    enableVideoCompress: <?php echo $enableVideoCompress ? 'true' : 'false'; ?>,
    gdQuality: <?php echo $gdQuality; ?>,
    videoQuality: <?php echo $videoQuality; ?>,
    videoCodec: '<?php echo $videoCodec; ?>',
    phpMaxFilesize: '<?php echo $phpMaxFilesize; ?>',
    allowedTypes: 'jpg,jpeg,png,gif,bmp,webp,svg,mp4,avi,mov,wmv,flv,mp3,wav,ogg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar,avif',
    adminStaticUrl: '<?php echo $options->adminStaticUrl; ?>',
    pluginUrl: '<?php echo Helper::options()->pluginUrl; ?>/MediaLibrary',
    hasExifTool: <?php echo MediaLibrary_ExifPrivacy::isExifToolAvailable() ? 'true' : 'false'; ?>,
    hasPhpExif: <?php echo extension_loaded('exif') ? 'true' : 'false'; ?>
};
</script>

<script src="<?php echo Helper::options()->pluginUrl; ?>/MediaLibrary/assets/js/panel.js"></script>

<?php
include 'copyright.php';
include 'common-js.php';
include 'table-js.php';
include 'footer.php';
?>
