<?php
namespace MediaLibrary_ObjectStorage\Adapters;

/**
 * 腾讯云COS适配器
 * 需要安装腾讯云COS SDK: composer require qcloud/cos-sdk-v5
 */
class TencentCOSAdapter extends AbstractAdapter
{
    private $client;

    protected function init()
    {
        $secretId = $this->getConfig('secret_id');
        $secretKey = $this->getConfig('secret_key');
        $region = $this->getConfig('region');
        $bucket = $this->getConfig('bucket');

        if (!$secretId || !$secretKey || !$region || !$bucket) {
            throw new \Exception('腾讯云COS配置不完整');
        }

        // 检查SDK是否已加载
        if (!class_exists('\Qcloud\Cos\Client')) {
            // 加载SDK phar文件
            $sdkPath = __DIR__ . '/../../vendor/tencent-cos/cos-sdk-v5-7.phar';
            if (file_exists($sdkPath)) {
                require_once $sdkPath;
            } else {
                throw new \Exception('腾讯云COS SDK未安装，SDK路径: ' . $sdkPath);
            }
        }

        try {
            $this->client = new \Qcloud\Cos\Client([
                'region' => $region,
                'credentials' => [
                    'secretId' => $secretId,
                    'secretKey' => $secretKey
                ]
            ]);
        } catch (\Exception $e) {
            throw new \Exception('腾讯云COS初始化失败: ' . $e->getMessage());
        }
    }

    public function upload($localPath, $remotePath)
    {
        try {
            $remotePath = $this->normalizePath($remotePath);
            $bucket = $this->getConfig('bucket');

            $result = $this->client->upload(
                $bucket,
                $remotePath,
                fopen($localPath, 'rb')
            );

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

            $this->client->deleteObject([
                'Bucket' => $bucket,
                'Key' => $remotePath
            ]);

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

            $this->client->headObject([
                'Bucket' => $bucket,
                'Key' => $remotePath
            ]);

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
            // 使用自定义域名
            $domain = rtrim($domain, '/');
            return $domain . '/' . $remotePath;
        } else {
            // 使用默认域名
            $bucket = $this->getConfig('bucket');
            $region = $this->getConfig('region');
            return "https://{$bucket}.cos.{$region}.myqcloud.com/{$remotePath}";
        }
    }

    public function testConnection()
    {
        try {
            $bucket = $this->getConfig('bucket');
            $this->client->headBucket(['Bucket' => $bucket]);

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
        return '腾讯云COS';
    }
}
