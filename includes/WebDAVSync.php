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
            MediaLibrary_Logger::log('webdav_sync', '同步失败：WebDAV 客户端未初始化', [
                'file' => $relativePath
            ], 'error');
            throw new Exception('WebDAV 客户端未初始化');
        }

        $localFile = $this->localPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!file_exists($localFile) || !is_file($localFile)) {
            MediaLibrary_Logger::log('webdav_sync', '同步失败：本地文件不存在', [
                'file' => $relativePath,
                'local_path' => $localFile
            ], 'error');
            throw new Exception('文件不存在: ' . $relativePath);
        }

        $fileSize = filesize($localFile);
        MediaLibrary_Logger::log('webdav_sync', '开始同步文件到远程', [
            'file' => $relativePath,
            'size' => $fileSize,
            'size_human' => $this->formatFileSize($fileSize)
        ]);

        // 构建远程路径，确保包含配置的远程根目录
        $remotePath = $this->buildRemoteEntryPath($relativePath);

        // 确保远程目录存在
        $remoteDir = dirname($remotePath);
        if ($remoteDir !== '.' && $remoteDir !== '/' && $remoteDir !== '') {
            MediaLibrary_Logger::log('webdav_sync', '确保远程目录存在', [
                'dir' => $remoteDir
            ]);
            $this->ensureRemoteDirectory($remoteDir);
        }

        // 上传文件
        $mime = $this->getMimeType($localFile);
        $startTime = microtime(true);

        try {
            $this->webdavClient->uploadFile($remotePath, $localFile, $mime);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            MediaLibrary_Logger::log('webdav_sync', '文件上传成功', [
                'file' => $relativePath,
                'remote_path' => $remotePath,
                'size' => $fileSize,
                'duration_ms' => $duration
            ]);
        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_sync', '文件上传失败: ' . $e->getMessage(), [
                'file' => $relativePath,
                'remote_path' => $remotePath,
                'error' => $e->getMessage()
            ], 'error');
            throw $e;
        }

        // 更新元数据
        $metadata = $this->loadMetadata();
        $hash = $this->calculateFileHash($localFile);
        $mtime = filemtime($localFile);

        $metadata['files'][$relativePath] = [
            'hash' => $hash,
            'size' => $fileSize,
            'mtime' => $mtime,
            'syncTime' => time(),
            'remoteMtime' => $mtime, // 远程文件的修改时间（预期与本地相同）
            'remoteEtag' => '' // ETag 将在下次列表/同步时更新
        ];

        $this->saveMetadata($metadata);

        MediaLibrary_Logger::log('webdav_sync', '文件已同步到远程', [
            'file' => $relativePath,
            'remote_path' => $remotePath,
            'hash' => substr($hash, 0, 8) . '...'
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
            MediaLibrary_Logger::log('webdav_delete', '删除失败：WebDAV 客户端未初始化', [
                'file' => $relativePath
            ], 'error');
            throw new Exception('WebDAV 客户端未初始化');
        }

        // 构建远程路径
        $remotePath = $this->buildRemoteEntryPath($relativePath);

        MediaLibrary_Logger::log('webdav_delete', '开始删除远程文件', [
            'file' => $relativePath,
            'remote_path' => $remotePath
        ]);

        try {
            // 删除远程文件
            $this->webdavClient->delete($remotePath);

            MediaLibrary_Logger::log('webdav_delete', '远程文件删除成功', [
                'file' => $relativePath,
                'remote_path' => $remotePath
            ]);
        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_delete', '远程文件删除失败: ' . $e->getMessage(), [
                'file' => $relativePath,
                'remote_path' => $remotePath,
                'error' => $e->getMessage()
            ], 'error');
            throw $e;
        }

        // 更新元数据
        $metadata = $this->loadMetadata();
        if (isset($metadata['files'][$relativePath])) {
            unset($metadata['files'][$relativePath]);
            $this->saveMetadata($metadata);
            MediaLibrary_Logger::log('webdav_delete', '元数据已更新', [
                'file' => $relativePath
            ]);
        }

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
     * 检测文件重命名
     * 参考 flymd 实现，通过哈希匹配识别重命名操作
     *
     * @param array $localIndex 当前本地文件索引
     * @param array $metadata 元数据
     * @return array 重命名映射 [oldPath => newPath, ...]
     */
    private function detectRenames($localIndex, $metadata)
    {
        $renamedPairs = [];

        // 找出本地存在但元数据中没有的文件（新文件或重命名后的文件）
        $localOnly = [];
        foreach ($localIndex as $path => $info) {
            if (!isset($metadata['files'][$path]) || $metadata['files'][$path]['syncTime'] == 0) {
                $localOnly[$path] = $info;
            }
        }

        // 找出元数据中存在但本地没有的文件（已删除或重命名前的文件）
        $metadataOnly = [];
        foreach ($metadata['files'] as $path => $info) {
            if (!isset($localIndex[$path]) && $info['syncTime'] > 0) {
                $metadataOnly[$path] = $info;
            }
        }

        // 如果没有候选文件，直接返回
        if (empty($localOnly) || empty($metadataOnly)) {
            return $renamedPairs;
        }

        // 通过哈希匹配检测重命名
        foreach ($localOnly as $newPath => $newFile) {
            foreach ($metadataOnly as $oldPath => $oldFile) {
                // 哈希相同且大小相同，很可能是重命名
                if ($newFile['hash'] === $oldFile['hash'] && $newFile['size'] === $oldFile['size']) {
                    $renamedPairs[$oldPath] = $newPath;

                    // 从候选列表中移除已匹配的文件
                    unset($metadataOnly[$oldPath]);
                    break; // 找到匹配后跳出内层循环
                }
            }
        }

        return $renamedPairs;
    }

    /**
     * 批量同步所有本地文件到远程（支持并发）
     *
     * @param callable $progressCallback 进度回调函数
     * @param bool $useConcurrent 是否使用并发同步（默认true）
     * @param int $concurrency 并发数量（默认5）
     * @return array 同步结果
     */
    public function syncAllToRemote($progressCallback = null, $useConcurrent = true, $concurrency = 5)
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
            'renamed' => 0,
            'errors' => []
        ];

        // 重命名检测（参考 flymd 实现）
        $renamedPairs = $this->detectRenames($localIndex, $metadata);

        // 处理重命名操作
        foreach ($renamedPairs as $oldPath => $newPath) {
            try {
                MediaLibrary_Logger::log('webdav_sync', '检测到文件重命名', [
                    'old' => $oldPath,
                    'new' => $newPath
                ]);

                // 使用 MOVE 操作重命名远程文件
                $this->webdavClient->move(
                    $this->buildRemoteEntryPath($oldPath),
                    $this->buildRemoteEntryPath($newPath),
                    true
                );

                // 更新元数据
                if (isset($metadata['files'][$oldPath])) {
                    $metadata['files'][$newPath] = $metadata['files'][$oldPath];
                    $metadata['files'][$newPath]['syncTime'] = time();
                    unset($metadata['files'][$oldPath]);
                }

                $result['renamed']++;

                MediaLibrary_Logger::log('webdav_sync', '文件重命名成功', [
                    'old' => $oldPath,
                    'new' => $newPath
                ]);

            } catch (Exception $e) {
                MediaLibrary_Logger::log('webdav_sync_error', '文件重命名失败，将重新上传', [
                    'old' => $oldPath,
                    'new' => $newPath,
                    'error' => $e->getMessage()
                ], 'warning');

                // MOVE 失败时，标记为需要重新上传
                // 不增加失败计数，因为会在后续上传阶段处理
            }
        }

        // 筛选需要同步的文件
        $filesToSync = [];
        foreach ($localIndex as $relativePath => $fileInfo) {
            // 如果文件刚被重命名，跳过
            if (isset($renamedPairs[$relativePath]) || in_array($relativePath, $renamedPairs)) {
                continue;
            }

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
                // 构建完整的本地路径
                $localPath = $this->localPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

                // 检测 MIME 类型
                $mime = 'application/octet-stream';
                if (function_exists('mime_content_type')) {
                    $detectedMime = @mime_content_type($localPath);
                    if ($detectedMime) {
                        $mime = $detectedMime;
                    }
                } elseif (function_exists('finfo_file')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detectedMime = @finfo_file($finfo, $localPath);
                    finfo_close($finfo);
                    if ($detectedMime) {
                        $mime = $detectedMime;
                    }
                }

                $filesToSync[$relativePath] = [
                    'remoteEntryPath' => $this->buildRemoteEntryPath($relativePath),
                    'localPath' => $localPath,
                    'mime' => $mime,
                    'hash' => $fileInfo['hash'],
                    'size' => $fileInfo['size'],
                    'mtime' => $fileInfo['mtime']
                ];
            }
        }

        // 如果没有文件需要同步，直接返回
        if (empty($filesToSync)) {
            return $result;
        }

        // 使用并发同步或顺序同步
        if ($useConcurrent && count($filesToSync) > 1) {
            // 并发同步模式
            MediaLibrary_Logger::log('webdav_sync', "开始并发同步，共 " . count($filesToSync) . " 个文件，并发数: {$concurrency}");

            try {
                // 确保远程目录存在
                $remoteDirs = [];
                $remotePathMap = [];
                foreach ($filesToSync as $relativePath => $file) {
                    $dir = dirname($file['remoteEntryPath']);
                    if ($dir === '.' || $dir === '') {
                        $dir = $this->buildRemoteEntryPath('');
                    }

                    if ($dir !== '/' && $dir !== '' && !isset($remoteDirs[$dir])) {
                        $remoteDirs[$dir] = true;
                        try {
                            $this->webdavClient->createDirectory($dir);
                        } catch (Exception $e) {
                            // 目录可能已存在，忽略错误
                        }
                    }

                    $remotePathMap[$file['remoteEntryPath']] = $relativePath;
                }

                // 准备并发上传的文件列表
                $uploadFiles = [];
                foreach ($filesToSync as $file) {
                    $uploadFiles[] = [
                        'remotePath' => $file['remoteEntryPath'],
                        'localPath' => $file['localPath'],
                        'mime' => $file['mime']
                    ];
                }

                // 执行并发上传
                $uploadResult = $this->webdavClient->uploadFilesConcurrent(
                    $uploadFiles,
                    $concurrency,
                    function($completed, $total, $file) use ($progressCallback) {
                        if ($progressCallback) {
                            call_user_func($progressCallback, $completed, $total, $file);
                        }
                    }
                );

                // 处理上传结果
                foreach ($uploadResult['success'] as $remotePath) {
                    if (!isset($remotePathMap[$remotePath])) {
                        continue;
                    }

                    $relativePath = $remotePathMap[$remotePath];
                    if (!isset($filesToSync[$relativePath])) {
                        continue;
                    }

                    $file = $filesToSync[$relativePath];

                    // 更新元数据
                    $metadata['files'][$relativePath] = [
                        'hash' => $file['hash'],
                        'size' => $file['size'],
                        'mtime' => $file['mtime'],
                        'syncTime' => time()
                    ];

                    $result['synced']++;
                }

                foreach ($uploadResult['failed'] as $failedFile) {
                    $relativePath = isset($remotePathMap[$failedFile['file']])
                        ? $remotePathMap[$failedFile['file']]
                        : $failedFile['file'];

                    $result['failed']++;
                    $result['errors'][] = [
                        'file' => $relativePath,
                        'error' => $failedFile['error']
                    ];

                    MediaLibrary_Logger::log('webdav_sync_error', '并发同步文件失败', [
                        'file' => $relativePath,
                        'remote_path' => $failedFile['file'],
                        'error' => $failedFile['error']
                    ], 'error');
                }

            } catch (Exception $e) {
                MediaLibrary_Logger::log('webdav_sync_error', '并发同步失败，回退到顺序同步', [
                    'error' => $e->getMessage()
                ], 'warning');

                // 回退到顺序同步
                $useConcurrent = false;
            }
        }

        // 顺序同步模式（作为回退或小文件量时使用）
        if (!$useConcurrent) {
            $current = 0;
            foreach ($filesToSync as $relativePath => $file) {
                $current++;

                if ($progressCallback) {
                    call_user_func($progressCallback, $current, count($filesToSync), $relativePath);
                }

                try {
                    $this->syncFileToRemote($relativePath);
                    $result['synced']++;
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
        }

        // 保存元数据
        $this->saveMetadata($metadata);

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

    /**
     * 格式化文件大小
     *
     * @param int $bytes 字节数
     * @return string 格式化后的大小
     */
    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }

    /**
     * 构建包含远程根目录的路径
     *
     * @param string $relativePath 本地相对路径
     * @return string 远程路径
     */
    private function buildRemoteEntryPath($relativePath)
    {
        $relativePath = ltrim((string)$relativePath, '/');
        $remoteRoot = trim((string)$this->remotePath, '/');

        if ($remoteRoot === '') {
            return $relativePath === '' ? '/' : '/' . $relativePath;
        }

        if ($relativePath === '') {
            return $remoteRoot;
        }

        return $remoteRoot . '/' . $relativePath;
    }

    /**
     * 测试本地 WebDAV 文件夹
     *
     * @return array 测试结果
     */
    public function testLocalPath()
    {
        $result = [
            'success' => false,
            'path' => $this->localPath,
            'exists' => false,
            'readable' => false,
            'writable' => false,
            'message' => ''
        ];

        try {
            // 检查路径是否存在
            if (!is_dir($this->localPath)) {
                $result['message'] = '目录不存在';
                MediaLibrary_Logger::log('webdav_test', '本地路径测试失败：目录不存在', [
                    'path' => $this->localPath
                ], 'error');
                return $result;
            }
            $result['exists'] = true;

            // 检查是否可读
            if (!is_readable($this->localPath)) {
                $result['message'] = '目录不可读，请检查权限';
                MediaLibrary_Logger::log('webdav_test', '本地路径测试失败：目录不可读', [
                    'path' => $this->localPath,
                    'permissions' => substr(sprintf('%o', fileperms($this->localPath)), -4)
                ], 'error');
                return $result;
            }
            $result['readable'] = true;

            // 检查是否可写
            if (!is_writable($this->localPath)) {
                $result['message'] = '目录不可写，请检查权限';
                MediaLibrary_Logger::log('webdav_test', '本地路径测试失败：目录不可写', [
                    'path' => $this->localPath,
                    'permissions' => substr(sprintf('%o', fileperms($this->localPath)), -4)
                ], 'error');
                return $result;
            }
            $result['writable'] = true;

            // 尝试创建测试文件
            $testFile = $this->localPath . DIRECTORY_SEPARATOR . '.test-write-' . time();
            if (!@file_put_contents($testFile, 'test')) {
                $result['message'] = '无法创建测试文件，请检查权限';
                MediaLibrary_Logger::log('webdav_test', '本地路径测试失败：无法创建测试文件', [
                    'path' => $this->localPath
                ], 'error');
                return $result;
            }
            @unlink($testFile);

            $result['success'] = true;
            $result['message'] = '本地路径测试成功';

            MediaLibrary_Logger::log('webdav_test', '本地路径测试成功', [
                'path' => $this->localPath,
                'permissions' => substr(sprintf('%o', fileperms($this->localPath)), -4)
            ]);

        } catch (Exception $e) {
            $result['message'] = '测试失败: ' . $e->getMessage();
            MediaLibrary_Logger::log('webdav_test', '本地路径测试异常: ' . $e->getMessage(), [
                'path' => $this->localPath
            ], 'error');
        }

        return $result;
    }

    /**
     * 测试远程 WebDAV 连接
     *
     * @return array 测试结果
     */
    public function testRemoteConnection()
    {
        $result = [
            'success' => false,
            'configured' => false,
            'connected' => false,
            'endpoint' => '',
            'message' => ''
        ];

        try {
            // 检查是否配置了远程 WebDAV
            if (empty($this->config['webdavEndpoint'])) {
                $result['message'] = '未配置远程 WebDAV 服务器';
                MediaLibrary_Logger::log('webdav_test', '远程连接测试跳过：未配置', [], 'info');
                return $result;
            }

            $result['configured'] = true;
            $result['endpoint'] = $this->config['webdavEndpoint'];

            // 检查用户名和密码
            if (empty($this->config['webdavUsername'])) {
                $result['message'] = '未配置 WebDAV 用户名';
                MediaLibrary_Logger::log('webdav_test', '远程连接测试失败：未配置用户名', [
                    'endpoint' => $result['endpoint']
                ], 'error');
                return $result;
            }

            // 初始化 WebDAV 客户端
            if (!$this->webdavClient) {
                $this->webdavClient = new MediaLibrary_WebDAVClient($this->config);
            }

            // 测试连接
            MediaLibrary_Logger::log('webdav_test', '开始测试远程 WebDAV 连接', [
                'endpoint' => $result['endpoint'],
                'username' => $this->config['webdavUsername']
            ]);

            $connected = $this->webdavClient->ping();

            if ($connected) {
                $result['success'] = true;
                $result['connected'] = true;
                $result['message'] = '远程连接测试成功';

                MediaLibrary_Logger::log('webdav_test', '远程 WebDAV 连接成功', [
                    'endpoint' => $result['endpoint']
                ]);
            } else {
                $result['message'] = '无法连接到远程 WebDAV 服务器';
                MediaLibrary_Logger::log('webdav_test', '远程 WebDAV 连接失败', [
                    'endpoint' => $result['endpoint']
                ], 'error');
            }

        } catch (Exception $e) {
            $result['message'] = '连接测试失败: ' . $e->getMessage();
            MediaLibrary_Logger::log('webdav_test', '远程连接测试异常: ' . $e->getMessage(), [
                'endpoint' => $result['endpoint'] ?? ''
            ], 'error');
        }

        return $result;
    }
}
