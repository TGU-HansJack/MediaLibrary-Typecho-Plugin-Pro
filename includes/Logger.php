<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 简单的日志工具，负责记录插件处理过程
 */
class MediaLibrary_Logger
{
    /**
     * 获取日志目录
     *
     * @return string
     */
    private static function getLogDir()
    {
        $dir = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * 获取日志文件路径
     *
     * @return string
     */
    public static function getLogFile()
    {
        $file = self::getLogDir() . '/medialibrary.log';
        if (!file_exists($file)) {
            @touch($file);
        }
        return $file;
    }

    /**
     * 记录日志
     *
     * @param string $action 操作名称
     * @param string $message 描述信息
     * @param array $context 附加数据
     * @param string $level 日志级别
     * @return void
     */
    public static function log($action, $message, array $context = [], $level = 'info')
    {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'action' => $action,
            'message' => $message,
            'context' => $context,
            'ip' => self::getClientIp(),
            'user' => self::getCurrentUser()
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE);
        @file_put_contents(self::getLogFile(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * 获取最近的日志
     *
     * @param int $limit 获取条数
     * @return array
     */
    public static function getLogs($limit = 200)
    {
        $file = self::getLogFile();
        if (!file_exists($file)) {
            return [];
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return [];
        }

        $lines = array_slice($lines, -abs(intval($limit)));
        $entries = [];

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return array_reverse($entries);
    }

    /**
     * 清空日志
     *
     * @return void
     */
    public static function clear()
    {
        @file_put_contents(self::getLogFile(), '');
    }

    /**
     * 获取客户端 IP
     *
     * @return string|null
     */
    private static function getClientIp()
    {
        $server = isset($_SERVER) ? $_SERVER : [];
        if (!empty($server['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $server['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        if (!empty($server['REMOTE_ADDR'])) {
            return $server['REMOTE_ADDR'];
        }
        return null;
    }

    /**
     * 尝试获取当前登录用户
     *
     * @return array|null
     */
    private static function getCurrentUser()
    {
        try {
            if (class_exists('Typecho_Widget')) {
                $user = Typecho_Widget::widget('Widget_User');
                if ($user && $user->hasLogin()) {
                    return [
                        'uid' => $user->uid,
                        'name' => $user->name,
                        'screenName' => $user->screenName,
                        'group' => $user->group
                    ];
                }
            }
        } catch (Exception $e) {
            // 忽略用户信息错误
        }

        return null;
    }
}
