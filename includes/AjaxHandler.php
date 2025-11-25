<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/Logger.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/PanelHelper.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/FileOperations.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/ImageProcessing.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/VideoProcessing.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/ExifPrivacy.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVClient.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVSync.php';

/**
 * Ajax请求处理类
 */
class MediaLibrary_AjaxHandler
{
    /**
     * 处理AJAX请求
     * 
     * @param Typecho_Request $request 请求对象
     * @param Typecho_Db $db 数据库连接
     * @param Typecho_Widget_Helper_Options $options 系统选项
     * @param Typecho_Widget_User $user 当前用户
     */
    public static function handleRequest($request, $db, $options, $user)
    {
        $action = $request->get('action');
        
        // 获取插件配置
        $configOptions = MediaLibrary_PanelHelper::getPluginConfig();
        extract($configOptions);
        
        // 确保输出 JSON
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            switch ($action) {
                case 'delete':
                    self::handleDeleteAction($request, $db);
                    break;
                    
                case 'upload':
                    self::handleUploadAction();
                    break;
                    
                case 'get_info':
                    self::handleGetInfoAction($request, $db, $options, $enableGetID3);
                    break;
                    
                case 'compress_images':
                    self::handleCompressImagesAction($request, $db, $options, $user, $gdQuality);
                    break;
                
                case 'crop_image':
                    self::handleCropImageAction($request, $db, $options, $user);
                    break;

                case 'add_watermark':
                    self::handleAddWatermarkAction($request, $db, $options, $user);
                    break;
    
                case 'compress_videos':
                    self::handleCompressVideosAction($request, $db, $options, $user, $videoQuality, $videoCodec);
                    break;
                    
                case 'check_privacy':
                    self::handleCheckPrivacyAction($request, $db, $options, $enableExif);
                    break;
                    
                case 'get_gps_data':
                    self::handleGetGpsDataAction($request, $db, $options);
                    break;
                    
                case 'get_smart_suggestion':
                    self::handleGetSmartSuggestionAction($request, $db);
                    break;
                    
                case 'remove_exif':
                    self::handleRemoveExifAction($request, $db, $enableExif);
                    break;

                case 'webdav_list':
                    self::handleWebDAVListAction($request, $configOptions);
                    break;

                case 'webdav_create_folder':
                    self::handleWebDAVCreateFolderAction($request, $configOptions);
                    break;

                case 'webdav_delete':
                    self::handleWebDAVDeleteAction($request, $configOptions);
                    break;

                case 'webdav_upload':
                    self::handleWebDAVUploadAction($request, $configOptions);
                    break;

                case 'webdav_test':
                    self::handleWebDAVTestAction($request, $configOptions);
                    break;
                case 'webdav_sync_download':
                    self::handleWebDAVSyncDownloadAction($request, $configOptions);
                    break;

                case 'webdav_sync_to_local':
                    self::handleWebDAVSyncToLocalAction($request, $configOptions, $db);
                    break;

                case 'webdav_sync_from_local':
                    self::handleWebDAVSyncFromLocalAction($request, $configOptions, $db);
                    break;

                case 'webdav_sync_all_local':
                    self::handleWebDAVSyncAllLocalAction($request, $configOptions, $db);
                    break;


                // 以下 WebDAV 同步方法已移除，请使用新的 WebDAVSync 类

                default:
                    MediaLibrary_Logger::log('ajax_unknown', '收到未知的操作请求', [
                        'action' => $action
                    ], 'warning');
                    echo json_encode(['success' => false, 'message' => '未知操作'], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            MediaLibrary_Logger::log('ajax_error', '操作失败: ' . $e->getMessage(), [
                'action' => $action,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 'error');
            echo json_encode(['success' => false, 'message' => '操作失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    /**
     * 处理删除请求
     */
    private static function handleDeleteAction($request, $db)
    {
        $cids = $request->getArray('cids');
        if (empty($cids)) {
            MediaLibrary_Logger::log('delete', '删除操作失败：未选择文件', [], 'warning');
            echo json_encode(['success' => false, 'message' => '请选择要删除的文件'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 获取 WebDAV 配置
        $configOptions = MediaLibrary_PanelHelper::getPluginConfig();
        $webdavClient = null;
        if ($configOptions['enableWebDAV']) {
            try {
                $webdavClient = new MediaLibrary_WebDAVClient($configOptions);
            } catch (Exception $e) {
                // WebDAV 客户端初始化失败，继续使用本地删除
                MediaLibrary_Logger::log('delete', 'WebDAV 客户端初始化失败: ' . $e->getMessage(), [], 'warning');
            }
        }

        $deleteCount = 0;
        $failedCount = 0;
        $failedFiles = [];

        foreach ($cids as $cid) {
            $cid = intval($cid);
            $attachment = $db->fetchRow($db->select()->from('table.contents')
                ->where('cid = ? AND type = ?', $cid, 'attachment'));

            if (!$attachment) {
                $failedCount++;
                $failedFiles[] = ['cid' => $cid, 'reason' => '文件记录不存在'];
                continue;
            }

            $fileDeleted = false;
            $dbDeleted = false;

            // 删除物理文件
            if (isset($attachment['text'])) {
                $attachmentData = @unserialize($attachment['text']);
                if (is_array($attachmentData) && isset($attachmentData['path'])) {
                    $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];

                    // 首先尝试删除本地文件
                    if (file_exists($filePath)) {
                        if (@unlink($filePath)) {
                            $fileDeleted = true;
                        } else {
                            MediaLibrary_Logger::log('delete', '本地文件删除失败', [
                                'cid' => $cid,
                                'path' => $attachmentData['path']
                            ], 'warning');
                        }
                    } else {
                        // 文件不存在，视为已删除
                        $fileDeleted = true;
                    }

                    // 如果启用了 WebDAV，也尝试从 WebDAV 删除
                    if ($webdavClient && isset($attachmentData['webdav_path'])) {
                        try {
                            $webdavClient->delete($attachmentData['webdav_path']);
                            MediaLibrary_Logger::log('delete', 'WebDAV 文件删除成功', [
                                'cid' => $cid,
                                'path' => $attachmentData['webdav_path']
                            ]);
                        } catch (Exception $e) {
                            MediaLibrary_Logger::log('delete', 'WebDAV 文件删除失败: ' . $e->getMessage(), [
                                'cid' => $cid,
                                'path' => $attachmentData['webdav_path']
                            ], 'warning');
                        }
                    }
                } else {
                    // 如果无法解析附件数据，也继续删除数据库记录
                    $fileDeleted = true;
                }
            } else {
                // 没有文件数据，直接删除数据库记录
                $fileDeleted = true;
            }

            // 删除数据库记录
            try {
                $db->query($db->delete('table.contents')->where('cid = ?', $cid));
                $dbDeleted = true;
                $deleteCount++;
            } catch (Exception $e) {
                MediaLibrary_Logger::log('delete', '数据库记录删除失败: ' . $e->getMessage(), [
                    'cid' => $cid
                ], 'error');
                $failedCount++;
                $failedFiles[] = [
                    'cid' => $cid,
                    'title' => isset($attachment['title']) ? $attachment['title'] : '未知',
                    'reason' => '数据库删除失败: ' . $e->getMessage()
                ];
            }
        }

        MediaLibrary_Logger::log('delete', '删除操作完成', [
            'requested_cids' => $cids,
            'deleted' => $deleteCount,
            'failed' => $failedCount
        ]);

        if ($failedCount > 0) {
            $message = "成功删除 {$deleteCount} 个文件，{$failedCount} 个文件删除失败";
            echo json_encode([
                'success' => $deleteCount > 0,
                'message' => $message,
                'deleted' => $deleteCount,
                'failed' => $failedCount,
                'failed_files' => $failedFiles
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => true,
                'message' => "成功删除 {$deleteCount} 个文件",
                'deleted' => $deleteCount
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * 处理上传请求
     */
    private static function handleUploadAction()
    {
        if (empty($_FILES)) {
            MediaLibrary_Logger::log('upload', '上传失败：没有文件上传', [], 'warning');
            echo json_encode(['success' => false, 'message' => '没有文件上传'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 获取存储类型参数
        $storage = isset($_GET['storage']) ? $_GET['storage'] : 'all';

        try {
            // 如果是 WebDAV 存储，使用 WebDAV 上传
            if ($storage === 'webdav') {
                $result = self::uploadToWebDAV($_FILES);
                if ($result['success']) {
                    MediaLibrary_Logger::log('upload', 'WebDAV 上传成功', [
                        'files' => self::summarizeUploadedFiles(),
                        'result' => $result
                    ]);
                    echo json_encode($result, JSON_UNESCAPED_UNICODE);
                } else {
                    MediaLibrary_Logger::log('upload', 'WebDAV 上传失败: ' . $result['message'], [
                        'files' => self::summarizeUploadedFiles()
                    ], 'error');
                    echo json_encode($result, JSON_UNESCAPED_UNICODE);
                }
                return;
            }

            // 默认使用 Typecho 的本地上传处理
            $upload = \Widget\Upload::alloc();
            $result = $upload->upload($_FILES);

            if ($result) {
                MediaLibrary_Logger::log('upload', '本地上传成功', [
                    'files' => self::summarizeUploadedFiles(),
                    'result' => $result
                ]);
                echo json_encode(['success' => true, 'count' => 1, 'data' => $result], JSON_UNESCAPED_UNICODE);
            } else {
                MediaLibrary_Logger::log('upload', '本地上传失败：上传返回空结果', [
                    'files' => self::summarizeUploadedFiles()
                ], 'error');
                echo json_encode(['success' => false, 'message' => '上传失败'], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            MediaLibrary_Logger::log('upload', '上传失败: ' . $e->getMessage(), [
                'files' => self::summarizeUploadedFiles()
            ], 'error');
            echo json_encode(['success' => false, 'message' => '上传失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 上传文件到 WebDAV
     */
    private static function uploadToWebDAV($files)
    {
        try {
            // 获取 WebDAV 配置
            $configOptions = MediaLibrary_PanelHelper::getPluginConfig();

            if (empty($configOptions['enableWebDAV'])) {
                return ['success' => false, 'message' => 'WebDAV 未启用'];
            }

            // 使用 WebDAVSync 类处理上传
            require_once __DIR__ . '/WebDAVSync.php';
            require_once __DIR__ . '/WebDAVClient.php';

            $sync = new MediaLibrary_WebDAVSync($configOptions);

            // 获取数据库和选项
            $db = Typecho_Db::get();
            $options = Typecho_Widget::widget('Widget_Options');
            $user = Typecho_Widget::widget('Widget_User');

            $uploadedFiles = [];
            foreach ($files as $file) {
                if (!isset($file['tmp_name']) || !isset($file['name']) || !is_uploaded_file($file['tmp_name'])) {
                    continue;
                }

                // 保存文件到本地 WebDAV 文件夹
                try {
                    $result = $sync->saveUploadedFile($file);

                    if ($result && isset($result['path'])) {
                        // 获取相对路径和完整路径
                        $relativePath = $result['path'];
                        $localPath = $result['local_path'];
                        $fileName = basename($relativePath);

                        // 构建公开访问 URL
                        $webdavClient = new MediaLibrary_WebDAVClient($configOptions);
                        $remotePath = $configOptions['webdavRemotePath'] . '/' . ltrim($relativePath, '/');
                        $publicUrl = $webdavClient->buildPublicUrl($remotePath);

                        // 保存到数据库
                        $attachmentData = [
                            'name' => $fileName,
                            'path' => $relativePath,
                            'size' => $file['size'],
                            'type' => $file['type'],
                            'mime' => $file['type'],
                            'storage' => 'webdav',
                            'webdav_path' => $relativePath,
                            'local_path' => $localPath
                        ];

                        // 插入数据库记录
                        $insertData = [
                            'title' => $fileName,
                            'slug' => $fileName,
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

                        $uploadedFiles[] = [
                            'cid' => $insertId,
                            'name' => $fileName,
                            'path' => $relativePath,
                            'url' => $publicUrl,
                            'size' => $file['size'],
                            'type' => $file['type']
                        ];

                        // 如果启用了自动同步，立即同步到远程
                        if (!empty($configOptions['webdavSyncEnabled']) &&
                            $configOptions['webdavSyncMode'] === 'onupload') {
                            try {
                                $sync->syncFileToRemote($relativePath);
                                MediaLibrary_Logger::log('upload', 'WebDAV 文件已同步到远程', [
                                    'file' => $fileName,
                                    'path' => $relativePath
                                ]);
                            } catch (Exception $e) {
                                MediaLibrary_Logger::log('upload', 'WebDAV 远程同步失败: ' . $e->getMessage(), [
                                    'file' => $fileName
                                ], 'warning');
                                // 不影响上传结果，只记录日志
                            }
                        }

                        MediaLibrary_Logger::log('upload', 'WebDAV 文件上传并记录成功', [
                            'cid' => $insertId,
                            'file' => $fileName,
                            'path' => $relativePath,
                            'url' => $publicUrl
                        ]);
                    } else {
                        return ['success' => false, 'message' => '保存文件失败: ' . $file['name']];
                    }
                } catch (Exception $e) {
                    return ['success' => false, 'message' => '上传失败: ' . $e->getMessage()];
                }
            }

            if (empty($uploadedFiles)) {
                return ['success' => false, 'message' => '没有文件上传成功'];
            }

            return [
                'success' => true,
                'message' => '成功上传到 WebDAV',
                'count' => count($uploadedFiles),
                'data' => $uploadedFiles
            ];
        } catch (Exception $e) {
            MediaLibrary_Logger::log('upload', 'WebDAV 上传错误: ' . $e->getMessage(), [], 'error');
            return ['success' => false, 'message' => 'WebDAV 上传错误: ' . $e->getMessage()];
        }
    }
    
    /**
     * 处理获取信息请求
     */
    private static function handleGetInfoAction($request, $db, $options, $enableGetID3)
    {
        $cid = intval($request->get('cid'));
        if (!$cid) {
            MediaLibrary_Logger::log('get_info', '获取文件信息失败：无效的文件ID', [], 'warning');
            echo json_encode(['success' => false, 'message' => '无效的文件ID'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $attachment = $db->fetchRow($db->select()->from('table.contents')
            ->where('cid = ? AND type = ?', $cid, 'attachment'));
            
        if (!$attachment) {
            MediaLibrary_Logger::log('get_info', '获取文件信息失败：文件不存在', [
                'cid' => $cid
            ], 'error');
            echo json_encode(['success' => false, 'message' => '文件不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $attachmentData = array();
        if (isset($attachment['text']) && !empty($attachment['text'])) {
            $unserialized = @unserialize($attachment['text']);
            if (is_array($unserialized)) {
                $attachmentData = $unserialized;
            }
        }
        
        $parentPost = MediaLibrary_PanelHelper::getParentPost($db, $cid);
        
        $detailedInfo = [];
        if (isset($attachmentData['path'])) {
            $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];
            $detailedInfo = MediaLibrary_PanelHelper::getDetailedFileInfo($filePath, $enableGetID3);
        }
        
        $info = [
            'title' => isset($attachment['title']) ? $attachment['title'] : '未命名文件',
            'mime' => isset($attachmentData['mime']) ? $attachmentData['mime'] : 'unknown',
            'size' => MediaLibrary_FileOperations::formatFileSize(isset($attachmentData['size']) ? intval($attachmentData['size']) : 0),
            'url' => isset($attachmentData['path']) ? 
                Typecho_Common::url($attachmentData['path'], $options->siteUrl) : '',
            'created' => isset($attachment['created']) ? date('Y-m-d H:i:s', $attachment['created']) : '',
            'path' => isset($attachmentData['path']) ? $attachmentData['path'] : '',
            'parent_post' => $parentPost,
            'detailed_info' => $detailedInfo
        ];
        
        MediaLibrary_Logger::log('get_info', '获取文件信息成功', [
            'cid' => $cid,
            'title' => $info['title']
        ]);
        echo json_encode(['success' => true, 'data' => $info], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 处理压缩图片请求
     */
    private static function handleCompressImagesAction($request, $db, $options, $user, $defaultQuality)
    {
        $cids = $request->getArray('cids');
        $quality = intval($request->get('quality', $defaultQuality));
        $outputFormat = $request->get('output_format', 'original');
        $compressMethod = $request->get('compress_method', 'gd');
        $replaceOriginal = $request->get('replace_original') === '1';
        $customName = $request->get('custom_name', '');
        
        if (empty($cids)) {
            MediaLibrary_Logger::log('compress_images', '压缩图片失败：未选择文件', [], 'warning');
            echo json_encode(['success' => false, 'message' => '请选择要压缩的图片'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $results = [];
        foreach ($cids as $cid) {
            $result = MediaLibrary_ImageProcessing::compressImage(
                $cid, 
                $quality, 
                $outputFormat, 
                $compressMethod, 
                $replaceOriginal, 
                $customName, 
                $db, 
                $options, 
                $user
            );
            $results[] = $result;
        }
        
        MediaLibrary_Logger::log('compress_images', '批量图片压缩完成', [
            'cids' => $cids,
            'quality' => $quality,
            'output_format' => $outputFormat,
            'method' => $compressMethod,
            'replace_original' => $replaceOriginal,
            'results' => $results
        ]);
        echo json_encode(['success' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 处理压缩视频请求
     */
    private static function handleCompressVideosAction($request, $db, $options, $user, $defaultQuality, $defaultCodec)
    {
        $cids = $request->getArray('cids');
        $quality = intval($request->get('video_quality', $defaultQuality));
        $codec = $request->get('video_codec', $defaultCodec);
        $replaceOriginal = $request->get('replace_original') === '1';
        $customName = $request->get('custom_name', '');
        
        if (empty($cids)) {
            MediaLibrary_Logger::log('compress_videos', '压缩视频失败：未选择文件', [], 'warning');
            echo json_encode(['success' => false, 'message' => '请选择要压缩的视频'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $results = [];
        foreach ($cids as $cid) {
            $result = MediaLibrary_VideoProcessing::compressVideo(
                $cid, 
                $quality, 
                $codec, 
                $replaceOriginal, 
                $customName, 
                $db, 
                $options, 
                $user
            );
            $results[] = $result;
        }
        
        MediaLibrary_Logger::log('compress_videos', '批量视频压缩完成', [
            'cids' => $cids,
            'quality' => $quality,
            'codec' => $codec,
            'replace_original' => $replaceOriginal,
            'results' => $results
        ]);
        echo json_encode(['success' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
    }

    /**
 * 处理裁剪图片请求
 */
private static function handleCropImageAction($request, $db, $options, $user)
{
    require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/ImageEditor.php';
    
    $cid = intval($request->get('cid'));
    $x = intval($request->get('x'));
    $y = intval($request->get('y'));
    $width = intval($request->get('width'));
    $height = intval($request->get('height'));
    $replaceOriginal = $request->get('replace_original') === '1';
    $customName = $request->get('custom_name', '');
    $useLibrary = $request->get('use_library', 'gd');
    
    if (!$cid) {
        MediaLibrary_Logger::log('crop_image', '裁剪失败：无效的文件ID', [], 'warning');
        echo json_encode(['success' => false, 'message' => '无效的文件ID'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    if ($width <= 0 || $height <= 0) {
        MediaLibrary_Logger::log('crop_image', '裁剪失败：无效的裁剪尺寸', [
            'width' => $width,
            'height' => $height
        ], 'warning');
        echo json_encode(['success' => false, 'message' => '无效的裁剪尺寸'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $result = MediaLibrary_ImageEditor::cropImage(
        $cid, $x, $y, $width, $height, $replaceOriginal, $customName, $useLibrary, $db, $options, $user
    );
    MediaLibrary_Logger::log('crop_image', $result['message'], [
        'cid' => $cid,
        'replace_original' => $replaceOriginal,
        'custom_name' => $customName,
        'use_library' => $useLibrary,
        'result' => $result
    ], !empty($result['success']) ? 'info' : 'error');
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}

/**
 * 处理添加水印请求
 */
private static function handleAddWatermarkAction($request, $db, $options, $user)
{
    require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/ImageEditor.php';
    
    $cid = intval($request->get('cid'));
    $replaceOriginal = $request->get('replace_original') === '1';
    $customName = $request->get('custom_name', '');
    $useLibrary = $request->get('use_library', 'gd');
    
    if (!$cid) {
        MediaLibrary_Logger::log('add_watermark', '水印失败：无效的文件ID', [], 'warning');
        echo json_encode(['success' => false, 'message' => '无效的文件ID'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 收集水印配置
    $watermarkConfig = [];
    
    // 水印类型 (text 或 image)
    $watermarkConfig['type'] = $request->get('watermark_type', 'text');
    
    // 水印位置
    $watermarkConfig['position'] = $request->get('watermark_position', 'bottom-right');
    $watermarkConfig['x'] = intval($request->get('watermark_x', 10));
    $watermarkConfig['y'] = intval($request->get('watermark_y', 10));
    
    // 水印透明度
    $watermarkConfig['opacity'] = intval($request->get('watermark_opacity', 70));

    // 如果是文本水印
    if ($watermarkConfig['type'] === 'text') {
        // 文本内容
        $watermarkConfig['text'] = self::sanitizeWatermarkText($request->get('watermark_text', ''));
        
        // 字体大小和颜色
        $watermarkConfig['fontSize'] = intval($request->get('watermark_font_size', 24));
        $watermarkConfig['color'] = $request->get('watermark_color', '#ffffff');
        
        // 预设类型
        $watermarkConfig['preset'] = $request->get('watermark_preset', '');
        
        // 字体路径
        $fontName = $request->get('watermark_font', 'msyh.ttf');
        $watermarkConfig['fontPath'] = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/assets/fonts/' . $fontName;
    }
    // 如果是图片水印
    elseif ($watermarkConfig['type'] === 'image') {
        // 水印图片路径
        $watermarkImage = $request->get('watermark_image', 'logo.png');
        $watermarkConfig['imagePath'] = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/assets/images/' . $watermarkImage;
        
        // 水印缩放比例
        $watermarkConfig['scale'] = floatval($request->get('watermark_scale', 1));
    } else {
        MediaLibrary_Logger::log('add_watermark', '水印失败：不支持的水印类型', [
            'type' => $watermarkConfig['type']
        ], 'warning');
        echo json_encode(['success' => false, 'message' => '不支持的水印类型'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $result = MediaLibrary_ImageEditor::addWatermark(
        $cid, $watermarkConfig, $replaceOriginal, $customName, $useLibrary, $db, $options, $user
    );
    MediaLibrary_Logger::log('add_watermark', $result['message'], [
        'cid' => $cid,
        'config' => $watermarkConfig,
        'replace_original' => $replaceOriginal,
        'custom_name' => $customName,
        'use_library' => $useLibrary,
        'result' => $result
    ], !empty($result['success']) ? 'info' : 'error');
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}

    
    /**
     * 处理检查隐私信息请求
     */
    private static function handleCheckPrivacyAction($request, $db, $options, $enableExif)
    {
        // 检查是否有可用的 EXIF 工具
        $hasExifTool = MediaLibrary_ExifPrivacy::isExifToolAvailable();
        $hasPhpExif = extension_loaded('exif');
        
        if (!$enableExif || (!$hasExifTool && !$hasPhpExif)) {
            MediaLibrary_Logger::log('check_privacy', 'EXIF检测失败：功能未启用或无可用工具', [
                'enable_exif' => $enableExif,
                'has_exiftool' => $hasExifTool,
                'has_exif_extension' => $hasPhpExif
            ], 'warning');
            echo json_encode(['success' => false, 'message' => 'EXIF功能未启用或无可用的EXIF工具'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $cids = $request->getArray('cids');
        if (empty($cids)) {
            MediaLibrary_Logger::log('check_privacy', 'EXIF检测失败：未选择图片', [], 'warning');
            echo json_encode(['success' => false, 'message' => '请选择要检测的图片'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $results = [];
        foreach ($cids as $cid) {
            $result = MediaLibrary_ExifPrivacy::checkImagePrivacy($cid, $db, $options);
            $results[] = $result;
        }
        
        MediaLibrary_Logger::log('check_privacy', '隐私检测完成', [
            'cids' => $cids,
            'results' => $results
        ]);
        echo json_encode(['success' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 处理获取GPS数据请求
     */
    private static function handleGetGpsDataAction($request, $db, $options)
    {
        $cids = $request->getArray('cids');
        if (empty($cids)) {
            MediaLibrary_Logger::log('get_gps_data', '获取GPS数据失败：未选择图片', [], 'warning');
            echo json_encode(['success' => false, 'message' => '请选择图片文件'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $gpsData = [];
        foreach ($cids as $cid) {
            $attachment = $db->fetchRow($db->select()->from('table.contents')
                ->where('cid = ? AND type = ?', $cid, 'attachment'));
                
            if ($attachment) {
                $attachmentData = @unserialize($attachment['text']);
                if (is_array($attachmentData) && isset($attachmentData['path'])) {
                    $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];
                    if (file_exists($filePath) && strpos($attachmentData['mime'], 'image/') === 0) {
                        $exifData = @exif_read_data($filePath);
                        if ($exifData && isset($exifData['GPSLatitude'], $exifData['GPSLongitude'], $exifData['GPSLatitudeRef'], $exifData['GPSLongitudeRef'])
                            && is_array($exifData['GPSLatitude']) && is_array($exifData['GPSLongitude'])) {
                            
                            try {
                                $lat = MediaLibrary_ExifPrivacy::exifToFloat($exifData['GPSLatitude'], $exifData['GPSLatitudeRef']);
                                $lng = MediaLibrary_ExifPrivacy::exifToFloat($exifData['GPSLongitude'], $exifData['GPSLongitudeRef']);
                                
                                $gpsData[] = [
                                    'cid' => $cid,
                                    'title' => $attachment['title'],
                                    'coords' => [$lng, $lat],
                                    'url' => Typecho_Common::url($attachmentData['path'], $options->siteUrl)
                                ];
                            } catch (Exception $e) {
                                // GPS解析失败，跳过
                            }
                        }
                    }
                }
            }
        }
        
        MediaLibrary_Logger::log('get_gps_data', 'GPS 数据获取完成', [
            'cids' => $cids,
            'count' => count($gpsData)
        ]);
        echo json_encode(['success' => true, 'data' => $gpsData], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 处理获取智能建议请求
     */
    private static function handleGetSmartSuggestionAction($request, $db)
    {
        $cids = $request->getArray('cids');
        if (empty($cids)) {
            MediaLibrary_Logger::log('smart_suggestion', '获取智能建议失败：未选择图片', [], 'warning');
            echo json_encode(['success' => false, 'message' => '请选择图片文件'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $suggestions = [];
        foreach ($cids as $cid) {
            $attachment = $db->fetchRow($db->select()->from('table.contents')
                ->where('cid = ? AND type = ?', $cid, 'attachment'));
                
            if ($attachment) {
                $attachmentData = @unserialize($attachment['text']);
                if (is_array($attachmentData) && isset($attachmentData['path'])) {
                    $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];
                    if (file_exists($filePath) && strpos($attachmentData['mime'], 'image/') === 0) {
                        $fileSize = filesize($filePath);
                        $suggestion = MediaLibrary_ImageProcessing::getSmartCompressionSuggestion($filePath, $attachmentData['mime'], $fileSize);
                        $suggestions[] = [
                            'cid' => $cid,
                            'filename' => $attachmentData['name'],
                            'size' => MediaLibrary_FileOperations::formatFileSize($fileSize),
                            'suggestion' => $suggestion
                        ];
                    }
                }
            }
        }
        
        MediaLibrary_Logger::log('smart_suggestion', '智能压缩建议生成完毕', [
            'cids' => $cids,
            'count' => count($suggestions)
        ]);
        echo json_encode(['success' => true, 'suggestions' => $suggestions], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 处理移除EXIF请求
     */
    private static function handleRemoveExifAction($request, $db, $enableExif)
    {
        // 检查是否有可用的 EXIF 工具
        $hasExifTool = MediaLibrary_ExifPrivacy::isExifToolAvailable();
        $hasPhpExif = extension_loaded('exif');
        $hasGD = extension_loaded('gd');
        
        if (!$enableExif || (!$hasExifTool && !$hasGD)) {
            MediaLibrary_Logger::log('remove_exif', '清除EXIF失败：功能未启用或缺少工具', [
                'enable_exif' => $enableExif,
                'has_exiftool' => $hasExifTool,
                'has_gd' => $hasGD
            ], 'warning');
            echo json_encode(['success' => false, 'message' => 'EXIF功能未启用或无可用的EXIF清除工具'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $cid = intval($request->get('cid'));
        if (!$cid) {
            MediaLibrary_Logger::log('remove_exif', '清除EXIF失败：无效的文件ID', [], 'warning');
            echo json_encode(['success' => false, 'message' => '无效的文件ID'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $attachment = $db->fetchRow($db->select()->from('table.contents')
            ->where('cid = ? AND type = ?', $cid, 'attachment'));
            
        if (!$attachment) {
            MediaLibrary_Logger::log('remove_exif', '清除EXIF失败：文件不存在', [
                'cid' => $cid
            ], 'error');
            echo json_encode(['success' => false, 'message' => '文件不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $attachmentData = @unserialize($attachment['text']);
        if (!is_array($attachmentData) || !isset($attachmentData['path'])) {
            MediaLibrary_Logger::log('remove_exif', '清除EXIF失败：文件数据损坏', [
                'cid' => $cid
            ], 'error');
            echo json_encode(['success' => false, 'message' => '文件数据错误'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];
        if (!file_exists($filePath)) {
            MediaLibrary_Logger::log('remove_exif', '清除EXIF失败：文件不存在于磁盘', [
                'cid' => $cid,
                'path' => $attachmentData['path']
            ], 'error');
            echo json_encode(['success' => false, 'message' => '文件不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // 检查是否为图片
        if (strpos($attachmentData['mime'], 'image/') !== 0) {
            MediaLibrary_Logger::log('remove_exif', '清除EXIF失败：文件不是图片', [
                'cid' => $cid,
                'mime' => $attachmentData['mime']
            ], 'warning');
            echo json_encode(['success' => false, 'message' => '只能清除图片文件的EXIF信息'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // 智能清除EXIF信息
        $result = MediaLibrary_ExifPrivacy::removeImageExif($filePath, $attachmentData['mime']);
        MediaLibrary_Logger::log('remove_exif', $result['message'], [
            'cid' => $cid,
            'path' => $attachmentData['path'],
            'result' => $result
        ], !empty($result['success']) ? 'info' : 'error');
        
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }


    /**
     * WebDAV 列表（读取本地 WebDAV 文件夹）
     */
    private static function handleWebDAVListAction($request, $configOptions)
    {
        $path = $request->get('path', '/');

        // 检查 WebDAV 是否已启用
        if (empty($configOptions['enableWebDAV'])) {
            echo json_encode([
                'success' => false,
                'message' => 'WebDAV 功能未启用。请在插件设置中启用 WebDAV 同步存储。',
                'need_config' => true
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 检查本地 WebDAV 文件夹是否配置
        if (empty($configOptions['webdavLocalPath'])) {
            echo json_encode([
                'success' => false,
                'message' => 'WebDAV 本地文件夹未配置。请在插件设置中填写"本地 WebDAV 文件夹路径"。',
                'need_config' => true
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // 检查本地文件夹是否存在
        $localPath = rtrim($configOptions['webdavLocalPath'], '/\\');
        if (!is_dir($localPath)) {
            echo json_encode([
                'success' => false,
                'message' => '本地 WebDAV 文件夹不存在: ' . $localPath . '。请先创建此目录或修改配置。',
                'need_create' => true,
                'local_path' => $localPath
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $sync = new MediaLibrary_WebDAVSync($configOptions);
            $subPath = trim($path, '/');
            $items = $sync->listLocalFiles($subPath);

            $formattedItems = [];
            foreach ($items as $item) {
                $formattedItems[] = [
                    'name' => $item['name'],
                    'path' => $item['path'],
                    'is_dir' => $item['type'] === 'directory' ? 1 : 0,
                    'size' => $item['size'],
                    'size_human' => $item['type'] === 'directory' ? '-' : MediaLibrary_FileOperations::formatFileSize($item['size']),
                    'modified' => $item['modified'],
                    'modified_format' => $item['modified_format'],
                    'mime' => $item['type'] === 'directory' ? 'directory' : 'application/octet-stream',
                ];
            }

            MediaLibrary_Logger::log('webdav_list', '读取本地 WebDAV 文件夹', [
                'path' => $path,
                'count' => count($formattedItems)
            ]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'current_path' => $path,
                    'items' => $formattedItems
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_list', 'WebDAV 列表失败: ' . $e->getMessage(), [
                'path' => $path
            ], 'error');
            echo json_encode(['success' => false, 'message' => 'WebDAV 操作失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * WebDAV 创建文件夹（在本地创建）
     */
    private static function handleWebDAVCreateFolderAction($request, $configOptions)
    {
        $parentPath = self::normalizeWebDAVPath($request->get('path', '/'));
        $name = trim($request->get('name', ''));

        if ($name === '' || preg_match('/[\\\\\/]/', $name)) {
            echo json_encode(['success' => false, 'message' => '文件夹名称不合法'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $target = self::joinWebDAVPath($parentPath, $name);

        try {
            $sync = new MediaLibrary_WebDAVSync($configOptions);
            $localPath = $sync->getLocalPath();

            // 构建完整的本地路径
            $fullPath = $localPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($target, '/'));

            if (!mkdir($fullPath, 0755, true)) {
                throw new Exception('创建目录失败');
            }

            MediaLibrary_Logger::log('webdav_create_folder', '创建本地目录成功', [
                'path' => $target
            ]);

            echo json_encode(['success' => true, 'message' => '目录创建成功'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_create_folder', '创建目录失败: ' . $e->getMessage(), [
                'path' => $target
            ], 'error');
            echo json_encode(['success' => false, 'message' => 'WebDAV 操作失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * WebDAV 删除（删除本地文件+同步删除远程）
     */
    private static function handleWebDAVDeleteAction($request, $configOptions)
    {
        $target = self::normalizeWebDAVPath($request->get('target', ''));

        if ($target === '/' || $target === '') {
            echo json_encode(['success' => false, 'message' => '不能删除根目录'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $sync = new MediaLibrary_WebDAVSync($configOptions);

            // 删除本地文件
            $sync->deleteLocalFile($target);

            // 根据删除策略处理远程文件
            $deleteStrategy = isset($configOptions['webdavDeleteStrategy']) ? $configOptions['webdavDeleteStrategy'] : 'auto';

            if ($deleteStrategy === 'auto' && !empty($configOptions['webdavEndpoint'])) {
                // 自动同步删除远程文件
                try {
                    $sync->deleteRemoteFile($target);
                    MediaLibrary_Logger::log('webdav_delete', '删除本地文件并同步删除远程成功', [
                        'path' => $target
                    ]);
                } catch (Exception $e) {
                    MediaLibrary_Logger::log('webdav_delete', '本地文件已删除，但远程删除失败: ' . $e->getMessage(), [
                        'path' => $target
                    ], 'warning');
                }
            } else {
                MediaLibrary_Logger::log('webdav_delete', '删除本地文件成功（未同步远程）', [
                    'path' => $target,
                    'strategy' => $deleteStrategy
                ]);
            }

            echo json_encode(['success' => true, 'message' => '删除成功'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_delete', '删除失败: ' . $e->getMessage(), [
                'path' => $target
            ], 'error');
            echo json_encode(['success' => false, 'message' => 'WebDAV 操作失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * WebDAV 上传（上传到本地+同步到远程）
     */
    private static function handleWebDAVUploadAction($request, $configOptions)
    {
        if (empty($_FILES['file'])) {
            echo json_encode(['success' => false, 'message' => '请选择要上传的文件'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $path = self::normalizeWebDAVPath($request->get('path', '/'));
        $files = self::normalizeUploadFiles($_FILES['file']);

        if (empty($files)) {
            echo json_encode(['success' => false, 'message' => '未检测到有效文件'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $uploaded = [];
        try {
            $sync = new MediaLibrary_WebDAVSync($configOptions);
            $subPath = trim($path, '/');

            foreach ($files as $file) {
                // 保存到本地 WebDAV 文件夹
                $result = $sync->saveUploadedFile($file, $subPath);

                // 根据同步模式处理
                $syncMode = isset($configOptions['webdavSyncMode']) ? $configOptions['webdavSyncMode'] : 'manual';
                $syncEnabled = isset($configOptions['webdavSyncEnabled']) && $configOptions['webdavSyncEnabled'];

                if ($syncEnabled && $syncMode === 'onupload' && !empty($configOptions['webdavEndpoint'])) {
                    // 立即同步到远程
                    try {
                        $relativePath = ltrim($result['path'], '/');
                        $sync->syncFileToRemote($relativePath);
                        MediaLibrary_Logger::log('webdav_upload', '上传文件并同步到远程成功', [
                            'file' => $result['name'],
                            'path' => $result['path']
                        ]);
                    } catch (Exception $e) {
                        MediaLibrary_Logger::log('webdav_upload', '文件已保存到本地，但同步失败: ' . $e->getMessage(), [
                            'file' => $result['name']
                        ], 'warning');
                    }
                } else {
                    MediaLibrary_Logger::log('webdav_upload', '上传文件到本地成功（未同步远程）', [
                        'file' => $result['name'],
                        'path' => $result['path']
                    ]);
                }

                $uploaded[] = [
                    'name' => $result['name'],
                    'path' => $result['path']
                ];
            }

            echo json_encode(['success' => true, 'message' => '上传完成', 'files' => $uploaded], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_upload', '上传失败: ' . $e->getMessage(), [
                'path' => $path
            ], 'error');
            echo json_encode(['success' => false, 'message' => 'WebDAV 上传失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * WebDAV 测试连接
     */
    private static function handleWebDAVTestAction($request, $configOptions)
    {
        try {
            MediaLibrary_Logger::log('webdav_test', '开始 WebDAV 连接测试', [
                'has_local_path' => !empty($configOptions['webdavLocalPath']),
                'has_endpoint' => !empty($configOptions['webdavEndpoint'])
            ]);

            $result = [
                'success' => false,
                'local' => null,
                'remote' => null,
                'message' => ''
            ];

            // 测试本地路径
            if (!empty($configOptions['enableWebDAV']) && !empty($configOptions['webdavLocalPath'])) {
                try {
                    $sync = new MediaLibrary_WebDAVSync($configOptions);
                    $result['local'] = $sync->testLocalPath();

                    if ($result['local']['success']) {
                        MediaLibrary_Logger::log('webdav_test', '本地路径测试通过', [
                            'path' => $result['local']['path']
                        ]);
                    }
                } catch (Exception $e) {
                    $result['local'] = [
                        'success' => false,
                        'message' => '测试失败: ' . $e->getMessage()
                    ];
                    MediaLibrary_Logger::log('webdav_test', '本地路径测试异常: ' . $e->getMessage(), [], 'error');
                }
            } else {
                $result['local'] = [
                    'success' => false,
                    'message' => 'WebDAV 未启用或未配置本地路径'
                ];
            }

            // 测试远程连接
            if (!empty($configOptions['webdavEndpoint'])) {
                try {
                    if (!isset($sync)) {
                        $sync = new MediaLibrary_WebDAVSync($configOptions);
                    }
                    $result['remote'] = $sync->testRemoteConnection();

                    if ($result['remote']['success']) {
                        MediaLibrary_Logger::log('webdav_test', '远程连接测试通过', [
                            'endpoint' => $result['remote']['endpoint']
                        ]);
                    }
                } catch (Exception $e) {
                    $result['remote'] = [
                        'success' => false,
                        'message' => '测试失败: ' . $e->getMessage()
                    ];
                    MediaLibrary_Logger::log('webdav_test', '远程连接测试异常: ' . $e->getMessage(), [], 'error');
                }
            } else {
                $result['remote'] = [
                    'success' => false,
                    'configured' => false,
                    'message' => '未配置远程 WebDAV 服务器'
                ];
            }

            // 判断整体是否成功
            $localOk = $result['local'] && $result['local']['success'];
            $remoteOk = !empty($configOptions['webdavEndpoint']) ? ($result['remote'] && $result['remote']['success']) : true;

            $result['success'] = $localOk && $remoteOk;

            if ($result['success']) {
                $result['message'] = 'WebDAV 配置测试通过';
                MediaLibrary_Logger::log('webdav_test', 'WebDAV 配置测试完全通过');
            } else {
                $messages = [];
                if (!$localOk) {
                    $messages[] = '本地路径: ' . ($result['local']['message'] ?? '未知错误');
                }
                if (!$remoteOk) {
                    $messages[] = '远程连接: ' . ($result['remote']['message'] ?? '未知错误');
                }
                $result['message'] = '测试失败 - ' . implode('; ', $messages);
                MediaLibrary_Logger::log('webdav_test', 'WebDAV 配置测试失败', [
                    'local_ok' => $localOk,
                    'remote_ok' => $remoteOk
                ], 'warning');
            }

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_test', 'WebDAV 测试异常: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 'error');
            echo json_encode([
                'success' => false,
                'message' => '测试失败: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private static function getWebDAVClient($configOptions)
    {
        if (empty($configOptions['enableWebDAV'])) {
            throw new Exception('WebDAV 功能未启用');
        }
        if (empty($configOptions['webdavEndpoint']) || empty($configOptions['webdavUsername'])) {
            throw new Exception('WebDAV 配置不完整');
        }

        return new MediaLibrary_WebDAVClient($configOptions);
    }

    private static function normalizeWebDAVPath($path)
    {
        $path = trim((string)$path);
        if ($path === '' || $path === '/') {
            return '/';
        }

        $path = str_replace('\\', '/', $path);
        $segments = explode('/', $path);
        $safe = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }
            $safe[] = $segment;
        }

        if (empty($safe)) {
            return '/';
        }

        return '/' . implode('/', $safe);
    }

    private static function joinWebDAVPath($base, $name)
    {
        $base = self::normalizeWebDAVPath($base);
        $name = trim($name, '/');
        if ($base === '/') {
            return '/' . $name;
        }
        return rtrim($base, '/') . '/' . $name;
    }

    private static function normalizeUploadFiles($file)
    {
        $normalized = [];
        if (is_array($file['name'])) {
            $count = count($file['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($file['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $normalized[] = [
                    'name' => $file['name'][$i],
                    'tmp_name' => $file['tmp_name'][$i],
                    'type' => isset($file['type'][$i]) ? $file['type'][$i] : 'application/octet-stream'
                ];
            }
        } else {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $normalized[] = [
                    'name' => $file['name'],
                    'tmp_name' => $file['tmp_name'],
                    'type' => isset($file['type']) ? $file['type'] : 'application/octet-stream'
                ];
            }
        }

        return $normalized;
    }

    /**
     * 规范化水印文本，避免编码问题导致乱码
     *
     * @param string $text
     * @return string
     */
    private static function sanitizeWatermarkText($text)
    {
        $text = trim((string)$text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F]/u', '', $text);

        if (function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($text, ['UTF-8', 'GBK', 'GB2312', 'BIG5', 'ISO-8859-1'], true);
            if ($encoding && $encoding !== 'UTF-8') {
                $text = mb_convert_encoding($text, 'UTF-8', $encoding);
            }
        } else {
            $converted = @iconv('GBK', 'UTF-8//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        return $text;
    }

    /**
     * 汇总上传文件的基本信息
     *
     * @return array
     */
    private static function summarizeUploadedFiles()
    {
        $summary = [];
        foreach ($_FILES as $field => $file) {
            if (is_array($file['name'])) {
                foreach ($file['name'] as $index => $name) {
                    $summary[] = [
                        'field' => $field,
                        'name' => $name,
                        'type' => isset($file['type'][$index]) ? $file['type'][$index] : null,
                        'size' => isset($file['size'][$index]) ? intval($file['size'][$index]) : null
                    ];
                }
            } else {
                $summary[] = [
                    'field' => $field,
                    'name' => isset($file['name']) ? $file['name'] : null,
                    'type' => isset($file['type']) ? $file['type'] : null,
                    'size' => isset($file['size']) ? intval($file['size']) : null
                ];
            }
        }

        return $summary;
    }

    /**
     * WebDAV 同步下载
     */
    private static function handleWebDAVSyncDownloadAction($request, $configOptions)
    {
        $path = $request->get('path', '');

        if ($path === '' || $path === '/') {
            echo json_encode(['success' => false, 'message' => '请指定要下载的文件路径'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $client = self::getWebDAVClient($configOptions);

            // 构建本地路径
            $webdavDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/webdav';
            $localPath = $webdavDir . '/' . ltrim($path, '/');

            // 下载文件
            $client->downloadFile($path, $localPath);

            MediaLibrary_Logger::log('webdav_sync_download', '从 WebDAV 下载文件成功', [
                'remote_path' => $path,
                'local_path' => $localPath
            ]);

            echo json_encode([
                'success' => true,
                'message' => '文件下载成功',
                'local_path' => $localPath
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_sync_download', '下载失败: ' . $e->getMessage(), [
                'path' => $path
            ], 'error');
            echo json_encode(['success' => false, 'message' => 'WebDAV 下载失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * WebDAV 同步到本地（将 WebDAV 文件同步到 Typecho 媒体库）
     */
    private static function handleWebDAVSyncToLocalAction($request, $configOptions, $db)
    {
        $path = $request->get('path', '');

        if ($path === '' || $path === '/') {
            echo json_encode(['success' => false, 'message' => '请指定要同步的文件路径'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $client = self::getWebDAVClient($configOptions);

            // 先下载文件到临时目录
            $webdavDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/webdav';
            $tempPath = $webdavDir . '/' . ltrim($path, '/');

            // 下载文件
            $client->downloadFile($path, $tempPath);

            // 获取文件信息
            $filename = basename($path);
            $filesize = filesize($tempPath);
            $mime = mime_content_type($tempPath) ?: 'application/octet-stream';

            // 将文件移动到 Typecho 上传目录
            $uploadDir = defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : '/usr/uploads';
            $uploadPath = __TYPECHO_ROOT_DIR__ . $uploadDir;

            // 生成唯一文件名
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $basename = pathinfo($filename, PATHINFO_FILENAME);
            $newFilename = $basename . '_' . time() . '.' . $ext;
            $finalPath = $uploadPath . '/' . $newFilename;

            // 移动文件
            if (!copy($tempPath, $finalPath)) {
                throw new Exception('无法复制文件到上传目录');
            }

            // 删除临时文件
            @unlink($tempPath);

            // 插入到数据库
            $date = new Typecho_Date();
            $insert = [
                'title' => $filename,
                'slug' => $newFilename,
                'created' => $date->timeStamp,
                'modified' => $date->timeStamp,
                'text' => serialize([
                    'name' => $filename,
                    'path' => $uploadDir . '/' . $newFilename,
                    'size' => $filesize,
                    'type' => $mime,
                    'mime' => $mime,
                    'storage' => 'webdav'
                ]),
                'order' => 0,
                'authorId' => Typecho_Cookie::get('__typecho_uid'),
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

            $cid = $db->query($db->insert('table.contents')->rows($insert));

            MediaLibrary_Logger::log('webdav_sync_to_local', '同步 WebDAV 文件到本地成功', [
                'remote_path' => $path,
                'local_path' => $finalPath,
                'cid' => $cid
            ]);

            echo json_encode([
                'success' => true,
                'message' => '文件同步成功',
                'cid' => $cid,
                'filename' => $newFilename
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_sync_to_local', '同步失败: ' . $e->getMessage(), [
                'path' => $path
            ], 'error');
            echo json_encode(['success' => false, 'message' => 'WebDAV 同步失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * WebDAV 同步本地文件到服务器（单个文件）
     */
    private static function handleWebDAVSyncFromLocalAction($request, $configOptions, $db)
    {
        if (!$configOptions['webdavSyncEnabled']) {
            echo json_encode(['success' => false, 'message' => '同步功能未启用'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $cid = intval($request->get('cid'));
        if (!$cid) {
            echo json_encode(['success' => false, 'message' => '无效的文件ID'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $client = self::getWebDAVClient($configOptions);

            // 获取文件信息
            $attachment = $db->fetchRow($db->select()->from('table.contents')
                ->where('cid = ? AND type = ?', $cid, 'attachment'));

            if (!$attachment) {
                throw new Exception('文件不存在');
            }

            $attachmentData = @unserialize($attachment['text']);
            if (!is_array($attachmentData) || !isset($attachmentData['path'])) {
                throw new Exception('文件数据错误');
            }

            $localPath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];
            if (!file_exists($localPath)) {
                throw new Exception('本地文件不存在');
            }

            // 构建 WebDAV 目标路径
            $syncPath = $configOptions['webdavSyncPath'];
            $filename = basename($attachmentData['path']);
            $remotePath = rtrim($syncPath, '/') . '/' . $filename;

            // 上传文件到 WebDAV
            $mime = isset($attachmentData['mime']) ? $attachmentData['mime'] : 'application/octet-stream';
            $client->uploadFile($remotePath, $localPath, $mime);

            // 更新数据库标记
            $attachmentData['webdav_synced'] = true;
            $attachmentData['webdav_path'] = $remotePath;
            $attachmentData['webdav_sync_time'] = time();

            $db->query($db->update('table.contents')
                ->rows(['text' => serialize($attachmentData)])
                ->where('cid = ?', $cid));

            MediaLibrary_Logger::log('webdav_sync_from_local', '同步本地文件到 WebDAV 成功', [
                'cid' => $cid,
                'local_path' => $localPath,
                'remote_path' => $remotePath
            ]);

            echo json_encode([
                'success' => true,
                'message' => '文件同步成功',
                'remote_path' => $remotePath
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_sync_from_local', '同步失败: ' . $e->getMessage(), [
                'cid' => $cid
            ], 'error');
            echo json_encode(['success' => false, 'message' => '同步失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * WebDAV 批量同步所有本地文件到服务器
     */
    private static function handleWebDAVSyncAllLocalAction($request, $configOptions, $db)
    {
        if (empty($configOptions['webdavSyncEnabled'])) {
            echo json_encode(['success' => false, 'message' => '同步功能未启用'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (empty($configOptions['enableWebDAV'])) {
            echo json_encode(['success' => false, 'message' => 'WebDAV 功能未启用'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $sync = new MediaLibrary_WebDAVSync($configOptions);
            $result = $sync->syncAllToRemote();

            MediaLibrary_Logger::log('webdav_sync_all_local', '批量同步完成', [
                'total' => $result['total'],
                'synced' => $result['synced'],
                'failed' => $result['failed'],
                'skipped' => $result['skipped'],
                'renamed' => $result['renamed']
            ]);

            $message = sprintf(
                '同步完成：成功 %d 个，跳过 %d 个，失败 %d 个',
                $result['synced'],
                $result['skipped'],
                $result['failed']
            );

            echo json_encode([
                'success' => true,
                'message' => $message,
                'stats' => [
                    'total' => $result['total'],
                    'synced' => $result['synced'],
                    'failed' => $result['failed'],
                    'skipped' => $result['skipped'],
                    'renamed' => $result['renamed']
                ],
                'errors' => $result['errors']
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_sync_all_local', '批量同步失败: ' . $e->getMessage(), [], 'error');
            echo json_encode(['success' => false, 'message' => '批量同步失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}
