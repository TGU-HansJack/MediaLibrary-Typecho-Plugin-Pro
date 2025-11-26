#!/bin/bash

# MediaLibrary 对象存储SDK自动安装脚本
# 适用于 Linux/macOS

echo "================================"
echo "MediaLibrary SDK 安装脚本"
echo "================================"
echo ""

# 检查Composer是否已安装
if ! command -v composer &> /dev/null; then
    echo "错误: 未检测到Composer，请先安装Composer"
    echo "访问 https://getcomposer.org/ 下载安装"
    exit 1
fi

echo "检测到Composer，开始安装SDK..."
echo ""

# 创建vendor目录
mkdir -p includes/vendor

# 创建composer.json
echo "创建composer.json配置文件..."
cat > composer.json << 'EOF'
{
    "name": "medialibrary/object-storage",
    "description": "Object Storage SDKs for MediaLibrary Plugin",
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

echo "开始安装SDK包..."
echo ""

# 安装依赖
composer install

if [ $? -eq 0 ]; then
    echo ""
    echo "================================"
    echo "SDK安装完成！"
    echo "================================"
    echo ""
    echo "已安装的SDK:"
    echo "  ✓ 腾讯云COS SDK"
    echo "  ✓ 阿里云OSS SDK"
    echo "  ✓ 七牛云Kodo SDK"
    echo "  ✓ 又拍云USS SDK"
    echo ""
    echo "注意："
    echo "  - 百度云BOS SDK 和 华为云OBS SDK 需要手动下载"
    echo "  - LskyPro 无需SDK，直接通过API调用"
    echo ""
    echo "请参考 docs/SDK_INSTALLATION.md 了解详细信息"
    echo ""
else
    echo ""
    echo "================================"
    echo "SDK安装失败！"
    echo "================================"
    echo ""
    echo "可能的原因："
    echo "  1. 网络连接问题"
    echo "  2. PHP版本不兼容（需要PHP 7.4+）"
    echo "  3. 权限不足"
    echo ""
    echo "请手动安装或查看错误日志"
    exit 1
fi
