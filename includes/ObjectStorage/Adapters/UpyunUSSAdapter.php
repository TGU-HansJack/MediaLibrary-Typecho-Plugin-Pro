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
            $error = '又拍云USS配置不完整';
            $this->log('object_storage_init', $error, [
                'bucket_name' => $bucketName,
                'operator_name' => $operatorName,
                'has_operator_password' => !empty($operatorPassword)
            ], 'error');
            throw new \Exception($error);
        }

        // 检查SDK是否已加载
        if (!class_exists('\Upyun\Upyun')) {
            $sdkPath = __DIR__ . '/../../vendor/upyun-uss/vendor/autoload.php';
            if (file_exists($sdkPath)) {
                require_once $sdkPath;
                $this->log('object_storage_init', '又拍云USS SDK加载成功', [
                    'sdk_path' => $sdkPath
                ], 'info');
            } else {
                $error = '又拍云USS SDK未安装，SDK路径: ' . $sdkPath;
                $this->log('object_storage_init', $error, [
                    'sdk_path' => $sdkPath
                ], 'error');
                throw new \Exception($error);
            }
        }

        try {
            $config = new \Upyun\Config($bucketName, $operatorName, $operatorPassword);
            $this->client = new \Upyun\Upyun($config);
            $this->log('object_storage_init', '又拍云USS客户端初始化成功', [
                'bucket_name' => $bucketName,
                'operator_name' => $operatorName
            ], 'info');
        } catch (\Exception $e) {
            $error = '又拍云USS初始化失败: ' . $e->getMessage();
            $this->log('object_storage_init', $error, [
                'bucket_name' => $bucketName,
                'operator_name' => $operatorName,
                'error' => $e->getMessage()
            ], 'error');
            throw new \Exception($error);
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
            $this->client->delete($remotePath);

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

            $bucketName = $this->getConfig('bucket_name');
            $this->logConnectionTest(true, '连接测试成功', ['bucket_name' => $bucketName]);
            return [
                'success' => true,
                'message' => '连接成功'
            ];
        } catch (\Exception $e) {
            $bucketName = $this->getConfig('bucket_name');
            $this->logConnectionTest(false, '连接测试失败: ' . $e->getMessage(), [
                'bucket_name' => $bucketName,
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
        return '又拍云USS';
    }
}
