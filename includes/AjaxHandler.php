<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/Logger.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVClient.php';

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

                case 'scan_folder':
                    self::handleScanFolderAction($request, $db);
                    break;

                case 'import_files':
                    self::handleImportFilesAction($request, $db, $user);
                    break;

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
                $webdavClient = new MediaLibrary_WebDAVClient(
                    $configOptions['webdavUrl'],
                    $configOptions['webdavUsername'],
                    $configOptions['webdavPassword']
                );
            } catch (Exception $e) {
                // WebDAV 客户端初始化失败，继续使用本地删除
                MediaLibrary_Logger::log('delete', 'WebDAV 客户端初始化失败: ' . $e->getMessage(), [], 'warning');
            }
        }

        $deleteCount = 0;
        foreach ($cids as $cid) {
            $cid = intval($cid);
            $attachment = $db->fetchRow($db->select()->from('table.contents')
                ->where('cid = ? AND type = ?', $cid, 'attachment'));

            if ($attachment) {
                // 删除物理文件
                if (isset($attachment['text'])) {
                    $attachmentData = @unserialize($attachment['text']);
                    if (is_array($attachmentData) && isset($attachmentData['path'])) {
                        $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];

                        // 判断是否为文件夹导入的文件
                        $isFolderImport = isset($attachmentData['source']) && $attachmentData['source'] === 'folder_import';

                        // 对于文件夹导入的文件，只删除数据库记录，不删除物理文件
                        if (!$isFolderImport) {
                            // 首先尝试删除本地文件
                            if (file_exists($filePath)) {
                                @unlink($filePath);
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
                            // 文件夹导入的文件，记录日志但不删除物理文件
                            MediaLibrary_Logger::log('delete', '文件夹导入的文件，仅删除数据库记录', [
                                'cid' => $cid,
                                'path' => $attachmentData['path']
                            ]);
                        }
                    }
                }

                // 删除数据库记录
                $db->query($db->delete('table.contents')->where('cid = ?', $cid));
                $deleteCount++;
            }
        }

        MediaLibrary_Logger::log('delete', '删除操作完成', [
            'requested_cids' => $cids,
            'deleted' => $deleteCount
        ]);
        echo json_encode(['success' => true, 'message' => "成功删除 {$deleteCount} 个文件"], JSON_UNESCAPED_UNICODE);
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

            if (!$configOptions['enableWebDAV']) {
                return ['success' => false, 'message' => 'WebDAV 未启用'];
            }

            $client = new MediaLibrary_WebDAVClient(
                $configOptions['webdavUrl'],
                $configOptions['webdavUsername'],
                $configOptions['webdavPassword']
            );

            // 获取数据库和选项
            $db = Typecho_Db::get();
            $options = Typecho_Widget::widget('Widget_Options');
            $user = Typecho_Widget::widget('Widget_User');

            $uploadedFiles = [];
            foreach ($files as $file) {
                if (isset($file['tmp_name']) && isset($file['name'])) {
                    $fileName = $file['name'];
                    $remotePath = $configOptions['webdavPath'] . '/' . $fileName;
                    $content = file_get_contents($file['tmp_name']);

                    // 上传到 WebDAV
                    if ($client->put($remotePath, $content)) {
                        // 构建公开访问 URL
                        $publicUrl = rtrim($configOptions['webdavUrl'], '/') . '/' . ltrim($remotePath, '/');

                        // 保存到数据库
                        $attachmentData = [
                            'name' => $fileName,
                            'path' => $remotePath,
                            'size' => $file['size'],
                            'type' => $file['type'],
                            'mime' => $file['type'],
                            'webdav_path' => $remotePath,  // 添加 WebDAV 路径标记
                            'storage' => 'webdav'  // 添加存储类型标记
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
                            'path' => $remotePath,
                            'url' => $publicUrl,
                            'size' => $file['size']
                        ];

                        MediaLibrary_Logger::log('upload', 'WebDAV 文件上传并记录成功', [
                            'cid' => $insertId,
                            'file' => $fileName,
                            'path' => $remotePath
                        ]);
                    } else {
                        return ['success' => false, 'message' => '上传到 WebDAV 失败: ' . $fileName];
                    }
                }
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
     * WebDAV 列表
     */
    private static function handleWebDAVListAction($request, $configOptions)
    {
        $path = $request->get('path', '/');

        try {
            $client = self::getWebDAVClient($configOptions);
            $result = $client->listDirectory($path);

            $items = [];
            foreach ($result['items'] as $item) {
                $items[] = [
                    'name' => $item['name'],
                    'path' => $item['path'],
                    'is_dir' => $item['is_dir'] ? 1 : 0,
                    'size' => $item['size'],
                    'size_human' => $item['is_dir'] ? '-' : MediaLibrary_FileOperations::formatFileSize($item['size']),
                    'modified' => $item['modified'],
                    'mime' => $item['mime'],
                    'public_url' => $item['public_url']
                ];
            }

            MediaLibrary_Logger::log('webdav_list', '读取 WebDAV 目录', [
                'path' => $result['path'],
                'count' => count($items)
            ]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'current_path' => $result['path'],
                    'items' => $items
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
     * WebDAV 创建文件夹
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
            $client = self::getWebDAVClient($configOptions);
            $client->createDirectory($target);

            MediaLibrary_Logger::log('webdav_create_folder', '创建 WebDAV 目录成功', [
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
     * WebDAV 删除
     */
    private static function handleWebDAVDeleteAction($request, $configOptions)
    {
        $target = self::normalizeWebDAVPath($request->get('target', ''));

        if ($target === '/' || $target === '') {
            echo json_encode(['success' => false, 'message' => '不能删除根目录'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $client = self::getWebDAVClient($configOptions);
            $client->delete($target);

            MediaLibrary_Logger::log('webdav_delete', '删除 WebDAV 文件/目录成功', [
                'path' => $target
            ]);

            echo json_encode(['success' => true, 'message' => '删除成功'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_delete', '删除失败: ' . $e->getMessage(), [
                'path' => $target
            ], 'error');
            echo json_encode(['success' => false, 'message' => 'WebDAV 操作失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * WebDAV 上传
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
            $client = self::getWebDAVClient($configOptions);
            foreach ($files as $file) {
                $target = self::joinWebDAVPath($path, $file['name']);
                $client->uploadFile($target, $file['tmp_name'], $file['type']);
                $uploaded[] = [
                    'name' => $file['name'],
                    'path' => $target
                ];
            }

            MediaLibrary_Logger::log('webdav_upload', '上传到 WebDAV 成功', [
                'path' => $path,
                'count' => count($uploaded)
            ]);

            echo json_encode(['success' => true, 'message' => '上传完成', 'files' => $uploaded], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_upload', '上传失败: ' . $e->getMessage(), [
                'path' => $path
            ], 'error');
            echo json_encode(['success' => false, 'message' => 'WebDAV 上传失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
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
     * 处理扫描文件夹请求
     */
    private static function handleScanFolderAction($request, $db)
    {
        $baseDir = $request->get('base_dir', '/usr/uploads');

        MediaLibrary_Logger::log('scan_folder', '开始扫描文件夹', [
            'base_dir' => $baseDir
        ]);

        $result = MediaLibrary_PanelHelper::scanUploadDirectory($db, $baseDir);

        if ($result['success']) {
            MediaLibrary_Logger::log('scan_folder', '扫描完成', [
                'base_dir' => $baseDir,
                'total_files' => $result['data']['total_files_in_system'],
                'orphaned_count' => $result['data']['orphaned_count']
            ]);
        } else {
            MediaLibrary_Logger::log('scan_folder', '扫描失败: ' . $result['message'], [
                'base_dir' => $baseDir
            ], 'error');
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 处理导入文件请求
     */
    private static function handleImportFilesAction($request, $db, $user)
    {
        $filesJson = $request->get('files');

        if (empty($filesJson)) {
            MediaLibrary_Logger::log('import_files', '导入失败：未提供文件列表', [], 'warning');
            echo json_encode(['success' => false, 'message' => '未提供文件列表'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $files = json_decode($filesJson, true);

        if (!is_array($files)) {
            MediaLibrary_Logger::log('import_files', '导入失败：文件列表格式错误', [], 'warning');
            echo json_encode(['success' => false, 'message' => '文件列表格式错误'], JSON_UNESCAPED_UNICODE);
            return;
        }

        MediaLibrary_Logger::log('import_files', '开始导入文件', [
            'count' => count($files)
        ]);

        $result = MediaLibrary_PanelHelper::importFilesToDatabase($files, $db, $user->uid);

        if ($result['success']) {
            MediaLibrary_Logger::log('import_files', '导入完成', [
                'imported' => $result['data']['imported'],
                'failed' => $result['data']['failed']
            ]);
        } else {
            MediaLibrary_Logger::log('import_files', '导入失败', [], 'error');
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
