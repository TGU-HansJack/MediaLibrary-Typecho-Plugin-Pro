<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/Logger.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVClient.php';

/**
 * WebDAV 同步管理类
 * 参考 flymd 项目实现，提供本地文件夹到远程 WebDAV 的同步功能
 */
class MediaLibrary_WebDAVSync
{
    private $config;
    private $localPath;
    private $remotePath;
    private $webdavClient;
    private $metadataFile;

    /**
     * 构造函数
     *
     * @param array $config 插件配置
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        // 本地 WebDAV 文件夹路径
        $this->localPath = isset($config['webdavLocalPath']) ? rtrim($config['webdavLocalPath'], '/\\') : '';
        if (empty($this->localPath)) {
            throw new Exception('本地 WebDAV 文件夹路径未配置');
        }

        // 如果目录不存在，尝试创建
        if (!is_dir($this->localPath)) {
            if (!@mkdir($this->localPath, 0755, true)) {
                throw new Exception('本地 WebDAV 文件夹不存在且无法自动创建: ' . $this->localPath);
            }
            MediaLibrary_Logger::log('webdav_init', '自动创建本地 WebDAV 文件夹', [
                'path' => $this->localPath
            ]);
        }

        // 远程路径
        $this->remotePath = isset($config['webdavRemotePath']) ? trim($config['webdavRemotePath'], '/') : 'typecho';

        // 元数据文件路径
        $this->metadataFile = $this->localPath . DIRECTORY_SEPARATOR . '.webdav-sync-metadata.json';

        // 初始化 WebDAV 客户端
        if (!empty($config['webdavEndpoint'])) {
            $this->webdavClient = new MediaLibrary_WebDAVClient($config);
        }
    }

    /**
     * 列出本地 WebDAV 文件夹中的文件
     *
     * @param string $subPath 子路径
     * @return array 文件列表
     */
    public function listLocalFiles($subPath = '')
    {
        $fullPath = $this->localPath;
        if (!empty($subPath)) {
            $fullPath .= DIRECTORY_SEPARATOR . trim($subPath, '/\\');
        }

        if (!is_dir($fullPath)) {
            return [];
        }

        $items = [];
        $entries = scandir($fullPath);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.webdav-sync-metadata.json') {
                continue;
            }

            $entryPath = $fullPath . DIRECTORY_SEPARATOR . $entry;
            $relativePath = empty($subPath) ? $entry : $subPath . '/' . $entry;

            $isDir = is_dir($entryPath);
            $size = $isDir ? 0 : filesize($entryPath);
            $mtime = filemtime($entryPath);

