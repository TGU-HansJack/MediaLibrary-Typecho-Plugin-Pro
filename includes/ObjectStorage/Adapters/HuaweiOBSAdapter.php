<?php
namespace MediaLibrary_ObjectStorage\Adapters;

/**
 * 华为云OBS适配器
 * 需要安装华为云SDK
 */
class HuaweiOBSAdapter extends AbstractAdapter
{
    private $client;

    protected function init()
    {
        $accessKey = $this->getConfig('access_key');
        $secretKey = $this->getConfig('secret_key');
        $endpoint = $this->getConfig('endpoint');
        $bucket = $this->getConfig('bucket');

        if (!$accessKey || !$secretKey || !$endpoint || !$bucket) {
            throw new \Exception('华为云OBS配置不完整');
        }

        // 检查SDK是否已加载
        if (!class_exists('\Obs\ObsClient')) {
            // 先加载composer autoload
            $autoloadPath = __DIR__ . '/../../vendor/huawei-obs/vendor/autoload.php';
            if (file_exists($autoloadPath)) {
                require_once $autoloadPath;
            }
            // 再加载OBS autoloader
            $sdkPath = __DIR__ . '/../../vendor/huawei-obs/obs-autoloader.php';
            if (file_exists($sdkPath)) {
                require_once $sdkPath;
            } else {
                throw new \Exception('华为云OBS SDK未安装，SDK路径: ' . $sdkPath);
            }
        }

        try {
            $this->client = new \Obs\ObsClient([
                'key' => $accessKey,
                'secret' => $secretKey,
                'endpoint' => $endpoint,
            ]);
        } catch (\Exception $e) {
            throw new \Exception('华为云OBS初始化失败: ' . $e->getMessage());
        }
    }

    public function upload($localPath, $remotePath)
    {
        try {
            $remotePath = $this->normalizePath($remotePath);
            $bucket = $this->getConfig('bucket');

            $resp = $this->client->putObject([
                'Bucket' => $bucket,
                'Key' => $remotePath,
                'SourceFile' => $localPath,
            ]);

            if ($resp['HttpStatusCode'] === 200) {
                return [
                    'success' => true,
                    'url' => $this->getUrl($remotePath),
                    'error' => ''
                ];
            } else {
                throw new \Exception('上传失败: ' . ($resp['Reason'] ?? '未知错误'));
            }
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

            $resp = $this->client->deleteObject([
                'Bucket' => $bucket,
                'Key' => $remotePath,
            ]);

            if ($resp['HttpStatusCode'] === 204 || $resp['HttpStatusCode'] === 200) {
                return ['success' => true, 'error' => ''];
            } else {
                throw new \Exception('删除失败: ' . ($resp['Reason'] ?? '未知错误'));
            }
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

            $resp = $this->client->getObjectMetadata([
                'Bucket' => $bucket,
                'Key' => $remotePath,
            ]);

            return $resp['HttpStatusCode'] === 200;
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
            $resp = $this->client->headBucket(['Bucket' => $bucket]);

            if ($resp['HttpStatusCode'] === 200) {
                return [
                    'success' => true,
                    'message' => '连接成功'
                ];
            } else {
                throw new \Exception($resp['Reason'] ?? '连接失败');
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '连接失败: ' . $e->getMessage()
            ];
        }
    }

    public function getName()
    {
        return '华为云OBS';
    }
}
