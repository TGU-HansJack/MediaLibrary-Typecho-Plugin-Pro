<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 简单的 WebDAV 客户端，提供列目录、上传、删除等基础操作
 */
class MediaLibrary_WebDAVClient
{
    private $endpoint;
    private $basePath;
    private $baseUri;
    private $username;
    private $password;
    private $verifySSL;
    private $timeout;
    private $rootPrefix;
    private $authHeader = null;

    public function __construct(array $config)
    {
        if (!function_exists('curl_init')) {
            throw new Exception('服务器未启用 cURL 扩展，无法使用 WebDAV 功能');
        }

        $endpoint = isset($config['webdavEndpoint']) ? trim($config['webdavEndpoint']) : '';
        if ($endpoint === '') {
            throw new Exception('未配置 WebDAV 服务器地址');
        }

        $endpoint = $this->normalizeEndpointScheme($endpoint);
        $this->endpoint = rtrim($endpoint, '/');
        $this->basePath = self::normalizeBasePath(isset($config['webdavBasePath']) ? $config['webdavBasePath'] : '/');
        $this->baseUri = rtrim($this->endpoint, '/') . ($this->basePath === '/' ? '' : $this->basePath);
        $this->username = isset($config['webdavUsername']) ? (string)$config['webdavUsername'] : '';
        $this->password = isset($config['webdavPassword']) ? (string)$config['webdavPassword'] : '';
        $this->verifySSL = empty($config['webdavVerifySSL']) ? false : true;
        $this->timeout = isset($config['webdavTimeout']) ? max(3, intval($config['webdavTimeout'])) : 30;

        $endpointPath = parse_url($this->endpoint, PHP_URL_PATH);
        $endpointPath = $endpointPath ? rtrim($endpointPath, '/') : '';
        $basePart = $this->basePath === '/' ? '' : $this->basePath;
        $combined = $endpointPath . $basePart;
        $this->rootPrefix = $combined === '' ? '/' : $combined;

        $this->authHeader = $this->buildAuthHeader($this->username, $this->password);
    }

