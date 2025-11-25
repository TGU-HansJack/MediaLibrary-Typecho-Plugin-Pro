<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 图片编辑器类 - 处理裁剪和水印
 */
class MediaLibrary_ImageEditor
{
    /**
     * 裁剪图片
     * 
     * @param int $cid 文件ID
     * @param int $x 裁剪起始X坐标
     * @param int $y 裁剪起始Y坐标
     * @param int $width 裁剪宽度
     * @param int $height 裁剪高度
     * @param bool $replaceOriginal 是否替换原图
     * @param string $customName 自定义文件名
     * @param string $useLibrary 使用的图像处理库 (gd 或 imagick)
     * @param Typecho_Db $db 数据库实例
     * @param Typecho_Widget_Helper_Options $options 配置选项
     * @param Typecho_Widget_User $user 当前用户
     * @return array 处理结果
     */
    public static function cropImage($cid, $x, $y, $width, $height, $replaceOriginal, $customName, $useLibrary, $db, $options, $user)
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
        
        $originalPath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];
        if (!file_exists($originalPath)) {
            return ['success' => false, 'message' => '原文件不存在', 'cid' => $cid];
        }
        
        // 检查是否为图片
        if (strpos($attachmentData['mime'], 'image/') !== 0) {
            return ['success' => false, 'message' => '只能裁剪图片文件', 'cid' => $cid];
        }
        
        // 处理输出路径
        $pathInfo = pathinfo($originalPath);
        if ($replaceOriginal) {
            $outputPath = $originalPath;
            $tempPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_crop_temp.' . $pathInfo['extension'];
        } else {
            if (!empty($customName)) {
                $outputPath = $pathInfo['dirname'] . '/' . $customName . '.' . $pathInfo['extension'];
            } else {
                $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_cropped.' . $pathInfo['extension'];
            }
            $tempPath = $outputPath;
        }
        
        // 进行裁剪操作
        $result = self::performCrop($originalPath, $tempPath, $x, $y, $width, $height, $useLibrary);
        
        if (!$result['success']) {
            return $result;
        }
        
        // 替换原文件流程
        if ($replaceOriginal) {
            // 备份原文件尺寸信息
            list($originalWidth, $originalHeight) = getimagesize($originalPath);
            
            if (!@unlink($originalPath) || !rename($tempPath, $originalPath)) {
                @unlink($tempPath);
                return ['success' => false, 'message' => '替换原文件失败', 'cid' => $cid];
            }
            
            // 更新数据库中的文件信息
            $attachmentData['size'] = filesize($originalPath);
            
            // 可选: 更新图片尺寸信息
            if (isset($attachmentData['width']) && isset($attachmentData['height'])) {
                $attachmentData['width'] = $width;
                $attachmentData['height'] = $height;
            }
            
            $db->query($db->update('table.contents')
                ->rows(['text' => serialize($attachmentData)])
                ->where('cid = ?', $cid));
                
            return [
                'success' => true,
                'message' => '图片裁剪成功',
                'cid' => $cid,
                'original_dimensions' => $originalWidth . 'x' . $originalHeight,
                'new_dimensions' => $width . 'x' . $height,
                'url' => Typecho_Common::url($attachmentData['path'], $options->siteUrl)
            ];
        } else {
            // 添加新文件到数据库
            $newAttachmentData = $attachmentData;
            $newAttachmentData['path'] = str_replace(__TYPECHO_ROOT_DIR__, '', $outputPath);
            $newAttachmentData['size'] = filesize($outputPath);
            $newAttachmentData['name'] = basename($outputPath);
            
            // 添加尺寸信息
            $newAttachmentData['width'] = $width;
            $newAttachmentData['height'] = $height;
            
            $struct = [
                'title' => basename($outputPath),
                'slug' => basename($outputPath),
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
            
            $insertId = $db->query($db->insert('table.contents')->rows($struct));
            
            if ($insertId) {
                return [
                    'success' => true,
                    'message' => '图片裁剪成功，已创建新文件',
                    'cid' => $insertId,
                    'new_dimensions' => $width . 'x' . $height,
                    'url' => Typecho_Common::url($newAttachmentData['path'], $options->siteUrl)
                ];
            } else {
                return ['success' => false, 'message' => '保存裁剪图片到数据库失败', 'cid' => $cid];
            }
        }
    }
    
    /**
     * 执行实际的裁剪操作
     * 
     * @param string $sourcePath 源文件路径
     * @param string $destPath 目标文件路径
     * @param int $x 裁剪起始X坐标
     * @param int $y 裁剪起始Y坐标
     * @param int $width 裁剪宽度
     * @param int $height 裁剪高度
     * @param string $library 使用的库 (gd 或 imagick)
     * @return array 处理结果
     */
    private static function performCrop($sourcePath, $destPath, $x, $y, $width, $height, $library = 'gd')
    {
        if ($library == 'imagick' && extension_loaded('imagick')) {
            return self::cropWithImagick($sourcePath, $destPath, $x, $y, $width, $height);
        } else {
            return self::cropWithGD($sourcePath, $destPath, $x, $y, $width, $height);
        }
    }
    
    /**
     * 使用GD库裁剪图片
     */
    private static function cropWithGD($sourcePath, $destPath, $x, $y, $width, $height)
    {
        try {
            // 获取图片MIME类型
            $imageInfo = @getimagesize($sourcePath);
            if (!$imageInfo) {
                return ['success' => false, 'message' => '无法获取图片信息'];
            }
            
            $mime = $imageInfo['mime'];
            
            // 创建图像资源
            $srcImage = null;
            switch ($mime) {
                case 'image/jpeg':
                    $srcImage = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $srcImage = imagecreatefrompng($sourcePath);
                    break;
                case 'image/gif':
                    $srcImage = imagecreatefromgif($sourcePath);
                    break;
                case 'image/webp':
                    $srcImage = imagecreatefromwebp($sourcePath);
                    break;
                default:
                    return ['success' => false, 'message' => 'GD库不支持此图片格式: ' . $mime];
            }
            
            if (!$srcImage) {
                return ['success' => false, 'message' => '无法创建图像资源'];
            }
            
            // 创建裁剪后的图像资源
            $destImage = imagecreatetruecolor($width, $height);
            
            // 处理PNG和WebP的透明度
            if ($mime == 'image/png' || $mime == 'image/webp') {
                imagecolortransparent($destImage, imagecolorallocate($destImage, 0, 0, 0));
                imagealphablending($destImage, false);
                imagesavealpha($destImage, true);
            }
            
            // 执行裁剪
            if (!imagecopy($destImage, $srcImage, 0, 0, $x, $y, $width, $height)) {
                imagedestroy($srcImage);
                imagedestroy($destImage);
                return ['success' => false, 'message' => '裁剪操作失败'];
            }
            
            // 保存裁剪后的图像
            $result = false;
            switch ($mime) {
                case 'image/jpeg':
                    $result = imagejpeg($destImage, $destPath, 95);
                    break;
                case 'image/png':
                    $result = imagepng($destImage, $destPath, 9);
                    break;
                case 'image/gif':
                    $result = imagegif($destImage, $destPath);
                    break;
                case 'image/webp':
                    $result = imagewebp($destImage, $destPath, 95);
                    break;
            }
            
            // 清理资源
            imagedestroy($srcImage);
            imagedestroy($destImage);
            
            if (!$result) {
                return ['success' => false, 'message' => '无法保存裁剪后的图片'];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'GD裁剪错误: ' . $e->getMessage()];
        }
    }
    
    /**
     * 使用Imagick库裁剪图片
     */
    private static function cropWithImagick($sourcePath, $destPath, $x, $y, $width, $height)
    {
        try {
            $imagick = new Imagick($sourcePath);
            
            // 执行裁剪
            $imagick->cropImage($width, $height, $x, $y);
            
            // 重置图片页面几何形状
            $imagick->setImagePage(0, 0, 0, 0);
            
            // 保存图片
            if (!$imagick->writeImage($destPath)) {
                $imagick->destroy();
                return ['success' => false, 'message' => '无法保存裁剪后的图片'];
            }
            
            $imagick->destroy();
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Imagick裁剪错误: ' . $e->getMessage()];
        }
    }
    
    /**
     * 添加水印
     * 
     * @param int $cid 文件ID
     * @param array $watermarkConfig 水印配置
     * @param bool $replaceOriginal 是否替换原图
     * @param string $customName 自定义文件名
     * @param string $useLibrary 使用的图像处理库
     * @param Typecho_Db $db 数据库实例
     * @param Typecho_Widget_Helper_Options $options 配置选项
     * @param Typecho_Widget_User $user 当前用户
     * @return array 处理结果
     */
    public static function addWatermark($cid, $watermarkConfig, $replaceOriginal, $customName, $useLibrary, $db, $options, $user)
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
        
        $originalPath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];
        if (!file_exists($originalPath)) {
            return ['success' => false, 'message' => '原文件不存在', 'cid' => $cid];
        }
        
        // 检查是否为图片
        if (strpos($attachmentData['mime'], 'image/') !== 0) {
            return ['success' => false, 'message' => '只能为图片添加水印', 'cid' => $cid];
        }
        
        // 处理输出路径
        $pathInfo = pathinfo($originalPath);
        if ($replaceOriginal) {
            $outputPath = $originalPath;
            $tempPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_watermark_temp.' . $pathInfo['extension'];
        } else {
            if (!empty($customName)) {
                $outputPath = $pathInfo['dirname'] . '/' . $customName . '.' . $pathInfo['extension'];
            } else {
                $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_watermarked.' . $pathInfo['extension'];
            }
            $tempPath = $outputPath;
        }
        
        // 执行添加水印操作
        $result = self::performWatermark($originalPath, $tempPath, $watermarkConfig, $useLibrary);
        
        if (!$result['success']) {
            return $result;
        }
        
        // 替换原文件流程
        if ($replaceOriginal) {
            if (!@unlink($originalPath) || !rename($tempPath, $originalPath)) {
                @unlink($tempPath);
                return ['success' => false, 'message' => '替换原文件失败', 'cid' => $cid];
            }
            
            // 更新数据库中的文件大小
            $attachmentData['size'] = filesize($originalPath);
            
            $db->query($db->update('table.contents')
                ->rows(['text' => serialize($attachmentData)])
                ->where('cid = ?', $cid));
                
            return [
                'success' => true,
                'message' => '水印添加成功',
                'cid' => $cid,
                'url' => Typecho_Common::url($attachmentData['path'], $options->siteUrl)
            ];
        } else {
            // 添加新文件到数据库
            $newAttachmentData = $attachmentData;
            $newAttachmentData['path'] = str_replace(__TYPECHO_ROOT_DIR__, '', $outputPath);
            $newAttachmentData['size'] = filesize($outputPath);
            $newAttachmentData['name'] = basename($outputPath);
            
            $struct = [
                'title' => basename($outputPath),
                'slug' => basename($outputPath),
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
            
            $insertId = $db->query($db->insert('table.contents')->rows($struct));
            
            if ($insertId) {
                return [
                    'success' => true,
                    'message' => '水印添加成功，已创建新文件',
                    'cid' => $insertId,
                    'url' => Typecho_Common::url($newAttachmentData['path'], $options->siteUrl)
                ];
            } else {
                return ['success' => false, 'message' => '保存水印图片到数据库失败', 'cid' => $cid];
            }
        }
    }
    
    /**
     * 执行添加水印操作
     * 
     * @param string $sourcePath 源文件路径
     * @param string $destPath 目标文件路径
     * @param array $config 水印配置
     * @param string $library 使用的库
     * @return array 处理结果
     */
    private static function performWatermark($sourcePath, $destPath, $config, $library = 'gd')
    {
        if ($library == 'imagick' && extension_loaded('imagick')) {
            return self::watermarkWithImagick($sourcePath, $destPath, $config);
        } else {
            return self::watermarkWithGD($sourcePath, $destPath, $config);
        }
    }
    
    /**
     * 使用GD库添加水印
     */
    private static function watermarkWithGD($sourcePath, $destPath, $config)
    {
        try {
            // 获取图片MIME类型
            $imageInfo = @getimagesize($sourcePath);
            if (!$imageInfo) {
                return ['success' => false, 'message' => '无法获取图片信息'];
            }

            $mime = $imageInfo['mime'];
            $sourceWidth = $imageInfo[0];
            $sourceHeight = $imageInfo[1];

            // 创建图像资源
            $srcImage = null;
            switch ($mime) {
                case 'image/jpeg':
                    $srcImage = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $srcImage = imagecreatefrompng($sourcePath);
                    break;
                case 'image/gif':
                    $srcImage = imagecreatefromgif($sourcePath);
                    break;
                case 'image/webp':
                    $srcImage = imagecreatefromwebp($sourcePath);
                    break;
                default:
                    return ['success' => false, 'message' => 'GD库不支持此图片格式: ' . $mime];
            }

            if (!$srcImage) {
                return ['success' => false, 'message' => '无法创建图像资源'];
            }

            // 水印类型
            $type = $config['type'];

            // 水印位置计算
            $x = intval($config['x']);
            $y = intval($config['y']);

            // 根据预设位置调整坐标
            if (isset($config['position'])) {
                switch ($config['position']) {
                    case 'top-left':
                        $x = 10;
                        $y = 10;
                        break;
                    case 'top-center':
                        // 后续计算水印宽度后再调整
                        $x = $sourceWidth / 2;
                        $y = 10;
                        break;
                    case 'top-right':
                        // 后续计算水印宽度后再调整
                        $x = $sourceWidth - 10;
                        $y = 10;
                        break;
                    case 'middle-left':
                        $x = 10;
                        $y = $sourceHeight / 2;
                        break;
                    case 'middle-center':
                        $x = $sourceWidth / 2;
                        $y = $sourceHeight / 2;
                        break;
                    case 'middle-right':
                        $x = $sourceWidth - 10;
                        $y = $sourceHeight / 2;
                        break;
                    case 'bottom-left':
                        $x = 10;
                        $y = $sourceHeight - 10;
                        break;
                    case 'bottom-center':
                        $x = $sourceWidth / 2;
                        $y = $sourceHeight - 10;
                        break;
                    case 'bottom-right':
                        $x = $sourceWidth - 10;
                        $y = $sourceHeight - 10;
                        break;
                }
            }

            // 水印透明度
            $opacity = isset($config['opacity']) ? intval($config['opacity']) : 70;

            // 文本水印
            if ($type === 'text') {
                $text = self::normalizeWatermarkText($config['text']);

                // 检查是否包含中文字符
                $hasChinese = preg_match('/[\x{4e00}-\x{9fa5}]/u', $text);

                $fontSize = isset($config['fontSize']) ? intval($config['fontSize']) : 24;
                $fontColor = isset($config['color']) ? $config['color'] : '#ffffff';

                // 转换颜色格式
                if (preg_match('/#([a-f0-9]{2})([a-f0-9]{2})([a-f0-9]{2})/i', $fontColor, $matches)) {
                    $r = hexdec($matches[1]);
                    $g = hexdec($matches[2]);
                    $b = hexdec($matches[3]);
                } else {
                    $r = 255;
                    $g = 255;
                    $b = 255;
                }

                // 指定字体路径
                $preferredFont = isset($config['fontPath']) ? $config['fontPath'] : __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/assets/fonts/msyh.ttf';
                $resolvedFont = self::resolveFontPath($preferredFont);
                $useBuiltinFont = false;

                // 如果包含中文但没有找到字体文件，返回错误
                if ($hasChinese && !$resolvedFont) {
                    imagedestroy($srcImage);
                    return ['success' => false, 'message' => '无法添加中文水印：未找到中文字体文件。请在插件的 assets/fonts 目录下添加中文字体文件（如 msyh.ttf、simhei.ttf 等），或使用系统自带的中文字体。'];
                }

                if (!$resolvedFont) {
                    // 使用GD内置字体作为兜底，虽然不支持完整中文，但可避免报错
                    $resolvedFont = 5;
                    $useBuiltinFont = true;
                }

                // 计算文本尺寸
                if ($useBuiltinFont) {
                    $textWidth = strlen($text) * imagefontwidth($resolvedFont);
                    $textHeight = imagefontheight($resolvedFont);
                } else {
                    $box = imagettfbbox($fontSize, 0, $resolvedFont, $text);
                    $textWidth = abs($box[4] - $box[0]);
                    $textHeight = abs($box[5] - $box[1]);
                }

                // 根据预设位置调整实际坐标
                if (isset($config['position'])) {
                    switch ($config['position']) {
                        case 'top-center':
                        case 'middle-center':
                        case 'bottom-center':
                            $x -= $textWidth / 2;
                            break;
                        case 'top-right':
                        case 'middle-right':
                        case 'bottom-right':
                            $x -= $textWidth;
                            break;
                    }

                    switch ($config['position']) {
                        case 'middle-left':
                        case 'middle-center':
                        case 'middle-right':
                            $y -= $textHeight / 2;
                            break;
                        case 'bottom-left':
                        case 'bottom-center':
                        case 'bottom-right':
                            $y -= $textHeight;
                            break;
                    }
                }

                // 使用预设文本样式
                if (isset($config['preset']) && $config['preset'] === 'ai-generated') {
                    $text = "AI生成图像 - " . date('Y-m-d');
                    $fontSize = max(12, intval($sourceWidth / 50));
                } elseif (isset($config['preset']) && $config['preset'] === 'copyright') {
                    $text = "© " . date('Y') . " - 版权所有";
                    $fontSize = max(12, intval($sourceWidth / 50));
                }

                $text = self::normalizeWatermarkText($text);

                // 创建文本颜色
                $textColor = imagecolorallocatealpha($srcImage, $r, $g, $b, 127 - ($opacity * 1.27));

                // 绘制文本
                if ($useBuiltinFont) {
                    imagestring($srcImage, $resolvedFont, $x, $y, $text, $textColor);
                } else {
                    imagettftext($srcImage, $fontSize, 0, $x, $y + $textHeight, $textColor, $resolvedFont, $text);
                }
            }
            // 图像水印
            elseif ($type === 'image') {
                $watermarkPath = $config['imagePath'];
                if (!file_exists($watermarkPath)) {
                    return ['success' => false, 'message' => '水印图片不存在: ' . $watermarkPath];
                }
                
                // 创建水印图像资源
                $watermarkInfo = @getimagesize($watermarkPath);
                if (!$watermarkInfo) {
                    return ['success' => false, 'message' => '无法获取水印图片信息'];
                }
                
                $watermarkMime = $watermarkInfo['mime'];
                $watermarkWidth = $watermarkInfo[0];
                $watermarkHeight = $watermarkInfo[1];
                
                $watermarkImage = null;
                switch ($watermarkMime) {
                    case 'image/jpeg':
                        $watermarkImage = imagecreatefromjpeg($watermarkPath);
                        break;
                    case 'image/png':
                        $watermarkImage = imagecreatefrompng($watermarkPath);
                        break;
                    case 'image/gif':
                        $watermarkImage = imagecreatefromgif($watermarkPath);
                        break;
                    case 'image/webp':
                        $watermarkImage = imagecreatefromwebp($watermarkPath);
                        break;
                    default:
                        return ['success' => false, 'message' => 'GD库不支持此水印图片格式: ' . $watermarkMime];
                }
                
                if (!$watermarkImage) {
                    return ['success' => false, 'message' => '无法创建水印图像资源'];
                }
                
                // 缩放水印图片
                if (isset($config['scale']) && $config['scale'] > 0 && $config['scale'] != 1) {
                    $newWidth = intval($watermarkWidth * $config['scale']);
                    $newHeight = intval($watermarkHeight * $config['scale']);
                    
                    $scaledWatermark = imagecreatetruecolor($newWidth, $newHeight);
                    imagealphablending($scaledWatermark, false);
                    imagesavealpha($scaledWatermark, true);
                    
                    imagecopyresampled(
                        $scaledWatermark, $watermarkImage,
                        0, 0, 0, 0,
                        $newWidth, $newHeight, $watermarkWidth, $watermarkHeight
                    );
                    
                    imagedestroy($watermarkImage);
                    $watermarkImage = $scaledWatermark;
                    $watermarkWidth = $newWidth;
                    $watermarkHeight = $newHeight;
                }
                
                // 根据预设位置调整实际坐标
                if (isset($config['position'])) {
                    switch ($config['position']) {
                        case 'top-center':
                        case 'middle-center':
                        case 'bottom-center':
                            $x -= $watermarkWidth / 2;
                            break;
                        case 'top-right':
                        case 'middle-right':
                        case 'bottom-right':
                            $x -= $watermarkWidth;
                            break;
                    }
                    
                    switch ($config['position']) {
                        case 'middle-left':
                        case 'middle-center':
                        case 'middle-right':
                            $y -= $watermarkHeight / 2;
                            break;
                        case 'bottom-left':
                        case 'bottom-center':
                        case 'bottom-right':
                            $y -= $watermarkHeight;
                            break;
                    }
                }
                
                // 设置透明度混合
                imagealphablending($srcImage, true);
                
                // 合并水印到原图
                self::imagecopymerge_alpha($srcImage, $watermarkImage, $x, $y, 0, 0, $watermarkWidth, $watermarkHeight, $opacity);
                
                // 清理水印资源
                imagedestroy($watermarkImage);
            } else {
                return ['success' => false, 'message' => '不支持的水印类型: ' . $type];
            }
            
            // 保存结果
            $result = false;
            switch ($mime) {
                case 'image/jpeg':
                    $result = imagejpeg($srcImage, $destPath, 95);
                    break;
                case 'image/png':
                    imagealphablending($srcImage, false);
                    imagesavealpha($srcImage, true);
                    $result = imagepng($srcImage, $destPath, 9);
                    break;
                case 'image/gif':
                    $result = imagegif($srcImage, $destPath);
                    break;
                case 'image/webp':
                    $result = imagewebp($srcImage, $destPath, 95);
                    break;
            }
            
            // 清理资源
            imagedestroy($srcImage);
            
            if (!$result) {
                return ['success' => false, 'message' => '无法保存水印图片'];
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'GD水印错误: ' . $e->getMessage()];
        }
    }
    
    /**
     * 支持透明度的imagecopymerge函数
     */
    private static function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct)
    {
        // 创建一个临时图像
        $cut = imagecreatetruecolor($src_w, $src_h);
        imagealphablending($cut, false);
        imagesavealpha($cut, true);
        
        // 复制源图像到临时图像
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
        
        // 合并水印到临时图像
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
        
        // 将临时图像与透明度合并到目标图像
        imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
        
        // 清理临时图像
        imagedestroy($cut);
        
        return true;
    }
    
    /**
     * 使用Imagick库添加水印
     */
    private static function watermarkWithImagick($sourcePath, $destPath, $config)
    {
        try {
            // 创建Imagick对象
            $image = new Imagick($sourcePath);
            
            // 获取图像尺寸
            $sourceWidth = $image->getImageWidth();
            $sourceHeight = $image->getImageHeight();
            
            // 水印类型
            $type = $config['type'];
            
            // 水印位置计算
            $x = intval($config['x']);
            $y = intval($config['y']);
            
            // 根据预设位置调整坐标
            if (isset($config['position'])) {
                switch ($config['position']) {
                    case 'top-left':
                        $x = 10;
                        $y = 10;
                        break;
                    case 'top-center':
                        // 后续计算水印宽度后再调整
                        $x = $sourceWidth / 2;
                        $y = 10;
                        break;
                    case 'top-right':
                        // 后续计算水印宽度后再调整
                        $x = $sourceWidth - 10;
                        $y = 10;
                        break;
                    case 'middle-left':
                        $x = 10;
                        $y = $sourceHeight / 2;
                        break;
                    case 'middle-center':
                        $x = $sourceWidth / 2;
                        $y = $sourceHeight / 2;
                        break;
                    case 'middle-right':
                        $x = $sourceWidth - 10;
                        $y = $sourceHeight / 2;
                        break;
                    case 'bottom-left':
                        $x = 10;
                        $y = $sourceHeight - 10;
                        break;
                    case 'bottom-center':
                        $x = $sourceWidth / 2;
                        $y = $sourceHeight - 10;
                        break;
                    case 'bottom-right':
                        $x = $sourceWidth - 10;
                        $y = $sourceHeight - 10;
                        break;
                }
            }
            
            // 水印透明度
            $opacity = isset($config['opacity']) ? intval($config['opacity']) : 70;
            
            // 文本水印
            if ($type === 'text') {
                $text = self::normalizeWatermarkText($config['text']);

                // 检查是否包含中文字符
                $hasChinese = preg_match('/[\x{4e00}-\x{9fa5}]/u', $text);

                $fontSize = isset($config['fontSize']) ? intval($config['fontSize']) : 24;
                $fontColor = isset($config['color']) ? $config['color'] : '#ffffff';

                // 指定字体路径
                $preferredFont = isset($config['fontPath']) ? $config['fontPath'] : __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/assets/fonts/msyh.ttf';
                $fontPath = self::resolveFontPath($preferredFont);

                // 如果包含中文但没有找到字体文件，返回错误
                if ($hasChinese && !$fontPath) {
                    return ['success' => false, 'message' => '无法添加中文水印：未找到中文字体文件。请在插件的 assets/fonts 目录下添加中文字体文件（如 msyh.ttf、simhei.ttf 等），或使用系统自带的中文字体。'];
                }

                if (!$fontPath) {
                    return ['success' => false, 'message' => '未找到可用的字体文件，请在插件 fonts 目录或系统中安装字体'];
                }

                // 使用预设文本样式
                if (isset($config['preset']) && $config['preset'] === 'ai-generated') {
                    $text = "AI生成图像 - " . date('Y-m-d');
                    $fontSize = max(12, intval($sourceWidth / 50));
                } elseif (isset($config['preset']) && $config['preset'] === 'copyright') {
                    $text = "© " . date('Y') . " - 版权所有";
                    $fontSize = max(12, intval($sourceWidth / 50));
                }

                $text = self::normalizeWatermarkText($text);

                // 创建绘制对象
                $draw = new ImagickDraw();

                // 设置文本属性
                $draw->setFont($fontPath);
                $draw->setFontSize($fontSize);
                $draw->setFillColor(new ImagickPixel($fontColor));
                
                // 计算文本尺寸
                $metrics = $image->queryFontMetrics($draw, $text);
                $textWidth = $metrics['textWidth'];
                $textHeight = $metrics['textHeight'];
                
                // 根据预设位置调整实际坐标
                if (isset($config['position'])) {
                    switch ($config['position']) {
                        case 'top-center':
                        case 'middle-center':
                        case 'bottom-center':
                            $x -= $textWidth / 2;
                            break;
                        case 'top-right':
                        case 'middle-right':
                        case 'bottom-right':
                            $x -= $textWidth;
                            break;
                    }
                    
                    switch ($config['position']) {
                        case 'middle-left':
                        case 'middle-center':
                        case 'middle-right':
                            $y -= $textHeight / 2;
                            break;
                        case 'bottom-left':
                        case 'bottom-center':
                        case 'bottom-right':
                            $y -= $textHeight;
                            break;
                    }
                }
                
                // 调整y坐标（Imagick文本基线在底部）
                $y += $textHeight * 0.75;
                
                // 设置透明度
                $draw->setFillOpacity($opacity / 100);
                
                // 绘制文本
                $image->annotateImage($draw, $x, $y, 0, $text);
                
                // 清理绘制对象
                $draw->clear();
                $draw->destroy();
                
            }
            // 图像水印
            elseif ($type === 'image') {
                $watermarkPath = $config['imagePath'];
                if (!file_exists($watermarkPath)) {
                    return ['success' => false, 'message' => '水印图片不存在: ' . $watermarkPath];
                }
                
                // 创建水印对象
                $watermark = new Imagick($watermarkPath);
                
                // 获取水印尺寸
                $watermarkWidth = $watermark->getImageWidth();
                $watermarkHeight = $watermark->getImageHeight();
                
                // 缩放水印图片
                if (isset($config['scale']) && $config['scale'] > 0 && $config['scale'] != 1) {
                    $newWidth = intval($watermarkWidth * $config['scale']);
                    $newHeight = intval($watermarkHeight * $config['scale']);
                    $watermark->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
                    $watermarkWidth = $newWidth;
                    $watermarkHeight = $newHeight;
                }
                
                // 根据预设位置调整实际坐标
                if (isset($config['position'])) {
                    switch ($config['position']) {
                        case 'top-center':
                        case 'middle-center':
                        case 'bottom-center':
                            $x -= $watermarkWidth / 2;
                            break;
                        case 'top-right':
                        case 'middle-right':
                        case 'bottom-right':
                            $x -= $watermarkWidth;
                            break;
                    }
                    
                    switch ($config['position']) {
                        case 'middle-left':
                        case 'middle-center':
                        case 'middle-right':
                            $y -= $watermarkHeight / 2;
                            break;
                        case 'bottom-left':
                        case 'bottom-center':
                        case 'bottom-right':
                            $y -= $watermarkHeight;
                            break;
                    }
                }
                
                // 设置水印透明度
                $watermark->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity / 100, Imagick::CHANNEL_ALPHA);
                
                // 合成水印
                $image->compositeImage($watermark, Imagick::COMPOSITE_OVER, $x, $y);
                
                // 清理水印对象
                $watermark->destroy();
                
            } else {
                return ['success' => false, 'message' => '不支持的水印类型: ' . $type];
            }
            
            // 保存结果
            if (!$image->writeImage($destPath)) {
                $image->destroy();
                return ['success' => false, 'message' => '无法保存水印图片'];
            }
            
            // 清理资源
            $image->destroy();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Imagick水印错误: ' . $e->getMessage()];
        }
    }

    /**
     * 将水印文本规范为UTF-8并去除不可见字符
     *
     * @param string $text
     * @return string
     */
    private static function normalizeWatermarkText($text)
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
     * 查找可用的字体文件路径，优先使用插件内字体，其次尝试系统常见字体
     *
     * @param string|null $preferredPath
     * @return string|null
     */
    private static function resolveFontPath($preferredPath = null)
    {
        $candidates = [];
        if (!empty($preferredPath)) {
            $candidates[] = $preferredPath;
        }

        $fontDir = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/assets/fonts/';
        $fallbackFonts = [
            'msyh.ttf',
            'msyh.ttc',
            'simhei.ttf',
            'simkai.ttf',
            'simsun.ttc',
            'SourceHanSansSC-Regular.otf',
            'NotoSansSC-Regular.otf'
        ];
        foreach ($fallbackFonts as $fontFile) {
            $candidates[] = $fontDir . $fontFile;
        }

        $systemRoot = getenv('SystemRoot');
        if ($systemRoot) {
            $systemRoot = rtrim($systemRoot, '\\/');
            $candidates[] = $systemRoot . '/Fonts/msyh.ttc';
            $candidates[] = $systemRoot . '/Fonts/simhei.ttf';
            $candidates[] = $systemRoot . '/Fonts/simsun.ttc';
        }

        $candidates = array_merge($candidates, [
            '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/opentype/noto/NotoSansCJKsc-Regular.otf',
            '/usr/share/fonts/truetype/droid/DroidSansFallbackFull.ttf',
            '/Library/Fonts/STHeiti Light.ttc',
            '/Library/Fonts/STHeiti Medium.ttc',
            '/System/Library/Fonts/STHeiti Light.ttc'
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * 直接通过文件路径裁剪图片（不依赖数据库）
     *
     * @param string $filePath 文件完整路径
     * @param int $x 裁剪起始X坐标
     * @param int $y 裁剪起始Y坐标
     * @param int $width 裁剪宽度
     * @param int $height 裁剪高度
     * @param bool $replaceOriginal 是否替换原图
     * @param string $customName 自定义文件名
     * @param string $useLibrary 使用的图像处理库 (gd 或 imagick)
     * @return array 处理结果
     */
    public static function cropImageByPath($filePath, $x, $y, $width, $height, $replaceOriginal = false, $customName = '', $useLibrary = 'gd')
    {
        // 检查文件是否存在
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => '文件不存在'];
        }

        // 检查是否为图片
        $imageInfo = @getimagesize($filePath);
        if (!$imageInfo) {
            return ['success' => false, 'message' => '无法获取图片信息'];
        }

        $mime = $imageInfo['mime'];
        if (strpos($mime, 'image/') !== 0) {
            return ['success' => false, 'message' => '只能裁剪图片文件'];
        }

        // 处理输出路径
        $pathInfo = pathinfo($filePath);
        if ($replaceOriginal) {
            $outputPath = $filePath;
            $tempPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_crop_temp.' . $pathInfo['extension'];
        } else {
            if (!empty($customName)) {
                $outputPath = $pathInfo['dirname'] . '/' . $customName . '.' . $pathInfo['extension'];
            } else {
                $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_cropped.' . $pathInfo['extension'];
            }
            $tempPath = $outputPath;
        }

        // 进行裁剪操作
        $result = self::performCrop($filePath, $tempPath, $x, $y, $width, $height, $useLibrary);

        if (!$result['success']) {
            return $result;
        }

        // 替换原文件流程
        if ($replaceOriginal) {
            // 备份原文件尺寸信息
            list($originalWidth, $originalHeight) = getimagesize($filePath);

            if (!@unlink($filePath) || !rename($tempPath, $filePath)) {
                @unlink($tempPath);
                return ['success' => false, 'message' => '替换原文件失败'];
            }

            return [
                'success' => true,
                'message' => '图片裁剪成功',
                'original_dimensions' => $originalWidth . 'x' . $originalHeight,
                'new_dimensions' => $width . 'x' . $height,
                'path' => $filePath
            ];
        } else {
            return [
                'success' => true,
                'message' => '图片裁剪成功，已创建新文件',
                'new_dimensions' => $width . 'x' . $height,
                'path' => $outputPath
            ];
        }
    }

    /**
     * 直接通过文件路径添加水印（不依赖数据库）
     *
     * @param string $filePath 文件完整路径
     * @param array $watermarkConfig 水印配置
     * @param bool $replaceOriginal 是否替换原图
     * @param string $customName 自定义文件名
     * @param string $useLibrary 使用的图像处理库
     * @return array 处理结果
     */
    public static function addWatermarkByPath($filePath, $watermarkConfig, $replaceOriginal = false, $customName = '', $useLibrary = 'gd')
    {
        // 检查文件是否存在
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => '文件不存在'];
        }

        // 检查是否为图片
        $imageInfo = @getimagesize($filePath);
        if (!$imageInfo) {
            return ['success' => false, 'message' => '无法获取图片信息'];
        }

        $mime = $imageInfo['mime'];
        if (strpos($mime, 'image/') !== 0) {
            return ['success' => false, 'message' => '只能为图片添加水印'];
        }

        // 处理输出路径
        $pathInfo = pathinfo($filePath);
        if ($replaceOriginal) {
            $outputPath = $filePath;
            $tempPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_watermark_temp.' . $pathInfo['extension'];
        } else {
            if (!empty($customName)) {
                $outputPath = $pathInfo['dirname'] . '/' . $customName . '.' . $pathInfo['extension'];
            } else {
                $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_watermarked.' . $pathInfo['extension'];
            }
            $tempPath = $outputPath;
        }

        // 执行添加水印操作
        $result = self::performWatermark($filePath, $tempPath, $watermarkConfig, $useLibrary);

        if (!$result['success']) {
            return $result;
        }

        // 替换原文件流程
        if ($replaceOriginal) {
            if (!@unlink($filePath) || !rename($tempPath, $filePath)) {
                @unlink($tempPath);
                return ['success' => false, 'message' => '替换原文件失败'];
            }

            return [
                'success' => true,
                'message' => '水印添加成功',
                'path' => $filePath
            ];
        } else {
            return [
                'success' => true,
                'message' => '水印添加成功，已创建新文件',
                'path' => $outputPath
            ];
        }
    }
}
