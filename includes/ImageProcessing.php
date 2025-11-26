<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/FileOperations.php';

/**
 * 图像处理工具类
 */
class MediaLibrary_ImageProcessing
{
    /**
     * 压缩图片
     * 
     * @param int $cid 文件ID
     * @param int $quality 压缩质量
     * @param string $outputFormat 输出格式
     * @param string $compressMethod 压缩方法
     * @param bool $replaceOriginal 是否替换原文件
     * @param string $customName 自定义文件名
     * @param Typecho_Db $db 数据库连接
     * @param Typecho_Widget_Helper_Options $options 系统选项
     * @param Typecho_Widget_User $user 当前用户
     * @return array 操作结果
     */
    public static function compressImage($cid, $quality, $outputFormat, $compressMethod, $replaceOriginal, $customName, $db, $options, $user)
    {
        $attachment = $db->fetchRow($db->select()->from('table.contents')
            ->where('cid = ? AND type = ?', $cid, 'attachment'));
            
        if (!$attachment) {
            return ['success' => false, 'message' => '文件不存在', 'cid' => $cid];
        }
        
        $attachmentData = @unserialize($attachment['text']);
        if (!is_array($attachmentData) || !isset($attachmentData['path'])) {
            return ['success' => false, 'message' => '文件数据错误', 'cid' => $cid];
        }
        
        $originalPath = MediaLibrary_FileOperations::resolveAttachmentPath($attachmentData['path']);
        if (!$originalPath || !file_exists($originalPath)) {
            return ['success' => false, 'message' => '原文件不存在', 'cid' => $cid];
        }
        
        // 检查是否为图片
        if (strpos($attachmentData['mime'], 'image/') !== 0 && 
            !in_array(strtolower(pathinfo($attachmentData['name'] ?? '', PATHINFO_EXTENSION)), ['avif'])) {
            return ['success' => false, 'message' => '只能压缩图片文件', 'cid' => $cid];
        }
        
        $pathInfo = pathinfo($originalPath);
        
        // 获取原始文件大小
        $originalSize = filesize($originalPath);
        
        if ($replaceOriginal) {
            return self::compressAndReplaceImage($cid, $originalPath, $pathInfo, $attachmentData, 
                                              $quality, $outputFormat, $compressMethod, 
                                              $originalSize, $db);
        } else {
            return self::compressAndKeepImage($cid, $originalPath, $pathInfo, $attachmentData, 
                                           $quality, $outputFormat, $compressMethod, 
                                           $originalSize, $customName, $db, $options, $user);
        }
    }
    
