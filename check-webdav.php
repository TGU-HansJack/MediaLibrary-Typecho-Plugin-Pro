<?php
/**
 * 简单的 WebDAV Action 状态检查
 * 使用方法：将此文件放在 Typecho 根目录，访问 http://你的网站/check-webdav.php
 */

define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));

// 加载配置
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

// 初始化数据库
$db = Typecho_Db::get();
$prefix = $db->getPrefix();

echo "<h1>WebDAV Action 状态检查</h1>\n";

// 1. 检查插件是否激活
echo "<h2>1. 插件状态</h2>\n";
try {
    $result = $db->fetchRow($db->select()
        ->from('table.options')
        ->where('name = ?', 'plugin:MediaLibrary'));

    if ($result) {
        echo "<p style='color: green;'>✓ MediaLibrary 插件已激活</p>\n";
    } else {
        echo "<p style='color: red;'>✗ MediaLibrary 插件未激活</p>\n";
        echo "<p>请在后台启用插件</p>\n";
        exit;
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 检查失败: " . $e->getMessage() . "</p>\n";
    exit;
}

// 2. 检查 Action 是否注册
echo "<h2>2. Action 注册状态</h2>\n";
try {
    $result = $db->fetchRow($db->select()
        ->from('table.options')
        ->where('name = ?', 'actionTable'));

    if ($result) {
        $actionTable = unserialize($result['value']);

        echo "<h3>已注册的 Actions:</h3>\n";
        echo "<ul>\n";
        foreach ($actionTable as $name => $class) {
            $color = ($name === 'medialibrary-webdav') ? 'green' : 'black';
            echo "<li style='color: $color;'><strong>$name</strong> → $class</li>\n";
        }
        echo "</ul>\n";

        if (isset($actionTable['medialibrary-webdav'])) {
            echo "<p style='color: green; font-size: 18px;'><strong>✓ medialibrary-webdav Action 已注册！</strong></p>\n";
            echo "<p>类名: <code>" . $actionTable['medialibrary-webdav'] . "</code></p>\n";
        } else {
            echo "<p style='color: red; font-size: 18px;'><strong>✗ medialibrary-webdav Action 未注册</strong></p>\n";
            echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;'>\n";
            echo "<h3>解决方法：</h3>\n";
            echo "<ol>\n";
            echo "<li>登录 Typecho 后台</li>\n";
            echo "<li>进入 <strong>控制台</strong> → <strong>插件</strong></li>\n";
            echo "<li>找到 MediaLibrary 插件</li>\n";
            echo "<li>先点击 <strong>禁用</strong></li>\n";
            echo "<li>再点击 <strong>启用</strong></li>\n";
            echo "<li>刷新本页面重新检查</li>\n";
            echo "</ol>\n";
            echo "</div>\n";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 检查失败: " . $e->getMessage() . "</p>\n";
}

// 3. 访问测试
echo "<h2>3. 访问测试</h2>\n";
$siteUrl = rtrim($result ? Typecho_Widget::widget('Widget_Options')->siteUrl : 'http://localhost', '/');
$webdavUrl = $siteUrl . '/action/medialibrary-webdav';

echo "<p>WebDAV 地址: <a href='$webdavUrl' target='_blank'>$webdavUrl</a></p>\n";
echo "<p>使用命令行测试：</p>\n";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
echo "curl -X PROPFIND $webdavUrl \\\n";
echo "  -u \"用户名:密码\" \\\n";
echo "  -H \"Depth: 0\" \\\n";
echo "  -v";
echo "</pre>\n";

echo "<h2>4. 清理说明</h2>\n";
echo "<p style='color: orange;'>⚠ 检查完成后，请删除此文件（check-webdav.php）</p>\n";

?>
<style>
body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; }
h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
h2 { color: #555; margin-top: 30px; }
code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
pre { overflow-x: auto; }
</style>
