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
}