            $items[] = [
                'name' => $entry,
                'path' => '/' . str_replace('\\', '/', $relativePath),
                'type' => $isDir ? 'directory' : 'file',
                'size' => $size,
                'modified' => $mtime,
                'modified_format' => date('Y-m-d H:i:s', $mtime)
            ];
        }

        // 按类型和名称排序
        usort($items, function($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcmp($a['name'], $b['name']);
        });

        return $items;
    }

    /**
     * 计算文件哈希
     *
     * @param string $filePath 文件路径
     * @return string 哈希值
     */
    private function calculateFileHash($filePath)
    {
        return hash_file('sha256', $filePath);
    }

    /**
     * 读取同步元数据
     *
     * @return array 元数据
     */
    private function loadMetadata()
    {
        if (!file_exists($this->metadataFile)) {
            return [
                'files' => [],
                'lastSyncTime' => 0
            ];
        }

        $content = file_get_contents($this->metadataFile);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            return [
                'files' => [],
                'lastSyncTime' => 0
            ];
        }

        return $data;
    }

    /**
     * 保存同步元数据
     *
     * @param array $metadata 元数据
     */
    private function saveMetadata($metadata)
    {
        $content = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($this->metadataFile, $content);
    }

    /**
     * 扫描本地文件
     *
     * @param array $lastMetadata 上次的元数据
     * @return array 文件索引
     */
    private function scanLocalFiles($lastMetadata = null)
    {
        $index = [];
        $this->scanLocalDirectory('', $index, $lastMetadata);
        return $index;
    }

    /**
     * 递归扫描本地目录
     *
     * @param string $relativePath 相对路径
     * @param array &$index 文件索引
     * @param array $lastMetadata 上次的元数据
     */
    private function scanLocalDirectory($relativePath, &$index, $lastMetadata)
    {
        $fullPath = $this->localPath;
        if (!empty($relativePath)) {
            $fullPath .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        }

        if (!is_dir($fullPath)) {
            return;
        }

        $entries = scandir($fullPath);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.webdav-sync-metadata.json') {
                continue;
            }

            $entryPath = $fullPath . DIRECTORY_SEPARATOR . $entry;
            $relPath = empty($relativePath) ? $entry : $relativePath . '/' . $entry;

            if (is_dir($entryPath)) {
                // 递归扫描子目录
                $this->scanLocalDirectory($relPath, $index, $lastMetadata);
            } else {
                // 记录文件信息
                $size = filesize($entryPath);
                $mtime = filemtime($entryPath);

                // 优化：如果文件大小未变且有哈希记录，复用上次的哈希
                $lastFile = isset($lastMetadata['files'][$relPath]) ? $lastMetadata['files'][$relPath] : null;
                $hash = '';

                if ($lastFile && isset($lastFile['size']) && $lastFile['size'] === $size && isset($lastFile['hash'])) {
                    $hash = $lastFile['hash'];
                } else {
                    $hash = $this->calculateFileHash($entryPath);
                }

                $index[$relPath] = [
                    'hash' => $hash,
                    'size' => $size,
                    'mtime' => $mtime,
                    'syncTime' => isset($lastFile['syncTime']) ? $lastFile['syncTime'] : 0
                ];
            }
        }
    }

    /**
     * 同步单个文件到远程 WebDAV
     *
     * @param string $relativePath 相对路径
     * @return bool 是否成功
     */
    public function syncFileToRemote($relativePath)
    {
        if (!$this->webdavClient) {
            throw new Exception('WebDAV 客户端未初始化');
        }

        $localFile = $this->localPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!file_exists($localFile) || !is_file($localFile)) {
            throw new Exception('文件不存在: ' . $relativePath);
        }

        // 构建远程路径
        $remotePath = $this->remotePath . '/' . ltrim($relativePath, '/');

        // 确保远程目录存在
        $remoteDir = dirname($remotePath);
        if ($remoteDir !== '.' && $remoteDir !== '/') {
            $this->ensureRemoteDirectory($remoteDir);
        }

        // 上传文件
        $mime = $this->getMimeType($localFile);
        $this->webdavClient->uploadFile($remotePath, $localFile, $mime);

        // 更新元数据
        $metadata = $this->loadMetadata();
        $hash = $this->calculateFileHash($localFile);
        $mtime = filemtime($localFile);

        $metadata['files'][$relativePath] = [
            'hash' => $hash,
            'size' => filesize($localFile),
            'mtime' => $mtime,
            'syncTime' => time()
        ];

        $this->saveMetadata($metadata);

        MediaLibrary_Logger::log('webdav_sync', '文件已同步到远程', [
            'file' => $relativePath,
            'remote_path' => $remotePath
        ]);

        return true;
    }

    /**
     * 删除远程 WebDAV 文件
     *
     * @param string $relativePath 相对路径
     * @return bool 是否成功
     */
    public function deleteRemoteFile($relativePath)
    {
        if (!$this->webdavClient) {
            throw new Exception('WebDAV 客户端未初始化');
        }

        // 构建远程路径
        $remotePath = $this->remotePath . '/' . ltrim($relativePath, '/');

        // 删除远程文件
        $this->webdavClient->delete($remotePath);

        // 更新元数据
        $metadata = $this->loadMetadata();
        if (isset($metadata['files'][$relativePath])) {
            unset($metadata['files'][$relativePath]);
            $this->saveMetadata($metadata);
        }

        MediaLibrary_Logger::log('webdav_sync', '远程文件已删除', [
            'file' => $relativePath,
            'remote_path' => $remotePath
        ]);

        return true;
    }

    /**
     * 确保远程目录存在
     *
     * @param string $remotePath 远程路径
     */
    private function ensureRemoteDirectory($remotePath)
    {
        $parts = explode('/', trim($remotePath, '/'));
        $currentPath = '';

        foreach ($parts as $part) {
            if (empty($part)) continue;

            $currentPath .= '/' . $part;

            try {
                $this->webdavClient->createDirectory($currentPath);
            } catch (Exception $e) {
                // 目录可能已存在，忽略错误
            }
        }
    }

    /**
     * 获取文件 MIME 类型
     *
     * @param string $filePath 文件路径
     * @return string MIME 类型
     */
    private function getMimeType($filePath)
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
        ];

        if (isset($mimeTypes[$extension])) {
            return $mimeTypes[$extension];
        }

        // 尝试使用 finfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if ($mime) {
                return $mime;
            }
        }

        return 'application/octet-stream';
    }

    /**
     * 批量同步所有本地文件到远程
     *
     * @param callable $progressCallback 进度回调函数
     * @return array 同步结果
     */
    public function syncAllToRemote($progressCallback = null)
    {
        if (!$this->webdavClient) {
            throw new Exception('WebDAV 客户端未初始化');
        }

        // 加载元数据
        $metadata = $this->loadMetadata();

        // 扫描本地文件
        $localIndex = $this->scanLocalFiles($metadata);

        $result = [
            'total' => count($localIndex),
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => []
        ];

        $current = 0;
        foreach ($localIndex as $relativePath => $fileInfo) {
            $current++;

            if ($progressCallback) {
                call_user_func($progressCallback, $current, $result['total'], $relativePath);
            }

            try {
                // 检查是否需要同步
                $needSync = true;
                if (isset($metadata['files'][$relativePath])) {
                    $lastFile = $metadata['files'][$relativePath];
                    if ($lastFile['hash'] === $fileInfo['hash'] && $lastFile['syncTime'] > 0) {
                        $needSync = false;
                        $result['skipped']++;
                    }
                }

                if ($needSync) {
                    $this->syncFileToRemote($relativePath);
                    $result['synced']++;
                }
            } catch (Exception $e) {
                $result['failed']++;
                $result['errors'][] = [
                    'file' => $relativePath,
                    'error' => $e->getMessage()
                ];

                MediaLibrary_Logger::log('webdav_sync_error', '同步文件失败', [
                    'file' => $relativePath,
                    'error' => $e->getMessage()
                ], 'error');
            }
        }

        return $result;
    }

    /**
     * 保存上传的文件到本地 WebDAV 文件夹
     *
     * @param array $file $_FILES 数组中的文件信息
     * @param string $subPath 子路径（可选）
     * @return array 保存结果
     */
    public function saveUploadedFile($file, $subPath = '')
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('无效的上传文件');
        }

        // 目标目录
        $targetDir = $this->localPath;
        if (!empty($subPath)) {
            $targetDir .= DIRECTORY_SEPARATOR . trim($subPath, '/\\');
        }

        // 确保目录存在
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                throw new Exception('无法创建目录: ' . $targetDir);
            }
        }

        // 生成唯一文件名
        $originalName = isset($file['name']) ? $file['name'] : 'unnamed';
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);

        // 清理文件名
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);

        // 生成唯一文件名
        $targetName = $basename . '.' . $extension;
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $targetName;

        $counter = 1;
        while (file_exists($targetPath)) {
            $targetName = $basename . '_' . $counter . '.' . $extension;
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $targetName;
            $counter++;
        }

        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('文件保存失败');
        }

        // 相对路径
        $relativePath = empty($subPath) ? $targetName : $subPath . '/' . $targetName;

        return [
            'name' => $targetName,
            'path' => '/' . str_replace('\\', '/', $relativePath),
            'full_path' => $targetPath,
            'size' => filesize($targetPath),
            'mime' => $this->getMimeType($targetPath)
        ];
    }

    /**
     * 删除本地文件
     *
     * @param string $relativePath 相对路径
     * @return bool 是否成功
     */
    public function deleteLocalFile($relativePath)
    {
        $localFile = $this->localPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!file_exists($localFile)) {
            return true; // 文件不存在，视为删除成功
        }

        if (is_dir($localFile)) {
            // 递归删除目录
            return $this->deleteDirectory($localFile);
        } else {
            // 删除文件
            return unlink($localFile);
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

            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * 获取本地 WebDAV 文件夹路径
     *
     * @return string 路径
     */
    public function getLocalPath()
    {
        return $this->localPath;
    }

    /**
     * 获取远程路径
     *
     * @return string 路径
     */
    public function getRemotePath()
    {
        return $this->remotePath;
    }
}