    /**
     * 测试连接
     */
    public function ping()
    {
        try {
            $this->propfind('/', 0);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 列出目录内容
     */
    public function listDirectory($path = '/')
    {
        $normalizedPath = $this->normalizeRelativePath($path);
        list($response,) = $this->propfind($normalizedPath, 1);

        if ($response === null || $response === '') {
            return [
                'path' => $normalizedPath,
                'items' => []
            ];
        }

        $xml = @simplexml_load_string($response);
        if (!$xml) {
            throw new Exception('无法解析 WebDAV 响应');
        }
        $xml->registerXPathNamespace('d', 'DAV:');

        $items = [];
        foreach ($xml->xpath('d:response') as $node) {
            $hrefNode = $node->xpath('d:href');
            if (!$hrefNode || empty($hrefNode[0])) {
                continue;
            }

            $relative = $this->extractRelativePath((string)$hrefNode[0]);
            if ($relative === false) {
                continue;
            }

            if ($this->isSamePath($normalizedPath, $relative)) {
                continue; // 跳过当前目录本身
            }

            $propNodes = $node->xpath('d:propstat/d:prop');
            if (!$propNodes || empty($propNodes[0])) {
                continue;
            }

            $prop = $propNodes[0];
            $isDir = !empty($prop->xpath('d:resourcetype/d:collection'));
            $nameNode = $prop->xpath('d:displayname');
            $name = $nameNode && !empty($nameNode[0]) ? (string)$nameNode[0] : '';
            $name = $name !== '' ? $name : basename($relative);

            $sizeNode = $prop->xpath('d:getcontentlength');
            $size = $isDir ? 0 : ($sizeNode && !empty($sizeNode[0]) ? intval($sizeNode[0]) : 0);

            $typeNode = $prop->xpath('d:getcontenttype');
            $mime = $typeNode && !empty($typeNode[0]) ? (string)$typeNode[0] : '';

            $modifiedNode = $prop->xpath('d:getlastmodified');
            $modified = $modifiedNode && !empty($modifiedNode[0]) ? (string)$modifiedNode[0] : '';
            $modifiedTimestamp = 0;
            if ($modified !== '') {
                $timestamp = strtotime($modified);
                if ($timestamp) {
                    $modifiedTimestamp = $timestamp;
                    $modified = date('Y-m-d H:i', $timestamp);
                }
            }

            // 提取 ETag（用于目录剪枝优化）
            $etagNode = $prop->xpath('d:getetag');
            $etag = $etagNode && !empty($etagNode[0]) ? (string)$etagNode[0] : '';
            // 移除 ETag 中的引号
            $etag = trim($etag, '"');

            $items[] = [
                'name' => $name,
                'path' => $relative,
                'is_dir' => $isDir,
                'size' => $size,
                'mime' => $mime,
                'modified' => $modified,
                'modified_timestamp' => $modifiedTimestamp,
                'etag' => $etag,
                'public_url' => $this->buildPublicUrl($relative)
            ];
        }

        usort($items, function ($a, $b) {
            if ($a['is_dir'] && !$b['is_dir']) {
                return -1;
            }
            if ($b['is_dir'] && !$a['is_dir']) {
                return 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'path' => $normalizedPath,
            'items' => $items
        ];
    }

    /**
     * 新建目录
     */
    public function createDirectory($path)
    {
        $target = $this->normalizeRelativePath($path);
        if ($target === '/') {
            throw new Exception('不能创建根目录');
        }

        $url = $this->buildUrl($target);
        $ch = $this->prepareCurl($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MKCOL');
        $this->applyHeaders($ch);
        $this->executeCurl($ch);
        return true;
    }

    /**
     * 删除文件或目录
     */
    public function delete($path)
    {
        $target = $this->normalizeRelativePath($path);
        if ($target === '/') {
            throw new Exception('不能删除根目录');
        }

        $url = $this->buildUrl($target);
        $ch = $this->prepareCurl($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        $this->applyHeaders($ch);
        $this->executeCurl($ch);
        return true;
    }

    /**
     * 移动文件或目录（重命名）
     * 使用 WebDAV MOVE 方法，参考 flymd 实现
     *
     * @param string $sourcePath 源路径
     * @param string $destPath 目标路径
     * @param bool $overwrite 是否覆盖目标文件（默认false）
     * @return bool
     */
    public function move($sourcePath, $destPath, $overwrite = false)
    {
        $source = $this->normalizeRelativePath($sourcePath);
        $dest = $this->normalizeRelativePath($destPath);

        if ($source === '/') {
            throw new Exception('不能移动根目录');
        }

        $sourceUrl = $this->buildUrl($source);
        $destUrl = $this->buildUrl($dest);

        $ch = $this->prepareCurl($sourceUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MOVE');
        $this->applyHeaders($ch, array(
            'Destination: ' . $destUrl,
            'Overwrite: ' . ($overwrite ? 'T' : 'F')
        ));

        try {
            $this->executeCurl($ch);
            return true;
        } catch (Exception $e) {
            // 如果 MOVE 失败，可能是跨服务器或服务不支持
            throw new Exception('MOVE 操作失败: ' . $e->getMessage());
        }
    }

    /**
     * 复制文件或目录
     * 使用 WebDAV COPY 方法
     *
     * @param string $sourcePath 源路径
     * @param string $destPath 目标路径
     * @param bool $overwrite 是否覆盖目标文件（默认false）
     * @return bool
     */
    public function copy($sourcePath, $destPath, $overwrite = false)
    {
        $source = $this->normalizeRelativePath($sourcePath);
        $dest = $this->normalizeRelativePath($destPath);

        if ($source === '/') {
            throw new Exception('不能复制根目录');
        }

        $sourceUrl = $this->buildUrl($source);
        $destUrl = $this->buildUrl($dest);

        $ch = $this->prepareCurl($sourceUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'COPY');
        $this->applyHeaders($ch, array(
            'Destination: ' . $destUrl,
            'Overwrite: ' . ($overwrite ? 'T' : 'F')
        ));

        try {
            $this->executeCurl($ch);
            return true;
        } catch (Exception $e) {
            throw new Exception('COPY 操作失败: ' . $e->getMessage());
        }
    }

    /**
     * 上传文件
     */
    public function uploadFile($targetPath, $localFile, $mime = 'application/octet-stream')
    {
        $target = $this->normalizeRelativePath($targetPath);
        if (!file_exists($localFile)) {
            throw new Exception('上传的临时文件不存在');
        }

        $url = $this->buildUrl($target);
        $ch = $this->prepareCurl($url);
        $fp = fopen($localFile, 'rb');
        if (!$fp) {
            throw new Exception('无法读取上传文件');
        }

        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localFile));
        $this->applyHeaders($ch, array('Content-Type: ' . ($mime ?: 'application/octet-stream')));

        try {
            $this->executeCurl($ch);
        } finally {
            fclose($fp);
        }

        return true;
    }

    /**
     * 下载文件
     */
    public function downloadFile($remotePath, $localPath)
    {
        $target = $this->normalizeRelativePath($remotePath);
        $url = $this->buildUrl($target);

        // 确保本地目录存在
        $localDir = dirname($localPath);
        if (!is_dir($localDir)) {
            if (!mkdir($localDir, 0755, true)) {
                throw new Exception('无法创建本地目录: ' . $localDir);
            }
        }

        $ch = $this->prepareCurl($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        $this->applyHeaders($ch);

        // 将响应写入文件
        $fp = fopen($localPath, 'wb');
        if (!$fp) {
            throw new Exception('无法创建本地文件: ' . $localPath);
        }

        curl_setopt($ch, CURLOPT_FILE, $fp);

        try {
            $response = curl_exec($ch);
            if ($response === false) {
                $error = curl_error($ch);
                $code = curl_errno($ch);
                curl_close($ch);
                fclose($fp);
                @unlink($localPath);
                throw new Exception('WebDAV 下载失败：' . $error . ' (#' . $code . ')');
            }

            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            fclose($fp);

            if ($status >= 400) {
                @unlink($localPath);
                throw new Exception('WebDAV 下载失败 (HTTP ' . $status . ')');
            }

            return true;
        } catch (Exception $e) {
            if (is_resource($fp)) {
                fclose($fp);
            }
            @unlink($localPath);
            throw $e;
        }
    }

    /**
     * 构建公开访问地址
     */
    public function buildPublicUrl($path)
    {
        $normalized = $this->normalizeRelativePath($path);
        $base = rtrim($this->baseUri, '/');

        if ($normalized === '/') {
            return $base . '/';
        }

        return $base . $this->encodeRelativePath($normalized);
    }

    /**
     * 并发上传多个文件
     * 参考 flymd 项目实现，使用 curl_multi 提升批量上传速度
     *
     * @param array $files 文件列表 [[remotePath => localPath, mime => mime], ...]
     * @param int $concurrency 并发数量（默认5）
     * @param callable $progressCallback 进度回调 function($completed, $total, $file)
     * @return array 上传结果 ['success' => [...], 'failed' => [...]]
     */
    public function uploadFilesConcurrent($files, $concurrency = 5, $progressCallback = null)
    {
        if (!function_exists('curl_multi_init') || !function_exists('curl_multi_add_handle')) {
            throw new Exception('服务器未启用 cURL Multi 功能，无法使用并发上传');
        }

        if (empty($files)) {
            return ['success' => [], 'failed' => []];
        }

        $result = [
            'success' => [],
            'failed' => []
        ];

        $total = count($files);
        $completed = 0;

        // 分批处理，每批最多 $concurrency 个文件
        for ($i = 0; $i < $total; $i += $concurrency) {
            $batch = array_slice($files, $i, $concurrency, true);
            $batchResult = $this->uploadBatch($batch);

            // 合并结果
            $result['success'] = array_merge($result['success'], $batchResult['success']);
            $result['failed'] = array_merge($result['failed'], $batchResult['failed']);

            // 更新进度
            $completed += count($batch);
            if ($progressCallback) {
                call_user_func($progressCallback, $completed, $total, null);
            }
        }

        return $result;
    }

    /**
     * 使用 curl_multi 批量上传文件
     *
     * @param array $batch 文件批次
     * @return array 上传结果
     */
    private function uploadBatch($batch)
    {
        $result = [
            'success' => [],
            'failed' => []
        ];

        // 创建 curl_multi 句柄
        $mh = curl_multi_init();
        $handles = [];
        $fileHandles = [];

        // 为每个文件创建 curl 句柄
        foreach ($batch as $key => $fileInfo) {
            $remotePath = $fileInfo['remotePath'];
            $localPath = $fileInfo['localPath'];
            $mime = isset($fileInfo['mime']) ? $fileInfo['mime'] : 'application/octet-stream';

            try {
                // 验证本地文件存在
                if (!file_exists($localPath)) {
                    throw new Exception('本地文件不存在: ' . $localPath);
                }

                $target = $this->normalizeRelativePath($remotePath);
                $url = $this->buildUrl($target);
                $ch = $this->prepareCurl($url);
                $fp = fopen($localPath, 'rb');

                if (!$fp) {
                    curl_close($ch);
                    throw new Exception('无法读取文件: ' . $localPath);
                }

                curl_setopt($ch, CURLOPT_UPLOAD, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_INFILE, $fp);
                curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localPath));
                $this->applyHeaders($ch, array('Content-Type: ' . $mime));

                // 添加到 multi handle
                curl_multi_add_handle($mh, $ch);

                // 保存句柄和文件句柄的映射
                $handles[(int)$ch] = [
                    'key' => $key,
                    'remotePath' => $remotePath,
                    'localPath' => $localPath,
                    'ch' => $ch
                ];
                $fileHandles[(int)$ch] = $fp;

            } catch (Exception $e) {
                $result['failed'][] = [
                    'file' => $remotePath,
                    'error' => $e->getMessage()
                ];
            }
        }

        // 执行并发请求
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 1.0);
        } while ($running > 0);

        // 处理结果
        foreach ($handles as $id => $info) {
            $ch = $info['ch'];
            $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);

            // 关闭文件句柄
            if (isset($fileHandles[$id]) && is_resource($fileHandles[$id])) {
                fclose($fileHandles[$id]);
            }

            if ($error || $httpCode >= 400) {
                $result['failed'][] = [
                    'file' => $info['remotePath'],
                    'error' => $error ? $error : 'HTTP ' . $httpCode
                ];
            } else {
                $result['success'][] = $info['remotePath'];
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);

        return $result;
    }

    /**
     * 发送 PROPFIND 请求
     */
    private function propfind($path, $depth = 1)
    {
        $url = $this->buildUrl($path);
        $ch = $this->prepareCurl($url);

        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind xmlns:d="DAV:">'
            . '<d:prop>'
            . '<d:displayname/><d:resourcetype/><d:getcontentlength/><d:getcontenttype/><d:getlastmodified/><d:getetag/>'
            . '</d:prop>'
            . '</d:propfind>';

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
        $this->applyHeaders($ch, array(
            'Depth: ' . intval($depth),
            'Content-Type: application/xml; charset="utf-8"'
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        return $this->executeCurl($ch, true);
    }

    private function prepareCurl($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySSL ? 2 : 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MediaLibrary-WebDAV/1.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        return $ch;
    }

    private function executeCurl($ch, $allowEmptyResponse = true)
    {
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            $code = curl_errno($ch);
            curl_close($ch);
            throw new Exception('WebDAV 请求失败：' . $error . ' (#' . $code . ')');
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new Exception('WebDAV 请求失败 (HTTP ' . $status . ')');
        }

        if (!$allowEmptyResponse && $response === '') {
            throw new Exception('WebDAV 返回空响应');
        }

        return array($response, $status);
    }

    private static function normalizeBasePath($path)
    {
        $path = trim((string)$path);
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . trim($path, '/');
    }

    private function normalizeRelativePath($path)
    {
        $path = trim((string)$path);
        if ($path === '' || $path === '/') {
            return '/';
        }

        $path = str_replace('\\', '/', $path);
        $segments = explode('/', $path);
        $safe = array();
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                continue;
            }
            $safe[] = $segment;
        }

        if (empty($safe)) {
            return '/';
        }

        return '/' . implode('/', $safe);
    }

    private function extractRelativePath($href)
    {
        if ($href === '') {
            return false;
        }

        $path = parse_url($href, PHP_URL_PATH);
        if ($path === null) {
            $path = $href;
        }
        $path = rawurldecode($path);
        if ($path === '') {
            $path = '/';
        }

        $prefix = rtrim($this->rootPrefix, '/');
        if ($prefix !== '' && strpos($path, $prefix) === 0) {
            $path = substr($path, strlen($prefix));
        }

        return '/' . ltrim($path, '/');
    }

    private function buildUrl($path)
    {
        $normalized = $this->normalizeRelativePath($path);
        $base = rtrim($this->baseUri, '/');

        if ($normalized === '/') {
            return $base . '/';
        }

        return $base . $this->encodeRelativePath($normalized);
    }

    private function isSamePath($a, $b)
    {
        return rtrim($a, '/') === rtrim($b, '/');
    }

    private function encodeRelativePath($path)
    {
        $trimmed = ltrim($path, '/');
        if ($trimmed === '') {
            return '/';
        }

        $segments = array_map('rawurlencode', explode('/', $trimmed));
        return '/' . implode('/', $segments);
    }

    private function normalizeEndpointScheme($endpoint)
    {
        if (stripos($endpoint, 'davs://') === 0) {
            return 'https://' . substr($endpoint, 7);
        }
        if (stripos($endpoint, 'dav://') === 0) {
            return 'http://' . substr($endpoint, 6);
        }
        return $endpoint;
    }

    private function buildAuthHeader($username, $password)
    {
        $credentials = $username . ':' . $password;
        if ($credentials === ':') {
            return null;
        }

        if (function_exists('mb_convert_encoding')) {
            $encodedCredentials = mb_convert_encoding($credentials, 'UTF-8', 'UTF-8');
        } else {
            $encodedCredentials = $credentials;
        }

        return base64_encode($encodedCredentials);
    }

    private function applyHeaders($ch, array $headers = [])
    {
        if ($this->authHeader !== null) {
            $hasAuth = false;
            foreach ($headers as $header) {
                if (stripos($header, 'authorization:') === 0) {
                    $hasAuth = true;
                    break;
                }
            }

            if (!$hasAuth) {
                $headers[] = 'Authorization: Basic ' . $this->authHeader;
            }
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
    }
}
