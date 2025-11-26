<?php
namespace MediaLibrary_ObjectStorage;

use MediaLibrary_ObjectStorage\Adapters\TencentCOSAdapter;
use MediaLibrary_ObjectStorage\Adapters\AliyunOSSAdapter;
use MediaLibrary_ObjectStorage\Adapters\QiniuKodoAdapter;
use MediaLibrary_ObjectStorage\Adapters\UpyunUSSAdapter;
use MediaLibrary_ObjectStorage\Adapters\BaiduBOSAdapter;
use MediaLibrary_ObjectStorage\Adapters\HuaweiOBSAdapter;
use MediaLibrary_ObjectStorage\Adapters\LskyProAdapter;

/**
 * 对象存储工厂类
 * 用于创建不同类型的对象存储适配器实例
 */
class StorageFactory
{
    /**
     * 存储类型常量
     */
    const TYPE_TENCENT_COS = 'tencent_cos';
    const TYPE_ALIYUN_OSS = 'aliyun_oss';
    const TYPE_QINIU_KODO = 'qiniu_kodo';
    const TYPE_UPYUN_USS = 'upyun_uss';
    const TYPE_BAIDU_BOS = 'baidu_bos';
    const TYPE_HUAWEI_OBS = 'huawei_obs';
    const TYPE_LSKYPRO = 'lskypro';

    /**
     * 创建存储适配器实例
     * @param string $type 存储类型
     * @param array $config 配置参数
     * @return StorageInterface
     * @throws \Exception
     */
    public static function create($type, $config)
    {
        switch ($type) {
            case self::TYPE_TENCENT_COS:
                return new TencentCOSAdapter($config);
            case self::TYPE_ALIYUN_OSS:
                return new AliyunOSSAdapter($config);
            case self::TYPE_QINIU_KODO:
                return new QiniuKodoAdapter($config);
            case self::TYPE_UPYUN_USS:
                return new UpyunUSSAdapter($config);
            case self::TYPE_BAIDU_BOS:
                return new BaiduBOSAdapter($config);
            case self::TYPE_HUAWEI_OBS:
                return new HuaweiOBSAdapter($config);
            case self::TYPE_LSKYPRO:
                return new LskyProAdapter($config);
            default:
                throw new \Exception("不支持的存储类型: {$type}");
        }
    }

    /**
     * 获取所有支持的存储类型
     * @return array
     */
    public static function getSupportedTypes()
    {
        return [
            self::TYPE_TENCENT_COS => '腾讯云COS',
            self::TYPE_ALIYUN_OSS => '阿里云OSS',
            self::TYPE_QINIU_KODO => '七牛云Kodo',
            self::TYPE_UPYUN_USS => '又拍云USS',
            self::TYPE_BAIDU_BOS => '百度云BOS',
            self::TYPE_HUAWEI_OBS => '华为云OBS',
            self::TYPE_LSKYPRO => 'LskyPro',
        ];
    }
}
