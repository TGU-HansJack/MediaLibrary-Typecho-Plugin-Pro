<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVClient.php';

/**
 * 面板助手类 - 处理面板显示逻辑
 */
class MediaLibrary_PanelHelper
{
    /**
     * 获取插件配置
     * 
     * @return array 配置选项
     */
    public static function getPluginConfig()
    {
        try {
            $config = Helper::options()->plugin('MediaLibrary');
            // 兼容复选框和旧版本配置
            $enableGetID3 = is_array($config->enableGetID3) ? in_array('1', $config->enableGetID3) : ($config->enableGetID3 == '1');
            $enableExif = is_array($config->enableExif) ? in_array('1', $config->enableExif) : ($config->enableExif == '1');
            $enableGD = is_array($config->enableGD) ? in_array('1', $config->enableGD) : ($config->enableGD == '1');
            $enableImageMagick = is_array($config->enableImageMagick) ? in_array('1', $config->enableImageMagick) : ($config->enableImageMagick == '1');
            $enableFFmpeg = is_array($config->enableFFmpeg) ? in_array('1', $config->enableFFmpeg) : ($config->enableFFmpeg == '1');
            $enableVideoCompress = is_array($config->enableVideoCompress) ? in_array('1', $config->enableVideoCompress) : ($config->enableVideoCompress == '1');
            $enableWebDAV = is_array($config->enableWebDAV) ? in_array('1', $config->enableWebDAV) : ($config->enableWebDAV == '1');
            $gdQuality = intval($config->gdQuality ?? 80);
            $videoQuality = intval($config->videoQuality ?? 23);
            $videoCodec = $config->videoCodec ?? 'libx264';
            $webdavEndpoint = isset($config->webdavEndpoint) ? trim($config->webdavEndpoint) : '';
            $webdavBasePath = isset($config->webdavBasePath) ? trim($config->webdavBasePath) : '/';
            $webdavUsername = isset($config->webdavUsername) ? trim($config->webdavUsername) : '';
            $webdavPassword = isset($config->webdavPassword) ? (string)$config->webdavPassword : '';
            $webdavVerifySSL = !isset($config->webdavVerifySSL) || (is_array($config->webdavVerifySSL) ? in_array('1', $config->webdavVerifySSL) : ($config->webdavVerifySSL == '1'));
        } catch (Exception $e) {
            $enableGetID3 = false;
            $enableExif = false;
            $enableGD = false;
            $enableImageMagick = false;
            $enableFFmpeg = false;
            $enableVideoCompress = false;
            $enableWebDAV = false;
            $gdQuality = 80;
            $videoQuality = 23;
            $videoCodec = 'libx264';
            $webdavEndpoint = '';
            $webdavBasePath = '/';
            $webdavUsername = '';
            $webdavPassword = '';
            $webdavVerifySSL = true;
        }
        
        return [
            'enableGetID3' => $enableGetID3,
            'enableExif' => $enableExif,
            'enableGD' => $enableGD,
            'enableImageMagick' => $enableImageMagick,
            'enableFFmpeg' => $enableFFmpeg,
            'enableVideoCompress' => $enableVideoCompress,
            'enableWebDAV' => $enableWebDAV,
            'gdQuality' => $gdQuality,
            'videoQuality' => $videoQuality,
            'videoCodec' => $videoCodec,
            'webdavEndpoint' => $webdavEndpoint,
            'webdavBasePath' => self::normalizeWebDAVPath($webdavBasePath),
            'webdavUsername' => $webdavUsername,
            'webdavPassword' => $webdavPassword,
            'webdavVerifySSL' => $webdavVerifySSL,
            'webdavTimeout' => 10
        ];
    }
    
