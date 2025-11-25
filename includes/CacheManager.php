<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 缓存管理类
 * 用于管理插件的所有本地 JSON 缓存，提升面板响应速度
 *
 * @package MediaLibrary
 */
class MediaLibrary_CacheManager
{
    /**
     * 缓存目录路径
     * @var string
     */
    private static $cacheDir;

    /**
     * 缓存配置
     * @var array
     */
    private static $cacheConfig = [
        'type-stats' => 'media_type-stats-{storage}.json',
        'post-info' => 'media_post-info.json',
        'file-details' => 'media_file-details.json',
        'exif-privacy' => 'media_exif-privacy.json',
        'smart-suggestions' => 'media_smart-suggestions.json',
    ];

    /**
     * 初始化缓存管理器
     */
    public static function init()
    {
        try {
            // 确保 __TYPECHO_ROOT_DIR__ 已定义
            if (!defined('__TYPECHO_ROOT_DIR__')) {
                self::$cacheDir = sys_get_temp_dir() . '/medialibrary_cache';
            } else {
                self::$cacheDir = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/cache';
            }

            // 确保缓存目录存在
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0755, true);
            }

            // 如果目录创建失败，使用临时目录
            if (!is_dir(self::$cacheDir) || !is_writable(self::$cacheDir)) {
                self::$cacheDir = sys_get_temp_dir() . '/medialibrary_cache';
                if (!is_dir(self::$cacheDir)) {
                    @mkdir(self::$cacheDir, 0755, true);
                }
            }

