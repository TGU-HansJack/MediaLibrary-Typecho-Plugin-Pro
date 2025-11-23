<?php
/**
 * 插件更新处理器
 * 处理更新检测和安装
 */

// 加载 Typecho
define('__TYPECHO_ROOT_DIR__', dirname(dirname(dirname(dirname(__FILE__)))));
define('__TYPECHO_ADMIN__', true);

require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

// 安全检查
session_start();

// 检查用户权限
$user = Typecho_Widget::widget('Widget_User');
if (!$user->pass('administrator')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '权限不足'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 加载更新类
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/EnvironmentCheck.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/PluginUpdater.php';

// 获取操作类型
$action = isset($_POST['action']) ? $_POST['action'] : '';

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    case 'check_update':
        // 检查更新
        $result = MediaLibrary_PluginUpdater::checkForUpdates();
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    case 'install_update':
        // 安装更新
        $downloadUrl = isset($_POST['download_url']) ? $_POST['download_url'] : '';

        if (empty($downloadUrl)) {
            echo json_encode(['success' => false, 'message' => '下载地址无效'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 验证下载地址
        if (strpos($downloadUrl, 'github.com') === false && strpos($downloadUrl, 'api.github.com') === false) {
            echo json_encode(['success' => false, 'message' => '下载地址不是来自 GitHub'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = MediaLibrary_PluginUpdater::downloadAndInstall($downloadUrl);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['success' => false, 'message' => '未知操作'], JSON_UNESCAPED_UNICODE);
}

exit;
