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
$storage = $request->get('storage', 'all');

// 处理 AJAX 请求
if ($request->get('action')) {
    MediaLibrary_AjaxHandler::handleRequest($request, $db, $options, $user);
    exit;
}

// 获取插件配置
$configOptions = MediaLibrary_PanelHelper::getPluginConfig();
extract($configOptions);
$webdavStatus = MediaLibrary_PanelHelper::getWebDAVStatus($configOptions);
$storageStatusList = MediaLibrary_PanelHelper::getStorageStatusList($webdavStatus);
$showWebDAVManager = !empty($webdavStatus['enabled']);

// 获取系统上传限制
$phpMaxFilesize = function_exists('ini_get') ? trim(ini_get('upload_max_filesize')) : '2M';
if (preg_match("/^([0-9]+)([a-z]{1,2})$/i", $phpMaxFilesize, $matches)) {
    $phpMaxFilesize = strtolower($matches[1] . $matches[2] . (1 == strlen($matches[2]) ? 'b' : ''));
}

// 固定每页显示数量
$pageSize = 20;

// 获取媒体列表
$mediaListData = MediaLibrary_PanelHelper::getMediaList($db, $page, $pageSize, $keywords, $type, $storage);
$attachments = $mediaListData['attachments'];
$total = $mediaListData['total'];

// 获取各类型文件的统计数据
$typeStatistics = MediaLibrary_PanelHelper::getTypeStatistics($db, $storage);

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
$cssVersion = '3.3.3'; // 修复底部分页栏覆盖左侧栏问题
?>

<link rel="stylesheet" href="<?php echo Helper::options()->pluginUrl; ?>/MediaLibrary/assets/css/panel.css?v=<?php echo $cssVersion; ?>">

<div class="main">
    <div class="body container">
        <div class="colgroup">
            <div class="col-mb-12">
                <div class="media-viewport">
                    <div class="media-library-container">
                        <!-- 标题栏 -->
                        <div class="typecho-page-title">
                            <h2>媒体库管理</h2>
                            <p>现代化的媒体总览，共 <?php echo number_format($total); ?> 个文件 | <?php echo $rangeDescription; ?></p>
                        </div>

                        <!-- 主内容区域：左侧边栏 + 右侧内容 -->
                        <div class="media-layout">
                            <!-- 左侧侧栏 -->
                            <?php include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/templates/sidebar.php'; ?>

                            <!-- 右侧主内容 -->
                            <div class="media-main">
                                <div class="media-panel">
                                    <?php include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/templates/toolbar.php'; ?>

                                    <div class="media-panel-body">
                                        <?php if ($view === 'grid'): ?>
                                            <?php include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/templates/grid-view.php'; ?>
                                        <?php else: ?>
                                            <?php include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/templates/list-view.php'; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 固定底部分页 -->
                        <div class="media-pagination-fixed">
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
window.mediaLibraryStorage = '<?php echo $storage; ?>';
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
    hasPhpExif: <?php echo extension_loaded('exif') ? 'true' : 'false'; ?>,
    enableWebDAV: <?php echo $webdavStatus['enabled'] ? 'true' : 'false'; ?>,
    webdavConfigured: <?php echo $webdavStatus['configured'] ? 'true' : 'false'; ?>,
    webdavConnected: <?php echo $webdavStatus['connected'] ? 'true' : 'false'; ?>,
    webdavRoot: '<?php echo addslashes($webdavStatus['root']); ?>'
};
</script>

<script src="<?php echo Helper::options()->pluginUrl; ?>/MediaLibrary/assets/js/panel.js?v=<?php echo $cssVersion; ?>"></script>

<?php
include 'common-js.php';
include 'table-js.php';
?>
