# 开发工具

这个目录包含了 MediaLibrary 插件的开发和部署工具。

## SDK 安装脚本

### install-sdk.bat (Windows)
Windows 系统下的 SDK 自动安装脚本。

使用方法：
```bash
cd tools
install-sdk.bat
```

### install-sdk.sh (Linux/macOS)
Linux 和 macOS 系统下的 SDK 自动安装脚本。

使用方法：
```bash
cd tools
chmod +x install-sdk.sh
./install-sdk.sh
```

## 功能说明

这些脚本会自动：
1. 检查 Composer 是否已安装
2. 创建必要的目录结构
3. 生成 composer.json 配置文件
4. 安装对象存储 SDK（腾讯云COS、阿里云OSS、七牛云Kodo、又拍云USS）

更多详细信息请参考：[SDK安装指南](../docs/SDK_INSTALLATION.md)
