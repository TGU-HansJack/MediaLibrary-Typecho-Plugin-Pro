<?php
/**
 * WebDAV 定时同步任务脚本
 *
 * 用法：
 * 1. 命令行: php /path/to/cron-webdav-sync.php
 * 2. Crontab: 0 * * * * /usr/bin/php /path/to/cron-webdav-sync.php >> /path/to/sync.log 2>&1
 * 3. URL 触发: curl "https://your-site.com/usr/plugins/MediaLibrary/cron-webdav-sync.php?key=YOUR_SECRET_KEY"
 *
 * @package MediaLibrary
 * @author HansJack
 */

// 如果通过 HTTP 访问，检查密钥
if (php_sapi_name() !== 'cli') {
    $secretKey = isset($_GET['key']) ? $_GET['key'] : '';
    $configKey = '';

    // 尝试从配置读取密钥
    if (file_exists(__DIR__ . '/../../config.inc.php')) {
        require_once __DIR__ . '/../../config.inc.php';
        if (defined('__TYPECHO_ROOT_DIR__')) {
            $db = Typecho_Db::get();
            try {
                $options = $db->fetchRow($db->select()->from('table.options')->where('name = ?', 'plugin:MediaLibrary'));
                if ($options) {
                    $config = unserialize($options['value']);
                    $configKey = isset($config['webdavCronKey']) ? $config['webdavCronKey'] : '';
                }
            } catch (Exception $e) {
                // 忽略错误
            }
        }
    }

    // 如果没有配置密钥或密钥不匹配，拒绝访问
    if (empty($configKey) || $secretKey !== $configKey) {
        header('HTTP/1.1 403 Forbidden');
        die('Access denied. Invalid or missing secret key.');
    }
}

// 定义根目录
if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__DIR__, 2));
}

// 加载 Typecho
require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 输出日志函数
function cronLog($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $output = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    echo $output;

    // 同时写入日志文件
    $logFile = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/logs/cron-sync.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logFile, $output, FILE_APPEND | LOCK_EX);
}

try {
    cronLog('==================== WebDAV 定时同步任务开始 ====================');

    // 获取数据库实例
    $db = Typecho_Db::get();

    // 读取插件配置
    cronLog('读取插件配置...');
    $options = $db->fetchRow($db->select()->from('table.options')->where('name = ?', 'plugin:MediaLibrary'));

    if (!$options) {
        cronLog('未找到插件配置，任务终止', 'ERROR');
        exit(1);
    }

    $config = unserialize($options['value']);

    // 检查是否启用 WebDAV
    if (empty($config['enableWebDAV'])) {
        cronLog('WebDAV 功能未启用，任务终止', 'WARN');
        exit(0);
    }

    // 检查是否启用定时同步
    if (empty($config['webdavSyncEnabled']) || $config['webdavSyncMode'] !== 'scheduled') {
        cronLog('定时同步模式未启用，任务终止', 'WARN');
        cronLog('当前同步模式: ' . ($config['webdavSyncMode'] ?? 'manual'));
        exit(0);
    }

    // 检查同步间隔（如果配置了）
    $syncInterval = isset($config['webdavSyncInterval']) ? (int)$config['webdavSyncInterval'] : 3600; // 默认1小时
    $lastSyncFile = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/logs/last-sync-time.txt';

    if (file_exists($lastSyncFile)) {
        $lastSyncTime = (int)file_get_contents($lastSyncFile);
        $timeSinceLastSync = time() - $lastSyncTime;

        if ($timeSinceLastSync < $syncInterval) {
            cronLog(sprintf(
                '距离上次同步仅 %d 秒，未达到同步间隔 %d 秒，跳过本次同步',
                $timeSinceLastSync,
                $syncInterval
            ), 'INFO');
            exit(0);
        }
    }

    cronLog('配置检查通过，开始同步...');
    cronLog('本地路径: ' . ($config['webdavLocalPath'] ?? '未配置'));
    cronLog('远程地址: ' . ($config['webdavEndpoint'] ?? '未配置'));

    // 加载 WebDAVSync 类
    require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVSync.php';
    require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVClient.php';
    require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/Logger.php';

    // 创建同步实例
    $sync = new MediaLibrary_WebDAVSync($config);

    // 记录同步开始时间
    $startTime = microtime(true);

    // 执行同步
    cronLog('开始批量同步所有文件...');
    $result = $sync->syncAllToRemote(function($current, $total, $file) {
        cronLog(sprintf('同步进度: [%d/%d] %s', $current, $total, $file));
    });

    // 记录同步结束时间
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    // 输出统计信息
    cronLog('==================== 同步完成 ====================');
    cronLog('总文件数: ' . $result['total']);
    cronLog('已同步: ' . $result['synced']);
    cronLog('已跳过: ' . $result['skipped']);
    cronLog('失败: ' . $result['failed']);
    cronLog('耗时: ' . $duration . ' 秒');

    // 记录错误
    if (!empty($result['errors'])) {
        cronLog('错误列表:', 'ERROR');
        foreach ($result['errors'] as $error) {
            cronLog('  - ' . $error, 'ERROR');
        }
    }

    // 更新最后同步时间
    file_put_contents($lastSyncFile, time());

    // 返回状态码
    exit($result['failed'] > 0 ? 1 : 0);

} catch (Exception $e) {
    cronLog('同步任务异常: ' . $e->getMessage(), 'ERROR');
    cronLog('堆栈跟踪: ' . $e->getTraceAsString(), 'ERROR');
    exit(1);
}