    /**
     * 获取媒体列表 - 纯文件夹扫描模式
     *
     * @param Typecho_Db $db 数据库连接
     * @param int $page 当前页码
     * @param int $pageSize 每页显示数量
     * @param string $keywords 搜索关键词
     * @param string $type 文件类型过滤
     * @param string $storage 存储类型过滤 (all, local, webdav)
     * @return array 媒体列表数据
     */
    public static function getMediaList($db, $page, $pageSize, $keywords, $type, $storage = 'all')
    {
        // 扫描文件夹获取所有文件
        $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads';

        if (!is_dir($uploadDir)) {
            return [
                'attachments' => [],
                'total' => 0
            ];
        }

        // 递归扫描获取所有文件
        $allFiles = [];
        self::scanDirectoryRecursive($uploadDir, '/usr/uploads', $allFiles);

        // 应用过滤和搜索
        $filteredFiles = [];
        foreach ($allFiles as $fileInfo) {
            // 关键词搜索
            if (!empty($keywords) && stripos($fileInfo['name'], $keywords) === false) {
                continue;
            }

            // 类型过滤
            if ($type !== 'all') {
                $mime = $fileInfo['mime'];
                switch ($type) {
                    case 'image':
                        if (strpos($mime, 'image/') !== 0) continue 2;
                        break;
                    case 'video':
                        if (strpos($mime, 'video/') !== 0) continue 2;
                        break;
                    case 'audio':
                        if (strpos($mime, 'audio/') !== 0) continue 2;
                        break;
                    case 'document':
                        if (strpos($mime, 'application/') !== 0) continue 2;
                        break;
                }
            }

            $filteredFiles[] = $fileInfo;
        }

        // 按修改时间降序排序
        usort($filteredFiles, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });

        // 分页
        $total = count($filteredFiles);
        $offset = ($page - 1) * $pageSize;
        $pagedFiles = array_slice($filteredFiles, $offset, $pageSize);

        // 转换为兼容模板的格式
        $attachments = [];
        foreach ($pagedFiles as $fileInfo) {
            // 生成唯一的文件标识（使用文件路径的hash）
            $fileId = md5($fileInfo['relative_path']);

            $attachments[] = [
                'cid' => $fileId,  // 使用hash作为ID
                'title' => $fileInfo['name'],
                'mime' => $fileInfo['mime'],
                'isImage' => $fileInfo['is_image'],
                'isVideo' => $fileInfo['is_video'],
                'isDocument' => strpos($fileInfo['mime'], 'application/') === 0,
                'size' => $fileInfo['size_formatted'],
                'url' => Typecho_Common::url($fileInfo['relative_path'], Typecho_Widget::widget('Widget_Options')->siteUrl),
                'hasValidUrl' => true,
                'modified' => $fileInfo['modified'],
                'attachment' => [
                    'name' => $fileInfo['name'],
                    'path' => $fileInfo['relative_path'],
                    'size' => $fileInfo['size'],
                    'mime' => $fileInfo['mime']
                ],
                'file_path' => $fileInfo['full_path'],  // 保存完整路径用于删除
                'relative_path' => $fileInfo['relative_path']  // 保存相对路径
            ];
        }

