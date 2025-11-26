# MediaLibrary 对象存储SDK集成说明

## SDK集成概述

MediaLibrary插件已经将所有主流云服务的SDK集成到插件中，用户无需手动安装任何SDK。

## 已集成的SDK

| 云服务 | SDK名称 | 版本 | 集成方式 | 文件位置 |
|--------|---------|------|----------|----------|
| 腾讯云COS | cos-sdk-v5 | 7.x | PHAR文件 | `includes/vendor/tencent-cos/phar/cos-sdk-v5-7.phar` |
| 阿里云OSS | aliyun-oss-php-sdk | 2.6.0 | PHAR文件 | `includes/vendor/aliyun-oss/aliyun_oss/aliyun-oss-php-sdk-2.6.0.phar` |
| 七牛云Kodo | qiniu-php-sdk | 最新版 | Composer | `includes/vendor/qiniu-kodo/qiniu_kodo/` |
| 又拍云USS | upyun-sdk | 最新版 | Composer | `includes/vendor/upyun-uss/upyun_uss/` |
| 百度云BOS | BaiduBce | 最新版 | PHAR文件 | `includes/vendor/baidu-bos/baidu_bos/BaiduBce.phar` |
| 华为云OBS | obs-php-sdk | 最新版 | Composer | `includes/vendor/huawei-obs/huawei_obs/` |
| LskyPro | 无需SDK | - | API调用 | - |

## SDK来源

所有SDK均来自参考项目 `typecho-bearsimple-2.x` 的官方实现，经过验证可以正常使用。

## 目录结构

```
MediaLibrary/
├── includes/
│   ├── vendor/                          # SDK目录
│   │   ├── tencent-cos/                 # 腾讯云COS SDK
│   │   │   └── phar/
│   │   │       └── cos-sdk-v5-7.phar   # PHAR封装的SDK
│   │   │
│   │   ├── aliyun-oss/                  # 阿里云OSS SDK
│   │   │   └── aliyun_oss/
│   │   │       └── aliyun-oss-php-sdk-2.6.0.phar
│   │   │
│   │   ├── qiniu-kodo/                  # 七牛云Kodo SDK
│   │   │   └── qiniu_kodo/
│   │   │       ├── autoload.php        # 自动加载入口
│   │   │       └── src/                # 源代码
│   │   │
│   │   ├── upyun-uss/                   # 又拍云USS SDK
│   │   │   └── upyun_uss/
│   │   │       └── vendor/
│   │   │           └── autoload.php    # Composer自动加载
│   │   │
│   │   ├── baidu-bos/                   # 百度云BOS SDK
│   │   │   └── baidu_bos/
│   │   │       └── BaiduBce.phar       # PHAR封装的SDK
│   │   │
│   │   └── huawei-obs/                  # 华为云OBS SDK
│   │       └── huawei_obs/
│   │           ├── obs-autoloader.php  # OBS专用加载器
│   │           └── vendor/
│   │               └── autoload.php    # Composer自动加载
│   │
│   └── ObjectStorage/                   # 对象存储适配器
│       ├── StorageInterface.php
│       ├── StorageFactory.php
│       └── Adapters/                    # 各云服务适配器
│           ├── AbstractAdapter.php
│           ├── TencentCOSAdapter.php
│           ├── AliyunOSSAdapter.php
│           ├── QiniuKodoAdapter.php
│           ├── UpyunUSSAdapter.php
│           ├── BaiduBOSAdapter.php
│           ├── HuaweiOBSAdapter.php
│           └── LskyProAdapter.php
```

## SDK加载机制

每个适配器在初始化时会自动加载对应的SDK：

### 1. PHAR文件加载（腾讯云、阿里云、百度云）

```php
// 示例：腾讯云COS
if (!class_exists('\Qcloud\Cos\Client')) {
    $sdkPath = __DIR__ . '/../../vendor/tencent-cos/phar/cos-sdk-v5-7.phar';
    if (file_exists($sdkPath)) {
        require_once $sdkPath;
    } else {
        throw new \Exception('腾讯云COS SDK未安装');
    }
}
```

### 2. Composer自动加载（七牛云、又拍云）

```php
// 示例：七牛云Kodo
if (!class_exists('\Qiniu\Auth')) {
    $sdkPath = __DIR__ . '/../../vendor/qiniu-kodo/qiniu_kodo/autoload.php';
    if (file_exists($sdkPath)) {
        require_once $sdkPath;
    } else {
        throw new \Exception('七牛云Kodo SDK未安装');
    }
}
```

