<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/Logger.php';

/**
 * 文件操作工具类
 */
class MediaLibrary_FileOperations
{
    /**
     * 删除文件
     * 
     * @param array $cids 要删除的文件ID数组
     * @param Typecho_Db $db 数据库连接
     * @return array 操作结果
     */
    public static function deleteFiles($cids, $db)
    {
        $deleteCount = 0;
        foreach ($cids as $cid) {
            $attachment = $db->fetchRow($db->select()->from('table.contents')
                ->where('cid = ? AND type = ?', $cid, 'attachment'));
                
            if ($attachment) {
                $attachmentData = unserialize($attachment['text']);
                $filePath = __TYPECHO_ROOT_DIR__ . $attachmentData['path'];
                
                // 删除文件
                if (file_exists($filePath)) {
                    @unlink($filePath);
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
        return ['success' => true, 'message' => "成功删除 {$deleteCount} 个文件"];
    }
    
    /**
     * 获取文件信息
     * 
     * @param int $cid 文件ID
     * @param Typecho_Db $db 数据库连接
     * @param Typecho_Widget_Helper_Options $options 系统选项
     * @return array 文件信息
     */
    public static function getFileInfo($cid, $db, $options)
    {
        $attachment = $db->fetchRow($db->select()->from('table.contents')
            ->where('cid = ? AND type = ?', $cid, 'attachment'));
            
        if (!$attachment) {
            MediaLibrary_Logger::log('get_info', '获取文件信息失败：文件不存在', [
                'cid' => $cid
            ], 'error');
            return ['success' => false, 'message' => '文件不存在'];
        }
        
        $attachmentData = unserialize($attachment['text']);
        $info = [
            'title' => $attachment['title'],
            'mime' => $attachmentData['mime'],
            'size' => self::formatFileSize($attachmentData['size']),
            'url' => Typecho_Common::url($attachmentData['path'], $options->siteUrl),
            'created' => date('Y-m-d H:i:s', $attachment['created']),
            'path' => $attachmentData['path']
        ];
        
        MediaLibrary_Logger::log('get_info', '获取文件信息成功', [
            'cid' => $cid,
            'title' => $attachment['title']
        ]);
        return ['success' => true, 'data' => $info];
    }
    
    /**
     * 格式化文件大小
     * 
     * @param int $bytes 字节数
     * @return string 格式化后的大小
     */
    public static function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
