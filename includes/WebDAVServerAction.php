<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __DIR__ . '/WebDAVServer.php';
require_once __DIR__ . '/WebDAVStorage.php';

use MediaLibrary\WebDAV\Server;

/**
 * MediaLibrary WebDAV 服务器 Action
 *
 * 访问路径: /action/medialibrary-webdav
 *
 * 这个 Action 将媒体库暴露为 WebDAV 服务器，允许通过 WebDAV 客户端访问、上传、管理媒体文件
 */
class MediaLibrary_WebDAVServerAction extends Typecho_Widget implements Widget_Interface_Do
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

    /**
     * 执行 Action
     */
    public function action()
    {
        // 检查用户权限 - 需要登录才能访问 WebDAV
        try {
            $this->user->pass('contributor');
        } catch (Exception $e) {
            // 未登录，要求 HTTP 基本认证
            $this->requireAuth();
            return;
        }

        // 如果提供了 HTTP Basic Auth，验证之
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            if (!$this->verifyAuth($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
                $this->requireAuth('Invalid credentials');
                return;
            }
        }

        // 初始化 WebDAV 服务器
        $storage = new MediaLibrary_WebDAVStorage();
        $server = new Server();
        $server->setStorage($storage);

        // 设置 base URI
        // 请求 URI 格式: /action/medialibrary-webdav[/path]
        $baseUri = '/action/medialibrary-webdav';
        $server->setBaseURI($baseUri);

        // 路由请求
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        // 解析 URI，移除查询字符串
        $uri = strtok($requestUri, '?');

        if (!$server->route($uri)) {
            http_response_code(404);
            die('Not Found');
        }

        exit;
    }

    /**
     * 要求 HTTP 基本认证
     */
    private function requireAuth($message = 'Authentication required')
    {
        http_response_code(401);
        header('WWW-Authenticate: Basic realm="MediaLibrary WebDAV"');
        header('Content-Type: text/html; charset=utf-8');

        echo '<!DOCTYPE html>';
        echo '<html><head><meta charset="utf-8"><title>401 Unauthorized</title></head>';
        echo '<body><h1>401 Unauthorized</h1>';
        echo '<p>' . htmlspecialchars($message) . '</p>';
        echo '<p>Please provide valid Typecho credentials to access MediaLibrary WebDAV.</p>';
        echo '</body></html>';

        exit;
    }

    /**
     * 验证 HTTP 基本认证
     */
    private function verifyAuth($username, $password)
    {
        try {
            // 查询用户
            $user = $this->db->fetchRow($this->db->select()
                ->from('table.users')
                ->where('name = ? OR mail = ?', $username, $username)
                ->limit(1));

            if (!$user) {
                return false;
            }

            // 验证密码
            $hashValidate = Typecho_Common::hashValidate($password, $user['password']);

            if (!$hashValidate) {
                return false;
            }

            // 检查权限 - 至少需要贡献者权限
            if ($user['group'] !== 'administrator' && $user['group'] !== 'editor' && $user['group'] !== 'contributor') {
                return false;
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
