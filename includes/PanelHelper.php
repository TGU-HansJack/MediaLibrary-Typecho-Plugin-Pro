<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVClient.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/CacheManager.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/EnvironmentCheck.php';

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
            // 兼容复选框和旧版本配置，未设置时按环境能力自动启用
            $enableGetID3 = self::normalizeCheckboxOption($config, 'enableGetID3') ?? false;

            $enableExif = self::normalizeCheckboxOption($config, 'enableExif');
            if ($enableExif === null) {
                $enableExif = extension_loaded('exif') || MediaLibrary_ExifPrivacy::isExifToolAvailable();
            }

            $enableGD = self::normalizeCheckboxOption($config, 'enableGD');
            if ($enableGD === null) {
                $enableGD = extension_loaded('gd');
            }

            $enableImageMagick = self::normalizeCheckboxOption($config, 'enableImageMagick');
            if ($enableImageMagick === null) {
                $enableImageMagick = extension_loaded('imagick');
            }

            $enableFFmpeg = self::normalizeCheckboxOption($config, 'enableFFmpeg');
            if ($enableFFmpeg === null) {
                $enableFFmpeg = class_exists('MediaLibrary_EnvironmentCheck')
                    ? MediaLibrary_EnvironmentCheck::checkFFmpeg()
                    : false;
            }

            $enableVideoCompress = self::normalizeCheckboxOption($config, 'enableVideoCompress');
            if ($enableVideoCompress === null) {
                $enableVideoCompress = $enableFFmpeg;
            }

            $enableWebDAV = self::normalizeCheckboxOption($config, 'enableWebDAV') ?? false;
            $gdQuality = intval($config->gdQuality ?? 80);
            $videoQuality = intval($config->videoQuality ?? 23);
            $videoCodec = $config->videoCodec ?? 'libx264';

            // WebDAV 配置
            $webdavLocalPath = isset($config->webdavLocalPath) ? trim($config->webdavLocalPath) : '';
            $webdavEndpoint = isset($config->webdavEndpoint) ? trim($config->webdavEndpoint) : '';
            $webdavRemotePath = isset($config->webdavRemotePath) ? trim($config->webdavRemotePath) : '/typecho';
            $webdavUsername = isset($config->webdavUsername) ? trim($config->webdavUsername) : '';
            $webdavPassword = isset($config->webdavPassword) ? (string)$config->webdavPassword : '';
            $webdavVerifySSL = !isset($config->webdavVerifySSL) || (is_array($config->webdavVerifySSL) ? in_array('1', $config->webdavVerifySSL) : ($config->webdavVerifySSL == '1'));
            $webdavSyncEnabled = is_array($config->webdavSyncEnabled ?? false) ? in_array('1', $config->webdavSyncEnabled) : (($config->webdavSyncEnabled ?? '0') == '1');
            $webdavSyncMode = isset($config->webdavSyncMode) ? (string)$config->webdavSyncMode : 'manual';
            $webdavConflictStrategy = isset($config->webdavConflictStrategy) ? (string)$config->webdavConflictStrategy : 'newest';
            $webdavDeleteStrategy = isset($config->webdavDeleteStrategy) ? (string)$config->webdavDeleteStrategy : 'auto';

            // 兼容旧配置
            $webdavBasePath = isset($config->webdavBasePath) ? trim($config->webdavBasePath) : '/';
            $webdavSyncPath = isset($config->webdavSyncPath) ? trim($config->webdavSyncPath) : '/uploads';
            $webdavSyncDelete = is_array($config->webdavSyncDelete ?? false) ? in_array('1', $config->webdavSyncDelete) : (($config->webdavSyncDelete ?? '0') == '1');
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
            $webdavLocalPath = '';
            $webdavEndpoint = '';
            $webdavRemotePath = '/typecho';
            $webdavBasePath = '/';
            $webdavUsername = '';
            $webdavPassword = '';
            $webdavVerifySSL = true;
            $webdavSyncEnabled = false;
            $webdavSyncPath = '/uploads';
            $webdavSyncMode = 'manual';
            $webdavConflictStrategy = 'newest';
            $webdavDeleteStrategy = 'auto';
            $webdavSyncDelete = false;
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
            'webdavLocalPath' => $webdavLocalPath,
            'webdavEndpoint' => $webdavEndpoint,
            'webdavRemotePath' => $webdavRemotePath,
            'webdavBasePath' => self::normalizeWebDAVPath($webdavBasePath),
            'webdavUsername' => $webdavUsername,
            'webdavPassword' => $webdavPassword,
            'webdavVerifySSL' => $webdavVerifySSL,
            'webdavTimeout' => 10,
            'webdavSyncEnabled' => $webdavSyncEnabled,
            'webdavSyncPath' => $webdavSyncPath,
            'webdavSyncMode' => $webdavSyncMode,
            'webdavConflictStrategy' => $webdavConflictStrategy,
            'webdavDeleteStrategy' => $webdavDeleteStrategy,
            'webdavSyncDelete' => $webdavSyncDelete
        ];
    }
    
    /**
     * 获取媒体列表
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
        // WebDAV 存储：直接读取本地 WebDAV 文件夹，不查询数据库
        if ($storage === 'webdav') {
            return self::getWebDAVFolderList($page, $pageSize, $keywords, $type);
        }

        // 构建查询 - 添加去重和更严格的条件
        $select = $db->select()->from('table.contents')
            ->where('table.contents.type = ?', 'attachment')
            ->where('table.contents.status = ?', 'publish')  // 只查询已发布的附件
            ->order('table.contents.created', Typecho_Db::SORT_DESC);

        if (!empty($keywords)) {
            $select->where('table.contents.title LIKE ?', '%' . $keywords . '%');
        }

        // 存储类型筛选
        // WebDAV 文件在上传时会在 text 字段中添加 'storage' => 'webdav' 标记
        $adapterName = method_exists($db, 'getAdapterName') ? strtolower($db->getAdapterName()) : 'unknown';
        $supportsBinaryLike = strpos($adapterName, 'mysql') !== false;
        $likeOperator = $supportsBinaryLike ? 'LIKE BINARY' : 'LIKE';
        $webdavMarker = '%s:7:"storage";s:6:"webdav"%';

        if ($storage !== 'all') {
            if ($storage === 'webdav') {
                // 筛选 WebDAV 文件：查找 text 字段包含 webdav 存储标记的文件
                $select->where("table.contents.text {$likeOperator} ?", $webdavMarker);
            } elseif ($storage === 'local') {
                // 筛选本地文件：排除带有 webdav 标记的文件，同时允许 text 为空
                $likeExpression = "table.contents.text {$likeOperator} ?";
                $select->where(
                    "(table.contents.text IS NULL OR table.contents.text = '' OR ({$likeExpression}) = 0)",
                    $webdavMarker
                );
            }
        }

        if ($type !== 'all') {
            switch ($type) {
                case 'image':
                    $select->where('table.contents.text LIKE ?', '%image%');
                    break;
                case 'video':
                    $select->where('table.contents.text LIKE ?', '%video%');
                    break;
                case 'audio':
                    $select->where('table.contents.text LIKE ?', '%audio%');
                    break;
                case 'document':
                    $select->where('table.contents.text LIKE ?', '%application%');
                    break;
            }
        }
        
        // 获取总数 - 使用 DISTINCT 避免重复计数
        $totalQuery = clone $select;
        $total = $db->fetchObject($totalQuery->select('COUNT(DISTINCT table.contents.cid) as total'))->total;
        
        // 分页查询 - 添加 DISTINCT 和 GROUP BY
        $offset = ($page - 1) * $pageSize;
        $attachments = $db->fetchAll($select->group('table.contents.cid')->limit($pageSize)->offset($offset));
        
        // 处理附件数据 - 添加去重逻辑
        $processedCids = array(); // 用于跟踪已处理的 CID
        $uniqueAttachments = array();
        
        foreach ($attachments as $attachment) {
            // 跳过已处理的 CID
            if (in_array($attachment['cid'], $processedCids)) {
                continue;
            }
            
            $processedCids[] = $attachment['cid'];
            
            $textData = isset($attachment['text']) ? $attachment['text'] : '';
            
            $attachmentData = array();
            if (!empty($textData)) {
                $unserialized = @unserialize($textData);
                if (is_array($unserialized)) {
                    $attachmentData = $unserialized;
                }
            }
            
            $attachment['attachment'] = $attachmentData;
            $attachment['mime'] = isset($attachmentData['mime']) ? $attachmentData['mime'] : 'application/octet-stream';
            $attachment['isImage'] = isset($attachmentData['mime']) && (
                strpos($attachmentData['mime'], 'image/') === 0 || 
                in_array(strtolower(pathinfo($attachmentData['name'] ?? '', PATHINFO_EXTENSION)), ['avif'])
            );
            
            $attachment['isDocument'] = isset($attachmentData['mime']) && (
                strpos($attachmentData['mime'], 'application/pdf') === 0 ||
                strpos($attachmentData['mime'], 'application/msword') === 0 ||
                strpos($attachmentData['mime'], 'application/vnd.openxmlformats-officedocument.wordprocessingml') === 0 ||
                strpos($attachmentData['mime'], 'application/vnd.ms-powerpoint') === 0 ||
                strpos($attachmentData['mime'], 'application/vnd.openxmlformats-officedocument.presentationml') === 0 ||
                strpos($attachmentData['mime'], 'application/vnd.ms-excel') === 0 ||
                strpos($attachmentData['mime'], 'application/vnd.openxmlformats-officedocument.spreadsheetml') === 0
            );

            $attachment['isVideo'] = isset($attachmentData['mime']) && strpos($attachmentData['mime'], 'video/') === 0;
            $attachment['size'] = MediaLibrary_FileOperations::formatFileSize(isset($attachmentData['size']) ? intval($attachmentData['size']) : 0);
            
            if (isset($attachmentData['path']) && !empty($attachmentData['path'])) {
                $attachment['url'] = Typecho_Common::url($attachmentData['path'], Typecho_Widget::widget('Widget_Options')->siteUrl);
                $attachment['hasValidUrl'] = true;
            } else {
                $attachment['url'] = '';
                $attachment['hasValidUrl'] = false;
            }
            
            if (!isset($attachment['title']) || empty($attachment['title'])) {
                $attachment['title'] = isset($attachmentData['name']) ? $attachmentData['name'] : '未命名文件';
            }
            
            // 获取所属文章信息
            $attachment['parent_post'] = self::getParentPost($db, $attachment['cid']);
            
            $uniqueAttachments[] = $attachment;
        }
        
        return [
            'attachments' => $uniqueAttachments,
            'total' => $total
        ];
    }

    /**
     * 获取本地 WebDAV 文件夹的文件列表
     *
     * @param int $page 当前页码
     * @param int $pageSize 每页显示数量
     * @param string $keywords 搜索关键词
     * @param string $type 文件类型过滤
     * @return array 媒体列表数据
     */
    private static function getWebDAVFolderList($page, $pageSize, $keywords, $type)
    {
        require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVSync.php';

        $configOptions = self::getPluginConfig();

        // 检查 WebDAV 是否启用
        if (empty($configOptions['enableWebDAV'])) {
            return [
                'attachments' => [],
                'total' => 0,
                'error' => 'WebDAV 功能未启用'
            ];
        }

        // 检查本地路径是否配置
        if (empty($configOptions['webdavLocalPath'])) {
            return [
                'attachments' => [],
                'total' => 0,
                'error' => 'WebDAV 本地文件夹未配置'
            ];
        }

        $localPath = rtrim($configOptions['webdavLocalPath'], '/\\');

        // 检查本地文件夹是否存在
        if (!is_dir($localPath)) {
            return [
                'attachments' => [],
                'total' => 0,
                'error' => '本地 WebDAV 文件夹不存在: ' . $localPath
            ];
        }

        try {
            $sync = new MediaLibrary_WebDAVSync($configOptions);
            $allItems = $sync->listLocalFiles('');

            // 过滤文件（不包括目录）
            $files = array_filter($allItems, function($item) {
                return $item['type'] === 'file';
            });

            // 应用关键词过滤
            if (!empty($keywords)) {
                $files = array_filter($files, function($item) use ($keywords) {
                    return stripos($item['name'], $keywords) !== false;
                });
            }

            // 应用类型过滤
            if ($type !== 'all') {
                $files = array_filter($files, function($item) use ($type) {
                    $mime = self::guessMimeType($item['name']);
                    switch ($type) {
                        case 'image':
                            return strpos($mime, 'image/') === 0;
                        case 'video':
                            return strpos($mime, 'video/') === 0;
                        case 'audio':
                            return strpos($mime, 'audio/') === 0;
                        case 'document':
                            return strpos($mime, 'application/') === 0;
                        default:
                            return true;
                    }
                });
            }

            // 按修改时间降序排序
            usort($files, function($a, $b) {
                return $b['modified'] - $a['modified'];
            });

            $total = count($files);

            // 分页
            $offset = ($page - 1) * $pageSize;
            $pagedFiles = array_slice($files, $offset, $pageSize);

            // 转换为面板期望的格式
            $attachments = [];
            foreach ($pagedFiles as $file) {
                $mime = self::guessMimeType($file['name']);
                $isImage = strpos($mime, 'image/') === 0;
                $isVideo = strpos($mime, 'video/') === 0;
                $isDocument = strpos($mime, 'application/') === 0 ||
                              in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)),
                                      ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);

                // 构建 URL（从本地路径生成可访问的 URL）
                $relativePath = ltrim($file['path'], '/');
                $url = self::buildWebDAVFileUrl($relativePath, $configOptions);

                $attachments[] = [
                    'cid' => 0, // WebDAV 文件没有数据库 ID
                    'title' => $file['name'],
                    'slug' => '',
                    'created' => $file['modified'],
                    'modified' => $file['modified'],
                    'text' => '',
                    'order' => 0,
                    'authorId' => 0,
                    'template' => '',
                    'type' => 'attachment',
                    'status' => 'publish',
                    'password' => '',
                    'commentsNum' => 0,
                    'allowComment' => '0',
                    'allowPing' => '0',
                    'allowFeed' => '0',
                    'parent' => 0,
                    'attachment' => [
                        'name' => $file['name'],
                        'path' => $relativePath,
                        'size' => $file['size'],
                        'type' => 'file',
                        'mime' => $mime,
                        'storage' => 'webdav'
                    ],
                    'mime' => $mime,
                    'isImage' => $isImage,
                    'isVideo' => $isVideo,
                    'isDocument' => $isDocument,
                    'size' => MediaLibrary_FileOperations::formatFileSize($file['size']),
                    'url' => $url,
                    'hasValidUrl' => !empty($url),
                    'parent_post' => ['status' => 'unarchived', 'post' => null],
                    'webdav_file' => true // 标记为 WebDAV 文件
                ];
            }

            return [
                'attachments' => $attachments,
                'total' => $total
            ];

        } catch (Exception $e) {
            return [
                'attachments' => [],
                'total' => 0,
                'error' => 'WebDAV 读取失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 根据文件名猜测 MIME 类型
     *
     * @param string $filename 文件名
     * @return string MIME 类型
     */
    private static function guessMimeType($filename)
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
            // 视频
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv',
            // 音频
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            // 文档
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed'
        ];

        return isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'application/octet-stream';
    }

    /**
     * 构建 WebDAV 文件的访问 URL
     *
     * @param string $relativePath 相对路径
     * @param array $configOptions 配置选项
     * @return string 文件 URL
     */
    private static function buildWebDAVFileUrl($relativePath, $configOptions)
    {
        // 从本地 WebDAV 路径构建 URL
        // 如果配置了 webdavLocalPath，尝试生成可访问的 URL
        $localPath = rtrim($configOptions['webdavLocalPath'], '/\\');

        // 尝试将本地路径转换为 web 可访问路径
        // 假设 webdav 文件夹在网站根目录下
        $rootDir = __TYPECHO_ROOT_DIR__;
        if (strpos($localPath, $rootDir) === 0) {
            $webPath = substr($localPath, strlen($rootDir));
            $webPath = str_replace('\\', '/', $webPath);
            $webPath = ltrim($webPath, '/');
            return Typecho_Common::url($webPath . '/' . $relativePath, Helper::options()->siteUrl);
        }

        // 如果无法生成 URL，返回空字符串
        // 可以考虑通过 WebDAV 远程 URL 访问
        if (!empty($configOptions['webdavEndpoint'])) {
            $remotePath = isset($configOptions['webdavRemotePath']) ? trim($configOptions['webdavRemotePath'], '/') : 'typecho';
            return rtrim($configOptions['webdavEndpoint'], '/') . '/' . $remotePath . '/' . ltrim($relativePath, '/');
        }

        return '';
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
        // 尝试从缓存读取
        $postInfo = MediaLibrary_CacheManager::get('post-info');
        if ($postInfo !== null && isset($postInfo[$attachmentCid])) {
            return $postInfo[$attachmentCid];
        }

        try {
            $attachment = $db->fetchRow($db->select()->from('table.contents')
                ->where('cid = ? AND type = ?', $attachmentCid, 'attachment'));

            if ($attachment && $attachment['parent'] > 0) {
                $parentPost = $db->fetchRow($db->select()->from('table.contents')
                    ->where('cid = ?', $attachment['parent']));

                if ($parentPost) {
                    $result = [
                        'status' => 'archived',
                        'post' => [
                            'cid' => $parentPost['cid'],
                            'title' => $parentPost['title'],
                            'type' => $parentPost['type']
                        ]
                    ];

                    // 更新缓存
                    MediaLibrary_CacheManager::updatePostInfo($attachmentCid, $db);

                    return $result;
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
     * @param bool $useCache 是否使用缓存
     * @return array 文件详情
     */
    public static function getDetailedFileInfo($filePath, $enableGetID3 = false, $useCache = true)
    {
        $info = [];

        if (!file_exists($filePath)) {
            return $info;
        }

        // 尝试从缓存读取
        if ($useCache) {
            $cached = MediaLibrary_CacheManager::getOrUpdateFileDetails($filePath, $enableGetID3);
            if ($cached !== null) {
                // 移除缓存管理字段
                unset($cached['mtime'], $cached['path'], $cached['cached_at']);
                return $cached;
            }
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
     * 获取各类型文件的统计数量
     *
     * @param Typecho_Db $db 数据库连接
     * @param string $storage 存储类型过滤 (all, local, webdav)
     * @return array 各类型文件数量统计
     */
    public static function getTypeStatistics($db, $storage = 'all')
    {
        // 尝试从缓存读取
        try {
            $cached = MediaLibrary_CacheManager::get('type-stats', $storage);
            if ($cached !== null) {
                return $cached;
            }
        } catch (Exception $e) {
            // 缓存读取失败，继续执行数据库查询
        }

        // WebDAV 存储：统计本地 WebDAV 文件夹的文件
        if ($storage === 'webdav') {
            $stats = self::getWebDAVFolderTypeStatistics();
            try {
                MediaLibrary_CacheManager::set('type-stats', $stats, $storage);
            } catch (Exception $e) {
                // 缓存写入失败，不影响返回结果
            }
            return $stats;
        }

        // 基础查询
        $baseQuery = $db->select()->from('table.contents')
            ->where('table.contents.type = ?', 'attachment')
            ->where('table.contents.status = ?', 'publish');

        // 存储类型筛选
        $adapterName = method_exists($db, 'getAdapterName') ? strtolower($db->getAdapterName()) : 'unknown';
        $supportsBinaryLike = strpos($adapterName, 'mysql') !== false;
        $likeOperator = $supportsBinaryLike ? 'LIKE BINARY' : 'LIKE';
        $webdavMarker = '%s:7:"storage";s:6:"webdav"%';

        if ($storage !== 'all') {
            if ($storage === 'webdav') {
                $baseQuery->where("table.contents.text {$likeOperator} ?", $webdavMarker);
            } elseif ($storage === 'local') {
                $likeExpression = "table.contents.text {$likeOperator} ?";
                $baseQuery->where(
                    "(table.contents.text IS NULL OR table.contents.text = '' OR ({$likeExpression}) = 0)",
                    $webdavMarker
                );
            }
        }

        $statistics = [
            'image' => 0,
            'video' => 0,
            'audio' => 0,
            'document' => 0
        ];

        // 统计图片数量
        $imageQuery = clone $baseQuery;
        $imageQuery->where('table.contents.text LIKE ?', '%image%');
        $statistics['image'] = $db->fetchObject($imageQuery->select('COUNT(DISTINCT table.contents.cid) as total'))->total;

        // 统计视频数量
        $videoQuery = clone $baseQuery;
        $videoQuery->where('table.contents.text LIKE ?', '%video%');
        $statistics['video'] = $db->fetchObject($videoQuery->select('COUNT(DISTINCT table.contents.cid) as total'))->total;

        // 统计音频数量
        $audioQuery = clone $baseQuery;
        $audioQuery->where('table.contents.text LIKE ?', '%audio%');
        $statistics['audio'] = $db->fetchObject($audioQuery->select('COUNT(DISTINCT table.contents.cid) as total'))->total;

        // 统计文档数量
        $documentQuery = clone $baseQuery;
        $documentQuery->where('table.contents.text LIKE ?', '%application%');
        $statistics['document'] = $db->fetchObject($documentQuery->select('COUNT(DISTINCT table.contents.cid) as total'))->total;

        // 缓存结果
        try {
            MediaLibrary_CacheManager::set('type-stats', $statistics, $storage);
        } catch (Exception $e) {
            // 缓存写入失败，不影响返回结果
        }

        return $statistics;
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
     * 统计本地 WebDAV 文件夹中各类型文件的数量
     *
     * @return array 各类型文件数量统计
     */
    private static function getWebDAVFolderTypeStatistics()
    {
        require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVSync.php';

        $statistics = [
            'image' => 0,
            'video' => 0,
            'audio' => 0,
            'document' => 0
        ];

        try {
            $configOptions = self::getPluginConfig();

            if (empty($configOptions['enableWebDAV']) || empty($configOptions['webdavLocalPath'])) {
                return $statistics;
            }

            $localPath = rtrim($configOptions['webdavLocalPath'], '/\\');
            if (!is_dir($localPath)) {
                return $statistics;
            }

            $sync = new MediaLibrary_WebDAVSync($configOptions);
            $allItems = $sync->listLocalFiles('');

            // 统计各类型文件
            foreach ($allItems as $item) {
                if ($item['type'] !== 'file') {
                    continue;
                }

                $mime = self::guessMimeType($item['name']);

                if (strpos($mime, 'image/') === 0) {
                    $statistics['image']++;
                } elseif (strpos($mime, 'video/') === 0) {
                    $statistics['video']++;
                } elseif (strpos($mime, 'audio/') === 0) {
                    $statistics['audio']++;
                } elseif (strpos($mime, 'application/') === 0) {
                    $statistics['document']++;
                }
            }

        } catch (Exception $e) {
            // 统计失败，返回默认值
        }

        return $statistics;
    }

    /**
     * 兼容性处理：读取复选框配置
     *
     * @param Typecho_Config $config
     * @param string $key
     * @param mixed $default
     * @return bool|null
     */
    private static function normalizeCheckboxOption($config, $key, $default = null)
    {
        if (!isset($config->$key)) {
            return $default;
        }

        $value = $config->$key;
        if (is_array($value)) {
            if (empty($value)) {
                return $default;
            }
            return in_array('1', $value);
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return $value === true || $value === '1' || $value === 1;
    }
}
