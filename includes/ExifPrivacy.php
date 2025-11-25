<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/CacheManager.php';

/**
 * EXIF隐私工具类
 */
class MediaLibrary_ExifPrivacy
{
    /**
     * 检查是否有可用的 ExifTool
     * 
     * @return bool ExifTool是否可用
     */
    public static function isExifToolAvailable() 
    {
        static $checked = null;
        if ($checked !== null) {
            return $checked;
        }
        
        try {
            $exiftoolPath = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/lib/PHPExiftool/Exiftool.php';
            if (!file_exists($exiftoolPath)) {
                $checked = false;
                return false;
            }
            
            // 检查是否可以包含文件
            if (!is_readable($exiftoolPath)) {
                $checked = false;
                return false;
            }
            
            // 尝试包含并检查类是否存在
            @include_once $exiftoolPath;
            
            // 检查类是否存在（支持不同的命名空间）
            $classExists = class_exists('PHPExiftool\\Exiftool') || 
                          class_exists('PHPExiftool\Exiftool') || 
                          class_exists('Exiftool');
            
            $checked = $classExists;
            return $checked;
            
        } catch (Exception $e) {
            error_log('isExifToolAvailable error: ' . $e->getMessage());
            $checked = false;
            return false;
        } catch (Error $e) {
            error_log('isExifToolAvailable fatal error: ' . $e->getMessage());
            $checked = false;
            return false;
        }
    }
    
    /**
     * GPS坐标转换
     */
    private static function gps2Num($coordPart) 
    {
        if (is_array($coordPart) && count($coordPart) >= 2) {
            $parts = $coordPart;
        } else {
            $parts = explode('/', $coordPart);
        }
        
        if (count($parts) >= 2 && floatval($parts[1]) != 0) {
            return floatval($parts[0]) / floatval($parts[1]);
        }
        return floatval($coordPart);
    }
    
    /**
     * EXIF坐标转换为浮点数
     */
    public static function exifToFloat($exifCoord, $ref) 
    {
        if (!is_array($exifCoord) || count($exifCoord) < 3) {
            return 0;
        }
        
        $degrees = self::gps2Num($exifCoord[0]);
        $minutes = self::gps2Num($exifCoord[1]);
        $seconds = self::gps2Num($exifCoord[2]);
        $float = $degrees + ($minutes / 60) + ($seconds / 3600);
        return ($ref === 'S' || $ref === 'W') ? -$float : $float;
    }
    
