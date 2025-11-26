# MediaLibrary 对象存储功能使用指南

## 功能概述

MediaLibrary 插件现已支持将文件上传到云对象存储服务，**SDK已集成到插件中，无需手动安装**！

支持以下服务：

- **腾讯云COS** - 腾讯云对象存储
- **阿里云OSS** - 阿里云对象存储服务
- **七牛云Kodo** - 七牛云对象存储
- **又拍云USS** - 又拍云存储服务
- **百度云BOS** - 百度云对象存储
- **华为云OBS** - 华为云对象存储
- **LskyPro** - 开源图床系统

## 安装说明

### ✅ SDK已集成

**好消息！** 所有主流云服务的SDK已经集成到插件中，您无需进行任何额外安装：

- ✅ 腾讯云COS SDK（已集成）
- ✅ 阿里云OSS SDK（已集成）
- ✅ 七牛云Kodo SDK（已集成）
- ✅ 又拍云USS SDK（已集成）
- ✅ 百度云BOS SDK（已集成）
- ✅ 华为云OBS SDK（已集成）
- ✅ LskyPro（无需SDK，通过API调用）

### SDK位置

SDK文件已集成在以下目录（所有SDK文件直接位于对应目录下，无嵌套子目录）：
```
MediaLibrary/
└── includes/
    └── vendor/
        ├── tencent-cos/
        │   └── cos-sdk-v5-7.phar           # 腾讯云COS SDK
        ├── aliyun-oss/
        │   └── aliyun-oss-php-sdk-2.6.0.phar  # 阿里云OSS SDK
        ├── qiniu-kodo/
        │   ├── autoload.php                # 七牛云Kodo SDK
        │   └── src/
        ├── upyun-uss/
        │   ├── src/                        # 又拍云USS SDK
        │   └── vendor/
        ├── baidu-bos/
        │   └── BaiduBce.phar               # 百度云BOS SDK
        └── huawei-obs/
            ├── obs-autoloader.php          # 华为云OBS SDK
            ├── Obs/
            └── vendor/
```

## 配置说明

### 1. 启用对象存储

在插件设置页面中，勾选"启用对象存储"选项。

### 2. 选择存储类型

在"对象存储类型"下拉菜单中选择您要使用的云服务。

### 3. 配置相应的云服务参数

#### 腾讯云COS配置

