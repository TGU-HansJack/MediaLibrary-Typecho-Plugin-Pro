<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 环境检查工具类
 */
class MediaLibrary_EnvironmentCheck
{
    /**
     * 插件版本号（当无法从 Plugin.php 读取时的默认值）
     */
    const PLUGIN_VERSION = 'unknown';

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
     * 获取详细的PHP扩展检测信息
     *
     * @return array PHP扩展检测结果
     */
    public static function checkPHPExtensions()
    {
        $extensions = [
            'gd' => ['name' => 'GD 库', 'description' => '图片处理库，用于裁剪、水印等功能', 'required' => true],
            'imagick' => ['name' => 'ImageMagick', 'description' => '高级图片处理库，支持更多格式', 'required' => false],
            'exif' => ['name' => 'EXIF 扩展', 'description' => '读取图片 EXIF 信息', 'required' => false],
            'fileinfo' => ['name' => 'Fileinfo 扩展', 'description' => '检测文件类型', 'required' => true],
            'mbstring' => ['name' => 'Mbstring 扩展', 'description' => '多字节字符串处理，支持中文', 'required' => true],
            'curl' => ['name' => 'cURL 扩展', 'description' => 'HTTP 请求库，用于检查更新', 'required' => false],
            'zip' => ['name' => 'Zip 扩展', 'description' => '压缩文件处理，用于插件更新', 'required' => false],
        ];

        $result = [];
        foreach ($extensions as $ext => $info) {
            $result[] = [
                'name' => $info['name'],
                'description' => $info['description'],
                'required' => $info['required'],
                'status' => extension_loaded($ext),
                'version' => extension_loaded($ext) ? phpversion($ext) : null
            ];
        }

        return $result;
    }

    /**
     * 获取详细的PHP函数检测信息
     *
     * @return array PHP函数检测结果
     */
    public static function checkPHPFunctions()
    {
        $functions = [
            'exec' => ['name' => 'exec()', 'description' => '执行外部命令，用于 FFmpeg/ExifTool', 'required' => false],
            'shell_exec' => ['name' => 'shell_exec()', 'description' => '执行 shell 命令', 'required' => false],
            'imagecreatefromjpeg' => ['name' => 'imagecreatefromjpeg()', 'description' => 'GD 库 JPEG 支持', 'required' => true],
            'imagecreatefrompng' => ['name' => 'imagecreatefrompng()', 'description' => 'GD 库 PNG 支持', 'required' => true],
            'imagecreatefromwebp' => ['name' => 'imagecreatefromwebp()', 'description' => 'GD 库 WebP 支持', 'required' => false],
            'imagettftext' => ['name' => 'imagettftext()', 'description' => 'GD 库 TrueType 字体支持，用于水印', 'required' => true],
            'exif_read_data' => ['name' => 'exif_read_data()', 'description' => '读取 EXIF 数据', 'required' => false],
            'file_get_contents' => ['name' => 'file_get_contents()', 'description' => '读取文件内容', 'required' => true],
            'file_put_contents' => ['name' => 'file_put_contents()', 'description' => '写入文件内容', 'required' => true],
        ];

        $result = [];
        foreach ($functions as $func => $info) {
            $result[] = [
                'name' => $info['name'],
                'description' => $info['description'],
                'required' => $info['required'],
                'status' => function_exists($func)
            ];
        }

        return $result;
    }

    /**
     * 检查文件完整性
     *
     * @return array 文件完整性检测结果
     */
    public static function checkFileIntegrity()
    {
        $pluginDir = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary';

        $requiredFiles = [
            '/Plugin.php' => '插件主文件',
            '/panel.php' => '管理面板文件',
            '/includes/AjaxHandler.php' => 'AJAX 处理器',
            '/includes/ImageEditor.php' => '图片编辑器',
            '/includes/PanelHelper.php' => '面板助手',
            '/includes/EnvironmentCheck.php' => '环境检测',
            '/assets/js/media-library.js' => '主要 JavaScript 文件',
            '/assets/js/image-editor.js' => '图片编辑器 JavaScript',
            '/assets/css/media-library.css' => '主样式文件',
        ];

        $result = [
            'total' => count($requiredFiles),
            'found' => 0,
            'missing' => [],
            'files' => []
        ];

        foreach ($requiredFiles as $file => $description) {
            $fullPath = $pluginDir . $file;
            $exists = file_exists($fullPath);

            if ($exists) {
                $result['found']++;
            } else {
                $result['missing'][] = $file;
            }

            $result['files'][] = [
                'path' => $file,
                'description' => $description,
                'exists' => $exists,
                'size' => $exists ? filesize($fullPath) : 0
            ];
        }

        return $result;
    }

