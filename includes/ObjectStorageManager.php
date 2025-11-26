<?php
require_once __DIR__ . '/ObjectStorage/StorageInterface.php';
require_once __DIR__ . '/ObjectStorage/StorageFactory.php';
require_once __DIR__ . '/ObjectStorage/Adapters/AbstractAdapter.php';
require_once __DIR__ . '/ObjectStorage/Adapters/TencentCOSAdapter.php';
require_once __DIR__ . '/ObjectStorage/Adapters/AliyunOSSAdapter.php';
require_once __DIR__ . '/ObjectStorage/Adapters/QiniuKodoAdapter.php';
require_once __DIR__ . '/ObjectStorage/Adapters/UpyunUSSAdapter.php';
require_once __DIR__ . '/ObjectStorage/Adapters/BaiduBOSAdapter.php';
require_once __DIR__ . '/ObjectStorage/Adapters/HuaweiOBSAdapter.php';
require_once __DIR__ . '/ObjectStorage/Adapters/LskyProAdapter.php';

use MediaLibrary_ObjectStorage\StorageFactory;

/**
 * 对象存储管理器
 * 用于管理对象存储的上传、删除等操作
 */
class MediaLibrary_ObjectStorageManager
{
    private $db;
    private $configOptions;
    private $storage;
    private $initError = '';

    public function __construct($db, $configOptions)
    {
        $this->db = $db;
        $this->configOptions = $configOptions;
        $this->initStorage();
    }

    /**
     * 初始化存储适配器
     */
    private function initStorage()
    {
        if (!$this->isEnabled()) {
            return;
        }

        $storageType = $this->getOption('storageType', 'tencent_cos');
        $config = $this->getStorageConfig($storageType);

        try {
            $this->storage = StorageFactory::create($storageType, $config);
        } catch (\Exception $e) {
            $this->initError = $e->getMessage();
            MediaLibrary_Logger::log('object_storage_init', '对象存储初始化失败: ' . $e->getMessage(), [
                'storage_type' => $storageType,
                'error' => $e->getMessage()
            ], 'error');
            $this->storage = null;
        }
    }

    /**
     * 获取初始化错误信息
     */
    public function getInitError()
    {
        return $this->initError;
    }

    /**
     * 检查是否启用对象存储
     */
    public function isEnabled()
    {
        return isset($this->configOptions['enableObjectStorage'])
            && in_array('1', $this->configOptions['enableObjectStorage']);
    }

    /**
     * 获取存储配置
     */
    private function getStorageConfig($type)
    {
        switch ($type) {
            case 'tencent_cos':
                return [
                    'secret_id' => $this->getOption('cosSecretId'),
                    'secret_key' => $this->getOption('cosSecretKey'),
                    'region' => $this->getOption('cosRegion'),
                    'bucket' => $this->getOption('cosBucket'),
                    'domain' => $this->getOption('cosDomain'),
                    'path_prefix' => $this->getOption('storagePathPrefix', 'uploads/'),
                ];

            case 'aliyun_oss':
                return [
                    'access_key_id' => $this->getOption('ossAccessKeyId'),
                    'access_key_secret' => $this->getOption('ossAccessKeySecret'),
                    'endpoint' => $this->getOption('ossEndpoint'),
                    'bucket' => $this->getOption('ossBucket'),
                    'domain' => $this->getOption('ossDomain'),
                    'path_prefix' => $this->getOption('storagePathPrefix', 'uploads/'),
                ];

            case 'qiniu_kodo':
                return [
                    'access_key' => $this->getOption('qiniuAccessKey'),
                    'secret_key' => $this->getOption('qiniuSecretKey'),
                    'bucket' => $this->getOption('qiniuBucket'),
                    'domain' => $this->getOption('qiniuDomain'),
                    'path_prefix' => $this->getOption('storagePathPrefix', 'uploads/'),
                ];

            case 'upyun_uss':
                return [
                    'bucket_name' => $this->getOption('upyunBucketName'),
                    'operator_name' => $this->getOption('upyunOperatorName'),
                    'operator_password' => $this->getOption('upyunOperatorPassword'),
                    'domain' => $this->getOption('upyunDomain'),
                    'path_prefix' => $this->getOption('storagePathPrefix', 'uploads/'),
                ];

            case 'baidu_bos':
                return [
                    'access_key_id' => $this->getOption('bosAccessKeyId'),
                    'secret_access_key' => $this->getOption('bosSecretAccessKey'),
                    'endpoint' => $this->getOption('bosEndpoint'),
                    'bucket' => $this->getOption('bosBucket'),
                    'domain' => $this->getOption('bosDomain'),
                    'path_prefix' => $this->getOption('storagePathPrefix', 'uploads/'),
                ];

            case 'huawei_obs':
                return [
                    'access_key' => $this->getOption('obsAccessKey'),
                    'secret_key' => $this->getOption('obsSecretKey'),
                    'endpoint' => $this->getOption('obsEndpoint'),
                    'bucket' => $this->getOption('obsBucket'),
                    'domain' => $this->getOption('obsDomain'),
                    'path_prefix' => $this->getOption('storagePathPrefix', 'uploads/'),
                ];

            case 'lskypro':
                return [
                    'api_url' => $this->getOption('lskyproApiUrl'),
                    'token' => $this->getOption('lskyproToken'),
                    'strategy_id' => $this->getOption('lskyproStrategyId'),
                    'path_prefix' => $this->getOption('storagePathPrefix', 'uploads/'),
                ];

            default:
                return [];
        }
    }

