<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVClient.php';

/**
 * 闈㈡澘鍔╂墜绫?- 澶勭悊闈㈡澘鏄剧ず閫昏緫
 */
class MediaLibrary_PanelHelper
{
    /**
     * 鑾峰彇鎻掍欢閰嶇疆
     * 
     * @return array 閰嶇疆閫夐」
     */
    public static function getPluginConfig()
    {
        try {
            $config = Helper::options()->plugin('MediaLibrary');
            // 鍏煎澶嶉€夋鍜屾棫鐗堟湰閰嶇疆
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
     * 鑾峰彇濯掍綋鍒楄〃
     * 
     * @param Typecho_Db $db 鏁版嵁搴撹繛鎺?
     * @param int $page 褰撳墠椤电爜
     * @param int $pageSize 姣忛〉鏄剧ず鏁伴噺
     * @param string $keywords 鎼滅储鍏抽敭璇?
     * @param string $type 鏂囦欢绫诲瀷杩囨护
     * @param string $storage 瀛樺偍绫诲瀷杩囨护 (all, local, webdav)
     * @return array 濯掍綋鍒楄〃鏁版嵁
     */
    public static function getMediaList($db, $page, $pageSize, $keywords, $type, $storage = 'all')
    {
        // 鏋勫缓鏌ヨ - 娣诲姞鍘婚噸鍜屾洿涓ユ牸鐨勬潯浠?
        $select = $db->select()->from('table.contents')
            ->where('table.contents.type = ?', 'attachment')
            ->where('table.contents.status = ?', 'publish')  // 鍙煡璇㈠凡鍙戝竷鐨勯檮浠?
            ->order('table.contents.created', Typecho_Db::SORT_DESC);

        if (!empty($keywords)) {
            $select->where('table.contents.title LIKE ?', '%' . $keywords . '%');
        }
        // 存储类型筛选
        // WebDAV 文件在上传时会在 text 字段中添加 'storage' => 'webdav' 标记
        $webdavMarker = '%s:7:"storage";s:6:"webdav"%';
        if ($storage !== 'all') {
            if ($storage === 'webdav') {
                $select->where('table.contents.text LIKE BINARY ?', $webdavMarker);
            } elseif ($storage === 'local') {
                $select->where('(table.contents.text IS NULL OR table.contents.text = "" OR table.contents.text NOT LIKE BINARY ?)', $webdavMarker);
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
        
        // 鑾峰彇鎬绘暟 - 浣跨敤 DISTINCT 閬垮厤閲嶅璁℃暟
        $totalQuery = clone $select;
        $total = $db->fetchObject($totalQuery->select('COUNT(DISTINCT table.contents.cid) as total'))->total;
        
        // 鍒嗛〉鏌ヨ - 娣诲姞 DISTINCT 鍜?GROUP BY
        $offset = ($page - 1) * $pageSize;
        $attachments = $db->fetchAll($select->group('table.contents.cid')->limit($pageSize)->offset($offset));
        
        // 澶勭悊闄勪欢鏁版嵁 - 娣诲姞鍘婚噸閫昏緫
        $processedCids = array(); // 鐢ㄤ簬璺熻釜宸插鐞嗙殑 CID
        $uniqueAttachments = array();
        
        foreach ($attachments as $attachment) {
            // 璺宠繃宸插鐞嗙殑 CID
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
            
            // 鑾峰彇鎵€灞炴枃绔犱俊鎭?
            $attachment['parent_post'] = self::getParentPost($db, $attachment['cid']);
            
            $uniqueAttachments[] = $attachment;
        }
        
        return [
            'attachments' => $uniqueAttachments,
            'total' => $total
        ];
    }
    
    /**
     * 鑾峰彇鏂囦欢鎵€灞炴枃绔?
     * 
     * @param Typecho_Db $db 鏁版嵁搴撹繛鎺?
     * @param int $attachmentCid 闄勪欢ID
     * @return array 鎵€灞炴枃绔犱俊鎭?
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
     * 鑾峰彇璇︾粏鏂囦欢淇℃伅
     * 
     * @param string $filePath 鏂囦欢璺緞
     * @param bool $enableGetID3 鏄惁鍚敤GetID3
     * @return array 鏂囦欢璇︽儏
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
        
        // 鍙湁鍚敤 GetID3 鎵嶄娇鐢?
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
                    $info['dimensions'] = $fileInfo['video']['resolution_x'] . ' 脳 ' . $fileInfo['video']['resolution_y'];
                }
                
                if (isset($fileInfo['audio']['channels'])) {
                    $info['channels'] = $fileInfo['audio']['channels'] . ' 澹伴亾';
                }
                
                if (isset($fileInfo['audio']['sample_rate'])) {
                    $info['sample_rate'] = number_format($fileInfo['audio']['sample_rate']) . ' Hz';
                }
                
            } catch (Exception $e) {
                // GetID3 鍒嗘瀽澶辫触锛屽拷鐣ラ敊璇?
            }
        }
        
        return $info;
    }

    /**
     * 鑾峰彇 WebDAV 杩炴帴鐘舵€?
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
            $status['message'] = $status['connected'] ? 'WebDAV 鏈嶅姟杩炴帴姝ｅ父' : '鏃犳硶杩炴帴 WebDAV 鏈嶅姟';
        } catch (Exception $e) {
            $status['message'] = 'WebDAV 连接异常：' . $e->getMessage();
        }

        return $status;
    }

    /**
     * 鐢熸垚瀛樺偍鐘舵€佸垪琛?
     */
    public static function getStorageStatusList($webdavStatus)
    {
        $list = [];

        $list[] = [
            'key' => 'local',
            'name' => '鏈湴瀛樺偍',
            'icon' => '馃搧',
            'class' => 'active',
            'badge' => '娲昏穬',
            'description' => '浣跨敤 Typecho 榛樿涓婁紶鐩綍'
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
                $webdavBadge = '杩炴帴寮傚父';
            }
        }

        $list[] = [
            'key' => 'webdav',
            'name' => 'WebDAV',
            'icon' => '鈽侊笍',
            'class' => $webdavClass,
            'badge' => $webdavBadge,
            'description' => $webdavDesc
        ];

        $list[] = [
            'key' => 'object',
            'name' => '瀵硅薄瀛樺偍',
            'icon' => '馃寪',
            'class' => 'disabled',
            'badge' => '寮€鍙戜腑',
            'description' => '鍚庣画鐗堟湰灏嗘彁渚涘父瑙佸璞″瓨鍌ㄩ€傞厤'
        ];

        return $list;
    }

    /**
     * 瑙勮寖鍖?WebDAV 鍩虹璺緞
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
