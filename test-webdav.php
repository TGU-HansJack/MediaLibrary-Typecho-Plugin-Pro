<?php
/**
 * 测试 WebDAV Action 是否正确加载
 *
 * 使用方法：将此文件放在 Typecho 根目录，访问 http://你的网站/test-webdav.php
 */

define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));
define('__TYPECHO_DEBUG__', true);

// 加载 Typecho
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

// 加载 Typecho 核心
spl_autoload_register(function ($className) {
    $classPath = str_replace(['_', '\\'], '/', $className);
    $classFile = __TYPECHO_ROOT_DIR__ . '/var/' . $classPath . '.php';

    if (file_exists($classFile)) {
        require_once $classFile;
        return true;
    }
    return false;
});

// 检查插件文件
$pluginDir = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary';
$files = [
    'Plugin.php' => $pluginDir . '/Plugin.php',
    'WebDAVServer.php' => $pluginDir . '/includes/WebDAVServer.php',
    'WebDAVStorage.php' => $pluginDir . '/includes/WebDAVStorage.php',
    'WebDAVServerAction.php' => $pluginDir . '/includes/WebDAVServerAction.php',
];

echo "<h1>MediaLibrary WebDAV 测试</h1>\n";

echo "<h2>1. 文件检查</h2>\n";
foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $status = $exists ? '✓' : '✗';
    $color = $exists ? 'green' : 'red';
    echo "<p style='color: $color;'>$status $name: " . ($exists ? '存在' : '不存在') . "</p>\n";
}

echo "<h2>2. 语法检查</h2>\n";
foreach ($files as $name => $path) {
    if (file_exists($path)) {
        $output = [];
        $return = 0;
        exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $return);
        $status = $return === 0 ? '✓' : '✗';
        $color = $return === 0 ? 'green' : 'red';
        echo "<p style='color: $color;'>$status $name: " . implode('<br>', $output) . "</p>\n";
    }
}

echo "<h2>3. 类加载测试</h2>\n";
try {
    require_once $pluginDir . '/Plugin.php';
    echo "<p style='color: green;'>✓ Plugin.php 加载成功</p>\n";

    if (class_exists('MediaLibrary_WebDAVServerAction')) {
        echo "<p style='color: green;'>✓ MediaLibrary_WebDAVServerAction 类已加载</p>\n";
    } else {
        echo "<p style='color: red;'>✗ MediaLibrary_WebDAVServerAction 类未找到</p>\n";
    }

    if (class_exists('MediaLibrary\\WebDAV\\Server')) {
        echo "<p style='color: green;'>✓ MediaLibrary\\WebDAV\\Server 类已加载</p>\n";
    } else {
        echo "<p style='color: red;'>✗ MediaLibrary\\WebDAV\\Server 类未找到</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 加载错误: " . $e->getMessage() . "</p>\n";
}

echo "<h2>4. 插件状态</h2>\n";
try {
    $db = Typecho_Db::get();
    $plugins = Typecho_Plugin::export();

    if (isset($plugins['activated']['MediaLibrary'])) {
        echo "<p style='color: green;'>✓ MediaLibrary 插件已激活</p>\n";

        // 检查 Action 是否注册
        $options = Typecho_Widget::widget('Widget_Options');
        $actionTable = $options->actionTable;

        if (isset($actionTable['medialibrary-webdav'])) {
            echo "<p style='color: green;'>✓ medialibrary-webdav Action 已注册</p>\n";
            echo "<p>Action 类: " . $actionTable['medialibrary-webdav'] . "</p>\n";
        } else {
            echo "<p style='color: red;'>✗ medialibrary-webdav Action 未注册</p>\n";
            echo "<p style='color: orange;'>⚠ 请尝试：</p>\n";
            echo "<ol>\n";
            echo "<li>进入 Typecho 后台 → 控制台 → 插件</li>\n";
            echo "<li>先<strong>禁用</strong> MediaLibrary 插件</li>\n";
            echo "<li>再<strong>启用</strong> MediaLibrary 插件</li>\n";
            echo "</ol>\n";
        }

        echo "<h3>已注册的 Actions:</h3>\n";
        echo "<ul>\n";
        foreach ($actionTable as $name => $class) {
            echo "<li>$name → $class</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p style='color: red;'>✗ MediaLibrary 插件未激活</p>\n";
        echo "<p>请在 Typecho 后台启用插件</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 检查错误: " . $e->getMessage() . "</p>\n";
}

echo "<h2>5. 访问测试</h2>\n";
echo "<p>如果上述检查都通过，请访问：</p>\n";
echo "<p><a href='/action/medialibrary-webdav' target='_blank'>http://你的网站/action/medialibrary-webdav</a></p>\n";
echo "<p>应该会提示需要认证（HTTP 401）</p>\n";

echo "<h2>6. 清理说明</h2>\n";
echo "<p style='color: orange;'>⚠ 测试完成后，请删除此测试文件（test-webdav.php）</p>\n";