    /**
     * 压缩并替换原始图片
     */
    private static function compressAndReplaceImage($cid, $originalPath, $pathInfo, $attachmentData, 
                                                  $quality, $outputFormat, $compressMethod, 
                                                  $originalSize, $db)
    {
        if ($outputFormat === 'original') {
            // 保持原格式，直接覆盖原文件
            $compressedPath = $originalPath;
            $tempPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_temp_compress.' . $pathInfo['extension'];
            
            // 先压缩到临时文件
            $result = self::compressImageWithMethod($originalPath, $tempPath, $quality, $compressMethod, $outputFormat, $attachmentData['mime']);
            
            if (!$result['success']) {
                return array_merge($result, ['cid' => $cid]);
            }
            
            // 检查压缩效果
            $tempSize = filesize($tempPath);
            if ($tempSize >= $originalSize) {
                @unlink($tempPath);
                return [
                    'success' => false, 
                    'message' => '压缩后文件大小未减少（' . MediaLibrary_FileOperations::formatFileSize($tempSize) . ' >= ' . MediaLibrary_FileOperations::formatFileSize($originalSize) . '），建议调整压缩参数',
                    'cid' => $cid,
                    'original_size' => MediaLibrary_FileOperations::formatFileSize($originalSize),
                    'compressed_size' => MediaLibrary_FileOperations::formatFileSize($tempSize)
                ];
            }
            
            // 替换原文件
            if (!@unlink($originalPath) || !rename($tempPath, $originalPath)) {
                @unlink($tempPath);
                return ['success' => false, 'message' => '文件替换失败', 'cid' => $cid];
            }
            
            $compressedSize = filesize($originalPath);
            
            // 更新数据库中的文件大小
            $attachmentData['size'] = $compressedSize;
            $db->query($db->update('table.contents')
                ->rows(['text' => serialize($attachmentData)])
                ->where('cid = ?', $cid));
                
        } else {
            // 格式转换模式
            $newExt = $outputFormat;
            $tempPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_temp_convert.' . $newExt;
            
            // 压缩到临时文件
            $result = self::compressImageWithMethod($originalPath, $tempPath, $quality, $compressMethod, $outputFormat, $attachmentData['mime']);
            
            if (!$result['success']) {
                return array_merge($result, ['cid' => $cid]);
            }
            
            $tempSize = filesize($tempPath);
            
            // 删除原文件并重命名临时文件
            @unlink($originalPath);
            if (!rename($tempPath, $originalPath)) {
                return ['success' => false, 'message' => '文件替换失败', 'cid' => $cid];
            }
            
            $compressedSize = filesize($originalPath);
            
            // 更新数据库中的MIME类型和文件名
            $attachmentData['size'] = $compressedSize;
            $attachmentData['mime'] = 'image/' . $outputFormat;
            
            // 更新文件名扩展名但保持路径不变
            $newFileName = $pathInfo['filename'] . '.' . $newExt;
            $attachmentData['name'] = $newFileName;
            
            $db->query($db->update('table.contents')
                ->rows([
                    'text' => serialize($attachmentData),
                    'title' => $newFileName
                ])
                ->where('cid = ?', $cid));
                
            $savings = $originalSize > 0 ? round((1 - $compressedSize / $originalSize) * 100, 1) : 0;
            
            return [
                'success' => true,
                'message' => '图片压缩成功（格式已转换）',
                'cid' => $cid,
                'original_size' => MediaLibrary_FileOperations::formatFileSize($originalSize),
                'compressed_size' => MediaLibrary_FileOperations::formatFileSize($compressedSize),
                'savings' => $savings . '%',
                'method' => $compressMethod,
                'format' => $outputFormat,
                'format_changed' => true
            ];
        }
        
        // 计算节省的空间
        $savings = $originalSize > 0 ? round((1 - $compressedSize / $originalSize) * 100, 1) : 0;
        
        // 检查压缩效果
        if ($compressedSize >= $originalSize) {
            $message = '压缩完成，但文件大小未减少（可能是质量设置过高或原图已经很优化）';
        } else {
            $message = '图片压缩成功';
        }
        
        return [
            'success' => true,
            'message' => $message,
            'cid' => $cid,
            'original_size' => MediaLibrary_FileOperations::formatFileSize($originalSize),
            'compressed_size' => MediaLibrary_FileOperations::formatFileSize($compressedSize),
            'savings' => $savings . '%',
            'method' => $compressMethod,
            'format' => $outputFormat
        ];
    }
    
    /**
     * 压缩并保留原始图片
     */
    private static function compressAndKeepImage($cid, $originalPath, $pathInfo, $attachmentData, 
                                              $quality, $outputFormat, $compressMethod, 
                                              $originalSize, $customName, $db, $options, $user)
    {
        // 保留原文件，创建新文件
        $outputExt = $outputFormat === 'original' ? $pathInfo['extension'] : $outputFormat;
        
        if (!empty($customName)) {
            $compressedPath = $pathInfo['dirname'] . '/' . $customName . '.' . $outputExt;
        } else {
            $compressedPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_compressed.' . $outputExt;
        }
        
        // 压缩图片
        $result = self::compressImageWithMethod($originalPath, $compressedPath, $quality, $compressMethod, $outputFormat, $attachmentData['mime']);
        
        if (!$result['success']) {
            return array_merge($result, ['cid' => $cid]);
        }
        
        // 获取压缩后文件大小
        $compressedSize = filesize($compressedPath);
        
        // 添加到数据库
        $newAttachmentData = $attachmentData;
        $newAttachmentData['path'] = str_replace(__TYPECHO_ROOT_DIR__, '', $compressedPath);
        $newAttachmentData['size'] = $compressedSize;
        $newAttachmentData['name'] = basename($compressedPath);
        
        // 更新 MIME 类型
        if ($outputFormat !== 'original') {
            $newAttachmentData['mime'] = 'image/' . $outputFormat;
        } elseif (extension_loaded('fileinfo')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $compressedPath);
            if ($detectedMime) {
                $newAttachmentData['mime'] = $detectedMime;
            }
            finfo_close($finfo);
        }
        
        $struct = [
            'title' => basename($compressedPath),
            'slug' => basename($compressedPath),
            'created' => time(),
            'modified' => time(),
            'text' => serialize($newAttachmentData),
            'order' => 0,
            'authorId' => $user->uid,
            'template' => NULL,
            'type' => 'attachment',
            'status' => 'publish',
            'password' => NULL,
            'commentsNum' => 0,
            'allowComment' => 0,
            'allowPing' => 0,
            'allowFeed' => 0,
            'parent' => 0
        ];
        
