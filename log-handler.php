<?php
/**
 * 日志管理处理器
 */

define('__TYPECHO_ROOT_DIR__', dirname(dirname(dirname(dirname(__FILE__)))));
define('__TYPECHO_ADMIN__', true);

require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

try {
    $user = Typecho_Widget::widget('Widget_User');
    if (!$user->pass('administrator')) {
        echo json_encode(['success' => false, 'message' => '权限不足'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '无法验证用户权限'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/Logger.php';

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'get_logs';
$limit = isset($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 200;

switch ($action) {
    case 'get_logs':
    case 'refresh_logs':
        $logs = MediaLibrary_Logger::getLogs($limit);
        echo json_encode(['success' => true, 'logs' => $logs], JSON_UNESCAPED_UNICODE);
        break;

    case 'clear_logs':
        MediaLibrary_Logger::clear();
        echo json_encode(['success' => true, 'message' => '日志已清空'], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['success' => false, 'message' => '未知操作'], JSON_UNESCAPED_UNICODE);
}

exit;