            // 最后的fallback：如果还是失败，使用系统临时目录本身
            if (!is_dir(self::$cacheDir) || !is_writable(self::$cacheDir)) {
                self::$cacheDir = sys_get_temp_dir();
            }
        } catch (Exception $e) {
            // 如果初始化完全失败，使用系统临时目录
            self::$cacheDir = sys_get_temp_dir();
        }
    }

    /**
     * 获取缓存文件路径
     *
     * @param string $cacheType 缓存类型
     * @param string $suffix 后缀（例如存储类型）
     * @return string 缓存文件路径
     */
    public static function getCachePath($cacheType, $suffix = '')
    {
        try {
            if (self::$cacheDir === null) {
                self::init();
            }

            if (!isset(self::$cacheConfig[$cacheType])) {
                return null;
            }

            $filename = self::$cacheConfig[$cacheType];

            // 替换占位符
            if (!empty($suffix)) {
                $filename = str_replace('{storage}', $suffix, $filename);
            } else {
                $filename = str_replace('-{storage}', '', $filename);
            }

            return self::$cacheDir . '/' . $filename;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 读取缓存
     *
     * @param string $cacheType 缓存类型
     * @param string $suffix 后缀
     * @param int $maxAge 最大缓存时间（秒），0表示不检查
     * @return mixed 缓存数据，失败返回 null
     */
    public static function get($cacheType, $suffix = '', $maxAge = 0)
    {
        try {
            $cachePath = self::getCachePath($cacheType, $suffix);

            if (!$cachePath || !file_exists($cachePath)) {
                return null;
            }

            // 检查缓存是否过期
            if ($maxAge > 0) {
                $fileTime = filemtime($cachePath);
                if (time() - $fileTime > $maxAge) {
                    return null;
                }
            }

            $content = @file_get_contents($cachePath);
            if ($content === false) {
                return null;
            }

            $data = @json_decode($content, true);
            return $data;
        } catch (Exception $e) {
            // 缓存读取失败，返回 null 不影响正常功能
            return null;
        }
    }

    /**
     * 写入缓存
     *
     * @param string $cacheType 缓存类型
     * @param mixed $data 缓存数据
     * @param string $suffix 后缀
     * @return bool 是否成功
     */
    public static function set($cacheType, $data, $suffix = '')
    {
        try {
            $cachePath = self::getCachePath($cacheType, $suffix);

            if (!$cachePath) {
                return false;
            }

            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $result = @file_put_contents($cachePath, $json, LOCK_EX);
            return $result !== false;
        } catch (Exception $e) {
            // 缓存写入失败，不影响正常功能
            return false;
        }
    }

    /**
     * 删除缓存
     *
     * @param string $cacheType 缓存类型
     * @param string $suffix 后缀
     * @return bool 是否成功
     */
    public static function delete($cacheType, $suffix = '')
    {
        $cachePath = self::getCachePath($cacheType, $suffix);

        if (!$cachePath || !file_exists($cachePath)) {
            return true;
        }

        return @unlink($cachePath);
    }

    /**
     * 清空所有缓存
     *
     * @return array 清理结果统计
     */
    public static function clearAll()
    {
        if (self::$cacheDir === null) {
            self::init();
        }

        $result = [
            'success' => true,
            'deleted' => 0,
            'failed' => 0,
            'files' => []
        ];

        if (!is_dir(self::$cacheDir)) {
            return $result;
        }

        $files = glob(self::$cacheDir . '/media_*.json');

        foreach ($files as $file) {
            if (@unlink($file)) {
                $result['deleted']++;
                $result['files'][] = basename($file);
            } else {
                $result['failed']++;
                $result['success'] = false;
            }
        }

        return $result;
    }

    /**
     * 刷新类型统计缓存
     *
     * @param object $db 数据库对象
     * @param string $storage 存储类型
     * @return bool 是否成功
     */
    public static function refreshTypeStats($db, $storage = 'all')
    {
        try {
            $stats = MediaLibrary_PanelHelper::getTypeStatistics($db, $storage);
            return self::set('type-stats', $stats, $storage);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 刷新附件所属文章信息缓存
     *
     * @param object $db 数据库对象
     * @param int $batchSize 每批处理数量
     * @return bool 是否成功
     */
    public static function refreshPostInfo($db, $batchSize = 200)
    {
        try {
            $postInfo = [];

            // 分批查询所有附件及其父文章信息
            $offset = 0;
            $hasMore = true;
            $parentIds = [];

            while ($hasMore) {
                $query = $db->select('cid', 'parent')
                    ->from('table.contents')
                    ->where('type = ? AND parent > 0', 'attachment')
                    ->limit($batchSize)
                    ->offset($offset);

                $attachments = $db->fetchAll($query);

                if (empty($attachments)) {
                    $hasMore = false;
                    break;
                }

                foreach ($attachments as $attachment) {
                    $parentIds[$attachment['parent']] = $attachment['parent'];
                }

                $offset += $batchSize;

                if (count($attachments) < $batchSize) {
                    $hasMore = false;
                }
            }

            if (!empty($parentIds)) {
                // 批量查询父文章信息
                $posts = $db->fetchAll($db->select('cid', 'title', 'type', 'status')
                    ->from('table.contents')
                    ->where('cid IN ?', array_values($parentIds)));

                $postMap = [];
                foreach ($posts as $post) {
                    $postMap[$post['cid']] = [
                        'cid' => $post['cid'],
                        'title' => $post['title'],
                        'type' => $post['type'],
                        'status' => $post['status']
                    ];
                }

                // 重新遍历附件构建映射
                $offset = 0;
                $hasMore = true;

                while ($hasMore) {
                    $query = $db->select('cid', 'parent')
                        ->from('table.contents')
                        ->where('type = ? AND parent > 0', 'attachment')
                        ->limit($batchSize)
                        ->offset($offset);

                    $attachments = $db->fetchAll($query);

                    if (empty($attachments)) {
                        $hasMore = false;
                        break;
                    }

                    foreach ($attachments as $attachment) {
                        if (isset($postMap[$attachment['parent']])) {
                            $postInfo[$attachment['cid']] = [
                                'status' => 'archived',
                                'post' => $postMap[$attachment['parent']]
                            ];
                        }
                    }

                    $offset += $batchSize;

                    if (count($attachments) < $batchSize) {
                        $hasMore = false;
                    }
                }
            }

            return self::set('post-info', $postInfo);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 更新单个附件的文章信息
     *
     * @param int $cid 附件 ID
     * @param object $db 数据库对象
     * @return bool 是否成功
     */
    public static function updatePostInfo($cid, $db)
    {
        $postInfo = self::get('post-info') ?: [];

        $result = MediaLibrary_PanelHelper::getParentPost($db, $cid);

        if ($result['status'] === 'archived') {
            $postInfo[$cid] = $result;
        } else {
            unset($postInfo[$cid]);
        }

        return self::set('post-info', $postInfo);
    }

    /**
     * 刷新文件详情缓存
     *
     * @param object $db 数据库对象
     * @param bool $enableGetID3 是否启用 GetID3
     * @param int $batchSize 每批处理数量
     * @return bool 是否成功
     */
    public static function refreshFileDetails($db, $enableGetID3 = false, $batchSize = 100)
    {
        try {
            $fileDetails = self::get('file-details') ?: [];

            // 分批获取附件，避免内存溢出
            $offset = 0;
            $hasMore = true;

            while ($hasMore) {
                // 使用 LIMIT 分批查询
                $query = $db->select()
                    ->from('table.contents')
                    ->where('type = ?', 'attachment')
                    ->limit($batchSize)
                    ->offset($offset);

                $attachments = $db->fetchAll($query);

                if (empty($attachments)) {
                    $hasMore = false;
                    break;
                }

                foreach ($attachments as $attachment) {
                    $attachmentData = @unserialize($attachment['text']);
                    if (is_array($attachmentData) && isset($attachmentData['path'])) {
                        $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];

                        if (file_exists($filePath)) {
                            $mtime = filemtime($filePath);
                            $hash = md5($filePath);

                            // 只有文件修改时间变化时才重新生成
                            if (!isset($fileDetails[$hash]) || $fileDetails[$hash]['mtime'] !== $mtime) {
                                $info = MediaLibrary_PanelHelper::getDetailedFileInfo($filePath, $enableGetID3, false);
                                $fileDetails[$hash] = array_merge($info, [
                                    'mtime' => $mtime,
                                    'path' => $filePath,
                                    'cached_at' => time()
                                ]);
                            }
                        }
                    }
                }

                $offset += $batchSize;

                // 如果返回的数量少于批量大小，说明没有更多数据了
                if (count($attachments) < $batchSize) {
                    $hasMore = false;
                }
            }

            return self::set('file-details', $fileDetails);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取或更新单个文件详情
     *
     * @param string $filePath 文件路径
     * @param bool $enableGetID3 是否启用 GetID3
     * @return array|null 文件详情
     */
    public static function getOrUpdateFileDetails($filePath, $enableGetID3 = false)
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $fileDetails = self::get('file-details') ?: [];
        $hash = md5($filePath);
        $mtime = filemtime($filePath);

        // 检查缓存是否有效
        if (isset($fileDetails[$hash]) && $fileDetails[$hash]['mtime'] === $mtime) {
            return $fileDetails[$hash];
        }

        // 生成新的文件详情
        $info = MediaLibrary_PanelHelper::getDetailedFileInfo($filePath, $enableGetID3);
        $fileDetails[$hash] = array_merge($info, [
            'mtime' => $mtime,
            'path' => $filePath,
            'cached_at' => time()
        ]);

        self::set('file-details', $fileDetails);

        return $fileDetails[$hash];
    }

    /**
     * 刷新 EXIF/GPS 检测结果缓存
     *
     * @param object $db 数据库对象
     * @param object $options 选项对象
     * @param int $batchSize 每批处理数量
     * @return bool 是否成功
     */
    public static function refreshExifPrivacy($db, $options, $batchSize = 50)
    {
        try {
            $exifData = self::get('exif-privacy') ?: [];

            // 分批获取图片附件，避免内存溢出
            $offset = 0;
            $hasMore = true;

            while ($hasMore) {
                // 使用 LIMIT 分批查询
                $query = $db->select()
                    ->from('table.contents')
                    ->where('type = ?', 'attachment')
                    ->where('text LIKE ?', '%image%')
                    ->limit($batchSize)
                    ->offset($offset);

                $attachments = $db->fetchAll($query);

                if (empty($attachments)) {
                    $hasMore = false;
                    break;
                }

                foreach ($attachments as $attachment) {
                    $attachmentData = @unserialize($attachment['text']);
                    if (is_array($attachmentData) && isset($attachmentData['path'])) {
                        $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];

                        if (file_exists($filePath)) {
                            $mtime = filemtime($filePath);
                            $cid = $attachment['cid'];

                            // 只有文件修改时间变化时才重新检测
                            if (!isset($exifData[$cid]) || $exifData[$cid]['mtime'] !== $mtime) {
                                $result = MediaLibrary_ExifPrivacy::checkImagePrivacy($cid, $db, $options);

                                if ($result['success']) {
                                    $exifData[$cid] = [
                                        'has_privacy' => $result['has_privacy'],
                                        'privacy_info' => $result['privacy_info'],
                                        'gps_coords' => $result['gps_coords'],
                                        'mtime' => $mtime,
                                        'cached_at' => time()
                                    ];
                                }
                            }
                        }
                    }
                }

                $offset += $batchSize;

                if (count($attachments) < $batchSize) {
                    $hasMore = false;
                }
            }

            return self::set('exif-privacy', $exifData);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取或更新单个图片的 EXIF/GPS 数据
     *
     * @param int $cid 附件 ID
     * @param object $db 数据库对象
     * @param object $options 选项对象
     * @return array|null EXIF/GPS 数据
     */
    public static function getOrUpdateExifPrivacy($cid, $db, $options)
    {
        $exifData = self::get('exif-privacy') ?: [];

        $attachment = $db->fetchRow($db->select()->from('table.contents')
            ->where('cid = ? AND type = ?', $cid, 'attachment'));

        if (!$attachment) {
            return null;
        }

        $attachmentData = @unserialize($attachment['text']);
        if (!is_array($attachmentData) || !isset($attachmentData['path'])) {
            return null;
        }

        $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];
        if (!file_exists($filePath)) {
            return null;
        }

        $mtime = filemtime($filePath);

        // 检查缓存是否有效
        if (isset($exifData[$cid]) && $exifData[$cid]['mtime'] === $mtime) {
            return $exifData[$cid];
        }

        // 生成新的 EXIF 数据
        $result = MediaLibrary_ExifPrivacy::checkImagePrivacy($cid, $db, $options);

        if ($result['success']) {
            $exifData[$cid] = [
                'has_privacy' => $result['has_privacy'],
                'privacy_info' => $result['privacy_info'],
                'gps_coords' => $result['gps_coords'],
                'mtime' => $mtime,
                'cached_at' => time()
            ];

            self::set('exif-privacy', $exifData);

            return $exifData[$cid];
        }

        return null;
    }

    /**
     * 删除单个附件的 EXIF 缓存
     *
     * @param int $cid 附件 ID
     * @return bool 是否成功
     */
    public static function deleteExifPrivacy($cid)
    {
        $exifData = self::get('exif-privacy') ?: [];

        if (isset($exifData[$cid])) {
            unset($exifData[$cid]);
            return self::set('exif-privacy', $exifData);
        }

        return true;
    }

    /**
     * 刷新智能压缩建议缓存
     *
     * @param object $db 数据库对象
     * @param int $batchSize 每批处理数量
     * @return bool 是否成功
     */
    public static function refreshSmartSuggestions($db, $batchSize = 100)
    {
        try {
            $suggestions = self::get('smart-suggestions') ?: [];

            // 分批获取图片附件，避免内存溢出
            $offset = 0;
            $hasMore = true;

            while ($hasMore) {
                // 使用 LIMIT 分批查询
                $query = $db->select()
                    ->from('table.contents')
                    ->where('type = ?', 'attachment')
                    ->where('text LIKE ?', '%image%')
                    ->limit($batchSize)
                    ->offset($offset);

                $attachments = $db->fetchAll($query);

                if (empty($attachments)) {
                    $hasMore = false;
                    break;
                }

                foreach ($attachments as $attachment) {
                    $attachmentData = @unserialize($attachment['text']);
                    if (is_array($attachmentData) && isset($attachmentData['path'])) {
                        $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];

                        if (file_exists($filePath)) {
                            $fileSize = filesize($filePath);
                            $cid = $attachment['cid'];

                            // 只有文件大小变化时才重新生成建议
                            if (!isset($suggestions[$cid]) || $suggestions[$cid]['file_size'] !== $fileSize) {
                                $suggestion = MediaLibrary_ImageProcessing::getSmartCompressionSuggestion(
                                    $filePath,
                                    $attachmentData['mime'],
                                    $fileSize
                                );

                                $suggestions[$cid] = array_merge($suggestion, [
                                    'file_size' => $fileSize,
                                    'cached_at' => time()
                                ]);
                            }
                        }
                    }
                }

                $offset += $batchSize;

                if (count($attachments) < $batchSize) {
                    $hasMore = false;
                }
            }

            return self::set('smart-suggestions', $suggestions);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取或更新单个图片的智能压缩建议
     *
     * @param int $cid 附件 ID
     * @param object $db 数据库对象
     * @return array|null 压缩建议
     */
    public static function getOrUpdateSmartSuggestion($cid, $db)
    {
        $suggestions = self::get('smart-suggestions') ?: [];

        $attachment = $db->fetchRow($db->select()->from('table.contents')
            ->where('cid = ? AND type = ?', $cid, 'attachment'));

        if (!$attachment) {
            return null;
        }

        $attachmentData = @unserialize($attachment['text']);
        if (!is_array($attachmentData) || !isset($attachmentData['path'])) {
            return null;
        }

        $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];
        if (!file_exists($filePath)) {
            return null;
        }

        $fileSize = filesize($filePath);

        // 检查缓存是否有效
        if (isset($suggestions[$cid]) && $suggestions[$cid]['file_size'] === $fileSize) {
            return $suggestions[$cid];
        }

        // 生成新的建议
        $suggestion = MediaLibrary_ImageProcessing::getSmartCompressionSuggestion(
            $filePath,
            $attachmentData['mime'],
            $fileSize
        );

        $suggestions[$cid] = array_merge($suggestion, [
            'file_size' => $fileSize,
            'cached_at' => time()
        ]);

        self::set('smart-suggestions', $suggestions);

        return $suggestions[$cid];
    }

    /**
     * 删除指定附件的所有相关缓存
     *
     * @param int $cid 附件 ID
     * @param object $db 数据库对象
     * @return array 删除结果
     */
    public static function deleteAttachmentCache($cid, $db = null)
    {
        $result = [
            'post-info' => false,
            'exif-privacy' => false,
            'smart-suggestions' => false
        ];

        // 删除文章信息缓存
        $postInfo = self::get('post-info') ?: [];
        if (isset($postInfo[$cid])) {
            unset($postInfo[$cid]);
            $result['post-info'] = self::set('post-info', $postInfo);
        } else {
            $result['post-info'] = true;
        }

        // 删除 EXIF 缓存
        $result['exif-privacy'] = self::deleteExifPrivacy($cid);

        // 删除智能建议缓存
        $suggestions = self::get('smart-suggestions') ?: [];
        if (isset($suggestions[$cid])) {
            unset($suggestions[$cid]);
            $result['smart-suggestions'] = self::set('smart-suggestions', $suggestions);
        } else {
            $result['smart-suggestions'] = true;
        }

        return $result;
    }

    /**
     * 刷新所有缓存
     *
     * @param object $db 数据库对象
     * @param object $options 选项对象
     * @param bool $enableGetID3 是否启用 GetID3
     * @return array 刷新结果
     */
    public static function refreshAll($db, $options, $enableGetID3 = false)
    {
        $result = [
            'success' => true,
            'refreshed' => [],
            'failed' => []
        ];

        // 刷新类型统计缓存（所有存储类型）
        foreach (['all', 'local', 'webdav'] as $storage) {
            if (self::refreshTypeStats($db, $storage)) {
                $result['refreshed'][] = "type-stats-{$storage}";
            } else {
                $result['failed'][] = "type-stats-{$storage}";
                $result['success'] = false;
            }
        }

        // 刷新附件所属文章信息缓存
        if (self::refreshPostInfo($db)) {
            $result['refreshed'][] = 'post-info';
        } else {
            $result['failed'][] = 'post-info';
            $result['success'] = false;
        }

        // 刷新文件详情缓存
        if (self::refreshFileDetails($db, $enableGetID3)) {
            $result['refreshed'][] = 'file-details';
        } else {
            $result['failed'][] = 'file-details';
            $result['success'] = false;
        }

        // 刷新 EXIF/GPS 缓存
        if (self::refreshExifPrivacy($db, $options)) {
            $result['refreshed'][] = 'exif-privacy';
        } else {
            $result['failed'][] = 'exif-privacy';
            $result['success'] = false;
        }

        // 刷新智能压缩建议缓存
        if (self::refreshSmartSuggestions($db)) {
            $result['refreshed'][] = 'smart-suggestions';
        } else {
            $result['failed'][] = 'smart-suggestions';
            $result['success'] = false;
        }

        return $result;
    }

    /**
     * 获取缓存统计信息
     *
     * @return array 缓存统计
     */
    public static function getStats()
    {
        if (self::$cacheDir === null) {
            self::init();
        }

        $stats = [
            'cache_dir' => self::$cacheDir,
            'total_size' => 0,
            'files' => []
        ];

        if (!is_dir(self::$cacheDir)) {
            return $stats;
        }

        $files = glob(self::$cacheDir . '/media_*.json');

        foreach ($files as $file) {
            $size = filesize($file);
            $stats['total_size'] += $size;
            $stats['files'][] = [
                'name' => basename($file),
                'size' => $size,
                'modified' => filemtime($file),
                'age' => time() - filemtime($file)
            ];
        }

        return $stats;
    }
}
