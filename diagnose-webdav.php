<?php
/**
 * 直接测试 WebDAV Action 是否可以实例化
 */

define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));
require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

if (!function_exists('medialibrary_bootstrap_typecho')) {
    function medialibrary_bootstrap_typecho()
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        if (class_exists('\\Widget\\Init')) {
            \Widget\Init::alloc();
        } else {
            Typecho_Widget::widget('Widget_Init');
        }

        $initialized = true;
    }
}

medialibrary_bootstrap_typecho();

// 初始化环境
$db = Typecho_Db::get();
$options = Typecho_Widget::widget('Widget_Options');

echo "<h1>WebDAV Action 诊断</h1>\n";

// 1. 检查类文件是否存在
echo "<h2>1. 文件检查</h2>\n";
$actionFile = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVServerAction.php';
if (file_exists($actionFile)) {
    echo "<p style='color: green;'>✓ WebDAVServerAction.php 存在</p>\n";
} else {
    echo "<p style='color: red;'>✗ WebDAVServerAction.php 不存在</p>\n";
    exit;
}

// 2. 尝试加载 Plugin.php（会加载 Action）
echo "<h2>2. 加载插件</h2>\n";
try {
    require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/Plugin.php';
    echo "<p style='color: green;'>✓ Plugin.php 加载成功</p>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 加载失败: " . $e->getMessage() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
    exit;
}

// 3. 检查类是否存在
echo "<h2>3. 类检查</h2>\n";
if (class_exists('MediaLibrary_WebDAVServerAction')) {
    echo "<p style='color: green;'>✓ MediaLibrary_WebDAVServerAction 类存在</p>\n";
} else {
    echo "<p style='color: red;'>✗ MediaLibrary_WebDAVServerAction 类不存在</p>\n";
    exit;
}

// 4. 尝试实例化
echo "<h2>4. 实例化测试</h2>\n";
try {
    $request = new Typecho_Request();
    $response = new Typecho_Response();
    $action = new MediaLibrary_WebDAVServerAction($request, $response);
    echo "<p style='color: green;'>✓ Action 实例化成功</p>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 实例化失败: " . $e->getMessage() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}

// 5. 检查路由表
echo "<h2>5. 路由表检查</h2>\n";
$actionTable = $options->actionTable;

if (isset($actionTable['medialibrary-webdav'])) {
    echo "<p style='color: green;'>✓ medialibrary-webdav 在路由表中</p>\n";
    echo "<p>映射到类: <code>" . $actionTable['medialibrary-webdav'] . "</code></p>\n";
} else {
    echo "<p style='color: red;'>✗ medialibrary-webdav 不在路由表中</p>\n";
}

// 6. 测试路由解析
echo "<h2>6. 路由解析测试</h2>\n";
try {
    // 模拟 /action/medialibrary-webdav 请求
    $_SERVER['REQUEST_URI'] = '/action/medialibrary-webdav';
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';

    // 获取路由信息
    $pathInfo = Typecho_Router::get('do');

    echo "<p>路由规则: <code>" . var_export($pathInfo, true) . "</code></p>\n";

    // 尝试解析路由
    $params = Typecho_Router::match($_SERVER['REQUEST_URI']);

    if ($params) {
        echo "<p style='color: green;'>✓ 路由解析成功</p>\n";
        echo "<pre>" . print_r($params, true) . "</pre>\n";
    } else {
        echo "<p style='color: red;'>✗ 路由解析失败</p>\n";
    }

} catch (Exception $e) {
    echo "<p style='color: orange;'>路由测试: " . $e->getMessage() . "</p>\n";
}

// 7. 检查 Typecho 版本
echo "<h2>7. Typecho 版本信息</h2>\n";
if (defined('Typecho\\Common::VERSION')) {
    echo "<p>版本: " . Typecho\Common::VERSION . "</p>\n";
} elseif (defined('__TYPECHO_VERSION__')) {
    echo "<p>版本: " . __TYPECHO_VERSION__ . "</p>\n";
} else {
    echo "<p>无法检测版本</p>\n";
}

echo "<p>路由类: " . (class_exists('Typecho\\Router') ? 'Typecho\\Router (新版)' : 'Typecho_Router (旧版)') . "</p>\n";

// 8. 建议
echo "<h2>8. 解决建议</h2>\n";
echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;'>\n";
echo "<h3>问题分析</h3>\n";
echo "<p>Action 已注册但路由无法解析，可能原因：</p>\n";
echo "<ol>\n";
echo "<li><strong>路由表未刷新</strong> - 尝试重新激活插件</li>\n";
echo "<li><strong>Action 类加载失败</strong> - 检查上面的实例化测试</li>\n";
echo "<li><strong>路由规则冲突</strong> - 检查是否有其他插件占用了 /action/ 路径</li>\n";
echo "<li><strong>Web 服务器配置</strong> - 确保 URL 重写正确</li>\n";
echo "</ol>\n";

echo "<h3>推荐操作</h3>\n";
echo "<ol>\n";
echo "<li>如果实例化测试失败，说明代码有问题</li>\n";
echo "<li>如果实例化成功但路由失败，需要修改路由注册方式</li>\n";
echo "<li>尝试访问: <a href='/index.php/action/medialibrary-webdav'>/index.php/action/medialibrary-webdav</a> (带 index.php)</li>\n";
echo "</ol>\n";
echo "</div>\n";

?>
<style>
body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; }
h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
h2 { color: #555; margin-top: 30px; }
code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
</style>
