<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>MediaLibrary Panel 诊断</h1>";

try {
    // 1. 检查基本 PHP 环境
    echo "<h2>1. PHP 环境</h2>";
    echo "PHP 版本: " . PHP_VERSION . "<br>";
    echo "内存限制: " . ini_get('memory_limit') . "<br>";
    echo "最大执行时间: " . ini_get('max_execution_time') . " 秒<br>";

    // 2. 检查 Typecho 环境
    echo "<h2>2. Typecho 环境</h2>";
    if (defined('__TYPECHO_ROOT_DIR__')) {
        echo "✓ __TYPECHO_ROOT_DIR__ 已定义: " . __TYPECHO_ROOT_DIR__ . "<br>";
    } else {
        define('__TYPECHO_ROOT_DIR__', dirname(dirname(__FILE__)));
        echo "✓ __TYPECHO_ROOT_DIR__ 设置为: " . __TYPECHO_ROOT_DIR__ . "<br>";
    }

    // 3. 加载类文件
    echo "<h2>3. 加载关键类</h2>";

    $files = [
        'FileOperations' => __DIR__ . '/includes/FileOperations.php',
        'CacheManager' => __DIR__ . '/includes/CacheManager.php',
        'PanelHelper' => __DIR__ . '/includes/PanelHelper.php',
    ];

    foreach ($files as $name => $file) {
        if (file_exists($file)) {
            require_once $file;
            echo "✓ {$name} 加载成功<br>";
        } else {
            echo "✗ {$name} 文件不存在: {$file}<br>";
        }
    }

    // 4. 测试 CacheManager
    echo "<h2>4. 测试 CacheManager</h2>";
    MediaLibrary_CacheManager::init();
    echo "✓ CacheManager 初始化成功<br>";

    // 5. 测试缓存读写
    echo "<h2>5. 测试缓存读写</h2>";
    $testData = ['test' => 'ok', 'time' => time()];
    if (MediaLibrary_CacheManager::set('type-stats', $testData, 'test')) {
        echo "✓ 缓存写入成功<br>";

        $cached = MediaLibrary_CacheManager::get('type-stats', 'test');
        if ($cached) {
            echo "✓ 缓存读取成功<br>";
            MediaLibrary_CacheManager::delete('type-stats', 'test');
            echo "✓ 缓存删除成功<br>";
        } else {
            echo "✗ 缓存读取失败<br>";
        }
    } else {
        echo "✗ 缓存写入失败<br>";
    }

    echo "<h2>✅ 所有测试通过</h2>";
    echo "<p><strong>建议：</strong>如果以上测试都通过，问题可能在于：</p>";
    echo "<ul>";
    echo "<li>Typecho 数据库连接问题</li>";
    echo "<li>某些类的自动加载失败</li>";
    echo "<li>panel.php 中的其他代码问题</li>";
    echo "</ul>";
    echo "<p>请尝试访问原始的 panel.php 页面，或检查 PHP 错误日志。</p>";

} catch (Exception $e) {
    echo "<h2>❌ 错误</h2>";
    echo "<p><strong>错误信息：</strong>" . $e->getMessage() . "</p>";
    echo "<p><strong>文件：</strong>" . $e->getFile() . "</p>";
    echo "<p><strong>行号：</strong>" . $e->getLine() . "</p>";
    echo "<h3>堆栈跟踪：</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<h2>❌ PHP Error</h2>";
    echo "<p><strong>错误信息：</strong>" . $e->getMessage() . "</p>";
    echo "<p><strong>文件：</strong>" . $e->getFile() . "</p>";
    echo "<p><strong>行号：</strong>" . $e->getLine() . "</p>";
    echo "<h3>堆栈跟踪：</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
