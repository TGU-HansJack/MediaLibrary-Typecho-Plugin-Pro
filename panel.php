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

// 数据总览
$typeLabels = [
    'all' => '所有文件',
    'image' => '图片',
    'video' => '视频',
    'audio' => '音频',
    'document' => '文档'
];
$viewLabels = [
    'grid' => '网格视图',
    'list' => '列表视图'
];
$currentFilterLabel = $typeLabels[$type] ?? '所有文件';
$currentViewLabel = $viewLabels[$view] ?? '网格视图';
$currentRangeStart = $total > 0 ? (($page - 1) * $pageSize + 1) : 0;
$currentRangeEnd = $total > 0 ? min($page * $pageSize, $total) : 0;

$imagesCount = 0;
$videosCount = 0;
$documentsCount = 0;
$audioCount = 0;
$currentPageBytes = 0;

foreach ($attachments as $attachment) {
    if (!empty($attachment['isImage'])) {
        $imagesCount++;
    }
    if (!empty($attachment['isVideo'])) {
        $videosCount++;
    }
    if (!empty($attachment['isDocument'])) {
        $documentsCount++;
    }
    if (!empty($attachment['mime']) && strpos($attachment['mime'], 'audio/') === 0) {
        $audioCount++;
    }
    if (isset($attachment['attachment']['size'])) {
        $currentPageBytes += intval($attachment['attachment']['size']);
    }
}

$currentPageFootprint = $currentPageBytes > 0 ? MediaLibrary_FileOperations::formatFileSize($currentPageBytes) : '0 B';
$rangeDescription = $total > 0
    ? sprintf('当前显示 %d - %d / %d 个文件', $currentRangeStart, $currentRangeEnd, $total)
    : '暂无文件，开始上传吧';

// 计算分页
$totalPages = $total > 0 ? ceil($total / $pageSize) : 1;

$currentUrl = $options->adminUrl . 'extending.php?panel=MediaLibrary%2Fpanel.php';

// 版本号控制 - 用于强制刷新缓存
$cssVersion = '3.0.0'; // Windows 11 风格版本
?>

<link rel="stylesheet" href="<?php echo Helper::options()->pluginUrl; ?>/MediaLibrary/assets/css/panel.css?v=<?php echo $cssVersion; ?>">

<div class="main">
    <div class="body container">
        <div class="colgroup">
            <div class="col-mb-12">
                <div class="media-viewport">
                    <div class="media-library-container">
                        <div class="media-hero">
                            <div class="hero-text">
                                <span class="hero-eyebrow">Media Library</span>
                                <div class="typecho-page-title">
                                    <h2>媒体库管理</h2>
                                    <p>现代化的媒体总览，共 <?php echo number_format($total); ?> 个文件</p>
                                </div>
                                <div class="hero-meta">
                                    <span class="meta-chip">筛选：<?php echo $currentFilterLabel; ?></span>
                                    <span class="meta-chip">视图：<?php echo $currentViewLabel; ?></span>
                                    <span class="meta-chip">上传限制：<?php echo strtoupper($phpMaxFilesize); ?></span>
                                </div>
                                <p class="hero-range"><?php echo $rangeDescription; ?></p>
                            </div>
                            <div class="hero-card">
                                <span class="label">文件总数</span>
                                <strong><?php echo number_format($total); ?></strong>
                                <span class="hint">共 <?php echo $totalPages; ?> 页</span>
                            </div>
                        </div>

                        <div class="media-stat-grid">
                            <div class="stat-card">
                                <span class="label">图片</span>
                                <span class="value"><?php echo $imagesCount; ?></span>
                                <span class="hint">当前页</span>
                            </div>
                            <div class="stat-card">
                                <span class="label">视频</span>
                                <span class="value"><?php echo $videosCount; ?></span>
                                <span class="hint"><?php echo $audioCount; ?> 条音频</span>
                            </div>
                            <div class="stat-card">
                                <span class="label">文档</span>
                                <span class="value"><?php echo $documentsCount; ?></span>
                                <span class="hint">含 PDF / Office</span>
                            </div>
                            <div class="stat-card accent">
                                <span class="label">本页容量</span>
                                <span class="value"><?php echo $currentPageFootprint; ?></span>
                                <span class="hint">上传限制 <?php echo strtoupper($phpMaxFilesize); ?></span>
                            </div>
                        </div>

                        <div class="media-panel">
                            <?php include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/templates/toolbar.php'; ?>

                            <div class="media-panel-body">
                                <?php if ($view === 'grid'): ?>
                                    <?php include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/templates/grid-view.php'; ?>
                                <?php else: ?>
                                    <?php include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/templates/list-view.php'; ?>
                                <?php endif; ?>
                            </div>

                            <?php include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/templates/pagination.php'; ?>
                        </div>
                    </div>
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
<script src="<?php echo Helper::options()->pluginUrl; ?>/MediaLibrary/assets/js/echarts.min.js?v=<?php echo $cssVersion; ?>"></script>
<script src="<?php echo Helper::options()->pluginUrl; ?>/MediaLibrary/assets/js/image-editor.js?v=<?php echo $cssVersion; ?>"></script>

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

<script src="<?php echo Helper::options()->pluginUrl; ?>/MediaLibrary/assets/js/panel.js?v=<?php echo $cssVersion; ?>"></script>

<?php
include 'copyright.php';
include 'common-js.php';
include 'table-js.php';
include 'footer.php';
?>
