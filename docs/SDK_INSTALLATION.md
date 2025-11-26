# 对象存储SDK安装脚本

本脚本用于快速安装MediaLibrary插件所需的对象存储SDK。

## 使用方法

### Linux/macOS

```bash
cd /path/to/typecho/usr/plugins/MediaLibrary
chmod +x install-sdk.sh
./install-sdk.sh
```

### Windows

```cmd
cd C:\path\to\typecho\usr\plugins\MediaLibrary
install-sdk.bat
```

## 手动安装

如果自动脚本无法使用，请按以下步骤手动安装：

### 1. 安装Composer（如未安装）

访问 https://getcomposer.org/ 下载并安装Composer。

### 2. 安装各SDK

```bash
# 进入插件目录
cd usr/plugins/MediaLibrary

# 创建composer.json文件（如不存在）
cat > composer.json << EOF
{
    "require": {
        "qcloud/cos-sdk-v5": "^2.0",
        "aliyuncs/oss-sdk-php": "^2.6",
        "qiniu/php-sdk": "^7.0",
        "upyun/sdk": "^3.0"
    },
    "config": {
        "vendor-dir": "includes/vendor"
    }
}
EOF

# 安装依赖
composer install
```

### 3. 手动下载SDK（百度云BOS和华为云OBS）

#### 百度云BOS SDK

1. 访问: https://cloud.baidu.com/doc/BOS/s/Ojwvyso3m
2. 下载SDK压缩包
3. 解压到 `includes/vendor/baidu-bos/`

#### 华为云OBS SDK

1. 访问: https://support.huaweicloud.com/sdk-php-devg-obs/obs_23_0000.html
2. 下载SDK压缩包
3. 解压到 `includes/vendor/huawei-obs/`

## 验证安装

安装完成后，在Typecho后台的插件设置页面中启用对象存储功能，如果没有出现SDK相关错误，说明安装成功。

## 目录结构

安装完成后的目录结构应该如下：

```
MediaLibrary/
├── includes/
│   ├── vendor/
│   │   ├── qcloud-cos-sdk/
│   │   ├── aliyun-oss/
│   │   ├── qiniu-kodo/
│   │   ├── upyun-uss/
│   │   ├── baidu-bos/
│   │   └── huawei-obs/
│   ├── ObjectStorage/
│   │   ├── StorageInterface.php
│   │   ├── StorageFactory.php
│   │   └── Adapters/
│   └── ObjectStorageManager.php
├── docs/
│   └── OBJECT_STORAGE_GUIDE.md
└── ...
```

## 常见问题

### Q: Composer安装失败
A: 请检查网络连接，或使用国内镜像：
```bash
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
```

### Q: 权限错误
A: 确保插件目录有写入权限：
```bash
chmod -R 755 usr/plugins/MediaLibrary
```

### Q: PHP版本要求
A: 本插件和SDK需要PHP 7.4或更高版本。

## 技术支持

如有问题，请访问项目GitHub仓库提Issue。
