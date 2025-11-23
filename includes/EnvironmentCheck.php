<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 环境检查工具类
 */
class MediaLibrary_EnvironmentCheck
{
    /**
     * 检查系统环境
     * 
     * @return array 环境检测结果
     */
    public static function checkEnvironment()
    {
        return [
            'GetID3 库' => file_exists(__TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/lib/getid3/getid3.php'),
            'ExifTool 库' => self::checkExifTool(),
            'EXIF 扩展' => extension_loaded('exif'),
            'Fileinfo 扩展' => extension_loaded('fileinfo'),
            'GD 库' => extension_loaded('gd'),
            'ImageMagick' => extension_loaded('imagick'),
            'FFmpeg' => self::checkFFmpeg()
        ];
    }
    
    /**
     * 检查 ExifTool 是否可用
     * 
     * @return bool ExifTool是否可用
     */
    public static function checkExifTool()
    {
        $exiftoolPath = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/lib/PHPExiftool/Exiftool.php';
        if (!file_exists($exiftoolPath)) {
            return false;
        }
        
        try {
            // 检查系统是否安装了 exiftool 命令行工具
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
                    // 找到可用的 exiftool，检查 PHP 库
                    $content = file_get_contents($exiftoolPath);
                    if (strpos($content, 'class') !== false && strpos($content, 'Exiftool') !== false) {
                        return true;
                    }
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 检查 FFmpeg 是否可用
     * 
     * @return bool FFmpeg是否可用
     */
    public static function checkFFmpeg()
    {
        if (function_exists('exec')) {
            $output = [];
            $return_var = 0;
            @exec('ffmpeg -version 2>&1', $output, $return_var);
            return $return_var === 0 && !empty($output);
        }
        return false;
    }
}