        return [
            'attachments' => $attachments,
            'total' => $total
        ];
    }

    /**
     * 通过hash ID查找文件信息 - 纯文件夹模式辅助方法
     *
     * @param string $hashId 文件路径的MD5 hash
     * @return array|null 文件信息数组，未找到返回null
     */
    public static function getFileByHashId($hashId)
    {
        $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads';

        if (!is_dir($uploadDir)) {
            return null;
        }

        $allFiles = [];
        self::scanDirectoryRecursive($uploadDir, '/usr/uploads', $allFiles);

        foreach ($allFiles as $file) {
            if (md5($file['relative_path']) === $hashId) {
                return $file;
            }
        }

        return null;
    }

    /**
     * 获取文件所属文章
     * 
     * @param Typecho_Db $db 数据库连接
     * @param int $attachmentCid 附件ID
     * @return array 所属文章信息
     */
    public static function getParentPost($db, $attachmentCid)
    {
        try {
            $attachment = $db->fetchRow($db->select()->from('table.contents')
                ->where('cid = ? AND type = ?', $attachmentCid, 'attachment'));
                
            if ($attachment && $attachment['parent'] > 0) {
                $parentPost = $db->fetchRow($db->select()->from('table.contents')
                    ->where('cid = ?', $attachment['parent']));
                    
                if ($parentPost) {
                    return [
                        'status' => 'archived',
                        'post' => [
                            'cid' => $parentPost['cid'],
                            'title' => $parentPost['title'],
                            'type' => $parentPost['type']
                        ]
                    ];
                }
            }
            
            return ['status' => 'unarchived', 'post' => null];
        } catch (Exception $e) {
            return ['status' => 'unarchived', 'post' => null];
        }
    }
    
    /**
     * 获取详细文件信息
     * 
     * @param string $filePath 文件路径
     * @param bool $enableGetID3 是否启用GetID3
     * @return array 文件详情
     */
    public static function getDetailedFileInfo($filePath, $enableGetID3 = false)
    {
        $info = [];
        
        if (!file_exists($filePath)) {
            return $info;
        }
        
        $info['size'] = filesize($filePath);
        $info['modified'] = filemtime($filePath);
        $info['permissions'] = substr(sprintf('%o', fileperms($filePath)), -4);
        
        if (extension_loaded('fileinfo')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $info['mime'] = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            
            $finfoMime = finfo_open(FILEINFO_MIME);
            $info['mime_full'] = finfo_file($finfoMime, $filePath);
            finfo_close($finfoMime);
        }
        
        // 只有启用 GetID3 才使用
        if ($enableGetID3 && file_exists(__TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/lib/getid3/getid3.php')) {
            try {
                require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/lib/getid3/getid3.php';
                $getID3 = new getID3;
                $fileInfo = $getID3->analyze($filePath);
                
                if (isset($fileInfo['fileformat'])) {
                    $info['format'] = $fileInfo['fileformat'];
                }
                
                if (isset($fileInfo['playtime_string'])) {
                    $info['duration'] = $fileInfo['playtime_string'];
                }
                
                if (isset($fileInfo['bitrate'])) {
                    $info['bitrate'] = round($fileInfo['bitrate'] / 1000) . ' kbps';
                }
                
                if (isset($fileInfo['video']['resolution_x']) && isset($fileInfo['video']['resolution_y'])) {
                    $info['dimensions'] = $fileInfo['video']['resolution_x'] . ' × ' . $fileInfo['video']['resolution_y'];
                }
                
                if (isset($fileInfo['audio']['channels'])) {
                    $info['channels'] = $fileInfo['audio']['channels'] . ' 声道';
                }
                
                if (isset($fileInfo['audio']['sample_rate'])) {
                    $info['sample_rate'] = number_format($fileInfo['audio']['sample_rate']) . ' Hz';
                }
                
            } catch (Exception $e) {
                // GetID3 分析失败，忽略错误
            }
        }
        
        return $info;
    }

    /**
     * 获取 WebDAV 连接状态
     */
    public static function getWebDAVStatus($configOptions)
    {
        $status = [
            'enabled' => !empty($configOptions['enableWebDAV']),
            'configured' => false,
            'connected' => false,
            'message' => 'WebDAV 未启用',
            'root' => isset($configOptions['webdavBasePath']) ? $configOptions['webdavBasePath'] : '/'
        ];

        if (!$status['enabled']) {
            return $status;
        }

        $hasCredentials = !empty($configOptions['webdavEndpoint']) &&
            !empty($configOptions['webdavUsername']) &&
            ($configOptions['webdavPassword'] !== '');

        $status['configured'] = $hasCredentials;
        $status['message'] = $hasCredentials ? '尝试连接 WebDAV ...' : '请完善 WebDAV 配置';

        if (!$hasCredentials) {
            return $status;
        }

        try {
            $client = new MediaLibrary_WebDAVClient($configOptions);
            $status['connected'] = $client->ping();
            $status['message'] = $status['connected'] ? 'WebDAV 服务连接正常' : '无法连接 WebDAV 服务';
        } catch (Exception $e) {
            $status['message'] = 'WebDAV 连接异常：' . $e->getMessage();
        }

        return $status;
    }

    /**
     * 生成存储状态列表
     */
    public static function getStorageStatusList($webdavStatus)
    {
        $list = [];

        $list[] = [
            'key' => 'local',
            'name' => '本地存储',
            'icon' => '📁',
            'class' => 'active',
            'badge' => '活跃',
            'description' => '使用 Typecho 默认上传目录'
        ];

        $webdavClass = 'disabled';
        $webdavBadge = $webdavStatus['enabled'] ? '未配置' : '未启用';
        $webdavDesc = $webdavStatus['message'];

        if ($webdavStatus['enabled']) {
            if (!$webdavStatus['configured']) {
                $webdavClass = 'disabled';
                $webdavBadge = '未配置';
            } elseif ($webdavStatus['connected']) {
                $webdavClass = 'active';
                $webdavBadge = '已连接';
            } else {
                $webdavClass = 'error';
                $webdavBadge = '连接异常';
            }
        }

        $list[] = [
            'key' => 'webdav',
            'name' => 'WebDAV',
            'icon' => '☁️',
            'class' => $webdavClass,
            'badge' => $webdavBadge,
            'description' => $webdavDesc
        ];

        $list[] = [
            'key' => 'object',
            'name' => '对象存储',
            'icon' => '🌐',
            'class' => 'disabled',
            'badge' => '开发中',
            'description' => '后续版本将提供常见对象存储适配'
        ];

        return $list;
    }

    /**
     * 规范化 WebDAV 基础路径
     */
    private static function normalizeWebDAVPath($path)
    {
        $path = trim((string)$path);
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . trim($path, '/');
    }

    /**
     * 扫描上传目录中的文件
     *
     * @param Typecho_Db $db 数据库连接
     * @param string $baseDir 基础目录（相对路径，如 /usr/uploads）
     * @return array 扫描结果
     */
    public static function scanUploadDirectory($db, $baseDir = '/usr/uploads')
    {
        $fullPath = __TYPECHO_ROOT_DIR__ . $baseDir;

        if (!is_dir($fullPath)) {
            return [
                'success' => false,
                'message' => '目录不存在: ' . $baseDir
            ];
        }

        // 获取数据库中所有附件的路径
        $dbFiles = [];
        $attachments = $db->fetchAll($db->select()->from('table.contents')
            ->where('type = ?', 'attachment')
            ->where('status = ?', 'publish'));

        foreach ($attachments as $attachment) {
            if (!empty($attachment['text'])) {
                $attachmentData = @unserialize($attachment['text']);
                if (is_array($attachmentData) && isset($attachmentData['path'])) {
                    // 标准化路径用于比对
                    $normalizedPath = str_replace('\\', '/', $attachmentData['path']);
                    $dbFiles[$normalizedPath] = [
                        'cid' => $attachment['cid'],
                        'title' => $attachment['title'],
                        'path' => $attachmentData['path']
                    ];
                }
            }
        }

        // 递归扫描文件系统
        $filesInSystem = [];
        $orphanedFiles = [];
        self::scanDirectoryRecursive($fullPath, $baseDir, $filesInSystem);

        // 比对文件系统和数据库
        foreach ($filesInSystem as $fileInfo) {
            $relativePath = $fileInfo['relative_path'];
            $normalizedPath = str_replace('\\', '/', $relativePath);

            if (!isset($dbFiles[$normalizedPath])) {
                // 文件在文件系统中存在，但数据库中没有记录
                $orphanedFiles[] = $fileInfo;
            }
        }

        return [
            'success' => true,
            'data' => [
                'scanned_path' => $baseDir,
                'total_files_in_system' => count($filesInSystem),
                'total_files_in_db' => count($dbFiles),
                'orphaned_files' => $orphanedFiles,
                'orphaned_count' => count($orphanedFiles)
            ]
        ];
    }

    /**
     * 递归扫描目录（公共方法，供外部调用）
     *
     * @param string $dir 完整目录路径
     * @param string $baseDir 基础目录（相对路径）
     * @param array &$result 结果数组（引用传递）
     */
    public static function scanDirectoryRecursive($dir, $baseDir, &$result)
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($fullPath)) {
                // 递归扫描子目录
                self::scanDirectoryRecursive($fullPath, $baseDir, $result);
            } else if (is_file($fullPath)) {
                // 获取文件信息
                $fileSize = @filesize($fullPath);
                $mtime = @filemtime($fullPath);

                // 获取 MIME 类型
                $mime = 'application/octet-stream';
                if (extension_loaded('fileinfo')) {
                    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $detectedMime = @finfo_file($finfo, $fullPath);
                        if ($detectedMime) {
                            $mime = $detectedMime;
                        }
                        @finfo_close($finfo);
                    }
                }

                // 计算相对路径
                $relativePath = str_replace(__TYPECHO_ROOT_DIR__, '', $fullPath);
                $relativePath = str_replace('\\', '/', $relativePath);

                $result[] = [
                    'name' => $item,
                    'full_path' => $fullPath,
                    'relative_path' => $relativePath,
                    'size' => $fileSize,
                    'size_formatted' => MediaLibrary_FileOperations::formatFileSize($fileSize),
                    'mime' => $mime,
                    'modified' => $mtime,
                    'modified_formatted' => date('Y-m-d H:i:s', $mtime),
                    'is_image' => strpos($mime, 'image/') === 0,
                    'is_video' => strpos($mime, 'video/') === 0,
                    'is_audio' => strpos($mime, 'audio/') === 0,
                ];
            }
        }
    }

    /**
     * 批量导入文件到数据库
     *
     * @param array $files 文件列表
     * @param Typecho_Db $db 数据库连接
     * @param int $userId 用户ID
     * @return array 导入结果
     */
    public static function importFilesToDatabase($files, $db, $userId)
    {
        if (empty($files)) {
            return [
                'success' => false,
                'message' => '没有要导入的文件'
            ];
        }

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($files as $fileData) {
            try {
                // 检查文件是否存在
                if (!isset($fileData['full_path']) || !file_exists($fileData['full_path'])) {
                    $failed++;
                    $errors[] = $fileData['name'] . ': 文件不存在';
                    continue;
                }

                // 检查是否已经在数据库中
                $relativePath = $fileData['relative_path'];
                $existing = $db->fetchRow($db->select()->from('table.contents')
                    ->where('type = ?', 'attachment')
                    ->where('text LIKE ?', '%' . $db->escapeLike($relativePath) . '%')
                    ->limit(1));

                if ($existing) {
                    $failed++;
                    $errors[] = $fileData['name'] . ': 已存在于数据库中';
                    continue;
                }

                // 构建附件数据
                $attachmentData = [
                    'name' => $fileData['name'],
                    'path' => $fileData['relative_path'],
                    'size' => $fileData['size'],
                    'type' => $fileData['mime'],
                    'mime' => $fileData['mime']
                ];

                // 生成唯一的 slug
                $slug = self::generateUniqueSlug($fileData['name'], $db);

                // 插入数据库记录
                $insertData = [
                    'title' => $fileData['name'],
                    'slug' => $slug,
                    'created' => $fileData['modified'],
                    'modified' => $fileData['modified'],
                    'text' => serialize($attachmentData),
                    'order' => 0,
                    'authorId' => $userId,
                    'template' => NULL,
                    'type' => 'attachment',
                    'status' => 'publish',
                    'parent' => 0,
                    'allowComment' => 0,
                    'allowPing' => 0,
                    'allowFeed' => 0
                ];

                $db->query($db->insert('table.contents')->rows($insertData));
                $imported++;

            } catch (Exception $e) {
                $failed++;
                $errors[] = $fileData['name'] . ': ' . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'data' => [
                'imported' => $imported,
                'failed' => $failed,
                'errors' => $errors
            ]
        ];
    }

    /**
     * 生成唯一的 slug
     *
     * @param string $name 文件名
     * @param Typecho_Db $db 数据库连接
     * @return string 唯一的 slug
     */
    private static function generateUniqueSlug($name, $db)
    {
        // 移除扩展名和特殊字符
        $slug = pathinfo($name, PATHINFO_FILENAME);
        $slug = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            $slug = 'file-' . time();
        }

        // 检查是否重复
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $existing = $db->fetchRow($db->select()->from('table.contents')
                ->where('slug = ?', $slug)
                ->limit(1));

            if (!$existing) {
                break;
            }

            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
