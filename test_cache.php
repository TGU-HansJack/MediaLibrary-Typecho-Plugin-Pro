<?php
// 简单的缓存系统测试脚本
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing Cache System...\n\n";

// 模拟 Typecho 环境
define('__TYPECHO_ROOT_DIR__', dirname(__FILE__) . '/../..');

// 加载 CacheManager
require_once __DIR__ . '/includes/CacheManager.php';

try {
    echo "1. Initializing CacheManager...\n";
    MediaLibrary_CacheManager::init();
    echo "   ✓ CacheManager initialized\n\n";

    echo "2. Testing cache write...\n";
    $testData = ['test' => 'data', 'time' => time()];
    $result = MediaLibrary_CacheManager::set('type-stats', $testData, 'test');
    echo "   " . ($result ? "✓" : "✗") . " Cache write result: " . ($result ? "success" : "failed") . "\n\n";

    echo "3. Testing cache read...\n";
    $cached = MediaLibrary_CacheManager::get('type-stats', 'test');
    echo "   " . ($cached ? "✓" : "✗") . " Cache read result: " . ($cached ? "success" : "failed") . "\n";
    if ($cached) {
        echo "   Cached data: " . print_r($cached, true) . "\n\n";
    }

    echo "4. Testing cache stats...\n";
    $stats = MediaLibrary_CacheManager::getStats();
    echo "   ✓ Cache directory: " . $stats['cache_dir'] . "\n";
    echo "   ✓ Total cache files: " . count($stats['files']) . "\n";
    echo "   ✓ Total cache size: " . $stats['total_size'] . " bytes\n\n";

    echo "5. Testing cache delete...\n";
    $result = MediaLibrary_CacheManager::delete('type-stats', 'test');
    echo "   " . ($result ? "✓" : "✗") . " Cache delete result: " . ($result ? "success" : "failed") . "\n\n";

    echo "All tests completed successfully!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
