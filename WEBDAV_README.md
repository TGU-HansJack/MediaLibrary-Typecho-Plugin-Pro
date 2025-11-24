# 媒体库 WebDAV 服务器功能

## 概述

媒体库插件现在提供了 WebDAV 服务器功能，允许你通过 WebDAV 协议访问、上传、管理 Typecho 媒体库中的文件。

## 功能特性

- ✅ 列出媒体库中的所有文件
- ✅ 上传文件到媒体库
- ✅ 下载媒体库文件
- ✅ 删除媒体库文件
- ✅ 移动/重命名文件
- ✅ 按年月自动组织文件（虚拟目录结构）
- ✅ HTTP 基本认证（使用 Typecho 账号）
- ✅ 支持所有标准 WebDAV 客户端

## 访问地址

WebDAV 服务器的访问地址为：

```
http://你的网站地址/action/medialibrary-webdav
```

例如，如果你的网站是 `https://example.com`，那么 WebDAV 地址就是：

```
https://example.com/action/medialibrary-webdav
```

## 认证

WebDAV 服务器使用 HTTP 基本认证，需要提供你的 Typecho 账号凭证：

- **用户名**: Typecho 用户名或邮箱
- **密码**: Typecho 密码

**权限要求**: 至少需要"贡献者"权限才能访问 WebDAV 服务器

## 如何使用

### Windows 资源管理器

1. 打开"此电脑"
2. 点击"映射网络驱动器"或右键 → "添加一个网络位置"
3. 输入 WebDAV 地址: `http://你的网站地址/action/medialibrary-webdav`
4. 输入 Typecho 用户名和密码
5. 完成后即可像本地文件夹一样访问媒体库

### macOS Finder

1. 打开 Finder
2. 按 `Command + K` 或菜单栏"前往" → "连接服务器"
3. 输入 WebDAV 地址: `http://你的网站地址/action/medialibrary-webdav`
4. 点击"连接"
5. 选择"注册用户"，输入 Typecho 用户名和密码
6. 完成后即可访问媒体库

### Linux (命令行)

使用 `davfs2` 挂载：

```bash
# 安装 davfs2
sudo apt-get install davfs2  # Debian/Ubuntu
sudo yum install davfs2       # CentOS/RHEL

# 创建挂载点
sudo mkdir /mnt/medialibrary

# 挂载
sudo mount -t davfs http://你的网站地址/action/medialibrary-webdav /mnt/medialibrary

# 输入 Typecho 用户名和密码
```

### 使用 rclone

```bash
# 配置 rclone
rclone config

# 选择 n (New remote)
# 输入名称: medialibrary
# 选择: webdav
# 输入 url: http://你的网站地址/action/medialibrary-webdav
# 输入 vendor: other
# 输入 user: 你的Typecho用户名
# 输入 pass: 你的Typecho密码（会被加密）

# 列出文件
rclone ls medialibrary:

# 上传文件
rclone copy local-file.jpg medialibrary:/

# 下载文件
rclone copy medialibrary:/file.jpg ./
```

### 使用 curl (命令行测试)

```bash
# 列出根目录
curl -X PROPFIND http://你的网站地址/action/medialibrary-webdav \
  -u "用户名:密码" \
  -H "Depth: 1"

# 上传文件
curl -X PUT http://你的网站地址/action/medialibrary-webdav/test.jpg \
  -u "用户名:密码" \
  --data-binary @local-file.jpg

# 下载文件
curl -X GET http://你的网站地址/action/medialibrary-webdav/test.jpg \
  -u "用户名:密码" \
  -o downloaded-file.jpg

# 删除文件
curl -X DELETE http://你的网站地址/action/medialibrary-webdav/test.jpg \
  -u "用户名:密码"
```

## 目录结构

媒体库 WebDAV 服务器提供以下目录结构：

```
/                              (根目录)
├── 2024/                     (2024年上传的文件)
│   ├── 01/                   (1月)
│   │   ├── image1.jpg
│   │   └── document.pdf
│   └── 02/                   (2月)
│       └── photo.png
├── 2023/                     (2023年上传的文件)
│   └── 12/
│       └── old-file.doc
└── standalone-file.txt       (根目录文件)
```

## 支持的操作

| 操作 | HTTP 方法 | 说明 |
|-----|----------|------|
| 列出目录 | PROPFIND | 列出所有文件和子目录 |
| 下载文件 | GET | 下载指定文件 |
| 上传文件 | PUT | 上传新文件或覆盖现有文件 |
| 删除文件 | DELETE | 删除指定文件 |
| 移动文件 | MOVE | 移动或重命名文件 |
| 复制文件 | COPY | 复制文件 |

**注意**: 不支持创建自定义目录（MKCOL），因为目录结构是根据上传日期自动生成的。

## 安全建议

1. **使用 HTTPS**: 强烈建议在生产环境中使用 HTTPS，以保护账号密码和文件传输安全
2. **强密码**: 使用强密码保护 Typecho 账号
3. **限制权限**: 只给需要访问的用户分配适当的权限级别
4. **备份数据**: 定期备份媒体库文件和数据库

## 故障排查

### 无法连接

- 检查 WebDAV 地址是否正确
- 确认插件已激活
- 检查服务器是否支持 URL 重写

### 认证失败

- 确认使用的是正确的 Typecho 用户名（或邮箱）和密码
- 确认账号权限至少为"贡献者"

### 无法上传文件

- 检查文件大小是否超过服务器限制
- 检查磁盘空间是否充足
- 检查 uploads 目录权限（需要可写）

### 文件列表为空

- 确认媒体库中确实有附件
- 检查数据库中 `contents` 表的 `type='attachment'` 记录

## 技术实现

### 数据库操作

WebDAV 服务器直接操作 Typecho 的 `contents` 表：

- 读取: 查询 `type='attachment'` 的记录
- 上传: 插入新的附件记录
- 删除: 删除附件记录和物理文件
- 更新: 修改附件的修改时间

### 文件存储

- 上传的文件存储在 `usr/uploads` 目录（或自定义的上传目录）
- 自动按年月组织：`YYYY/MM/filename.ext`
- 文件路径存储在数据库的 `text` 字段

## 与现有功能的关系

- **WebDAV 客户端**: 原有的 WebDAV 客户端功能（连接外部 WebDAV 服务器）继续保留
- **WebDAV 服务器**: 新增的功能，让媒体库本身成为 WebDAV 服务器
- **相互独立**: 两个功能互不影响，可以同时使用

## 开发者信息

### 核心文件

- `includes/WebDAVServer.php` - WebDAV 服务器核心实现
- `includes/WebDAVStorage.php` - 媒体库存储层实现
- `includes/WebDAVServerAction.php` - Action 处理器

### 基于

- [picodav](https://github.com/kd2org/picodav) - WebDAV 服务器参考实现
- WebDAV 协议规范 (RFC 4918)

## 许可证

MIT License

## 反馈

如有问题或建议，请在 GitHub 仓库提交 Issue。