    /**
     * 获取当前插件版本
     * 优先从 Plugin.php 的 docblock 中读取 @version 标签
     *
     * @return string 插件版本号
     */
    public static function getCurrentVersion()
    {
        // 尝试从 Plugin.php 的 docblock 中读取版本号
        $pluginFile = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/Plugin.php';

        if (file_exists($pluginFile)) {
            $content = @file_get_contents($pluginFile);
            if ($content !== false) {
                // 匹配 @version 标签（支持各种格式：数字、字母、点、下划线、中划线）
                if (preg_match('/@version\s+([\w\.\-_]+)/i', $content, $matches)) {
                    return trim($matches[1]);
                }
            }
        }

        // 如果无法读取文件或未找到 @version 标签，回退到常量定义的版本号
        return self::PLUGIN_VERSION;
    }

    /**
     * 获取PHP和服务器信息
     *
     * @return array 系统信息
     */
    public static function getSystemInfo()
    {
        $dbInfo = self::getDatabaseInfo();

        return [
            'PHP 版本' => PHP_VERSION,
            '操作系统' => PHP_OS,
            '服务器软件' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
            'Typecho 版本' => Typecho_Common::VERSION,
            '插件版本' => self::PLUGIN_VERSION,
            '数据库类型' => $dbInfo['type'],
            '数据库版本' => $dbInfo['version'],
            '内存限制' => ini_get('memory_limit'),
            '最大上传大小' => ini_get('upload_max_filesize'),
            'POST 最大大小' => ini_get('post_max_size'),
            '最大执行时间' => ini_get('max_execution_time') . '秒',
        ];
    }

    /**
     * 获取数据库信息
     *
     * @return array 数据库类型和版本
     */
    private static function getDatabaseInfo()
    {
        try {
            $db = Typecho_Db::get();
            $adapterName = $db->getAdapterName();

            // 获取数据库类型
            $dbType = 'Unknown';
            if (stripos($adapterName, 'Mysql') !== false || stripos($adapterName, 'Pdo_Mysql') !== false) {
                $dbType = 'MySQL';
            } elseif (stripos($adapterName, 'SQLite') !== false || stripos($adapterName, 'Pdo_SQLite') !== false) {
                $dbType = 'SQLite';
            } elseif (stripos($adapterName, 'Pgsql') !== false || stripos($adapterName, 'Pdo_Pgsql') !== false) {
                $dbType = 'PostgreSQL';
            }

            // 获取数据库版本
            $dbVersion = 'Unknown';
            try {
                if (stripos($adapterName, 'Mysql') !== false) {
                    $version = $db->fetchRow($db->select()->from('information_schema.tables')->where('1=0'));
                    // 如果上面的查询失败，尝试使用 VERSION() 函数
                    $result = $db->fetchRow($db->query("SELECT VERSION() as version"));
                    if ($result && isset($result['version'])) {
                        $dbVersion = $result['version'];
                    }
                } elseif (stripos($adapterName, 'SQLite') !== false) {
                    $result = $db->fetchRow($db->query("SELECT sqlite_version() as version"));
                    if ($result && isset($result['version'])) {
                        $dbVersion = $result['version'];
                    }
                } elseif (stripos($adapterName, 'Pgsql') !== false) {
                    $result = $db->fetchRow($db->query("SELECT version() as version"));
                    if ($result && isset($result['version'])) {
                        $dbVersion = $result['version'];
                    }
                }
            } catch (Exception $e) {
                // 如果查询失败，保持 Unknown
                $dbVersion = 'Unable to retrieve';
            }

            return [
                'type' => $dbType,
                'version' => $dbVersion
            ];

        } catch (Exception $e) {
            return [
                'type' => 'Unknown',
                'version' => 'Unknown'
            ];
        }
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
