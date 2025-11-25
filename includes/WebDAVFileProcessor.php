<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/ImageProcessing.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/ExifPrivacy.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/Logger.php';

/**
 * WebDAV 文件处理类
 * 提供对 WebDAV 文件夹中文件的直接处理功能，不经过数据库
 */
class MediaLibrary_WebDAVFileProcessor
{
    private $webdavLocalPath;
    private $configOptions;

    /**
     * 构造函数
     *
     * @param array $configOptions 配置选项
     */
    public function __construct($configOptions)
    {
        $this->configOptions = $configOptions;
        $this->webdavLocalPath = rtrim($configOptions['webdavLocalPath'], '/\\');

        if (!is_dir($this->webdavLocalPath)) {
            throw new Exception('WebDAV 本地文件夹不存在');
        }
    }

    /**
     * 获取文件的完整路径
     *
     * @param string $relativePath 相对路径
     * @return string 完整路径
     */
    private function getFullPath($relativePath)
    {
        // 清理路径，防止目录遍历攻击
        $relativePath = str_replace(['../', '..\\'], '', $relativePath);
        $relativePath = ltrim($relativePath, '/\\');

        $fullPath = $this->webdavLocalPath . DIRECTORY_SEPARATOR .
                    str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        // 验证路径安全性
        $realPath = realpath($fullPath);
        $realWebdavPath = realpath($this->webdavLocalPath);

        if (!$realPath || strpos($realPath, $realWebdavPath) !== 0) {
            throw new Exception('非法的文件路径');
        }

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            throw new Exception('文件不存在: ' . $relativePath);
        }

