<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/AjaxHandler.php';

class MediaLibrary_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private $db;
    private $options;
    private $user;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->db = Typecho_Db::get();
        $this->options = Typecho_Widget::widget('Widget_Options');
        $this->user = Typecho_Widget::widget('Widget_User');
    }

    public function action()
    {
        // 使用 AjaxHandler 处理所有请求
        MediaLibrary_AjaxHandler::handleRequest($this->request, $this->db, $this->options, $this->user);
    }
}
