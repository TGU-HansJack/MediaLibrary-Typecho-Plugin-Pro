<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/Logger.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/FileOperations.php';

/**
 * 本地文件系统管理类
 * 提供直接从文件系统读取和管理本地上传文件的功能，不依赖数据库
 */
class MediaLibrary_LocalFileManager
{
    private $localPath;
    private $siteUrl;
    private $uploadDir;

    /**
     * 构造函数
     *
     * @param string $localPath 本地上传目录路径
     * @param string $siteUrl 站点 URL
     */
    public function __construct($localPath = null, $siteUrl = null)
    {
        // 如果未指定本地路径，使用 Typecho 默认上传目录
        if ($localPath === null) {
            $this->uploadDir = defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : '/usr/uploads';
            $this->localPath = __TYPECHO_ROOT_DIR__ . $this->uploadDir;
        } else {
            $this->localPath = rtrim($localPath, '/\\');
            $this->uploadDir = str_replace(__TYPECHO_ROOT_DIR__, '', $this->localPath);
        }

        // 确保路径存在
        if (!is_dir($this->localPath)) {
            throw new Exception('本地上传目录不存在: ' . $this->localPath);
        }

        // 获取站点 URL
        if ($siteUrl === null) {
            try {
                $this->siteUrl = Helper::options()->siteUrl;
            } catch (Exception $e) {
                $this->siteUrl = '';
            }
        } else {
            $this->siteUrl = rtrim($siteUrl, '/');
        }
    }

    /**
     * 获取本地路径
     *
     * @return string
     */
    public function getLocalPath()
    {
        return $this->localPath;
    }

    /**
     * 列出本地文件夹中的文件
     *
     * @param string $subPath 子路径（相对于上传目录）
     * @param bool $recursive 是否递归获取子目录文件
     * @return array 文件列表
     */
    public function listLocalFiles($subPath = '', $recursive = false)
    {
        $fullPath = $this->localPath;
        if (!empty($subPath)) {
            $fullPath .= DIRECTORY_SEPARATOR . trim($subPath, '/\\');
        }

        if (!is_dir($fullPath)) {
            return [];
        }

        $items = [];

        if ($recursive) {
            $this->scanDirectoryRecursive($fullPath, $subPath, $items);
        } else {
            $this->scanDirectorySingle($fullPath, $subPath, $items);
        }

        return $items;
    }

    /**
     * 扫描单层目录
     *
     * @param string $fullPath 完整路径
     * @param string $subPath 子路径
     * @param array &$items 文件列表
     */
    private function scanDirectorySingle($fullPath, $subPath, &$items)
    {
        $entries = scandir($fullPath);

        foreach ($entries as $entry) {
            // 跳过特殊目录和隐藏文件
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                continue;
            }

            $entryPath = $fullPath . DIRECTORY_SEPARATOR . $entry;
            $relativePath = empty($subPath) ? $entry : $subPath . '/' . $entry;

            $fileInfo = $this->getFileInfo($entryPath, $relativePath);
            if ($fileInfo) {
                $items[] = $fileInfo;
            }
        }

