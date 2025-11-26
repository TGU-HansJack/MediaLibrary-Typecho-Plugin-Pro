<?php
namespace MediaLibrary_ObjectStorage\Adapters;

/**
 * 阿里云OSS适配器
 * 需要安装阿里云OSS SDK: composer require aliyuncs/oss-sdk-php
 */
class AliyunOSSAdapter extends AbstractAdapter
{
    private $client;

    protected function init()
    {
        $accessKeyId = $this->getConfig('access_key_id');
        $accessKeySecret = $this->getConfig('access_key_secret');
        $endpoint = $this->getConfig('endpoint');
        $bucket = $this->getConfig('bucket');

        if (!$accessKeyId || !$accessKeySecret || !$endpoint || !$bucket) {
            throw new \Exception('阿里云OSS配置不完整');
        }

        // 检查SDK是否已加载
        if (!class_exists('\OSS\OssClient')) {
            $sdkPath = __DIR__ . '/../../vendor/aliyun-oss/aliyun-oss-php-sdk-2.6.0.phar';
            if (file_exists($sdkPath)) {
                require_once $sdkPath;
            } else {
                throw new \Exception('阿里云OSS SDK未安装，SDK路径: ' . $sdkPath);
            }
        }

        try {
            $this->client = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint);
        } catch (\Exception $e) {
            throw new \Exception('阿里云OSS初始化失败: ' . $e->getMessage());
        }
    }

    public function upload($localPath, $remotePath)
    {
        try {
            $remotePath = $this->normalizePath($remotePath);
            $bucket = $this->getConfig('bucket');

            $this->client->uploadFile($bucket, $remotePath, $localPath);

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

            return $this->client->doesObjectExist($bucket, $remotePath);
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
            $this->client->getBucketInfo($bucket);

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
        return '阿里云OSS';
    }
}
