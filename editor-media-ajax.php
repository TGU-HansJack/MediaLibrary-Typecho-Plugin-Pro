<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/FileOperations.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/PanelHelper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Typecho_Db::get();

    $page = max(1, intval(isset($_GET['page']) ? $_GET['page'] : 1));
    $perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
    $perPage = max(5, min(60, $perPage));
    $keywords = trim(isset($_GET['keywords']) ? (string)$_GET['keywords'] : '');
    $type = isset($_GET['type']) ? strtolower($_GET['type']) : 'all';
    $storage = isset($_GET['storage']) ? strtolower($_GET['storage']) : 'all';

    $mediaData = MediaLibrary_PanelHelper::getMediaList($db, $page, $perPage, $keywords, $type, $storage);

    if (isset($mediaData['error']) && $mediaData['error']) {
        throw new Exception($mediaData['error']);
    }

    $attachments = isset($mediaData['attachments']) && is_array($mediaData['attachments'])
        ? $mediaData['attachments']
        : array();
    $total = isset($mediaData['total']) ? intval($mediaData['total']) : count($attachments);

    $items = array();
    foreach ($attachments as $attachment) {
        $attachmentData = isset($attachment['attachment']) && is_array($attachment['attachment'])
            ? $attachment['attachment']
            : array();

        $cid = isset($attachment['cid']) ? intval($attachment['cid']) : 0;
        $path = isset($attachmentData['path']) ? (string)$attachmentData['path'] : '';
        $url = isset($attachment['url']) ? (string)$attachment['url'] : '';
        $hasValidUrl = isset($attachment['hasValidUrl'])
            ? (bool)$attachment['hasValidUrl']
            : (!empty($url));
        $storageType = isset($attachmentData['storage']) ? strtolower($attachmentData['storage']) : 'local';
        $filename = isset($attachmentData['name']) && $attachmentData['name'] !== ''
            ? (string)$attachmentData['name']
            : ($path ? basename($path) : '');
        $title = isset($attachment['title']) && $attachment['title'] !== ''
            ? (string)$attachment['title']
            : ($filename !== '' ? $filename : '未命名文件');
        $sizeBytes = isset($attachmentData['size']) ? intval($attachmentData['size']) : 0;
        $sizeLabel = isset($attachment['size']) ? (string)$attachment['size']
            : MediaLibrary_FileOperations::formatFileSize($sizeBytes);

        $mime = isset($attachment['mime']) && $attachment['mime'] !== ''
            ? (string)$attachment['mime']
            : (isset($attachmentData['mime']) ? (string)$attachmentData['mime'] : 'application/octet-stream');
        if ($mime === '' || $mime === 'application/octet-stream') {
            $guessedMime = MediaLibrary_FileOperations::guessMimeType($filename ?: $title);
            if ($guessedMime) {
                $mime = $guessedMime;
            }
        }

        $extension = strtolower(pathinfo($filename ?: $title, PATHINFO_EXTENSION));
        if ($extension === '' && strpos($mime, '/') !== false) {
            $extension = substr($mime, strpos($mime, '/') + 1);
        }

        $isImage = isset($attachment['isImage'])
            ? (bool)$attachment['isImage']
            : (strpos($mime, 'image/') === 0 || $extension === 'avif');
        $isVideo = isset($attachment['isVideo'])
            ? (bool)$attachment['isVideo']
            : (strpos($mime, 'video/') === 0);
        $isAudio = strpos($mime, 'audio/') === 0;
        $isDocument = isset($attachment['isDocument'])
            ? (bool)$attachment['isDocument']
            : (
                strpos($mime, 'application/pdf') === 0 ||
                strpos($mime, 'application/msword') === 0 ||
                strpos($mime, 'application/vnd.openxmlformats-officedocument') === 0 ||
                strpos($mime, 'application/vnd.ms-powerpoint') === 0 ||
                strpos($mime, 'application/vnd.ms-excel') === 0
            );

        $webdavPath = '';
        if (isset($attachment['webdav_path'])) {
            $webdavPath = (string)$attachment['webdav_path'];
        } elseif (isset($attachmentData['webdav_path'])) {
            $webdavPath = (string)$attachmentData['webdav_path'];
        }

        $objectStoragePath = isset($attachmentData['object_storage_path'])
            ? (string)$attachmentData['object_storage_path']
            : '';
        $objectStorageUrl = isset($attachmentData['object_storage_url'])
            ? (string)$attachmentData['object_storage_url']
            : '';

        $parentPost = isset($attachment['parent_post']) && is_array($attachment['parent_post'])
            ? $attachment['parent_post']
            : array('status' => 'unknown', 'post' => null);
        $parentPostData = isset($parentPost['post']) && is_array($parentPost['post'])
            ? $parentPost['post']
            : array();

        $items[] = array(
            'cid' => $cid,
            'title' => $title,
            'filename' => $filename ?: $title,
            'url' => $url,
            'thumbnail' => ($isImage && $hasValidUrl) ? $url : '',
            'path' => $path,
            'mime' => $mime ?: 'application/octet-stream',
            'extension' => $extension,
            'is_image' => $isImage,
            'is_video' => $isVideo,
            'is_audio' => $isAudio,
            'is_document' => $isDocument,
            'size' => $sizeLabel,
            'size_bytes' => $sizeBytes,
            'storage' => $storageType,
            'storage_label' => $storageType === 'webdav'
                ? 'WebDAV'
                : ($storageType === 'object_storage' ? '对象存储' : '本地存储'),
            'webdav_file' => !empty($attachment['webdav_file']),
            'webdav_path' => $webdavPath,
            'object_storage_path' => $objectStoragePath,
            'object_storage_url' => $objectStorageUrl,
            'has_url' => $hasValidUrl && $url !== '',
            'has_local_backup' => !empty($attachmentData['has_local_backup']),
            'created' => isset($attachment['created']) ? intval($attachment['created']) : 0,
            'created_label' => isset($attachment['created']) && $attachment['created']
                ? date('Y-m-d H:i', intval($attachment['created']))
                : '',
            'parent_post' => array(
                'status' => isset($parentPost['status']) ? $parentPost['status'] : 'unknown',
                'title' => isset($parentPostData['title']) ? $parentPostData['title'] : '',
                'cid' => isset($parentPostData['cid']) ? intval($parentPostData['cid']) : 0,
                'type' => isset($parentPostData['type']) ? $parentPostData['type'] : ''
            ),
            'can_copy' => $hasValidUrl && $url !== ''
        );
    }

    $pageCount = $perPage > 0 ? (int)ceil($total / $perPage) : 0;
    $hasMore = ($page * $perPage) < $total;

    echo json_encode(array(
        'success' => true,
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'page_count' => $pageCount,
        'has_more' => $hasMore,
        'next_page' => $hasMore ? $page + 1 : null,
        'filters' => array(
            'keywords' => $keywords,
            'type' => $type,
            'storage' => $storage
        ),
        'items' => $items
    ), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'message' => $e->getMessage()
    ), JSON_UNESCAPED_UNICODE);
}
