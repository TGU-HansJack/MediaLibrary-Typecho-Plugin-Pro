<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/Logger.php';

/**
 * 日志相关的 Action 入口
 */
class MediaLibrary_LogAction extends Typecho_Widget implements Widget_Interface_Do
{
    /**
     * @var Typecho_Widget_User
     */
    private $user;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->user = Typecho_Widget::widget('Widget_User');
    }

    /**
     * 入口
     *
     * @return void
     * @throws Typecho_Widget_Exception
     */
    public function action()
    {
        $this->user->pass('administrator');

        $action = $this->request->get('do', $this->request->get('action', 'get_logs'));
        $limit = max(1, min(1000, intval($this->request->get('limit', 200))));

        switch ($action) {
            case 'get_logs':
            case 'refresh_logs':
            case 'get':
                $logs = MediaLibrary_Logger::getLogs($limit);
                $this->response->throwJson(['success' => true, 'logs' => $logs]);
                break;

            case 'clear_logs':
            case 'clear':
                MediaLibrary_Logger::clear();
                MediaLibrary_Logger::log('logs_cleared', '管理员清空日志', [
                    'uid' => $this->user->uid,
                    'name' => $this->user->screenName
                ]);
                $this->response->throwJson(['success' => true, 'message' => '日志已清空']);
                break;

            default:
                $this->response->throwJson(['success' => false, 'message' => '未知操作']);
        }
    }
}
