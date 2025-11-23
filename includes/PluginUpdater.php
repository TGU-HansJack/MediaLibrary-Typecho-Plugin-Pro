<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 插件更新检测和下载类
 */
class MediaLibrary_PluginUpdater
{
    /**
     * GitHub 仓库地址
     */
    const GITHUB_REPO = 'TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro';

    /**
     * GitHub API 基础地址
     */
    const GITHUB_API_BASE = 'https://api.github.com';

    /**
     * 检查 GitHub 更新
     *
     * @return array 更新检测结果
     */
    public static function checkForUpdates()
    {
        try {
            $currentVersion = MediaLibrary_EnvironmentCheck::getCurrentVersion();

            // 获取最新版本信息
            $latestRelease = self::getLatestRelease();

            if (!$latestRelease) {
                return [
                    'success' => false,
                    'message' => '无法获取最新版本信息',
                    'current_version' => $currentVersion
                ];
            }

            $latestVersion = isset($latestRelease['tag_name']) ? ltrim($latestRelease['tag_name'], 'v') : null;

            if (!$latestVersion) {
                return [
                    'success' => false,
                    'message' => '无法解析最新版本号',
                    'current_version' => $currentVersion
                ];
            }

            // 比较版本
            $hasUpdate = version_compare($latestVersion, $currentVersion, '>');

            return [
                'success' => true,
                'has_update' => $hasUpdate,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'release_name' => isset($latestRelease['name']) ? $latestRelease['name'] : '',
                'release_notes' => isset($latestRelease['body']) ? $latestRelease['body'] : '',
                'release_date' => isset($latestRelease['published_at']) ? $latestRelease['published_at'] : '',
                'download_url' => isset($latestRelease['zipball_url']) ? $latestRelease['zipball_url'] : '',
                'html_url' => isset($latestRelease['html_url']) ? $latestRelease['html_url'] : ''
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '检查更新失败: ' . $e->getMessage(),
                'current_version' => MediaLibrary_EnvironmentCheck::getCurrentVersion()
            ];
        }
    }

    /**
     * 从 GitHub API 获取最新版本信息
     *
     * @return array|null 最新版本信息
     */
    private static function getLatestRelease()
    {
        $url = self::GITHUB_API_BASE . '/repos/' . self::GITHUB_REPO . '/releases/latest';

        // 使用 cURL
        if (function_exists('curl_init') && extension_loaded('curl')) {
            return self::fetchWithCurl($url);
        }

        // 使用 file_get_contents
        if (ini_get('allow_url_fopen')) {
            return self::fetchWithFileGetContents($url);
        }

        return null;
    }

    /**
     * 使用 cURL 获取数据
     *
     * @param string $url URL 地址
     * @return array|null 返回数据
     */
    private static function fetchWithCurl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MediaLibrary-Typecho-Plugin');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/vnd.github.v3+json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }

