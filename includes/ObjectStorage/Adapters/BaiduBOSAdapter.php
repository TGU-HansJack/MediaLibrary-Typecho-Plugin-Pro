<?php
namespace MediaLibrary_ObjectStorage\Adapters;

/**
 * 百度云BOS适配器
 * 需要安装百度云SDK
 */
class BaiduBOSAdapter extends AbstractAdapter
{
    private $client;

    protected function init()
    {
        $accessKeyId = $this->getConfig('access_key_id');
        $secretAccessKey = $this->getConfig('secret_access_key');
        $endpoint = $this->getConfig('endpoint');
        $bucket = $this->getConfig('bucket');

        if (!$accessKeyId || !$secretAccessKey || !$endpoint || !$bucket) {
            throw new \Exception('百度云BOS配置不完整');
        }

        // 检查SDK是否已加载
        if (!class_exists('\BaiduBce\Services\Bos\BosClient')) {
            $sdkPath = __DIR__ . '/../../vendor/baidu-bos/BaiduBce.phar';
            if (file_exists($sdkPath)) {
                require_once $sdkPath;
            } else {
                throw new \Exception('百度云BOS SDK未安装，SDK路径: ' . $sdkPath);
            }
        }

        try {
            $this->client = new \BaiduBce\Services\Bos\BosClient([
                'credentials' => [
                    'ak' => $accessKeyId,
                    'sk' => $secretAccessKey,
                ],
                'endpoint' => $endpoint,
            ]);
        } catch (\Exception $e) {
            throw new \Exception('百度云BOS初始化失败: ' . $e->getMessage());
        }
    }

    public function upload($localPath, $remotePath)
    {
        try {
            $remotePath = $this->normalizePath($remotePath);
            $bucket = $this->getConfig('bucket');

            $this->client->putObjectFromFile($bucket, $remotePath, $localPath);

            return [
                'success' => true,
                'url' => $this->getUrl($remotePath),
                'error' => ''
            ];
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return [
                'success' => false,
                'url' => '',
                'error' => $e->getMessage()
            ];
        }
    }

    public function delete($remotePath)
    {
        try {
            $remotePath = $this->normalizePath($remotePath);
            $bucket = $this->getConfig('bucket');

            $this->client->deleteObject($bucket, $remotePath);

            return ['success' => true, 'error' => ''];
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function exists($remotePath)
    {
        try {
            $remotePath = $this->normalizePath($remotePath);
            $bucket = $this->getConfig('bucket');

            $this->client->getObjectMetadata($bucket, $remotePath);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUrl($remotePath)
    {
        $remotePath = $this->normalizePath($remotePath);
        $domain = $this->getConfig('domain');

        if ($domain) {
            $domain = rtrim($domain, '/');
            return $domain . '/' . $remotePath;
        } else {
            $bucket = $this->getConfig('bucket');
            $endpoint = $this->getConfig('endpoint');
            return "https://{$bucket}.{$endpoint}/{$remotePath}";
        }
    }

    public function testConnection()
    {
        try {
            $bucket = $this->getConfig('bucket');
            $this->client->getBucketLocation($bucket);

            return [
                'success' => true,
                'message' => '连接成功'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '连接失败: ' . $e->getMessage()
            ];
        }
    }

    public function getName()
    {
        return '百度云BOS';
    }
}
