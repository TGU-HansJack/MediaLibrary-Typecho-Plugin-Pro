<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

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

    public function ajax()
    {
        $this->user->pass('contributor');
        
        $action = $this->request->get('action');
        
        switch ($action) {
            case 'delete':
                $this->deleteFiles();
                break;
            case 'get_info':
                $this->getFileInfo();
                break;
            default:
                $this->response->throwJson(['success' => false, 'message' => '未知操作']);
        }
    }
    
    private function deleteFiles()
    {
        require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/FileOperations.php';
        $cids = $this->request->getArray('cids');
        
        if (empty($cids)) {
            $this->response->throwJson(['success' => false, 'message' => '请选择要删除的文件']);
        }
        
        $result = MediaLibrary_FileOperations::deleteFiles($cids, $this->db);
        $this->response->throwJson($result);
    }
    
    private function getFileInfo()
    {
        require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/FileOperations.php';
        $cid = $this->request->get('cid');
        
        $result = MediaLibrary_FileOperations::getFileInfo($cid, $this->db, $this->options);
        $this->response->throwJson($result);
    }
    
    public function action()
    {
        $this->ajax();
    }
}
