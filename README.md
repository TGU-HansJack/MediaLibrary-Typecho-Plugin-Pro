# MediaLibrary Pro - Typecho 专业版媒体库管理插件

![PHP](https://img.shields.io/badge/PHP-%3E%3D7.2-blue?logo=php&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6-yellow?logo=javascript&logoColor=white)
![Typecho](https://img.shields.io/badge/Typecho-%3E%3D1.2.1-orange)
![License](https://img.shields.io/badge/License-Free-green)
![Version](https://img.shields.io/badge/Version-pro__0.1.3-blue)
[![GitHub Release](https://img.shields.io/github/v/release/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro)](https://github.com/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro/releases)
[![Release Workflow](https://img.shields.io/github/actions/workflow/status/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro/release.yml?label=release)](https://github.com/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro/actions/workflows/release.yml)

> 一个功能强大的 Typecho 专业版媒体库管理插件，提供完整的媒体文件管理、云存储集成、WebDAV 远程存储和多媒体处理功能。

## ✨ 核心特性

### 📁 媒体库管理
- **完整的文件管理** - 对媒体文件的查看、编辑、上传、删除和批量操作
- **智能搜索与筛选** - 按文件类型、日期、名称快速查找媒体文件
- **可视化统计** - 直观展示媒体库使用情况和存储空间分布
- **文件信息提取** - 自动提取媒体文件的元数据信息（尺寸、时长、格式等）

### 🖼️ 图片处理
- **智能压缩** - 自动压缩图片，减少存储空间占用，支持自定义压缩质量
- **隐私保护** - 检测并清理图片中的 EXIF 敏感信息（GPS 位置、设备信息等）
- **在线编辑** - 内置图片编辑器，支持裁剪、旋转、缩放等操作
- **多格式支持** - 支持 JPG、PNG、GIF、WebP 等主流图片格式

### 🎬 多媒体功能
- **在线预览** - 支持图片、视频、音频、PDF 等多种格式的在线预览
- **视频处理** - 提取视频缩略图、获取视频时长和分辨率信息
- **音频识别** - 自动识别音频格式、时长和比特率

### ☁️ 云存储集成
- **对象存储支持** - 支持 7 种主流云存储服务，SDK 已集成无需安装
  - 腾讯云 COS
  - 阿里云 OSS
  - 七牛云 Kodo
  - 又拍云 USS
  - 百度云 BOS
  - 华为云 OBS
  - LskyPro 图床
- **灵活配置** - 支持自定义域名、路径前缀、本地备份等
- **同步删除** - 可选择删除文件时同步删除云端文件

### 🌐 WebDAV 远程存储
- **远程文件管理** - 在后台直接浏览、上传、删除 WebDAV 存储中的文件
- **实时同步** - 支持定时任务自动同步本地和远程文件
- **双向管理** - 既可以上传本地文件到 WebDAV，也可以从 WebDAV 下载到本地
- **批量操作** - 支持批量上传、下载和删除操作

### ✍️ 编辑器增强
- **快速插入** - 在文章编辑器中快速预览和插入媒体文件
- **多种插入方式** - 支持插入图片、视频、音频、附件等
- **实时预览** - 插入前可实时预览媒体文件效果

### 📝 系统功能
- **详细日志** - 完整记录所有操作日志，便于追踪和审计
- **权限控制** - 支持细粒度的权限管理
- **缓存优化** - 智能缓存机制，提升访问速度
- **响应式设计** - 完美支持移动端访问和操作

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro&type=date&legend=top-left)](https://www.star-history.com/#TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro&type=date&legend=top-left)

## 🎯 系统要求

- **Typecho**: >= 1.2.1
- **PHP**: >= 7.2
- **MySQL**: >= 5.7
- **浏览器**: 现代浏览器（Chrome、Firefox、Safari、Edge）

## 📦 安装说明

### 方式一：从 Release 下载（推荐）

1. **下载最新版本**

   访问 [Releases 页面](https://github.com/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro/releases) 下载最新版本的 ZIP 文件

2. **解压并上传**

   解压下载的 ZIP 文件，将 `MediaLibrary` 文件夹上传到 Typecho 的 `usr/plugins/` 目录下

3. **激活插件**

   登录 Typecho 后台，进入「控制台」→「插件」→「MediaLibrary」，点击启用

4. **配置插件**

   启用后，在插件设置页面进行相关配置

### 方式二：从源码安装

1. **克隆仓库**
   ```bash
   git clone https://github.com/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro.git
   ```

2. **上传插件**

   将插件文件夹重命名为 `MediaLibrary`，然后上传到 Typecho 的 `usr/plugins/` 目录下

3. **激活插件**

   登录 Typecho 后台，进入「控制台」→「插件」→「MediaLibrary」，点击启用

4. **配置插件**

   启用后，在插件设置页面进行相关配置

## 📖 使用文档

### 在线教程
详细使用教程请访问：[https://www.hansjack.com/archives/medialibrary.html](https://www.hansjack.com/archives/medialibrary.html)

### 快速开始

1. **访问媒体库**
   - 在后台菜单中点击「媒体库」进入管理页面

2. **上传文件**
   - 点击上传按钮，选择要上传的媒体文件
   - 支持本地存储或云存储上传

3. **编辑器插入**
   - 在文章编辑页面，点击媒体库按钮即可快速插入媒体文件

### 完整文档

- [对象存储配置指南](docs/OBJECT_STORAGE_GUIDE.md) - 云存储服务配置和使用说明
- [WebDAV 功能说明](docs/WEBDAV_README.md) - WebDAV 远程存储配置和使用
- [WebDAV 定时同步](docs/WEBDAV_CRON_GUIDE.md) - 设置定时任务自动同步文件
- [WebDAV 功能增强](docs/WEBDAV_ENHANCEMENTS.md) - WebDAV 高级功能说明
- [SDK 集成说明](docs/SDK_INTEGRATION.md) - 云存储 SDK 技术细节
- [功能实现总结](docs/IMPLEMENTATION_SUMMARY.md) - 插件技术架构和实现细节

## 🛠️ 技术栈

- **后端**: PHP
- **前端**: JavaScript, HTML5, CSS3
- **图表**: ECharts
- **媒体处理**:
  - getID3 - 媒体文件信息提取
  - PHPExiftool - EXIF 数据处理

## 📂 目录结构

```
MediaLibrary/
├── Action.php                      # 核心功能处理
├── Plugin.php                      # 插件主文件
├── panel.php                       # 后台管理面板
├── write-post-media.php            # 编辑器媒体库组件
├── assets/                         # 静态资源
│   ├── css/                       # 样式文件
│   ├── js/                        # JavaScript 文件
│   └── images/                    # 图片资源
├── includes/                       # 核心功能类
│   ├── AjaxHandler.php            # Ajax 请求处理
│   ├── ObjectStorage/             # 对象存储功能
│   │   ├── Adapters/             # 云存储适配器
│   │   │   ├── AbstractAdapter.php
│   │   │   ├── TencentCOSAdapter.php
│   │   │   ├── AliyunOSSAdapter.php
│   │   │   ├── QiniuKodoAdapter.php
│   │   │   ├── UpyunUSSAdapter.php
│   │   │   ├── BaiduBOSAdapter.php
│   │   │   ├── HuaweiOBSAdapter.php
│   │   │   └── LskyProAdapter.php
│   │   ├── StorageInterface.php   # 存储接口定义
│   │   └── StorageFactory.php     # 存储工厂类
│   ├── ObjectStorageManager.php   # 对象存储管理器
│   └── vendor/                     # SDK 依赖（已集成）
│       ├── tencent-cos/           # 腾讯云 COS SDK
│       ├── aliyun-oss/            # 阿里云 OSS SDK
│       ├── qiniu-kodo/            # 七牛云 Kodo SDK
│       ├── upyun-uss/             # 又拍云 USS SDK
│       ├── baidu-bos/             # 百度云 BOS SDK
│       └── huawei-obs/            # 华为云 OBS SDK
├── lib/                            # 第三方库
│   ├── getid3/                    # 媒体信息提取库
│   └── PHPExiftool/               # EXIF 处理库
├── templates/                      # 模板文件
└── docs/                           # 文档目录
    ├── OBJECT_STORAGE_GUIDE.md    # 对象存储指南
    ├── WEBDAV_README.md           # WebDAV 说明
    ├── WEBDAV_CRON_GUIDE.md       # WebDAV 定时同步
    ├── WEBDAV_ENHANCEMENTS.md     # WebDAV 增强功能
    ├── SDK_INTEGRATION.md         # SDK 集成说明
    └── IMPLEMENTATION_SUMMARY.md  # 实现总结
```

## 🔧 配置选项

插件提供丰富的配置选项：

### 基础配置
- **文件上传限制** - 设置允许上传的文件大小和类型
- **存储路径** - 自定义媒体文件存储路径
- **缓存设置** - 配置缓存策略和缓存时间
- **日志级别** - 设置日志记录详细程度

### 图片处理
- **压缩质量** - 自定义图片压缩质量（0-100）
- **隐私检测** - 开启/关闭 EXIF 敏感信息检测和清理
- **缩略图生成** - 自动生成不同尺寸的缩略图

### 对象存储配置
- **存储类型** - 选择云存储服务提供商
- **认证信息** - 配置 AccessKey、SecretKey 等凭证
- **存储桶设置** - 配置 Bucket、Region、Endpoint 等
- **访问域名** - 自定义 CDN 加速域名
- **路径前缀** - 设置云端文件路径前缀
- **本地备份** - 选择是否同时保存到本地
- **同步删除** - 删除文件时同步删除云端文件

### WebDAV 远程存储
- **服务器地址** - WebDAV 服务器 URL
- **认证信息** - 用户名和密码/凭证
- **同步选项** - 配置自动同步策略
- **定时任务** - 设置定时同步间隔

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

如果你有任何建议或发现了 Bug，请在 [GitHub Issues](https://github.com/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro/issues) 中提出。

## 👨‍💻 作者

**HansJack**

- 网站: [https://www.hansjack.com](https://www.hansjack.com)


## 🙏 鸣谢

感谢以下开源项目和云服务提供商：

### 开源项目
- [Typecho](https://typecho.org/) - 优秀的博客程序
- [getID3](https://www.getid3.org/) - PHP 媒体文件解析库
- [PHPExiftool](https://github.com/PHPExiftool/PHPExiftool) - EXIF 数据处理工具
- [ECharts](https://echarts.apache.org/) - 数据可视化图表库

### 云服务 SDK
- [腾讯云 COS SDK](https://cloud.tencent.com/product/cos)
- [阿里云 OSS SDK](https://www.aliyun.com/product/oss)
- [七牛云 Kodo SDK](https://www.qiniu.com/products/kodo)
- [又拍云 USS SDK](https://www.upyun.com/products/file-storage)
- [百度云 BOS SDK](https://cloud.baidu.com/product/bos.html)
- [华为云 OBS SDK](https://www.huaweicloud.com/product/obs.html)
- [LskyPro](https://www.lsky.pro/) - 开源图床系统

## 📮 联系方式

如有问题或建议，可以通过以下方式联系：

- 提交 [GitHub Issue](https://github.com/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro/issues)
- 访问作者网站留言

---

⭐ 如果觉得这个插件对你有帮助，欢迎给项目点个 Star！
