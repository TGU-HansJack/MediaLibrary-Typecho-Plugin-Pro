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
            $error = '百度云BOS配置不完整';
            $this->log('object_storage_init', $error, [
                'endpoint' => $endpoint,
                'bucket' => $bucket,
                'has_access_key_id' => !empty($accessKeyId),
                'has_secret_access_key' => !empty($secretAccessKey)
            ], 'error');
            throw new \Exception($error);
        }

        // 检查SDK是否已加载
        if (!class_exists('\BaiduBce\Services\Bos\BosClient')) {
            $sdkPath = __DIR__ . '/../../vendor/baidu-bos/BaiduBce.phar';
            if (file_exists($sdkPath)) {
                require_once $sdkPath;
                $this->log('object_storage_init', '百度云BOS SDK加载成功', [
                    'sdk_path' => $sdkPath
                ], 'info');
            } else {
                $error = '百度云BOS SDK未安装，SDK路径: ' . $sdkPath;
                $this->log('object_storage_init', $error, [
                    'sdk_path' => $sdkPath
                ], 'error');
                throw new \Exception($error);
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
            $this->log('object_storage_init', '百度云BOS客户端初始化成功', [
                'endpoint' => $endpoint,
                'bucket' => $bucket
            ], 'info');
        } catch (\Exception $e) {
            $error = '百度云BOS初始化失败: ' . $e->getMessage();
            $this->log('object_storage_init', $error, [
                'endpoint' => $endpoint,
                'bucket' => $bucket,
                'error' => $e->getMessage()
            ], 'error');
            throw new \Exception($error);
        }
    }

    public function upload($localPath, $remotePath)
    {
        try {
            $remotePath = $this->normalizePath($remotePath);
            $bucket = $this->getConfig('bucket');

            $this->client->putObjectFromFile($bucket, $remotePath, $localPath);

            $url = $this->getUrl($remotePath);
            $this->logUpload($localPath, $remotePath, true, '', ['url' => $url]);

            return [
                'success' => true,
                'url' => $url,
                'error' => ''
            ];
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            $this->logUpload($localPath, $remotePath, false, $e->getMessage());
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

            $this->logDelete($remotePath, true);
            return ['success' => true, 'error' => ''];
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            $this->logDelete($remotePath, false, $e->getMessage());
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

            $this->logConnectionTest(true, '连接测试成功', ['bucket' => $bucket]);
            return [
                'success' => true,
                'message' => '连接成功'
            ];
        } catch (\Exception $e) {
            $bucket = $this->getConfig('bucket');
            $this->logConnectionTest(false, '连接测试失败: ' . $e->getMessage(), [
                'bucket' => $bucket,
                'error' => $e->getMessage()
            ]);
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
