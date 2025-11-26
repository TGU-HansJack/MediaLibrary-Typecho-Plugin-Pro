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
            $error = '腾讯云COS配置不完整';
            $this->log('object_storage_init', $error, [
                'region' => $region,
                'bucket' => $bucket,
                'has_secret_id' => !empty($secretId),
                'has_secret_key' => !empty($secretKey)
            ], 'error');
            throw new \Exception($error);
        }

        // 检查SDK是否已加载
        if (!class_exists('\Qcloud\Cos\Client')) {
            // 加载SDK phar文件
            $sdkPath = __DIR__ . '/../../vendor/tencent-cos/cos-sdk-v5-7.phar';
            if (file_exists($sdkPath)) {
                require_once $sdkPath;
                $this->log('object_storage_init', '腾讯云COS SDK加载成功', [
                    'sdk_path' => $sdkPath
                ], 'info');
            } else {
                $error = '腾讯云COS SDK未安装，SDK路径: ' . $sdkPath;
                $this->log('object_storage_init', $error, [
                    'sdk_path' => $sdkPath
                ], 'error');
                throw new \Exception($error);
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
            $this->log('object_storage_init', '腾讯云COS客户端初始化成功', [
                'region' => $region,
                'bucket' => $bucket
            ], 'info');
        } catch (\Exception $e) {
            $error = '腾讯云COS初始化失败: ' . $e->getMessage();
            $this->log('object_storage_init', $error, [
                'region' => $region,
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

            $result = $this->client->upload(
                $bucket,
                $remotePath,
                fopen($localPath, 'rb')
            );

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

            $this->client->deleteObject([
                'Bucket' => $bucket,
                'Key' => $remotePath
            ]);

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
        return '腾讯云COS';
    }
}
