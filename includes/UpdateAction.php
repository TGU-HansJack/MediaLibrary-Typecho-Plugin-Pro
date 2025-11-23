<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MediaLibrary_UpdateAction extends Typecho_Widget implements Widget_Interface_Do
{
    /** @var Widget_User */
    private $user;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->user = Typecho_Widget::widget('Widget_User');
    }

    public function action()
    {
        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');

        if (!$this->user->hasLogin()) {
            $this->response->throwJson(array('success' => false, 'message' => '请先登录后台'));
        }

        if (!$this->user->pass('administrator')) {
            $this->response->throwJson(array('success' => false, 'message' => '权限不足'));
        }

        require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/EnvironmentCheck.php';
        require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/PluginUpdater.php';

        $action = $this->request->get('action');

        switch ($action) {
            case 'check_update':
                $result = MediaLibrary_PluginUpdater::checkForUpdates();
                $this->response->throwJson($result);
                break;

            case 'install_update':
                $downloadUrl = $this->request->get('download_url');

                if (empty($downloadUrl)) {
                    $this->response->throwJson(array('success' => false, 'message' => '下载地址无效'));
                }

                if (strpos($downloadUrl, 'github.com') === false && strpos($downloadUrl, 'api.github.com') === false) {
                    $this->response->throwJson(array('success' => false, 'message' => '下载地址不是来自 GitHub'));
                }

                $result = MediaLibrary_PluginUpdater::downloadAndInstall($downloadUrl);
                $this->response->throwJson($result);
                break;

            default:
                $this->response->throwJson(array('success' => false, 'message' => '未知操作'));
        }
    }
}
