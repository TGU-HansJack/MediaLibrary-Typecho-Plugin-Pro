<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/FileOperations.php';

/**
 * 分片上传处理器
 * 支持大文件分片上传，避免上传超时和内存溢出
 */
class MediaLibrary_ChunkedUploadHandler
{
    /**
     * 临时目录
     * @var string
     */
    private $tempDir;

    /**
     * 分片大小 (默认 2MB)
     * @var int
     */
    private $chunkSize = 2097152;

    /**
     * 分片信息缓存文件扩展名
     * @var string
     */
    private $infoExt = '.chunked_info';

    /**
     * 构造函数
     */
    public function __construct()
    {
        // 使用插件的缓存目录存放临时分片
        $this->tempDir = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/cache/chunks/';

        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * 获取上传ID
     * @param string $filename 文件名
     * @param int $filesize 文件大小
     * @param string $filehash 文件MD5哈希（可选）
     * @return string 上传ID
     */
    public function getUploadId($filename, $filesize, $filehash = '')
    {
        // 基于文件名、大小和哈希生成唯一ID
        $baseString = $filename . '_' . $filesize . '_' . $filehash . '_' . session_id();
        return md5($baseString);
    }

    /**
     * 初始化分片上传
     * @param array $params 上传参数
     * @return array 初始化结果
     */
    public function initUpload($params)
    {
        $filename = isset($params['filename']) ? $params['filename'] : '';
        $filesize = isset($params['filesize']) ? intval($params['filesize']) : 0;
        $filehash = isset($params['filehash']) ? $params['filehash'] : '';
        $chunkSize = isset($params['chunkSize']) ? intval($params['chunkSize']) : $this->chunkSize;
        $storage = isset($params['storage']) ? $params['storage'] : 'local';

        if (empty($filename) || $filesize <= 0) {
            return [
                'success' => false,
                'message' => '无效的文件参数'
            ];
        }

        // 生成上传ID
        $uploadId = $this->getUploadId($filename, $filesize, $filehash);

        // 计算分片数量
        $totalChunks = ceil($filesize / $chunkSize);

        // 检查是否存在已有的上传任务（支持断点续传）
        $infoFile = $this->tempDir . $uploadId . $this->infoExt;
        $uploadedChunks = [];

        if (file_exists($infoFile)) {
            $existingInfo = @json_decode(file_get_contents($infoFile), true);
            if ($existingInfo &&
                $existingInfo['filename'] === $filename &&
                $existingInfo['filesize'] === $filesize) {
                // 已存在的上传任务，返回已上传的分片信息
                $uploadedChunks = isset($existingInfo['uploadedChunks']) ? $existingInfo['uploadedChunks'] : [];

                MediaLibrary_Logger::log('chunked_upload', '恢复分片上传任务', [
                    'upload_id' => $uploadId,
                    'filename' => $filename,
                    'uploaded_chunks' => count($uploadedChunks),
                    'total_chunks' => $totalChunks
                ]);
            }
        }

        // 保存上传信息
        $uploadInfo = [
            'uploadId' => $uploadId,
            'filename' => $filename,
            'filesize' => $filesize,
            'filehash' => $filehash,
            'chunkSize' => $chunkSize,
            'totalChunks' => $totalChunks,
            'uploadedChunks' => $uploadedChunks,
            'storage' => $storage,
            'createdAt' => time(),
            'updatedAt' => time()
        ];

        file_put_contents($infoFile, json_encode($uploadInfo, JSON_UNESCAPED_UNICODE));

        MediaLibrary_Logger::log('chunked_upload', '初始化分片上传', [
            'upload_id' => $uploadId,
            'filename' => $filename,
            'filesize' => $filesize,
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks,
            'storage' => $storage
        ]);

        return [
            'success' => true,
            'uploadId' => $uploadId,
            'chunkSize' => $chunkSize,
            'totalChunks' => $totalChunks,
            'uploadedChunks' => $uploadedChunks
        ];
    }

    /**
     * 上传分片
     * @param array $params 上传参数
     * @return array 上传结果
     */
    public function uploadChunk($params)
    {
        $uploadId = isset($params['uploadId']) ? $params['uploadId'] : '';
        $chunkIndex = isset($params['chunkIndex']) ? intval($params['chunkIndex']) : -1;
        $chunkData = isset($params['chunkData']) ? $params['chunkData'] : null;
        $chunkFile = isset($params['chunkFile']) ? $params['chunkFile'] : null;

        if (empty($uploadId) || $chunkIndex < 0) {
            return [
                'success' => false,
                'message' => '无效的分片参数'
            ];
        }

        // 读取上传信息
        $infoFile = $this->tempDir . $uploadId . $this->infoExt;
        if (!file_exists($infoFile)) {
            return [
                'success' => false,
                'message' => '上传任务不存在或已过期'
            ];
        }

        $uploadInfo = @json_decode(file_get_contents($infoFile), true);
        if (!$uploadInfo) {
            return [
                'success' => false,
                'message' => '上传信息损坏'
            ];
        }

        // 检查分片索引
        if ($chunkIndex >= $uploadInfo['totalChunks']) {
            return [
                'success' => false,
                'message' => '分片索引超出范围'
            ];
        }

        // 保存分片数据
        $chunkPath = $this->tempDir . $uploadId . '_chunk_' . $chunkIndex;

        if ($chunkFile && isset($chunkFile['tmp_name']) && is_uploaded_file($chunkFile['tmp_name'])) {
            // 从上传的文件保存
            if (!move_uploaded_file($chunkFile['tmp_name'], $chunkPath)) {
                return [
                    'success' => false,
                    'message' => '保存分片失败'
                ];
            }
        } elseif ($chunkData) {
            // 从 base64 数据保存
            $decodedData = base64_decode($chunkData);
            if ($decodedData === false) {
                return [
                    'success' => false,
                    'message' => '分片数据解码失败'
                ];
            }
            if (file_put_contents($chunkPath, $decodedData) === false) {
                return [
                    'success' => false,
                    'message' => '保存分片失败'
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => '缺少分片数据'
            ];
        }

        // 更新上传信息
        if (!in_array($chunkIndex, $uploadInfo['uploadedChunks'])) {
            $uploadInfo['uploadedChunks'][] = $chunkIndex;
            sort($uploadInfo['uploadedChunks']);
        }
        $uploadInfo['updatedAt'] = time();
        file_put_contents($infoFile, json_encode($uploadInfo, JSON_UNESCAPED_UNICODE));

        $uploadedCount = count($uploadInfo['uploadedChunks']);
        $isComplete = ($uploadedCount >= $uploadInfo['totalChunks']);

        MediaLibrary_Logger::log('chunked_upload', '分片上传完成', [
            'upload_id' => $uploadId,
            'chunk_index' => $chunkIndex,
            'uploaded_count' => $uploadedCount,
            'total_chunks' => $uploadInfo['totalChunks'],
            'is_complete' => $isComplete
        ]);

        return [
            'success' => true,
            'chunkIndex' => $chunkIndex,
            'uploadedChunks' => $uploadInfo['uploadedChunks'],
            'progress' => round($uploadedCount / $uploadInfo['totalChunks'] * 100, 2),
            'isComplete' => $isComplete
        ];
    }

    /**
     * 合并分片并完成上传
     * @param array $params 参数
     * @param array $configOptions 配置选项
     * @return array 合并结果
     */
    public function completeUpload($params, $configOptions)
    {
        $uploadId = isset($params['uploadId']) ? $params['uploadId'] : '';

        if (empty($uploadId)) {
            return [
                'success' => false,
                'message' => '无效的上传ID'
            ];
        }

        // 读取上传信息
        $infoFile = $this->tempDir . $uploadId . $this->infoExt;
        if (!file_exists($infoFile)) {
            return [
                'success' => false,
                'message' => '上传任务不存在或已过期'
            ];
        }

        $uploadInfo = @json_decode(file_get_contents($infoFile), true);
        if (!$uploadInfo) {
            return [
                'success' => false,
                'message' => '上传信息损坏'
            ];
        }

        // 检查是否所有分片都已上传
        if (count($uploadInfo['uploadedChunks']) < $uploadInfo['totalChunks']) {
            return [
                'success' => false,
                'message' => '分片未全部上传完成',
                'uploadedChunks' => $uploadInfo['uploadedChunks'],
                'totalChunks' => $uploadInfo['totalChunks']
            ];
        }

        // 创建临时合并文件
        $mergedFile = $this->tempDir . $uploadId . '_merged_' . $uploadInfo['filename'];
        $fp = fopen($mergedFile, 'wb');
        if (!$fp) {
            return [
                'success' => false,
                'message' => '无法创建合并文件'
            ];
        }

        // 按顺序合并分片
        for ($i = 0; $i < $uploadInfo['totalChunks']; $i++) {
            $chunkPath = $this->tempDir . $uploadId . '_chunk_' . $i;
            if (!file_exists($chunkPath)) {
                fclose($fp);
                @unlink($mergedFile);
                return [
                    'success' => false,
                    'message' => '分片 ' . $i . ' 丢失'
                ];
            }

            $chunkContent = file_get_contents($chunkPath);
            if ($chunkContent === false) {
                fclose($fp);
                @unlink($mergedFile);
                return [
                    'success' => false,
                    'message' => '读取分片 ' . $i . ' 失败'
                ];
            }

            fwrite($fp, $chunkContent);
        }

        fclose($fp);

        // 验证合并后的文件大小
        $mergedSize = filesize($mergedFile);
        if ($mergedSize !== $uploadInfo['filesize']) {
            MediaLibrary_Logger::log('chunked_upload', '合并文件大小不匹配', [
                'upload_id' => $uploadId,
                'expected_size' => $uploadInfo['filesize'],
                'actual_size' => $mergedSize
            ], 'warning');
            // 允许一定的误差（某些系统可能有细微差异）
            if (abs($mergedSize - $uploadInfo['filesize']) > 1024) {
                @unlink($mergedFile);
                return [
                    'success' => false,
                    'message' => '文件大小不匹配，可能存在传输错误'
                ];
            }
        }

        // 如果提供了文件哈希，验证文件完整性
        if (!empty($uploadInfo['filehash'])) {
            $actualHash = md5_file($mergedFile);
            if ($actualHash !== $uploadInfo['filehash']) {
                MediaLibrary_Logger::log('chunked_upload', '文件哈希不匹配', [
                    'upload_id' => $uploadId,
                    'expected_hash' => $uploadInfo['filehash'],
                    'actual_hash' => $actualHash
                ], 'warning');
                // 哈希不匹配只记录警告，不阻止上传
            }
        }

        MediaLibrary_Logger::log('chunked_upload', '分片合并完成', [
            'upload_id' => $uploadId,
            'filename' => $uploadInfo['filename'],
            'merged_size' => $mergedSize,
            'storage' => $uploadInfo['storage']
        ]);

        // 根据存储类型处理文件
        $storage = $uploadInfo['storage'];
        $result = $this->processCompletedFile($mergedFile, $uploadInfo, $configOptions);

        // 清理分片文件
        $this->cleanupChunks($uploadId, $uploadInfo['totalChunks']);

        return $result;
    }

    /**
     * 处理合并完成的文件
     * @param string $mergedFile 合并后的文件路径
     * @param array $uploadInfo 上传信息
     * @param array $configOptions 配置选项
     * @return array 处理结果
     */
    private function processCompletedFile($mergedFile, $uploadInfo, $configOptions)
    {
        $storage = $uploadInfo['storage'];
        $filename = $uploadInfo['filename'];
        $filesize = filesize($mergedFile);
        $mime = $this->getMimeType($mergedFile, $filename);

        // 获取数据库和用户
        $db = Typecho_Db::get();
        $options = Typecho_Widget::widget('Widget_Options');
        $user = Typecho_Widget::widget('Widget_User');

        // 生成文件名和路径
        $date = new Typecho_Date($options->gmtTime);
        $year = $date->year;
        $month = $date->month;
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $newFilename = sprintf('%u', crc32(uniqid())) . '.' . $ext;

        if ($storage === 'object_storage') {
            return $this->saveToObjectStorage($mergedFile, $uploadInfo, $configOptions, $db, $user, $options);
        } elseif ($storage === 'webdav') {
            return $this->saveToWebDAV($mergedFile, $uploadInfo, $configOptions, $db, $user, $options);
        } else {
            return $this->saveToLocal($mergedFile, $uploadInfo, $db, $user, $options);
        }
    }

    /**
     * 保存到本地存储
     */
    private function saveToLocal($mergedFile, $uploadInfo, $db, $user, $options)
    {
        $filename = $uploadInfo['filename'];
        $filesize = filesize($mergedFile);
        $mime = $this->getMimeType($mergedFile, $filename);

        $date = new Typecho_Date($options->gmtTime);
        $year = $date->year;
        $month = $date->month;
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $newFilename = sprintf('%u', crc32(uniqid())) . '.' . $ext;

        $uploadDir = defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : '/usr/uploads';
        $localDir = __TYPECHO_ROOT_DIR__ . $uploadDir . '/' . $year . '/' . $month;
        $localPath = $localDir . '/' . $newFilename;
        $relativePath = $uploadDir . '/' . $year . '/' . $month . '/' . $newFilename;

        if (!is_dir($localDir)) {
            if (!@mkdir($localDir, 0755, true)) {
                @unlink($mergedFile);
                return [
                    'success' => false,
                    'message' => '无法创建上传目录'
                ];
            }
        }

        if (!@rename($mergedFile, $localPath)) {
            if (!@copy($mergedFile, $localPath)) {
                @unlink($mergedFile);
                return [
                    'success' => false,
                    'message' => '移动文件失败'
                ];
            }
            @unlink($mergedFile);
        }

        // 使用 Typecho 的上传组件处理
        $upload = \Widget\Upload::alloc();

        // 构建上传文件数据
        $attachmentData = [
            'name' => $filename,
            'path' => $relativePath,
            'size' => $filesize,
            'type' => $mime,
            'mime' => $mime
        ];

        // 插入数据库
        $insertData = [
            'title' => $filename,
            'slug' => $newFilename,
            'created' => time(),
            'modified' => time(),
            'text' => serialize($attachmentData),
            'order' => 0,
            'authorId' => $user->uid,
            'template' => '',
            'type' => 'attachment',
            'status' => 'publish',
            'password' => '',
            'commentsNum' => 0,
            'allowComment' => 0,
            'allowPing' => 0,
            'allowFeed' => 0,
            'parent' => 0
        ];

        $insertId = $db->query($db->insert('table.contents')->rows($insertData));

        $fileUrl = Typecho_Common::url($relativePath, $options->siteUrl);

        MediaLibrary_Logger::log('chunked_upload', '本地上传完成', [
            'cid' => $insertId,
            'filename' => $filename,
            'path' => $relativePath,
            'url' => $fileUrl
        ]);

        return [
            'success' => true,
            'message' => '上传成功',
            'data' => [
                'cid' => $insertId,
                'name' => $filename,
                'url' => $fileUrl,
                'size' => $filesize,
                'type' => $mime,
                'isImage' => strpos($mime, 'image/') === 0
            ]
        ];
    }

    /**
     * 保存到对象存储
     */
    private function saveToObjectStorage($mergedFile, $uploadInfo, $configOptions, $db, $user, $options)
    {
        require_once __DIR__ . '/ObjectStorageManager.php';

        $filename = $uploadInfo['filename'];
        $filesize = filesize($mergedFile);
        $mime = $this->getMimeType($mergedFile, $filename);

        $storageManager = new MediaLibrary_ObjectStorageManager($db, $configOptions);

        if (!$storageManager->isEnabled()) {
            @unlink($mergedFile);
            return [
                'success' => false,
                'message' => '对象存储未启用'
            ];
        }

        $date = new Typecho_Date($options->gmtTime);
        $year = $date->year;
        $month = $date->month;
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $newFilename = sprintf('%u', crc32(uniqid())) . '.' . $ext;
        $remotePath = $year . '/' . $month . '/' . $newFilename;

        // 如果需要保存本地备份
        $localPath = null;
        $uploadDir = defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : '/usr/uploads';

        if ($storageManager->shouldSaveLocal()) {
            $localDir = __TYPECHO_ROOT_DIR__ . $uploadDir . '/' . $year . '/' . $month;
            $localPath = $localDir . '/' . $newFilename;

            if (!is_dir($localDir)) {
                @mkdir($localDir, 0755, true);
            }

            if (!@copy($mergedFile, $localPath)) {
                MediaLibrary_Logger::log('chunked_upload', '保存本地备份失败', [
                    'local_path' => $localPath
                ], 'warning');
                $localPath = null;
            }
        }

        // 上传到对象存储
        $result = $storageManager->upload($mergedFile, $remotePath);

        if (!$result['success']) {
            @unlink($mergedFile);
            if ($localPath) {
                @unlink($localPath);
            }
            return [
                'success' => false,
                'message' => '上传到对象存储失败: ' . $result['error']
            ];
        }

        @unlink($mergedFile);

        $fileUrl = $result['url'];
        $storageType = isset($configOptions['storageType']) ? $configOptions['storageType'] : 'unknown';

        // 保存到数据库
        $attachmentData = [
            'name' => $filename,
            'path' => $localPath ? str_replace(__TYPECHO_ROOT_DIR__, '', $localPath) : $remotePath,
            'size' => $filesize,
            'type' => $mime,
            'mime' => $mime,
            'storage' => 'object_storage',
            'storage_type' => $storageType,
            'object_storage_path' => $remotePath,
            'object_storage_url' => $fileUrl,
            'has_local_backup' => $storageManager->shouldSaveLocal()
        ];

        $insertData = [
            'title' => $filename,
            'slug' => $newFilename,
            'created' => time(),
            'modified' => time(),
            'text' => serialize($attachmentData),
            'order' => 0,
            'authorId' => $user->uid,
            'template' => '',
            'type' => 'attachment',
            'status' => 'publish',
            'password' => '',
            'commentsNum' => 0,
            'allowComment' => 0,
            'allowPing' => 0,
            'allowFeed' => 0,
            'parent' => 0
        ];

        $insertId = $db->query($db->insert('table.contents')->rows($insertData));

        MediaLibrary_Logger::log('chunked_upload', '对象存储上传完成', [
            'cid' => $insertId,
            'filename' => $filename,
            'remote_path' => $remotePath,
            'url' => $fileUrl
        ]);

        return [
            'success' => true,
            'message' => '上传成功',
            'data' => [
                'cid' => $insertId,
                'name' => $filename,
                'url' => $fileUrl,
                'size' => $filesize,
                'type' => $mime,
                'isImage' => strpos($mime, 'image/') === 0
            ]
        ];
    }

    /**
     * 保存到 WebDAV
     */
    private function saveToWebDAV($mergedFile, $uploadInfo, $configOptions, $db, $user, $options)
    {
        require_once __DIR__ . '/WebDAVSync.php';
        require_once __DIR__ . '/WebDAVClient.php';

        $filename = $uploadInfo['filename'];
        $filesize = filesize($mergedFile);
        $mime = $this->getMimeType($mergedFile, $filename);

        if (empty($configOptions['enableWebDAV'])) {
            @unlink($mergedFile);
            return [
                'success' => false,
                'message' => 'WebDAV 未启用'
            ];
        }

        try {
            $sync = new MediaLibrary_WebDAVSync($configOptions);
            $webdavClient = new MediaLibrary_WebDAVClient($configOptions);

            // 构造模拟的上传文件数组
            $fileData = [
                'name' => $filename,
                'tmp_name' => $mergedFile,
                'size' => $filesize,
                'type' => $mime
            ];

            $isRemoteOnly = isset($configOptions['webdavUploadMode']) && $configOptions['webdavUploadMode'] === 'remote-only';

            if ($isRemoteOnly) {
                $result = $sync->uploadFileDirectly($fileData);
            } else {
                $result = $sync->saveUploadedFile($fileData);
            }

            if (!$result || !isset($result['path'])) {
                @unlink($mergedFile);
                return [
                    'success' => false,
                    'message' => '保存到 WebDAV 失败'
                ];
            }

            @unlink($mergedFile);

            $relativePath = $result['path'];
            $localPath = isset($result['local_path']) ? $result['local_path'] : (isset($result['full_path']) ? $result['full_path'] : null);

            // 构建公开访问 URL
            $remotePath = $configOptions['webdavRemotePath'] . '/' . ltrim($relativePath, '/');
            $publicUrl = isset($result['public_url']) ? $result['public_url'] : $webdavClient->buildPublicUrl($remotePath);

            // 保存到数据库
            $attachmentData = [
                'name' => $filename,
                'path' => $relativePath,
                'size' => $filesize,
                'type' => $mime,
                'mime' => $mime,
                'storage' => 'webdav',
                'webdav_path' => $relativePath,
                'local_path' => $localPath
            ];

            $insertData = [
                'title' => $filename,
                'slug' => $filename,
                'created' => time(),
                'modified' => time(),
                'text' => serialize($attachmentData),
                'order' => 0,
                'authorId' => $user->uid,
                'template' => NULL,
                'type' => 'attachment',
                'status' => 'publish',
                'parent' => 0,
                'allowComment' => 0,
                'allowPing' => 0,
                'allowFeed' => 0
            ];

            $insertId = $db->query($db->insert('table.contents')->rows($insertData));

            // 如果启用了自动同步，同步到远程
            if (!$isRemoteOnly &&
                !empty($configOptions['webdavSyncEnabled']) &&
                $configOptions['webdavSyncMode'] === 'onupload') {
                try {
                    $sync->syncFileToRemote($relativePath);
                } catch (Exception $e) {
                    MediaLibrary_Logger::log('chunked_upload', 'WebDAV 同步失败: ' . $e->getMessage(), [], 'warning');
                }
            }

            MediaLibrary_Logger::log('chunked_upload', 'WebDAV 上传完成', [
                'cid' => $insertId,
                'filename' => $filename,
                'path' => $relativePath,
                'url' => $publicUrl
            ]);

            return [
                'success' => true,
                'message' => '上传成功',
                'data' => [
                    'cid' => $insertId,
                    'name' => $filename,
                    'url' => $publicUrl,
                    'size' => $filesize,
                    'type' => $mime,
                    'isImage' => strpos($mime, 'image/') === 0
                ]
            ];

        } catch (Exception $e) {
            @unlink($mergedFile);
            MediaLibrary_Logger::log('chunked_upload', 'WebDAV 上传错误: ' . $e->getMessage(), [], 'error');
            return [
                'success' => false,
                'message' => 'WebDAV 上传错误: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 清理分片文件
     * @param string $uploadId 上传ID
     * @param int $totalChunks 分片总数
     */
    public function cleanupChunks($uploadId, $totalChunks)
    {
        // 删除分片文件
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = $this->tempDir . $uploadId . '_chunk_' . $i;
            if (file_exists($chunkPath)) {
                @unlink($chunkPath);
            }
        }

        // 删除信息文件
        $infoFile = $this->tempDir . $uploadId . $this->infoExt;
        if (file_exists($infoFile)) {
            @unlink($infoFile);
        }

        // 删除合并的临时文件（如果存在）
        $pattern = $this->tempDir . $uploadId . '_merged_*';
        $mergedFiles = glob($pattern);
        if ($mergedFiles) {
            foreach ($mergedFiles as $file) {
                @unlink($file);
            }
        }

        MediaLibrary_Logger::log('chunked_upload', '清理分片文件', [
            'upload_id' => $uploadId,
            'total_chunks' => $totalChunks
        ]);
    }

    /**
     * 取消上传并清理
     * @param string $uploadId 上传ID
     * @return array 结果
     */
    public function cancelUpload($uploadId)
    {
        if (empty($uploadId)) {
            return [
                'success' => false,
                'message' => '无效的上传ID'
            ];
        }

        $infoFile = $this->tempDir . $uploadId . $this->infoExt;
        if (!file_exists($infoFile)) {
            return [
                'success' => true,
                'message' => '上传任务不存在或已清理'
            ];
        }

        $uploadInfo = @json_decode(file_get_contents($infoFile), true);
        $totalChunks = isset($uploadInfo['totalChunks']) ? $uploadInfo['totalChunks'] : 0;

        $this->cleanupChunks($uploadId, $totalChunks);

        MediaLibrary_Logger::log('chunked_upload', '取消上传任务', [
            'upload_id' => $uploadId
        ]);

        return [
            'success' => true,
            'message' => '上传已取消'
        ];
    }

    /**
     * 清理过期的分片上传任务
     * @param int $maxAge 最大保留时间（秒），默认 24 小时
     */
    public function cleanupExpired($maxAge = 86400)
    {
        $pattern = $this->tempDir . '*' . $this->infoExt;
        $infoFiles = glob($pattern);

        if (!$infoFiles) {
            return;
        }

        $now = time();
        $cleanedCount = 0;

        foreach ($infoFiles as $infoFile) {
            $info = @json_decode(file_get_contents($infoFile), true);
            if (!$info) {
                @unlink($infoFile);
                $cleanedCount++;
                continue;
            }

            $updatedAt = isset($info['updatedAt']) ? $info['updatedAt'] : 0;
            if ($now - $updatedAt > $maxAge) {
                $uploadId = isset($info['uploadId']) ? $info['uploadId'] : '';
                $totalChunks = isset($info['totalChunks']) ? $info['totalChunks'] : 0;

                if ($uploadId) {
                    $this->cleanupChunks($uploadId, $totalChunks);
                    $cleanedCount++;
                } else {
                    @unlink($infoFile);
                }
            }
        }

        if ($cleanedCount > 0) {
            MediaLibrary_Logger::log('chunked_upload', '清理过期上传任务', [
                'cleaned_count' => $cleanedCount
            ]);
        }
    }

    /**
     * 获取文件 MIME 类型
     * @param string $filePath 文件路径
     * @param string $filename 文件名
     * @return string MIME 类型
     */
    private function getMimeType($filePath, $filename)
    {
        // 首先尝试使用 finfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if ($mime && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }

        // 使用 mime_content_type
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($filePath);
            if ($mime && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }

        // 根据扩展名判断
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'avif' => 'image/avif',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv',
            'mkv' => 'video/x-matroska',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
        ];

        return isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';
    }
}