    /**
     * 获取配置选项
     */
    private function getOption($key, $default = '')
    {
        return isset($this->configOptions[$key]) ? $this->configOptions[$key] : $default;
    }

    /**
     * 是否保存本地备份
     */
    public function shouldSaveLocal()
    {
        return isset($this->configOptions['storageLocalSave'])
            && in_array('1', $this->configOptions['storageLocalSave']);
    }

    /**
     * 是否同步删除
     */
    public function shouldSyncDelete()
    {
        return isset($this->configOptions['storageSyncDelete'])
            && in_array('1', $this->configOptions['storageSyncDelete']);
    }

    /**
     * 上传文件到对象存储
     * @param string $localPath 本地文件路径
     * @param string $remotePath 远程文件路径
     * @return array 上传结果
     */
    public function upload($localPath, $remotePath)
    {
        if (!$this->storage) {
            $errorMsg = '对象存储未初始化';
            if ($this->initError) {
                $errorMsg .= ': ' . $this->initError;
            }
            return [
                'success' => false,
                'error' => $errorMsg
            ];
        }

        if (!file_exists($localPath)) {
            return [
                'success' => false,
                'error' => '本地文件不存在'
            ];
        }

        try {
            return $this->storage->upload($localPath, $remotePath);
        } catch (\Exception $e) {
            MediaLibrary_Logger::log('object_storage_upload', '上传失败: ' . $e->getMessage(), [
                'local_path' => $localPath,
                'remote_path' => $remotePath,
                'error' => $e->getMessage()
            ], 'error');
            return [
                'success' => false,
                'error' => '上传失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 从对象存储删除文件
     * @param string $remotePath 远程文件路径
     * @return array 删除结果
     */
    public function delete($remotePath)
    {
        if (!$this->storage) {
            return [
                'success' => false,
                'error' => '对象存储未初始化'
            ];
        }

        return $this->storage->delete($remotePath);
    }

    /**
     * 测试连接
     * @return array 测试结果
     */
    public function testConnection()
    {
        if (!$this->storage) {
            return [
                'success' => false,
                'message' => '对象存储未初始化，请检查配置和 SDK 是否正确安装'
            ];
        }

        try {
            return $this->storage->testConnection();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '连接测试失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取文件URL
     * @param string $remotePath 远程文件路径
     * @return string 文件URL
     */
    public function getUrl($remotePath)
    {
        if (!$this->storage) {
            return '';
        }

        return $this->storage->getUrl($remotePath);
    }
}
