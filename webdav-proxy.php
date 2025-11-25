<?php
/**
 * WebDAV 文件代理
 * 用于代理访问 WebDAV 本地文件夹中的文件
 */

// 定义 Typecho 根目录常量
if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(dirname(dirname(__FILE__))));
}

// 加载 Typecho
require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';
require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';

// 获取插件配置
$config = Typecho_Widget::widget('Widget_Options')->plugin('MediaLibrary');

// 检查 WebDAV 是否启用
$enableWebDAV = is_array($config->enableWebDAV)
    ? in_array('1', $config->enableWebDAV)
    : ($config->enableWebDAV == '1');

if (!$enableWebDAV) {
    header('HTTP/1.1 403 Forbidden');
    exit('WebDAV 功能未启用');
}

// 获取请求的文件路径
$requestedFile = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($requestedFile)) {
    header('HTTP/1.1 400 Bad Request');
    exit('未指定文件');
}

// 清理路径，防止目录遍历攻击
$requestedFile = str_replace(['../', '..\\'], '', $requestedFile);
$requestedFile = ltrim($requestedFile, '/\\');

// 获取 WebDAV 本地路径
$webdavLocalPath = isset($config->webdavLocalPath) ? trim($config->webdavLocalPath) : '';

if (empty($webdavLocalPath)) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('WebDAV 本地路径未配置');
}

// 构建完整文件路径
$filePath = rtrim($webdavLocalPath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $requestedFile);

// 检查文件是否存在
if (!file_exists($filePath) || !is_file($filePath)) {
    header('HTTP/1.1 404 Not Found');
    exit('文件不存在');
}

// 检查文件是否在允许的目录内
$realFilePath = realpath($filePath);
$realWebdavPath = realpath($webdavLocalPath);

if (strpos($realFilePath, $realWebdavPath) !== 0) {
    header('HTTP/1.1 403 Forbidden');
    exit('访问被拒绝');
}

// 获取文件信息
$fileSize = filesize($filePath);
$fileModified = filemtime($filePath);

// 确定 MIME 类型
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// 设置缓存头
$etag = md5_file($filePath);
$lastModified = gmdate('D, d M Y H:i:s', $fileModified) . ' GMT';

header('Last-Modified: ' . $lastModified);
header('ETag: "' . $etag . '"');
header('Cache-Control: public, max-age=31536000');

// 检查条件请求
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . $etag . '"') {
    header('HTTP/1.1 304 Not Modified');
    exit;
}

if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $fileModified) {
    header('HTTP/1.1 304 Not Modified');
    exit;
}

// 支持范围请求（用于视频播放）
$ranges = null;
if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
        $start = intval($matches[1]);
        $end = empty($matches[2]) ? $fileSize - 1 : intval($matches[2]);

        if ($start < $fileSize && $end < $fileSize && $start <= $end) {
            $ranges = [$start, $end];
        }
    }
}

// 发送文件头
header('Content-Type: ' . $mimeType);

if ($ranges) {
    // 范围请求
    list($start, $end) = $ranges;
    $length = $end - $start + 1;

    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    header('Content-Length: ' . $length);
    header('Accept-Ranges: bytes');

    // 输出部分内容
    $fp = fopen($filePath, 'rb');
    fseek($fp, $start);
    $remaining = $length;
    while ($remaining > 0 && !feof($fp)) {
        $chunk = min(8192, $remaining);
        echo fread($fp, $chunk);
        $remaining -= $chunk;
        flush();
    }
    fclose($fp);
} else {
    // 完整文件
    header('Content-Length: ' . $fileSize);
    header('Accept-Ranges: bytes');

    // 输出文件
    readfile($filePath);
}

exit;
