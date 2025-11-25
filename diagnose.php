<?php
// MediaLibrary 诊断脚本
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>MediaLibrary 缓存系统诊断</h1>";

// 1. 检查 cache 目录
echo "<h2>1. 检查 cache 目录</h2>";
$cacheDir = __DIR__ . '/cache';
if (is_dir($cacheDir)) {
    echo "✓ cache 目录存在: {$cacheDir}<br>";
    echo "✓ 可读: " . (is_readable($cacheDir) ? "是" : "否") . "<br>";
    echo "✓ 可写: " . (is_writable($cacheDir) ? "是" : "否") . "<br>";
} else {
    echo "✗ cache 目录不存在<br>";
}

// 2. 检查 CacheManager 类
echo "<h2>2. 检查 CacheManager 类</h2>";
if (file_exists(__DIR__ . '/includes/CacheManager.php')) {
    echo "✓ CacheManager.php 文件存在<br>";
    require_once __DIR__ . '/includes/CacheManager.php';
    if (class_exists('MediaLibrary_CacheManager')) {
        echo "✓ MediaLibrary_CacheManager 类可加载<br>";
    } else {
        echo "✗ MediaLibrary_CacheManager 类无法加载<br>";
    }
} else {
    echo "✗ CacheManager.php 文件不存在<br>";
}

// 3. 测试缓存操作
echo "<h2>3. 测试缓存操作</h2>";
try {
    MediaLibrary_CacheManager::init();
    echo "✓ 初始化成功<br>";

    $testData = ['test' => 'ok', 'time' => date('Y-m-d H:i:s')];
    if (MediaLibrary_CacheManager::set('type-stats', $testData, 'test')) {
        echo "✓ 写入测试缓存成功<br>";
    } else {
        echo "✗ 写入测试缓存失败<br>";
    }

    $cached = MediaLibrary_CacheManager::get('type-stats', 'test');
    if ($cached) {
        echo "✓ 读取测试缓存成功<br>";
        echo "  数据: <pre>" . print_r($cached, true) . "</pre>";
    } else {
        echo "✗ 读取测试缓存失败<br>";
    }

    MediaLibrary_CacheManager::delete('type-stats', 'test');
    echo "✓ 删除测试缓存成功<br>";

} catch (Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// 4. 检查其他必要文件
echo "<h2>4. 检查关键文件</h2>";
$files = [
    'includes/PanelHelper.php',
    'includes/AjaxHandler.php',
    'includes/ExifPrivacy.php'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✓ {$file} 存在<br>";
    } else {
        echo "✗ {$file} 不存在<br>";
    }
}

echo "<h2>诊断完成</h2>";
echo "<p>如果以上所有测试都通过，请尝试访问媒体库页面并查看具体错误信息。</p>";
echo "<p>如果页面仍然白屏，请检查 PHP 错误日志。</p>";
