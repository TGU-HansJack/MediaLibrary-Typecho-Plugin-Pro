<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __DIR__ . '/WebDAVServer.php';

use MediaLibrary\WebDAV\AbstractStorage;
use MediaLibrary\WebDAV\Exception as WebDAV_Exception;
use MediaLibrary\WebDAV\Server;

/**
 * 媒体库 WebDAV Storage 实现
 * 将 Typecho 的附件系统映射为 WebDAV 文件系统
 */
class MediaLibrary_WebDAVStorage extends AbstractStorage
{
    private $db;
    private $prefix;
    private $uploadDir;
    private $siteUrl;

    public function __construct()
    {
        $this->db = Typecho_Db::get();
        $this->prefix = $this->db->getPrefix();

        $options = Typecho_Widget::widget('Widget_Options');
        $this->uploadDir = defined('__TYPECHO_UPLOADS_DIR__')
            ? __TYPECHO_UPLOADS_DIR__
            : __TYPECHO_ROOT_DIR__ . '/usr/uploads';
        $this->siteUrl = $options->siteUrl;
    }

    /**
     * 检查用户权限
     */
    public function auth(): bool
    {
        // 检查用户是否已登录且有足够权限
        $user = Typecho_Widget::widget('Widget_User');
        try {
            $user->pass('contributor');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 解析路径 - 支持扁平结构和按日期组织的结构
     * 格式: / 或 /filename 或 /YYYY/MM/filename
     */
    private function parsePath(string $uri)
    {
        $uri = trim($uri, '/');

        if (empty($uri)) {
            return ['type' => 'root', 'path' => ''];
        }

        $parts = explode('/', $uri);

        // 检查是否是年份目录 (4位数字)
        if (count($parts) >= 1 && preg_match('/^\d{4}$/', $parts[0])) {
            if (count($parts) == 1) {
                return ['type' => 'year', 'year' => $parts[0]];
            } elseif (count($parts) == 2 && preg_match('/^\d{2}$/', $parts[1])) {
                return ['type' => 'month', 'year' => $parts[0], 'month' => $parts[1]];
            } elseif (count($parts) >= 2) {
                // /YYYY/MM/filename or /YYYY/filename
                return ['type' => 'file', 'path' => $uri];
            }
        }

        // 直接文件名
        return ['type' => 'file', 'path' => $uri];
    }

    /**
     * 从数据库获取附件记录
     */
    private function getAttachmentByPath(string $path)
    {
        $select = $this->db->select()
            ->from('table.contents')
            ->where('type = ?', 'attachment')
            ->where('text LIKE ?', '%' . $path . '%')
            ->limit(1);

        return $this->db->fetchRow($select);
    }

    /**
     * 列出目录内容
     */
    public function list(string $uri, ?array $properties): iterable
    {
        $parsed = $this->parsePath($uri);
        $items = [];

        if ($parsed['type'] === 'root') {
            // 列出所有年份目录和根目录文件
            $select = $this->db->select('DISTINCT YEAR(FROM_UNIXTIME(created)) as year')
                ->from('table.contents')
                ->where('type = ?', 'attachment')
                ->order('year', Typecho_Db::SORT_DESC);

            $years = $this->db->fetchAll($select);
            foreach ($years as $year) {
                if ($year['year']) {
                    $items[$year['year']] = null;
                }
            }

            // 列出根目录的文件（没有按日期组织的）
            $select = $this->db->select()
                ->from('table.contents')
                ->where('type = ?', 'attachment')
                ->order('created', Typecho_Db::SORT_DESC);

            $attachments = $this->db->fetchAll($select);
            foreach ($attachments as $attachment) {
                // 提取文件名
                if (preg_match('/\/([^\/]+)$/', $attachment['text'], $matches)) {
                    $filename = $matches[1];
                    // 如果文件名不包含年月路径，添加到根目录
                    if (!preg_match('/^\d{4}\/\d{2}/', $attachment['text'])) {
                        $items[$filename] = null;
                    }
                }
            }

        } elseif ($parsed['type'] === 'year') {
            // 列出指定年份的月份目录
            $select = $this->db->select('DISTINCT MONTH(FROM_UNIXTIME(created)) as month')
                ->from('table.contents')
                ->where('type = ?', 'attachment')
                ->where('YEAR(FROM_UNIXTIME(created)) = ?', $parsed['year'])
                ->order('month', Typecho_Db::SORT_DESC);

            $months = $this->db->fetchAll($select);
            foreach ($months as $month) {
                if ($month['month']) {
                    $items[sprintf('%02d', $month['month'])] = null;
                }
            }

        } elseif ($parsed['type'] === 'month') {
            // 列出指定年月的文件
            $year = $parsed['year'];
            $month = $parsed['month'];

            $select = $this->db->select()
                ->from('table.contents')
                ->where('type = ?', 'attachment')
                ->where('YEAR(FROM_UNIXTIME(created)) = ?', $year)
                ->where('MONTH(FROM_UNIXTIME(created)) = ?', intval($month))
                ->order('created', Typecho_Db::SORT_DESC);

            $attachments = $this->db->fetchAll($select);
            foreach ($attachments as $attachment) {
                if (preg_match('/\/([^\/]+)$/', $attachment['text'], $matches)) {
                    $filename = $matches[1];
                    $items[$filename] = null;
                }
            }
        }

        return $items;
    }

    /**
     * 获取文件信息
     */
    public function get(string $uri): ?array
    {
        $parsed = $this->parsePath($uri);

        if ($parsed['type'] !== 'file') {
            return null;
        }

        $attachment = $this->getAttachmentByPath($parsed['path']);

        if (!$attachment) {
            return null;
        }

        // 构建完整文件路径
        $filePath = $this->uploadDir . '/' . $attachment['text'];

        if (!file_exists($filePath)) {
            return null;
        }

        return ['path' => $filePath];
    }

    /**
     * 检查文件/目录是否存在
     */
    public function exists(string $uri): bool
    {
        $parsed = $this->parsePath($uri);

        if ($parsed['type'] === 'root') {
            return true;
        }

        if ($parsed['type'] === 'year' || $parsed['type'] === 'month') {
            // 检查是否有该年份或年月的附件
            $select = $this->db->select('COUNT(*) as count')
                ->from('table.contents')
                ->where('type = ?', 'attachment');

            if ($parsed['type'] === 'year') {
                $select->where('YEAR(FROM_UNIXTIME(created)) = ?', $parsed['year']);
            } else {
                $select->where('YEAR(FROM_UNIXTIME(created)) = ?', $parsed['year'])
                       ->where('MONTH(FROM_UNIXTIME(created)) = ?', intval($parsed['month']));
            }

            $result = $this->db->fetchRow($select);
            return $result['count'] > 0;
        }

        // 文件类型
        $attachment = $this->getAttachmentByPath($parsed['path']);
        return $attachment !== false;
    }

    /**
     * 获取文件/目录属性
     */
    public function get_file_property(string $uri, string $name, int $depth)
    {
        $parsed = $this->parsePath($uri);

        switch ($name) {
            case 'DAV::displayname':
                if ($parsed['type'] === 'root') {
                    return 'Media Library';
                } elseif ($parsed['type'] === 'year') {
                    return $parsed['year'];
                } elseif ($parsed['type'] === 'month') {
                    return $parsed['month'];
                } else {
                    return basename($uri);
                }

            case 'DAV::resourcetype':
                if ($parsed['type'] === 'file') {
                    return '';
                }
                return 'collection';

            case 'DAV::getcontentlength':
                if ($parsed['type'] !== 'file') {
                    return null;
                }

                $attachment = $this->getAttachmentByPath($parsed['path']);
                if (!$attachment) {
                    return null;
                }

                $filePath = $this->uploadDir . '/' . $attachment['text'];
                return file_exists($filePath) ? filesize($filePath) : null;

            case 'DAV::getcontenttype':
                if ($parsed['type'] !== 'file') {
                    return null;
                }

                $attachment = $this->getAttachmentByPath($parsed['path']);
                if (!$attachment) {
                    return null;
                }

                $filePath = $this->uploadDir . '/' . $attachment['text'];
                return file_exists($filePath) ? mime_content_type($filePath) : 'application/octet-stream';

            case 'DAV::getlastmodified':
                if ($parsed['type'] !== 'file') {
                    return null;
                }

                $attachment = $this->getAttachmentByPath($parsed['path']);
                if (!$attachment) {
                    return null;
                }

                return new \DateTime('@' . $attachment['modified']);

            case 'DAV::getetag':
                if ($parsed['type'] !== 'file') {
                    return null;
                }

                $attachment = $this->getAttachmentByPath($parsed['path']);
                if (!$attachment) {
                    return null;
                }

                return md5($attachment['cid'] . $attachment['modified']);

            default:
                return null;
        }
    }

    /**
     * PROPFIND 实现
     */
    public function propfind(string $uri, ?array $properties, int $depth): ?array
    {
        if (!$this->exists($uri)) {
            return null;
        }

        if (null === $properties) {
            $properties = Server::BASIC_PROPERTIES;
        }

        $out = [];

        foreach ($properties as $name) {
            $v = $this->get_file_property($uri, $name, $depth);

            if (null !== $v) {
                $out[$name] = $v;
            }
        }

        return $out;
    }

    /**
     * 上传文件
     */
    public function put(string $uri, $pointer, ?string $hash_algo, ?string $hash): bool
    {
        if (!$this->auth()) {
            throw new WebDAV_Exception('Access forbidden', 403);
        }

        $parsed = $this->parsePath($uri);

        if ($parsed['type'] !== 'file') {
            throw new WebDAV_Exception('Cannot upload to a directory', 409);
        }

        // 生成文件路径 (按当前日期组织)
        $date = new DateTime();
        $datePath = $date->format('Y/m');
        $filename = basename($uri);

        $targetDir = $this->uploadDir . '/' . $datePath;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $filename;
        $relativePath = $datePath . '/' . $filename;

        // 写入文件
        $out = fopen($targetPath, 'w');
        while (!feof($pointer)) {
            $bytes = fread($pointer, 8192);
            fwrite($out, $bytes);
        }
        fclose($out);
        fclose($pointer);

        // 验证哈希
        if ($hash && $hash_algo == 'MD5' && md5_file($targetPath) != $hash) {
            @unlink($targetPath);
            throw new WebDAV_Exception('The data sent does not match the supplied MD5 hash', 400);
        }

        // 添加到数据库
        $attachment = $this->db->fetchRow($this->db->select()
            ->from('table.contents')
            ->where('type = ?', 'attachment')
            ->where('text LIKE ?', '%' . $filename . '%')
            ->limit(1));

        $user = Typecho_Widget::widget('Widget_User');

        if (!$attachment) {
            // 新文件
            $this->db->query($this->db->insert('table.contents')->rows([
                'title' => $filename,
                'slug' => $filename,
                'created' => time(),
                'modified' => time(),
                'text' => $relativePath,
                'authorId' => $user->uid,
                'type' => 'attachment',
                'parent' => 0,
                'status' => 'publish',
                'allowComment' => 0,
                'allowPing' => 0,
                'allowFeed' => 0,
            ]));

            return true;
        } else {
            // 更新现有文件
            $this->db->query($this->db->update('table.contents')
                ->rows(['modified' => time()])
                ->where('cid = ?', $attachment['cid']));

            return false;
        }
    }

    /**
     * 删除文件
     */
    public function delete(string $uri): void
    {
        if (!$this->auth()) {
            throw new WebDAV_Exception('Access forbidden', 403);
        }

        $parsed = $this->parsePath($uri);

        if ($parsed['type'] === 'root') {
            throw new WebDAV_Exception('Cannot delete root directory', 403);
        }

        if ($parsed['type'] === 'year' || $parsed['type'] === 'month') {
            throw new WebDAV_Exception('Cannot delete date directories', 403);
        }

        $attachment = $this->getAttachmentByPath($parsed['path']);

        if (!$attachment) {
            throw new WebDAV_Exception('File not found', 404);
        }

        // 删除物理文件
        $filePath = $this->uploadDir . '/' . $attachment['text'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        // 从数据库删除
        $this->db->query($this->db->delete('table.contents')
            ->where('cid = ?', $attachment['cid']));
    }

    /**
     * 复制文件
     */
    public function copy(string $uri, string $destination): bool
    {
        if (!$this->auth()) {
            throw new WebDAV_Exception('Access forbidden', 403);
        }

        $srcParsed = $this->parsePath($uri);

        if ($srcParsed['type'] !== 'file') {
            throw new WebDAV_Exception('Can only copy files', 403);
        }

        $attachment = $this->getAttachmentByPath($srcParsed['path']);

        if (!$attachment) {
            throw new WebDAV_Exception('Source file not found', 404);
        }

        $srcPath = $this->uploadDir . '/' . $attachment['text'];

        // 使用 PUT 来处理复制
        $fp = fopen($srcPath, 'r');
        $result = $this->put($destination, $fp, null, null);

        return $result;
    }

    /**
     * 移动文件
     */
    public function move(string $uri, string $destination): bool
    {
        if (!$this->auth()) {
            throw new WebDAV_Exception('Access forbidden', 403);
        }

        // 先复制，再删除
        $overwritten = $this->copy($uri, $destination);
        $this->delete($uri);

        return $overwritten;
    }

    /**
     * 创建目录 (不支持，因为目录是虚拟的)
     */
    public function mkcol(string $uri): void
    {
        throw new WebDAV_Exception('Creating directories is not supported', 405);
    }

    /**
     * 修改时间戳
     */
    public function touch(string $uri, \DateTimeInterface $datetime): bool
    {
        if (!$this->auth()) {
            return false;
        }

        $parsed = $this->parsePath($uri);

        if ($parsed['type'] !== 'file') {
            return false;
        }

        $attachment = $this->getAttachmentByPath($parsed['path']);

        if (!$attachment) {
            return false;
        }

        $this->db->query($this->db->update('table.contents')
            ->rows(['modified' => $datetime->getTimestamp()])
            ->where('cid = ?', $attachment['cid']));

        return true;
    }
}