        return null;
    }

    /**
     * 使用 file_get_contents 获取数据
     *
     * @param string $url URL 地址
     * @return array|null 返回数据
     */
    private static function fetchWithFileGetContents($url)
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: MediaLibrary-Typecho-Plugin\r\n" .
                           "Accept: application/vnd.github.v3+json\r\n",
                'timeout' => 10
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response) {
            return json_decode($response, true);
        }

        return null;
    }

    /**
     * 下载并安装更新
     *
     * @param string $downloadUrl 下载地址
     * @return array 安装结果
     */
    public static function downloadAndInstall($downloadUrl)
    {
        try {
            // 检查是否有必要的扩展
            if (!extension_loaded('zip')) {
                return [
                    'success' => false,
                    'message' => '需要安装 Zip 扩展才能自动更新插件'
                ];
            }

            // 创建临时目录
            $tmpDir = sys_get_temp_dir() . '/medialibrary_update_' . time();
            if (!mkdir($tmpDir, 0755, true)) {
                return [
                    'success' => false,
                    'message' => '无法创建临时目录'
                ];
            }

            // 下载 ZIP 文件
            $zipFile = $tmpDir . '/plugin.zip';
            $zipContent = self::downloadFile($downloadUrl);

            if (!$zipContent) {
                self::cleanupTempDir($tmpDir);
                return [
                    'success' => false,
                    'message' => '下载插件文件失败'
                ];
            }

            // 保存 ZIP 文件
            if (!file_put_contents($zipFile, $zipContent)) {
                self::cleanupTempDir($tmpDir);
                return [
                    'success' => false,
                    'message' => '无法保存下载的文件'
                ];
            }

            // 解压文件
            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== true) {
                self::cleanupTempDir($tmpDir);
                return [
                    'success' => false,
                    'message' => '无法打开 ZIP 文件'
                ];
            }

            $extractPath = $tmpDir . '/extracted';
            if (!$zip->extractTo($extractPath)) {
                $zip->close();
                self::cleanupTempDir($tmpDir);
                return [
                    'success' => false,
                    'message' => '解压文件失败'
                ];
            }
            $zip->close();

            // 找到插件目录
            $pluginSourceDir = self::findPluginDirectory($extractPath);
            if (!$pluginSourceDir) {
                self::cleanupTempDir($tmpDir);
                return [
                    'success' => false,
                    'message' => '无法找到插件文件'
                ];
            }

            // 备份当前插件
            $pluginDir = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary';
            $backupDir = __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary_backup_' . time();

            if (!rename($pluginDir, $backupDir)) {
                self::cleanupTempDir($tmpDir);
                return [
                    'success' => false,
                    'message' => '无法备份当前插件'
                ];
            }

            // 复制新版本
            if (!self::recursiveCopy($pluginSourceDir, $pluginDir)) {
                // 恢复备份
                rename($backupDir, $pluginDir);
                self::cleanupTempDir($tmpDir);
                return [
                    'success' => false,
                    'message' => '安装新版本失败，已恢复备份'
                ];
            }

            // 清理临时文件和备份
            self::cleanupTempDir($tmpDir);
            self::cleanupTempDir($backupDir);

            return [
                'success' => true,
                'message' => '插件更新成功！请刷新页面查看新版本。'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '更新过程出错: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 下载文件
     *
     * @param string $url URL 地址
     * @return string|null 文件内容
     */
    private static function downloadFile($url)
    {
        // 使用 cURL
        if (function_exists('curl_init') && extension_loaded('curl')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5分钟超时
            curl_setopt($ch, CURLOPT_USERAGENT, 'MediaLibrary-Typecho-Plugin');

            $response = curl_exec($ch);
            curl_close($ch);

            return $response;
        }

        // 使用 file_get_contents
        if (ini_get('allow_url_fopen')) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: MediaLibrary-Typecho-Plugin\r\n",
                    'timeout' => 300
                ]
            ]);

            return @file_get_contents($url, false, $context);
        }

        return null;
    }

    /**
     * 查找插件目录
     *
     * @param string $extractPath 解压路径
     * @return string|null 插件目录路径
     */
    private static function findPluginDirectory($extractPath)
    {
        // GitHub ZIP 格式通常是 repo-name-commit/
        $dirs = glob($extractPath . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            if (file_exists($dir . '/Plugin.php')) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * 递归复制目录
     *
     * @param string $src 源目录
     * @param string $dst 目标目录
     * @return bool 是否成功
     */
    private static function recursiveCopy($src, $dst)
    {
        if (!is_dir($src)) {
            return false;
        }

        if (!file_exists($dst)) {
            if (!mkdir($dst, 0755, true)) {
                return false;
            }
        }

        $dir = opendir($src);
        if (!$dir) {
            return false;
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            if (is_dir($srcPath)) {
                if (!self::recursiveCopy($srcPath, $dstPath)) {
                    closedir($dir);
                    return false;
                }
            } else {
                if (!copy($srcPath, $dstPath)) {
                    closedir($dir);
                    return false;
                }
            }
        }

        closedir($dir);
        return true;
    }

    /**
     * 清理临时目录
     *
     * @param string $dir 目录路径
     */
    private static function cleanupTempDir($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_dir($file)) {
                    self::cleanupTempDir($file);
                } else {
                    @unlink($file);
                }
            }
            @rmdir($dir);
        } else {
            @unlink($dir);
        }
    }

    /**
     * 获取 GitHub 仓库地址
     *
     * @return string GitHub 仓库 URL
     */
    public static function getRepoUrl()
    {
        return 'https://github.com/' . self::GITHUB_REPO;
    }
}
