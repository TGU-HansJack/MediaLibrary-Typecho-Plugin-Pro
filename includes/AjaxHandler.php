<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

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
                    
                default:
                    echo json_encode(['success' => false, 'message' => '未知操作'], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
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
            echo json_encode(['success' => false, 'message' => '请选择要删除的文件'], JSON_UNESCAPED_UNICODE);
            return;
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
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                    }
                }
                
                // 删除数据库记录
                $db->query($db->delete('table.contents')->where('cid = ?', $cid));
                $deleteCount++;
            }
        }
        
        echo json_encode(['success' => true, 'message' => "成功删除 {$deleteCount} 个文件"], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 处理上传请求
     */
    private static function handleUploadAction()
    {
        if (empty($_FILES)) {
            echo json_encode(['success' => false, 'message' => '没有文件上传'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        try {
            // 使用 Typecho 的上传处理
            $upload = \Widget\Upload::alloc();
            $result = $upload->upload($_FILES);
            
            if ($result) {
                echo json_encode(['success' => true, 'count' => 1, 'data' => $result], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => '上传失败'], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => '上传失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * 处理获取信息请求
     */
    private static function handleGetInfoAction($request, $db, $options, $enableGetID3)
    {
        $cid = intval($request->get('cid'));
        if (!$cid) {
            echo json_encode(['success' => false, 'message' => '无效的文件ID'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $attachment = $db->fetchRow($db->select()->from('table.contents')
            ->where('cid = ? AND type = ?', $cid, 'attachment'));
            
        if (!$attachment) {
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
        echo json_encode(['success' => false, 'message' => '无效的文件ID'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    if ($width <= 0 || $height <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的裁剪尺寸'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $result = MediaLibrary_ImageEditor::cropImage(
        $cid, $x, $y, $width, $height, $replaceOriginal, $customName, $useLibrary, $db, $options, $user
    );
    
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
        $watermarkConfig['text'] = $request->get('watermark_text', '');
        
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
        echo json_encode(['success' => false, 'message' => '不支持的水印类型'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $result = MediaLibrary_ImageEditor::addWatermark(
        $cid, $watermarkConfig, $replaceOriginal, $customName, $useLibrary, $db, $options, $user
    );
    
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
            echo json_encode(['success' => false, 'message' => 'EXIF功能未启用或无可用的EXIF工具'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $cids = $request->getArray('cids');
        if (empty($cids)) {
            echo json_encode(['success' => false, 'message' => '请选择要检测的图片'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $results = [];
        foreach ($cids as $cid) {
            $result = MediaLibrary_ExifPrivacy::checkImagePrivacy($cid, $db, $options);
            $results[] = $result;
        }
        
        echo json_encode(['success' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 处理获取GPS数据请求
     */
    private static function handleGetGpsDataAction($request, $db, $options)
    {
        $cids = $request->getArray('cids');
        if (empty($cids)) {
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
        
        echo json_encode(['success' => true, 'data' => $gpsData], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 处理获取智能建议请求
     */
    private static function handleGetSmartSuggestionAction($request, $db)
    {
        $cids = $request->getArray('cids');
        if (empty($cids)) {
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
            echo json_encode(['success' => false, 'message' => 'EXIF功能未启用或无可用的EXIF清除工具'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $cid = intval($request->get('cid'));
        if (!$cid) {
            echo json_encode(['success' => false, 'message' => '无效的文件ID'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $attachment = $db->fetchRow($db->select()->from('table.contents')
            ->where('cid = ? AND type = ?', $cid, 'attachment'));
            
        if (!$attachment) {
            echo json_encode(['success' => false, 'message' => '文件不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $attachmentData = @unserialize($attachment['text']);
        if (!is_array($attachmentData) || !isset($attachmentData['path'])) {
            echo json_encode(['success' => false, 'message' => '文件数据错误'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];
        if (!file_exists($filePath)) {
            echo json_encode(['success' => false, 'message' => '文件不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // 检查是否为图片
        if (strpos($attachmentData['mime'], 'image/') !== 0) {
            echo json_encode(['success' => false, 'message' => '只能清除图片文件的EXIF信息'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // 智能清除EXIF信息
        $result = MediaLibrary_ExifPrivacy::removeImageExif($filePath, $attachmentData['mime']);
        
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
