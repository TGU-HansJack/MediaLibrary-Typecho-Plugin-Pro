<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 视频处理工具类
 */
class MediaLibrary_VideoProcessing
{
    /**
     * 压缩视频 - 纯文件夹模式
     *
     * @param string $cid 文件hash ID
     * @param int $quality 视频质量
     * @param string $codec 视频编码
     * @param bool $replaceOriginal 是否替换原文件
     * @param string $customName 自定义文件名
     * @param Typecho_Db $db 数据库连接
     * @param Typecho_Widget_Helper_Options $options 系统选项
     * @param Typecho_Widget_User $user 当前用户
     * @return array 操作结果
     */
    public static function compressVideo($cid, $quality, $codec, $replaceOriginal, $customName, $db, $options, $user)
    {
        // 通过hash找到文件
        $fileInfo = MediaLibrary_PanelHelper::getFileByHashId($cid);

        if (!$fileInfo) {
            return ['success' => false, 'message' => '文件不存在', 'cid' => $cid];
        }

        $originalPath = $fileInfo['full_path'];
        if (!file_exists($originalPath)) {
            return ['success' => false, 'message' => '原文件不存在', 'cid' => $cid];
        }

        // 检查是否为视频
        if (strpos($fileInfo['mime'], 'video/') !== 0) {
            return ['success' => false, 'message' => '只能压缩视频文件', 'cid' => $cid];
        }

        $pathInfo = pathinfo($originalPath);

        if ($replaceOriginal) {
            $compressedPath = $originalPath;
            $tempPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_temp.' . $pathInfo['extension'];
        } else {
            if (!empty($customName)) {
                $compressedPath = $pathInfo['dirname'] . '/' . $customName . '.' . $pathInfo['extension'];
            } else {
                $compressedPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_compressed.' . $pathInfo['extension'];
            }
            $tempPath = $compressedPath;
        }

        // 获取原始文件大小
        $originalSize = filesize($originalPath);

        // 使用FFmpeg压缩视频
        if (!function_exists('exec')) {
            return ['success' => false, 'message' => 'exec函数被禁用', 'cid' => $cid];
        }

        // 创建进度文件
        $progressFile = sys_get_temp_dir() . '/video_compress_' . $cid . '.log';

        $output = [];
        $return_var = 0;

        // 构建FFmpeg命令，添加进度输出
        $cmd = 'ffmpeg -i ' . escapeshellarg($originalPath) . ' -c:v ' . $codec . ' -crf ' . $quality . ' -c:a aac -b:a 128k -movflags +faststart -progress ' . escapeshellarg($progressFile) . ' ' . escapeshellarg($tempPath) . ' 2>&1';

        @exec($cmd, $output, $return_var);

        // 清理进度文件
        if (file_exists($progressFile)) {
            @unlink($progressFile);
        }

        if ($return_var !== 0 || !file_exists($tempPath)) {
            return ['success' => false, 'message' => 'FFmpeg压缩失败: ' . implode("\n", array_slice($output, -5)), 'cid' => $cid];
        }

        // 如果是替换原文件，需要移动临时文件
        if ($replaceOriginal) {
            if (!rename($tempPath, $originalPath)) {
                @unlink($tempPath);
                return ['success' => false, 'message' => '替换原文件失败', 'cid' => $cid];
            }
        }

        // 获取压缩后文件大小
        $compressedSize = filesize($compressedPath);

        // 计算节省的空间
        $savings = $originalSize > 0 ? round((1 - $compressedSize / $originalSize) * 100, 1) : 0;

        return [
            'success' => true,
            'message' => '视频压缩成功',
            'cid' => $cid,
            'original_size' => MediaLibrary_FileOperations::formatFileSize($originalSize),
            'compressed_size' => MediaLibrary_FileOperations::formatFileSize($compressedSize),
            'savings' => $savings . '%',
            'codec' => $codec,
            'quality' => $quality
        ];
    }
}
