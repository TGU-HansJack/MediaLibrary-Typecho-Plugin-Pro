# MediaLibrary - Typecho 媒体库管理插件

![PHP](https://img.shields.io/badge/PHP-%3E%3D5.6-blue?logo=php&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6-yellow?logo=javascript&logoColor=white)
![Typecho](https://img.shields.io/badge/Typecho-%3E%3D1.0-orange)
![License](https://img.shields.io/badge/License-Free-green)
![Version](https://img.shields.io/badge/Version-pro__0.1.0-blue)
[![GitHub Release](https://img.shields.io/github/v/release/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro)](https://github.com/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro/releases)
[![Release Workflow](https://img.shields.io/github/actions/workflow/status/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro/release.yml?label=release)](https://github.com/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro/actions/workflows/release.yml)

> 一个功能强大的 Typecho 媒体库管理插件，提供完整的媒体文件管理、预览、编辑和优化功能。

## ✨ 功能特性

- 📁 **完整的媒体库管理** - 对整体文件信息的查看、编辑、上传和删除
- 🖼️ **图片压缩** - 自动压缩图片，减少存储空间占用
- 🔒 **隐私检测** - 检测图片中的敏感信息（EXIF数据等）
- 🎬 **多媒体预览** - 支持图片、视频、音频等多种格式的在线预览
- ✍️ **编辑器集成** - 在文章编辑器中快速预览和插入媒体文件
- 📊 **可视化统计** - 媒体库使用情况的直观展示
- 🔍 **智能搜索** - 快速查找所需的媒体文件
- 🎨 **图片编辑器** - 内置图片编辑功能，支持裁剪、旋转等操作
- ☁️ **WebDAV 管理** - 在后台直接浏览、上传、删除 WebDAV 存储中的文件
- 📝 **日志系统** - 详细的操作日志记录

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

## 📖 使用教程

详细使用教程请访问：[https://www.hansjack.com/archives/medialibrary.html](https://www.hansjack.com/archives/medialibrary.html)

### 快速开始

1. **访问媒体库**

   在后台菜单中点击「媒体库」进入管理页面

2. **上传文件**

   点击上传按钮，选择要上传的媒体文件

3. **编辑器插入**

   在文章编辑页面，点击媒体库按钮即可快速插入媒体文件

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
├── Action.php              # 核心功能处理
├── Plugin.php              # 插件主文件
├── panel.php               # 后台管理面板
├── write-post-media.php    # 编辑器媒体库组件
├── assets/                 # 静态资源
│   ├── css/               # 样式文件
│   ├── js/                # JavaScript文件
│   └── images/            # 图片资源
├── includes/              # 核心功能类
├── lib/                   # 第三方库
│   ├── getid3/           # 媒体信息提取库
│   └── PHPExiftool/      # EXIF处理库
└── templates/             # 模板文件
```

## 🔧 配置选项

插件提供多项配置选项：

- 文件上传大小限制
- 图片压缩质量设置
- 隐私检测开关
- 缓存配置
- 日志级别设置
- WebDAV 远程存储管理（填写服务器地址、账户和凭证后即可在后台操作 WebDAV 文件）

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

如果你有任何建议或发现了 Bug，请在 [GitHub Issues](https://github.com/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro/issues) 中提出。

## 👨‍💻 作者

**HansJack**

- 网站: [https://www.hansjack.com](https://www.hansjack.com)


## 🙏 鸣谢

感谢以下开源项目：

- [Typecho](https://typecho.org/) - 优秀的博客程序
- [getID3](https://www.getid3.org/) - PHP 媒体文件解析库
- [PHPExiftool](https://github.com/PHPExiftool/PHPExiftool) - EXIF 数据处理工具
- [ECharts](https://echarts.apache.org/) - 数据可视化图表库

## 📮 联系方式

如有问题或建议，可以通过以下方式联系：

- 提交 [GitHub Issue](https://github.com/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro/issues)
- 访问作者网站留言

---

⭐ 如果觉得这个插件对你有帮助，欢迎给项目点个 Star！