    /**
     * 使用PHP EXIF扩展读取EXIF数据
     */
    public static function readExifWithPhpExif($filePath) 
    {
        if (!extension_loaded('exif') || !function_exists('exif_read_data')) {
            return false;
        }
        
        try {
            // 检查文件是否存在且可读
            if (!file_exists($filePath) || !is_readable($filePath)) {
                return false;
            }
            
            // 检查是否为图片文件
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo === false) {
                return false;
            }
            
            // 读取 EXIF 数据
            $exifData = @exif_read_data($filePath, 'ANY_TAG', true);
            
            if ($exifData === false || !is_array($exifData)) {
                return false;
            }
            
            return $exifData;
        } catch (Exception $e) {
            error_log('PHP EXIF read error: ' . $e->getMessage());
            return false;
        } catch (Error $e) {
            error_log('PHP EXIF read fatal error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 检查图片隐私信息
     */
    public static function checkImagePrivacy($cid, $db, $options) 
    {
        try {
            $attachment = $db->fetchRow($db->select()->from('table.contents')
                ->where('cid = ? AND type = ?', $cid, 'attachment'));
                
            if (!$attachment) {
                return ['success' => false, 'cid' => $cid, 'message' => '文件不存在'];
            }
            
            $attachmentData = @unserialize($attachment['text']);
            if (!is_array($attachmentData) || !isset($attachmentData['path'])) {
                return ['success' => false, 'cid' => $cid, 'message' => '文件数据错误'];
            }
            
            $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];
            if (!file_exists($filePath)) {
                return ['success' => false, 'cid' => $cid, 'message' => '文件不存在'];
            }
            
            // 检查是否为图片
            if (!isset($attachmentData['mime']) || strpos($attachmentData['mime'], 'image/') !== 0) {
                return ['success' => false, 'cid' => $cid, 'message' => '只能检测图片文件'];
            }
            
            $filename = $attachmentData['name'] ?? basename($filePath);
            $privacyInfo = [];
            $hasPrivacy = false;
            $gpsCoords = null;
            
            // 尝试使用 PHP EXIF 扩展
            $exifData = self::readExifWithPhpExif($filePath);
            
            if ($exifData && is_array($exifData)) {
                // 检查 GPS 信息
                if (isset($exifData['GPS']) && is_array($exifData['GPS'])) {
                    $gps = $exifData['GPS'];
                    if (isset($gps['GPSLatitude'], $gps['GPSLongitude'], $gps['GPSLatitudeRef'], $gps['GPSLongitudeRef'])) {
                        try {
                            $lat = self::exifToFloat($gps['GPSLatitude'], $gps['GPSLatitudeRef']);
                            $lng = self::exifToFloat($gps['GPSLongitude'], $gps['GPSLongitudeRef']);
                            
                            if ($lat != 0 && $lng != 0) {
                                $privacyInfo['GPS位置'] = "纬度: {$lat}, 经度: {$lng}";
                                $gpsCoords = [$lng, $lat];
                                $hasPrivacy = true;
                            }
                        } catch (Exception $e) {
                            error_log('GPS parsing error: ' . $e->getMessage());
                        }
                    }
                }
                
                // 检查相机信息
                if (isset($exifData['IFD0']) && is_array($exifData['IFD0'])) {
                    $ifd0 = $exifData['IFD0'];
                    $cameraInfo = [];
                    
                    if (isset($ifd0['Make'])) $cameraInfo[] = $ifd0['Make'];
                    if (isset($ifd0['Model'])) $cameraInfo[] = $ifd0['Model'];
                    
                    if (!empty($cameraInfo)) {
                        $privacyInfo['设备信息'] = implode(' ', $cameraInfo);
                        $hasPrivacy = true;
                    }
                    
                    if (isset($ifd0['DateTime'])) {
                        $privacyInfo['拍摄时间'] = $ifd0['DateTime'];
                        $hasPrivacy = true;
                    }
                }
                
                // 检查 EXIF 信息
                if (isset($exifData['EXIF']) && is_array($exifData['EXIF'])) {
                    $exif = $exifData['EXIF'];
                    
                    if (isset($exif['DateTimeOriginal'])) {
                        $privacyInfo['原始拍摄时间'] = $exif['DateTimeOriginal'];
                        $hasPrivacy = true;
                    }
                    
                    if (isset($exif['DateTimeDigitized'])) {
                        $privacyInfo['数字化时间'] = $exif['DateTimeDigitized'];
                        $hasPrivacy = true;
                    }
                }
            }
            
            $message = $hasPrivacy ? '发现隐私信息' : '未发现隐私信息';
            
            return [
                'success' => true,
                'cid' => $cid,
                'filename' => $filename,
                'has_privacy' => $hasPrivacy,
                'privacy_info' => $privacyInfo,
                'message' => $message,
                'gps_coords' => $gpsCoords,
                'image_url' => isset($attachmentData['path']) ? Typecho_Common::url($attachmentData['path'], $options->siteUrl) : null
            ];
            
        } catch (Exception $e) {
            error_log('checkImagePrivacy error: ' . $e->getMessage());
            return [
                'success' => false, 
                'cid' => $cid, 
                'message' => '检测失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 使用ExifTool清除EXIF信息
     */
    public static function removeExifWithExifTool($filePath) 
    {
        try {
            // 检查系统是否安装了 exiftool 命令行工具
            $exiftoolBinary = null;
            
            $possiblePaths = [
                'exiftool',
                '/usr/bin/exiftool',
                '/usr/local/bin/exiftool',
                '/opt/homebrew/bin/exiftool',
            ];
            
            foreach ($possiblePaths as $path) {
                $output = [];
                $return_var = 0;
                @exec($path . ' -ver 2>&1', $output, $return_var);
                if ($return_var === 0 && !empty($output)) {
                    $exiftoolBinary = $path;
                    break;
                }
            }
            
            if (!$exiftoolBinary) {
                return ['success' => false, 'message' => '系统未安装 exiftool 命令行工具'];
            }
            
            if (!file_exists($filePath)) {
                return ['success' => false, 'message' => '文件不存在'];
            }
            
            // 备份原文件信息
            $originalSize = filesize($filePath);
            $originalPerms = fileperms($filePath);
            
            // 检查文件格式
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo === false) {
                return ['success' => false, 'message' => '不是有效的图片文件'];
            }
            
            // 多步骤清除EXIF信息
            $commands = [
                // 第一步：清除所有EXIF信息
                $exiftoolBinary . ' -EXIF:all= -overwrite_original ' . escapeshellarg($filePath),
                // 第二步：清除所有GPS信息
                $exiftoolBinary . ' -GPS:all= -overwrite_original ' . escapeshellarg($filePath),
                // 第三步：清除所有XMP信息
                $exiftoolBinary . ' -XMP:all= -overwrite_original ' . escapeshellarg($filePath),
                // 第四步：清除所有IPTC信息
                $exiftoolBinary . ' -IPTC:all= -overwrite_original ' . escapeshellarg($filePath),
                // 第五步：清除所有Maker Notes
                $exiftoolBinary . ' -MakerNotes:all= -overwrite_original ' . escapeshellarg($filePath),
                // 第六步：清除所有时间相关信息
                $exiftoolBinary . ' -DateTime= -DateTimeOriginal= -DateTimeDigitized= -CreateDate= -ModifyDate= -overwrite_original ' . escapeshellarg($filePath),
            ];
            
            foreach ($commands as $cmd) {
                $output = [];
                $return_var = 0;
                @exec($cmd . ' 2>&1', $output, $return_var);
                
                // 检查文件是否仍然存在且有效
                if (!file_exists($filePath)) {
                    return ['success' => false, 'message' => '处理过程中文件丢失'];
                }
                
                $checkImageInfo = @getimagesize($filePath);
                if ($checkImageInfo === false) {
                    return ['success' => false, 'message' => '处理过程中图片文件损坏'];
                }
                
                if ($checkImageInfo[0] !== $imageInfo[0] || $checkImageInfo[1] !== $imageInfo[1]) {
                    return ['success' => false, 'message' => '处理过程中图片尺寸发生变化'];
                }
            }
            
            // 最终验证：检查是否还有隐私信息
            $verifyOutput = [];
            $verifyCmd = $exiftoolBinary . ' -a -s ' . escapeshellarg($filePath) . ' 2>&1';
            @exec($verifyCmd, $verifyOutput, $verifyReturn);
            
            // 恢复文件权限
            @chmod($filePath, $originalPerms);
            
            $newSize = filesize($filePath);
            $sizeInfo = '';
            
            if ($newSize < $originalSize) {
                $saved = $originalSize - $newSize;
                $sizeInfo = '，清除了 ' . MediaLibrary_FileOperations::formatFileSize($saved) . ' 的元数据';
            } elseif ($newSize === $originalSize) {
                $sizeInfo = '，文件大小保持不变';
            }
            
            // 检查是否仍有隐私信息
            $hasPrivacyInfo = false;
            $remainingInfo = [];
            
            foreach ($verifyOutput as $line) {
                if (preg_match('/DateTime(?!.*Profile)|GPS|Make|Model|Artist|Copyright|Software|UserComment|ImageDescription|CreateDate|ModifyDate|SerialNumber|LensModel|LensSerialNumber|OwnerName|CameraOwnerName/i', $line)) {
                    $hasPrivacyInfo = true;
                    $remainingInfo[] = $line;
                }
            }
            
            if ($hasPrivacyInfo) {
                // 检查剩余信息是否都是非隐私信息
                $nonPrivacyOnly = true;
                foreach ($remainingInfo as $info) {
                    if (!preg_match('/FileModifyDate|ProfileDateTime|FileAccessDate|FileInodeChangeDate|FilePermissions|FileType|MIMEType|ImageWidth|ImageHeight|ColorSpace|ProfileDescription/i', $info)) {
                        $nonPrivacyOnly = false;
                        break;
                    }
                }
                
                if ($nonPrivacyOnly) {
                    return [
                        'success' => true, 
                        'message' => 'EXIF隐私信息已彻底清除' . $sizeInfo . '，剩余信息为系统和颜色配置信息（非隐私）'
                    ];
                } else {
                    return [
                        'success' => false, 
                        'message' => '部分隐私信息可能仍然存在' . $sizeInfo . '。残留信息: ' . implode(', ', array_slice($remainingInfo, 0, 2))
                    ];
                }
            } else {
                return [
                    'success' => true, 
                    'message' => 'EXIF隐私信息已彻底清除' . $sizeInfo . '，图像质量保持不变'
                ];
            }
        } catch (Exception $e) {
            error_log('ExifTool remove error: ' . $e->getMessage());
            return ['success' => false, 'message' => '操作失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 清除图片EXIF信息
     * 
     * @param string $filePath 文件路径
     * @param string $mimeType 文件MIME类型
     * @return array 操作结果
     */
    public static function removeImageExif($filePath, $mimeType) 
    {
        // 使用ExifTool清除EXIF
        if (self::isExifToolAvailable()) {
            return self::removeExifWithExifTool($filePath);
        }
        
        return ['success' => false, 'message' => 'ExifTool 不可用，无法清除EXIF信息'];
    }
}