### 3. 多文件加载（华为云OBS）

```php
// 华为云OBS需要加载两个文件
if (!class_exists('\Obs\ObsClient')) {
    // 先加载composer autoload
    $autoloadPath = __DIR__ . '/../../vendor/huawei-obs/huawei_obs/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
    // 再加载OBS autoloader
    $sdkPath = __DIR__ . '/../../vendor/huawei-obs/huawei_obs/obs-autoloader.php';
    if (file_exists($sdkPath)) {
        require_once $sdkPath;
    } else {
        throw new \Exception('华为云OBS SDK未安装');
    }
}
```

## SDK版本信息

| SDK | 版本信息 | 说明 |
|-----|---------|------|
| 腾讯云COS | v5.7 | 支持所有COS功能，包括分片上传 |
| 阿里云OSS | 2.6.0 | 稳定版本，兼容性好 |
| 七牛云Kodo | 最新 | 支持所有Kodo功能 |
| 又拍云USS | 3.x | 支持所有USS功能 |
| 百度云BOS | 最新 | 支持所有BOS功能 |
| 华为云OBS | 最新 | 支持所有OBS功能 |

## PHP版本要求

- **最低要求**: PHP 7.4
- **推荐版本**: PHP 8.0+
- **扩展要求**:
  - curl
  - json
  - mbstring
  - openssl

## 文件大小

| SDK | 文件大小 |
|-----|---------|
| 腾讯云COS | ~2.2 MB |
| 阿里云OSS | ~1.0 MB |
| 七牛云Kodo | ~123 KB |
| 又拍云USS | ~1.2 MB |
| 百度云BOS | ~7.4 MB |
| 华为云OBS | ~2.7 MB |
| **总计** | **~14.6 MB** |

## 兼容性说明

### Typecho兼容性

- ✅ Typecho 1.2.0+
- ✅ Typecho 1.3.0 (开发版)

### PHP兼容性

- ✅ PHP 7.4
- ✅ PHP 8.0
- ✅ PHP 8.1
- ✅ PHP 8.2

### 操作系统兼容性

- ✅ Linux (推荐)
- ✅ Windows
- ✅ macOS
- ✅ FreeBSD

## 故障排除

### 1. SDK加载失败

**错误信息**: "XXX SDK未安装"

**解决方案**:
1. 检查 `includes/vendor/` 目录是否完整
2. 确认对应SDK的文件路径是否存在
3. 检查文件权限（需要可读权限）
4. 查看错误日志中显示的具体SDK路径

### 2. 类未找到错误

**错误信息**: "Class '\XXX' not found"

**解决方案**:
1. 确认SDK文件存在
2. 检查PHP版本是否满足要求
3. 确认必要的PHP扩展已安装
4. 重新上传SDK文件

### 3. 权限错误

**错误信息**: "Permission denied"

**解决方案**:
```bash
# Linux/macOS
chmod -R 755 usr/plugins/MediaLibrary/includes/vendor

# 或者
chmod -R 644 usr/plugins/MediaLibrary/includes/vendor/*.phar
```

## 更新SDK

如需更新SDK，可以：

1. **从参考项目获取最新SDK**:
   ```bash
   cp -r typecho-bearsimple-2.x/配套插件/BsCore/modules/* \
         MediaLibrary/includes/vendor/
   ```

2. **使用Composer更新**:
   ```bash
   cd usr/plugins/MediaLibrary
   composer update
   ```

## 技术支持

如有SDK相关问题，请：
1. 查看本文档
2. 检查PHP错误日志
3. 访问项目GitHub仓库提Issue
4. 查看各云服务商的SDK官方文档

## 参考链接

- 腾讯云COS SDK: https://github.com/tencentyun/cos-php-sdk-v5
- 阿里云OSS SDK: https://github.com/aliyun/aliyun-oss-php-sdk
- 七牛云SDK: https://github.com/qiniu/php-sdk
- 又拍云SDK: https://github.com/upyun/php-sdk
- 百度云BOS SDK: https://cloud.baidu.com/doc/BOS/s/Ojwvyso3m
- 华为云OBS SDK: https://support.huaweicloud.com/sdk-php-devg-obs/obs_23_0000.html

---

**最后更新**: 2025年11月26日
**插件版本**: v0.1.2-pro