        // 按类型和名称排序（目录在前）
        usort($items, function($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcmp($a['name'], $b['name']);
        });
    }

    /**
     * 递归扫描目录
     *
     * @param string $fullPath 完整路径
     * @param string $subPath 子路径
     * @param array &$items 文件列表
     */
    private function scanDirectoryRecursive($fullPath, $subPath, &$items)
    {
        $entries = scandir($fullPath);

        foreach ($entries as $entry) {
            // 跳过特殊目录和隐藏文件
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                continue;
            }

            $entryPath = $fullPath . DIRECTORY_SEPARATOR . $entry;
            $relativePath = empty($subPath) ? $entry : $subPath . '/' . $entry;

            if (is_dir($entryPath)) {
                // 递归扫描子目录
                $this->scanDirectoryRecursive($entryPath, $relativePath, $items);
            } else {
                $fileInfo = $this->getFileInfo($entryPath, $relativePath);
                if ($fileInfo) {
                    $items[] = $fileInfo;
                }
            }
        }
    }

    /**
     * 获取文件信息
     *
     * @param string $filePath 文件完整路径
     * @param string $relativePath 相对路径
     * @return array|null 文件信息
     */
    private function getFileInfo($filePath, $relativePath)
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $isDir = is_dir($filePath);
        $size = $isDir ? 0 : filesize($filePath);
        $mtime = filemtime($filePath);
        $name = basename($filePath);

        // 获取 MIME 类型
        $mime = 'application/octet-stream';
        if (!$isDir && extension_loaded('fileinfo')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $filePath);
            if ($detectedMime) {
                $mime = $detectedMime;
            }
            finfo_close($finfo);
        } elseif (!$isDir) {
            $mime = $this->guessMimeType($name);
        }

        return [
            'name' => $name,
            'path' => '/' . str_replace('\\', '/', $relativePath),
            'type' => $isDir ? 'directory' : 'file',
            'size' => $size,
            'modified' => $mtime,
            'modified_format' => date('Y-m-d H:i:s', $mtime),
            'mime' => $isDir ? 'directory' : $mime
        ];
    }

    /**
     * 根据文件名猜测 MIME 类型
     *
     * @param string $filename 文件名
     * @return string MIME 类型
     */
    private function guessMimeType($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $mimeTypes = [
            // 图片
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'avif' => 'image/avif',
            'ico' => 'image/x-icon',
            // 视频
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv',
            'mkv' => 'video/x-matroska',
            'webm' => 'video/webm',
            // 音频
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            'm4a' => 'audio/mp4',
            // 文档
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            // 压缩文件
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip'
        ];

        return isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'application/octet-stream';
    }

    /**
     * 获取文件详细信息
     *
     * @param string $relativePath 相对路径
     * @return array|null 文件详细信息
     */
    public function getFileDetails($relativePath)
    {
        $fullPath = $this->localPath . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);

        if (!file_exists($fullPath)) {
            return null;
        }

        $basicInfo = $this->getFileInfo($fullPath, $relativePath);

        // 添加额外的详细信息
        if ($basicInfo && $basicInfo['type'] === 'file') {
            $basicInfo['full_path'] = $fullPath;
            $basicInfo['permissions'] = substr(sprintf('%o', fileperms($fullPath)), -4);
            $basicInfo['readable'] = is_readable($fullPath);
            $basicInfo['writable'] = is_writable($fullPath);

            // 构建 URL
            $basicInfo['url'] = $this->buildFileUrl($relativePath);
        }

        return $basicInfo;
    }

    /**
     * 构建文件的访问 URL
     *
     * @param string $relativePath 相对路径
     * @return string 文件 URL
     */
    public function buildFileUrl($relativePath)
    {
        // 直接使用站点 URL + 上传目录路径
        $webPath = str_replace('\\', '/', $this->uploadDir);
        $webPath = trim($webPath, '/');
        $relativePath = ltrim($relativePath, '/');

        if (!empty($this->siteUrl)) {
            return $this->siteUrl . '/' . $webPath . '/' . $relativePath;
        }

        return '/' . $webPath . '/' . $relativePath;
    }

    /**
     * 删除文件或目录
     *
     * @param string $relativePath 相对路径
     * @return bool 是否成功
     */
    public function delete($relativePath)
    {
        $fullPath = $this->localPath . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);

        // 安全检查：确保路径在上传目录内
        $realPath = realpath($fullPath);
        $realUploadPath = realpath($this->localPath);

        if ($realPath === false || strpos($realPath, $realUploadPath) !== 0) {
            MediaLibrary_Logger::log('local_file_delete', '删除失败：路径不安全', [
                'path' => $relativePath
            ], 'error');
            return false;
        }

        if (!file_exists($fullPath)) {
            return false;
        }

        try {
            if (is_dir($fullPath)) {
                return $this->deleteDirectory($fullPath);
            } else {
                $result = @unlink($fullPath);
                if ($result) {
                    MediaLibrary_Logger::log('local_file_delete', '删除文件成功', [
                        'path' => $relativePath
                    ]);
                }
                return $result;
            }
        } catch (Exception $e) {
            MediaLibrary_Logger::log('local_file_delete', '删除失败: ' . $e->getMessage(), [
                'path' => $relativePath
            ], 'error');
            return false;
        }
    }

    /**
     * 递归删除目录
     *
     * @param string $dir 目录路径
     * @return bool 是否成功
     */
    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $entries = scandir($dir);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($entryPath)) {
                $this->deleteDirectory($entryPath);
            } else {
                @unlink($entryPath);
            }
        }

        return @rmdir($dir);
    }

    /**
     * 创建文件夹
     *
     * @param string $relativePath 相对路径
     * @return bool 是否成功
     */
    public function createDirectory($relativePath)
    {
        $fullPath = $this->localPath . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);

        if (is_dir($fullPath)) {
            return true;
        }

        try {
            $result = @mkdir($fullPath, 0755, true);
            if ($result) {
                MediaLibrary_Logger::log('local_file_create_dir', '创建目录成功', [
                    'path' => $relativePath
                ]);
            }
            return $result;
        } catch (Exception $e) {
            MediaLibrary_Logger::log('local_file_create_dir', '创建目录失败: ' . $e->getMessage(), [
                'path' => $relativePath
            ], 'error');
            return false;
        }
    }

    /**
     * 获取文件统计信息
     *
     * @param string $subPath 子路径
     * @return array 统计信息
     */
    public function getStatistics($subPath = '')
    {
        $files = $this->listLocalFiles($subPath, true);

        $stats = [
            'total_files' => 0,
            'total_directories' => 0,
            'total_size' => 0,
            'file_types' => [],
            'largest_file' => null,
            'newest_file' => null
        ];

        $largestSize = 0;
        $newestTime = 0;

        foreach ($files as $file) {
            if ($file['type'] === 'directory') {
                $stats['total_directories']++;
            } else {
                $stats['total_files']++;
                $stats['total_size'] += $file['size'];

                // 统计文件类型
                $mime = $file['mime'];
                $category = explode('/', $mime)[0];
                if (!isset($stats['file_types'][$category])) {
                    $stats['file_types'][$category] = 0;
                }
                $stats['file_types'][$category]++;

                // 找最大文件
                if ($file['size'] > $largestSize) {
                    $largestSize = $file['size'];
                    $stats['largest_file'] = $file;
                }

                // 找最新文件
                if ($file['modified'] > $newestTime) {
                    $newestTime = $file['modified'];
                    $stats['newest_file'] = $file;
                }
            }
        }

        $stats['total_size_formatted'] = MediaLibrary_FileOperations::formatFileSize($stats['total_size']);

        return $stats;
    }
}
