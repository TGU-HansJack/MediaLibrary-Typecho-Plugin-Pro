<?php
namespace MediaLibrary_ObjectStorage\Adapters;

/**
 * 七牛云Kodo适配器
 * 需要安装七牛云SDK: composer require qiniu/php-sdk
 */
class QiniuKodoAdapter extends AbstractAdapter
{
    private $auth;
    private $bucketManager;
    private $uploadManager;

    protected function init()
    {
        $accessKey = $this->getConfig('access_key');
        $secretKey = $this->getConfig('secret_key');
        $bucket = $this->getConfig('bucket');

        if (!$accessKey || !$secretKey || !$bucket) {
            $error = '七牛云Kodo配置不完整';
            $this->log('object_storage_init', $error, [
                'bucket' => $bucket,
                'has_access_key' => !empty($accessKey),
                'has_secret_key' => !empty($secretKey)
            ], 'error');
            throw new \Exception($error);
        }

        // 检查SDK是否已加载
        if (!class_exists('\Qiniu\Auth')) {
            $sdkPath = __DIR__ . '/../../vendor/qiniu-kodo/autoload.php';
            if (file_exists($sdkPath)) {
                require_once $sdkPath;
                $this->log('object_storage_init', '七牛云Kodo SDK加载成功', [
                    'sdk_path' => $sdkPath
                ], 'info');
            } else {
                $error = '七牛云Kodo SDK未安装，SDK路径: ' . $sdkPath;
                $this->log('object_storage_init', $error, [
                    'sdk_path' => $sdkPath
                ], 'error');
                throw new \Exception($error);
            }
        }

        try {
            $this->auth = new \Qiniu\Auth($accessKey, $secretKey);
            $this->bucketManager = new \Qiniu\Storage\BucketManager($this->auth);
            $this->uploadManager = new \Qiniu\Storage\UploadManager();
            $this->log('object_storage_init', '七牛云Kodo客户端初始化成功', [
                'bucket' => $bucket
            ], 'info');
        } catch (\Exception $e) {
            $error = '七牛云Kodo初始化失败: ' . $e->getMessage();
            $this->log('object_storage_init', $error, [
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

            $token = $this->auth->uploadToken($bucket, $remotePath);
            list($ret, $err) = $this->uploadManager->putFile($token, $remotePath, $localPath);

            if ($err !== null) {
                throw new \Exception($err->message());
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
            $bucket = $this->getConfig('bucket');

            $err = $this->bucketManager->delete($bucket, $remotePath);

            if ($err !== null) {
                throw new \Exception($err->message());
            }

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

            list($ret, $err) = $this->bucketManager->stat($bucket, $remotePath);

            return $err === null;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUrl($remotePath)
    {
        $remotePath = $this->normalizePath($remotePath);
        $domain = $this->getConfig('domain');

        if (!$domain) {
            throw new \Exception('七牛云Kodo需要配置访问域名');
        }

        $domain = rtrim($domain, '/');
        return $domain . '/' . $remotePath;
    }

    public function testConnection()
    {
        try {
            $bucket = $this->getConfig('bucket');
            list($ret, $err) = $this->bucketManager->bucketInfo($bucket);

            if ($err !== null) {
                throw new \Exception($err->message());
            }

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
        return '七牛云Kodo';
    }
}
