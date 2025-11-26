<?php
namespace MediaLibrary_ObjectStorage\Adapters;

/**
 * 又拍云USS适配器
 * 需要安装又拍云SDK: composer require upyun/sdk
 */
class UpyunUSSAdapter extends AbstractAdapter
{
    private $client;

    protected function init()
    {
        $bucketName = $this->getConfig('bucket_name');
        $operatorName = $this->getConfig('operator_name');
        $operatorPassword = $this->getConfig('operator_password');

        if (!$bucketName || !$operatorName || !$operatorPassword) {
            throw new \Exception('又拍云USS配置不完整');
        }

        // 检查SDK是否已加载
        if (!class_exists('\Upyun\Upyun')) {
            $sdkPath = __DIR__ . '/../../vendor/upyun-uss/vendor/autoload.php';
            if (file_exists($sdkPath)) {
                require_once $sdkPath;
            } else {
                throw new \Exception('又拍云USS SDK未安装，SDK路径: ' . $sdkPath);
            }
        }

        try {
            $config = new \Upyun\Config($bucketName, $operatorName, $operatorPassword);
            $this->client = new \Upyun\Upyun($config);
        } catch (\Exception $e) {
            throw new \Exception('又拍云USS初始化失败: ' . $e->getMessage());
        }
    }

    public function upload($localPath, $remotePath)
    {
        try {
            $remotePath = $this->normalizePath($remotePath);
            $file = fopen($localPath, 'r');

            $this->client->write($remotePath, $file);

            if (is_resource($file)) {
                fclose($file);
            }

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
            $this->client->delete($remotePath);

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
            $info = $this->client->info($remotePath);

            return !empty($info);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUrl($remotePath)
    {
        $remotePath = $this->normalizePath($remotePath);
        $domain = $this->getConfig('domain');

        if (!$domain) {
            $bucketName = $this->getConfig('bucket_name');
            $domain = "http://{$bucketName}.b0.upaiyun.com";
        }

        $domain = rtrim($domain, '/');
        return $domain . '/' . $remotePath;
    }

    public function testConnection()
    {
        try {
            $usage = $this->client->usage();

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
        return '又拍云USS';
    }
}