- **SecretId**: 在 [腾讯云控制台-个人API密钥](https://console.cloud.tencent.com/capi) 获取
- **SecretKey**: 同上获取
- **地域**: 例如 `ap-beijing`（北京）、`ap-shanghai`（上海）
- **存储桶名称**: 格式为 `xxxxx-xxxxxx`，在 [COS控制台](https://console.cloud.tencent.com/cos/bucket) 查看
- **访问域名**: 可选，留空使用默认域名

#### 阿里云OSS配置

- **AccessKey ID**: 在 [阿里云控制台-RAM访问控制](https://ram.console.aliyun.com/manage/ak) 获取
- **AccessKey Secret**: 同上获取
- **Endpoint**: 例如 `oss-cn-hangzhou.aliyuncs.com`
- **Bucket名称**: 存储空间名称
- **访问域名**: 可选，留空使用默认域名

#### 七牛云Kodo配置

- **AccessKey**: 在 [七牛云控制台](https://portal.qiniu.com/user/key) 获取
- **SecretKey**: 同上获取
- **Bucket名称**: 存储空间名称
- **访问域名**: 必填，需要绑定的域名（含http://或https://）

#### 又拍云USS配置

- **服务名称**: 云存储服务名称
- **操作员名称**: 操作员账号
- **操作员密码**: 操作员密码
- **访问域名**: 可选，留空使用默认域名

#### 百度云BOS配置

- **AccessKey ID**: 在 [百度云控制台](https://console.bce.baidu.com/iam/#/iam/accesslist) 获取
- **SecretAccessKey**: 同上获取
- **Endpoint**: 例如 `bj.bcebos.com`
- **Bucket名称**: 存储桶名称
- **访问域名**: 可选，留空使用默认域名

#### 华为云OBS配置

- **AccessKey**: 在 [华为云控制台](https://console.huaweicloud.com/iam) 获取
- **SecretKey**: 同上获取
- **Endpoint**: 例如 `obs.cn-north-4.myhuaweicloud.com`
- **Bucket名称**: 桶名称
- **访问域名**: 可选，留空使用默认域名

#### LskyPro配置

- **API地址**: LskyPro站点地址，例如 `https://your-lskypro.com`
- **Token**: 在LskyPro后台获取API Token
- **储存策略ID**: 可选，留空使用默认策略

### 4. 通用配置

- **对象存储路径前缀**: 设置文件在对象存储中的路径前缀，默认为 `uploads/`
- **同时保存到本地**: 勾选后，上传到对象存储的同时也在本地保存一份副本
- **删除时同步**: 勾选后，在媒体库删除文件时，同步删除对象存储中的文件

## 使用方法

### 上传文件到对象存储

1. 进入 MediaLibrary 媒体库页面
2. 点击"上传文件"按钮
3. 在上传对话框中，选择"对象存储"作为存储位置
4. 选择文件并上传

### 管理对象存储文件

- 对象存储的文件会在媒体库中显示
- 可以像管理本地文件一样进行预览、删除等操作
- 文件URL将自动使用对象存储的访问地址

## 故障排除

### 1. 配置错误

**错误信息**: "对象存储配置不完整"

**解决方案**:
- 检查所有必填配置项是否已填写
- 确认配置信息准确无误
- 查看插件日志获取详细错误信息

### 2. 上传失败

**可能原因**:
- 配置信息填写错误
- 网络连接问题
- 存储桶权限配置不正确
- PHP配置限制（文件大小、上传时间等）

**解决方案**:
- 检查配置信息是否正确
- 确认存储桶设置为"公有读私有写"
- 查看插件日志获取详细错误信息
- 检查PHP配置（upload_max_filesize、post_max_size、max_execution_time）

### 3. 文件无法访问

**可能原因**:
- 访问域名配置错误
- 存储桶权限不正确
- 跨域配置问题

**解决方案**:
- 检查访问域名配置
- 确认存储桶设置为公有读
- 配置CORS规则（如需要）

### 4. SDK相关问题

**错误信息**: "SDK未安装"或类似错误

**解决方案**:
- 确认插件文件完整，特别是 `includes/vendor/` 目录
- 检查文件权限，确保PHP可以读取SDK文件
- 查看错误日志中的具体SDK路径
- 如果SDK文件缺失，请重新下载完整的插件包

## 注意事项

1. **安全性**: 请妥善保管您的AccessKey/SecretKey等敏感信息
2. **费用**: 使用对象存储服务会产生费用，请注意控制成本
3. **备份**: 建议启用"同时保存到本地"选项，以防对象存储服务故障
4. **权限**: 确保存储桶权限设置正确，建议设置为"公有读私有写"
5. **域名**: 部分服务需要绑定自定义域名才能正常访问
6. **PHP版本**: 建议使用PHP 7.4或更高版本

## 性能优化建议

1. **使用CDN**: 为对象存储绑定CDN加速域名，提升访问速度
2. **选择就近区域**: 选择距离服务器较近的存储区域，减少延迟
3. **合理设置缓存**: 为静态资源设置合适的缓存策略
4. **压缩上传**: 对图片等文件进行适当压缩后再上传
5. **批量操作**: 尽量批量上传文件，减少API调用次数

## 技术支持

如遇到问题，请：
1. 查看插件日志文件（在插件设置页面）
2. 检查PHP错误日志
3. 访问项目GitHub仓库提Issue
4. 查看各云服务商的官方文档
5. 参考 [SDK集成说明](SDK_INTEGRATION.md)

## 更新日志

### v0.1.2-pro
- 新增对象存储支持
- 支持7种主流云存储服务
- SDK已集成到插件中，无需手动安装
- 支持本地备份和同步删除选项
- 完整的错误处理和日志记录