        $db->query($db->insert('table.contents')->rows($struct));
        
        // 计算节省的空间
        $savings = $originalSize > 0 ? round((1 - $compressedSize / $originalSize) * 100, 1) : 0;
        
        // 检查压缩效果
        if ($compressedSize >= $originalSize) {
            $message = '压缩完成，但文件大小未减少（可能是质量设置过高或原图已经很优化）';
        } else {
            $message = '图片压缩成功';
        }
        
        return [
            'success' => true,
            'message' => $message,
            'cid' => $cid,
            'original_size' => MediaLibrary_FileOperations::formatFileSize($originalSize),
            'compressed_size' => MediaLibrary_FileOperations::formatFileSize($compressedSize),
            'savings' => $savings . '%',
            'method' => $compressMethod,
            'format' => $outputFormat
        ];
    }
    
    /**
     * 根据方法压缩图片
     */
    public static function compressImageWithMethod($sourcePath, $destPath, $quality, $method, $outputFormat, $originalMime)
    {
        switch ($method) {
            case 'gd':
                return self::compressWithGD($sourcePath, $destPath, $quality, $outputFormat, $originalMime);
            case 'imagick':
                return self::compressWithImageMagick($sourcePath, $destPath, $quality, $outputFormat);
            case 'ffmpeg':
                return self::compressWithFFmpeg($sourcePath, $destPath, $quality, $outputFormat);
            default:
                return ['success' => false, 'message' => '不支持的压缩方法'];
        }
    }
    
    /**
     * 使用 GD 库压缩图片
     */
    private static function compressWithGD($sourcePath, $destPath, $quality, $outputFormat, $originalMime)
    {
        // 创建图像资源
        switch ($originalMime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($sourcePath);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($sourcePath);
                break;
            default:
                return ['success' => false, 'message' => 'GD不支持的图片格式'];
        }
        
        if (!$image) {
            return ['success' => false, 'message' => '无法读取图片'];
        }
        
        $success = false;
        $targetFormat = $outputFormat === 'original' ? $originalMime : 'image/' . $outputFormat;
        
        switch ($targetFormat) {
            case 'image/jpeg':
                $success = imagejpeg($image, $destPath, $quality);
                break;
            case 'image/png':
                $pngQuality = 9 - round(($quality / 100) * 9);
                $success = imagepng($image, $destPath, $pngQuality);
                break;
            case 'image/gif':
                $success = imagegif($image, $destPath);
                break;
            case 'image/webp':
                $success = imagewebp($image, $destPath, $quality);
                break;
            default:
                imagedestroy($image);
                return ['success' => false, 'message' => 'GD不支持输出该格式'];
        }
        
        imagedestroy($image);
        
        if ($success) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => '图片压缩失败'];
        }
    }
    
    /**
     * 使用 ImageMagick 压缩图片
     */
    private static function compressWithImageMagick($sourcePath, $destPath, $quality, $outputFormat)
    {
        if (!extension_loaded('imagick')) {
            return ['success' => false, 'message' => 'ImageMagick扩展未安装'];
        }
        
        try {
            $imagick = new Imagick($sourcePath);
            
            // 获取原始信息
            $originalFormat = $imagick->getImageFormat();
            $originalSize = filesize($sourcePath);
            
            // 设置压缩质量
            $imagick->setImageCompressionQuality($quality);
            
            // 如果是PNG转JPEG，需要设置背景色
            if ($outputFormat === 'jpeg' && strtolower($originalFormat) === 'png') {
                $imagick->setImageBackgroundColor('white');
                $imagick = $imagick->flattenImages();
            }
            
            // 设置输出格式
            if ($outputFormat !== 'original') {
                $imagick->setImageFormat($outputFormat);
            }
            
            // 优化图片
            $imagick->stripImage(); // 移除EXIF等元数据
            
            // 根据格式进行特殊优化
            switch (strtolower($outputFormat === 'original' ? $originalFormat : $outputFormat)) {
                case 'jpeg':
                    $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
                    $imagick->setImageCompressionQuality($quality);
                    break;
                case 'png':
                    $imagick->setImageCompression(Imagick::COMPRESSION_ZIP);
                    break;
                case 'webp':
                    $imagick->setImageFormat('webp');
                    $imagick->setImageCompressionQuality($quality);
                    break;
            }
            
            // 写入文件
            $imagick->writeImage($destPath);
            $imagick->destroy();
            
            // 检查文件是否成功创建
            if (!file_exists($destPath)) {
                return ['success' => false, 'message' => 'ImageMagick压缩失败：文件未生成'];
            }
            
            $newSize = filesize($destPath);
            
            // 如果压缩后文件更大，给出警告但仍然成功
            if ($newSize >= $originalSize) {
                return [
                    'success' => true, 
                    'message' => 'ImageMagick压缩完成，但文件大小未减少',
                    'warning' => true
                ];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'ImageMagick压缩失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 使用 FFmpeg 压缩图片
     */
    private static function compressWithFFmpeg($sourcePath, $destPath, $quality, $outputFormat)
    {
        if (!function_exists('exec')) {
            return ['success' => false, 'message' => 'exec函数被禁用'];
        }
        
        $output = [];
        $return_var = 0;
        
        // 构建FFmpeg命令
        $cmd = 'ffmpeg -i ' . escapeshellarg($sourcePath) . ' -q:v ' . intval($quality / 10) . ' ';
        
        if ($outputFormat !== 'original') {
            $cmd .= '-f ' . $outputFormat . ' ';
        }
        
        $cmd .= escapeshellarg($destPath) . ' 2>&1';
        
        @exec($cmd, $output, $return_var);
        
        if ($return_var === 0 && file_exists($destPath)) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'FFmpeg压缩失败'];
        }
    }
    
    /**
     * 获取智能压缩建议
     */
    public static function getSmartCompressionSuggestion($filePath, $mime, $fileSize)
    {
        $suggestions = [
            'quality' => 80,
            'format' => 'original',
            'method' => 'gd',
            'reason' => '默认设置'
        ];
        
        // 根据文件大小调整质量
        if ($fileSize > 10 * 1024 * 1024) { // 大于10MB
            $suggestions['quality'] = 50;
            $suggestions['reason'] = '超大文件，建议大幅压缩';
        } elseif ($fileSize > 5 * 1024 * 1024) { // 大于5MB
            $suggestions['quality'] = 60;
            $suggestions['reason'] = '大文件，建议降低质量以减小体积';
        } elseif ($fileSize > 2 * 1024 * 1024) { // 大于2MB
            $suggestions['quality'] = 70;
            $suggestions['reason'] = '中等文件，适度压缩';
        } elseif ($fileSize > 500 * 1024) { // 大于500KB
            $suggestions['quality'] = 80;
            $suggestions['reason'] = '标准压缩';
        } else { // 小于500KB
            $suggestions['quality'] = 90;
            $suggestions['reason'] = '小文件，保持高质量';
        }
        
        // 根据格式调整建议
        switch ($mime) {
            case 'image/png':
                if ($fileSize > 1024 * 1024) { // PNG大于1MB建议转JPEG
                    $suggestions['format'] = 'jpeg';
                    $suggestions['quality'] = min($suggestions['quality'], 85);
                    $suggestions['reason'] = 'PNG文件较大，建议转换为JPEG格式并适度压缩';
                } elseif ($fileSize > 500 * 1024) {
                    $suggestions['quality'] = min($suggestions['quality'], 75);
                    $suggestions['reason'] = 'PNG文件，建议适度压缩';
                }
                break;
                
            case 'image/gif':
                // GIF不建议压缩，可能会丢失动画
                $suggestions['quality'] = 95;
                $suggestions['format'] = 'original';
                $suggestions['reason'] = 'GIF文件，保持高质量避免丢失动画';
                break;
                
            case 'image/webp':
                $suggestions['quality'] = max(60, $suggestions['quality'] - 10);
                $suggestions['reason'] = 'WebP格式，可以使用较低质量仍保持良好效果';
                break;
                
            case 'image/jpeg':
                // JPEG已经是压缩格式，需要更保守的压缩
                if ($fileSize > 5 * 1024 * 1024) {
                    $suggestions['quality'] = 60;
                    $suggestions['reason'] = 'JPEG文件很大，建议适度压缩';
                } elseif ($fileSize > 2 * 1024 * 1024) {
                    $suggestions['quality'] = 75;
                    $suggestions['reason'] = 'JPEG文件较大，轻度压缩';
                } else {
                    $suggestions['quality'] = 85;
                    $suggestions['reason'] = 'JPEG文件，保持较高质量';
                }
                break;
        }
        
        // 选择最佳压缩方法
        if (extension_loaded('imagick')) {
            $suggestions['method'] = 'imagick';
        } elseif (extension_loaded('gd')) {
            $suggestions['method'] = 'gd';
        }
        
        return $suggestions;
    }
}