        return $fullPath;
    }

    /**
     * 压缩图片
     *
     * @param string $relativePath 相对路径
     * @param array $options 压缩选项
     * @return array 处理结果
     */
    public function compressImage($relativePath, $options = [])
    {
        $filePath = $this->getFullPath($relativePath);

        MediaLibrary_Logger::log('webdav_compress', '开始压缩 WebDAV 图片', [
            'file' => $relativePath,
            'options' => $options
        ]);

        $quality = isset($options['quality']) ? intval($options['quality']) : 80;
        $outputFormat = isset($options['output_format']) ? $options['output_format'] : 'original';
        $compressMethod = isset($options['compress_method']) ? $options['compress_method'] : 'gd';
        $replaceOriginal = isset($options['replace_original']) && $options['replace_original'];
        $customName = isset($options['custom_name']) ? $options['custom_name'] : '';

        $originalSize = filesize($filePath);

        try {
            // 确定输出路径
            if ($replaceOriginal) {
                $outputPath = $filePath;
            } else {
                $pathInfo = pathinfo($filePath);
                $suffix = !empty($customName) ? $customName : '_compressed';
                $outputPath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR .
                             $pathInfo['filename'] . $suffix;

                // 根据输出格式决定扩展名
                if ($outputFormat !== 'original') {
                    $outputPath .= '.' . $outputFormat;
                } else {
                    $outputPath .= '.' . $pathInfo['extension'];
                }
            }

            // 执行压缩
            $result = MediaLibrary_ImageProcessing::compressImage(
                $filePath,
                $outputPath,
                $quality,
                $compressMethod,
                $outputFormat
            );

            if (!$result['success']) {
                throw new Exception($result['message']);
            }

            $compressedSize = filesize($outputPath);
            $savings = round((1 - $compressedSize / $originalSize) * 100, 2);

            MediaLibrary_Logger::log('webdav_compress', 'WebDAV 图片压缩成功', [
                'file' => $relativePath,
                'original_size' => $originalSize,
                'compressed_size' => $compressedSize,
                'savings' => $savings . '%'
            ]);

            return [
                'success' => true,
                'message' => '压缩成功',
                'original_size' => $this->formatFileSize($originalSize),
                'compressed_size' => $this->formatFileSize($compressedSize),
                'savings' => $savings . '%',
                'method' => $compressMethod,
                'format' => $outputFormat,
                'output_file' => $replaceOriginal ? $relativePath : basename($outputPath)
            ];

        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_compress', 'WebDAV 图片压缩失败: ' . $e->getMessage(), [
                'file' => $relativePath
            ], 'error');

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 裁剪图片
     *
     * @param string $relativePath 相对路径
     * @param array $options 裁剪选项
     * @return array 处理结果
     */
    public function cropImage($relativePath, $options = [])
    {
        $filePath = $this->getFullPath($relativePath);

        MediaLibrary_Logger::log('webdav_crop', '开始裁剪 WebDAV 图片', [
            'file' => $relativePath,
            'options' => $options
        ]);

        $x = isset($options['x']) ? intval($options['x']) : 0;
        $y = isset($options['y']) ? intval($options['y']) : 0;
        $width = isset($options['width']) ? intval($options['width']) : 0;
        $height = isset($options['height']) ? intval($options['height']) : 0;
        $replaceOriginal = isset($options['replace_original']) && $options['replace_original'];

        if ($width <= 0 || $height <= 0) {
            return [
                'success' => false,
                'message' => '裁剪尺寸无效'
            ];
        }

        try {
            // 确定输出路径
            if ($replaceOriginal) {
                $outputPath = $filePath;
            } else {
                $pathInfo = pathinfo($filePath);
                $outputPath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR .
                             $pathInfo['filename'] . '_cropped.' . $pathInfo['extension'];
            }

            // 执行裁剪
            $result = MediaLibrary_ImageProcessing::cropImage(
                $filePath,
                $outputPath,
                $x,
                $y,
                $width,
                $height,
                $this->configOptions['enableGD'],
                $this->configOptions['enableImageMagick']
            );

            if (!$result['success']) {
                throw new Exception($result['message']);
            }

            MediaLibrary_Logger::log('webdav_crop', 'WebDAV 图片裁剪成功', [
                'file' => $relativePath,
                'dimensions' => "{$width}x{$height}"
            ]);

            return [
                'success' => true,
                'message' => '裁剪成功',
                'output_file' => $replaceOriginal ? $relativePath : basename($outputPath),
                'dimensions' => "{$width}x{$height}"
            ];

        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_crop', 'WebDAV 图片裁剪失败: ' . $e->getMessage(), [
                'file' => $relativePath
            ], 'error');

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 添加水印
     *
     * @param string $relativePath 相对路径
     * @param array $options 水印选项
     * @return array 处理结果
     */
    public function addWatermark($relativePath, $options = [])
    {
        $filePath = $this->getFullPath($relativePath);

        MediaLibrary_Logger::log('webdav_watermark', '开始为 WebDAV 图片添加水印', [
            'file' => $relativePath,
            'options' => $options
        ]);

        $text = isset($options['text']) ? $options['text'] : '';
        $position = isset($options['position']) ? $options['position'] : 'bottom-right';
        $opacity = isset($options['opacity']) ? intval($options['opacity']) : 50;
        $replaceOriginal = isset($options['replace_original']) && $options['replace_original'];

        if (empty($text)) {
            return [
                'success' => false,
                'message' => '水印文字不能为空'
            ];
        }

        try {
            // 确定输出路径
            if ($replaceOriginal) {
                $outputPath = $filePath;
            } else {
                $pathInfo = pathinfo($filePath);
                $outputPath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR .
                             $pathInfo['filename'] . '_watermark.' . $pathInfo['extension'];
            }

            // 执行添加水印
            $result = MediaLibrary_ImageProcessing::addWatermark(
                $filePath,
                $outputPath,
                $text,
                $position,
                $opacity,
                $this->configOptions['enableGD'],
                $this->configOptions['enableImageMagick']
            );

            if (!$result['success']) {
                throw new Exception($result['message']);
            }

            MediaLibrary_Logger::log('webdav_watermark', 'WebDAV 图片水印添加成功', [
                'file' => $relativePath
            ]);

            return [
                'success' => true,
                'message' => '水印添加成功',
                'output_file' => $replaceOriginal ? $relativePath : basename($outputPath)
            ];

        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_watermark', 'WebDAV 图片水印添加失败: ' . $e->getMessage(), [
                'file' => $relativePath
            ], 'error');

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 检测隐私信息
     *
     * @param string $relativePath 相对路径
     * @return array 检测结果
     */
    public function checkPrivacy($relativePath)
    {
        $filePath = $this->getFullPath($relativePath);

        MediaLibrary_Logger::log('webdav_privacy', '开始检测 WebDAV 图片隐私信息', [
            'file' => $relativePath
        ]);

        try {
            $result = MediaLibrary_ExifPrivacy::checkPrivacy($filePath);

            MediaLibrary_Logger::log('webdav_privacy', 'WebDAV 图片隐私检测完成', [
                'file' => $relativePath,
                'has_privacy' => $result['has_privacy']
            ]);

            $result['filename'] = basename($relativePath);
            $result['file_path'] = $relativePath;

            return $result;

        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_privacy', 'WebDAV 图片隐私检测失败: ' . $e->getMessage(), [
                'file' => $relativePath
            ], 'error');

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'filename' => basename($relativePath)
            ];
        }
    }

    /**
     * 清除 EXIF 信息
     *
     * @param string $relativePath 相对路径
     * @return array 处理结果
     */
    public function removeExif($relativePath)
    {
        $filePath = $this->getFullPath($relativePath);

        MediaLibrary_Logger::log('webdav_exif_remove', '开始清除 WebDAV 图片 EXIF', [
            'file' => $relativePath
        ]);

        try {
            $result = MediaLibrary_ExifPrivacy::removeExif($filePath);

            MediaLibrary_Logger::log('webdav_exif_remove', 'WebDAV 图片 EXIF 清除成功', [
                'file' => $relativePath
            ]);

            return $result;

        } catch (Exception $e) {
            MediaLibrary_Logger::log('webdav_exif_remove', 'WebDAV 图片 EXIF 清除失败: ' . $e->getMessage(), [
                'file' => $relativePath
            ], 'error');

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 格式化文件大小
     *
     * @param int $bytes 字节数
     * @return string 格式化后的大小
     */
    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }

    /**
     * 批量处理图片
     *
     * @param array $files 文件列表
     * @param string $operation 操作类型
     * @param array $options 选项
     * @return array 处理结果
     */
    public function batchProcess($files, $operation, $options = [])
    {
        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($files as $file) {
            try {
                switch ($operation) {
                    case 'compress':
                        $result = $this->compressImage($file, $options);
                        break;
                    case 'crop':
                        $result = $this->cropImage($file, $options);
                        break;
                    case 'watermark':
                        $result = $this->addWatermark($file, $options);
                        break;
                    case 'privacy':
                        $result = $this->checkPrivacy($file);
                        break;
                    case 'remove_exif':
                        $result = $this->removeExif($file);
                        break;
                    default:
                        $result = [
                            'success' => false,
                            'message' => '未知的操作类型'
                        ];
                }

                $result['file'] = $file;
                $results[] = $result;

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }

            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'file' => $file,
                    'message' => $e->getMessage()
                ];
                $failCount++;
            }
        }

        return [
            'success' => $failCount === 0,
            'results' => $results,
            'summary' => [
                'total' => count($files),
                'success' => $successCount,
                'failed' => $failCount
            ]
        ];
    }
}
