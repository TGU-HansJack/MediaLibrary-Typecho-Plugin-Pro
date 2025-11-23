<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 视频处理工具类
 */
class MediaLibrary_VideoProcessing
{
    /**
     * 压缩视频
     * 
     * @param int $cid 文件ID
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
        
        // 检查是否为视频
        if (strpos($attachmentData['mime'], 'video/') !== 0) {
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
        
        if (!$replaceOriginal) {
            // 添加到数据库
            $newAttachmentData = $attachmentData;
            $newAttachmentData['path'] = str_replace(__TYPECHO_ROOT_DIR__, '', $compressedPath);
            $newAttachmentData['size'] = $compressedSize;
            $newAttachmentData['name'] = basename($compressedPath);
            
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
        } else {
            // 替换原文件，更新数据库记录
            $attachmentData['size'] = $compressedSize;
            
            $db->query($db->update('table.contents')
                ->rows(['text' => serialize($attachmentData)])
                ->where('cid = ?', $cid));
        }
        
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
