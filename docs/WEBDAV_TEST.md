# WebDAV 测试连接功能

## 概述

插件提供了详细的日志记录和测试连接功能，帮助你快速诊断 WebDAV 配置问题。

## 📊 详细日志功能

### 日志记录内容

所有 WebDAV 操作都会记录详细日志，包括：

#### 1. 初始化日志
```json
{
  "action": "webdav_init",
  "message": "自动创建本地 WebDAV 文件夹",
  "context": {
    "path": "/var/www/html/usr/uploads/webdav"
  }
}
```

#### 2. 文件同步日志
```json
{
  "action": "webdav_sync",
  "message": "开始同步文件到远程",
  "context": {
    "file": "test.jpg",
    "size": 102400,
    "size_human": "100 KB"
  }
}
```

```json
{
  "action": "webdav_sync",
  "message": "文件上传成功",
  "context": {
    "file": "test.jpg",
    "remote_path": "/typecho/test.jpg",
    "size": 102400,
    "duration_ms": 250.5
  }
}
```

#### 3. 删除操作日志
```json
{
  "action": "webdav_delete",
  "message": "开始删除远程文件",
  "context": {
    "file": "test.jpg",
    "remote_path": "/typecho/test.jpg"
  }
}
```

#### 4. 测试连接日志
```json
{
  "action": "webdav_test",
  "message": "本地路径测试成功",
  "context": {
    "path": "/var/www/html/usr/uploads/webdav",
    "permissions": "0755"
  }
}
```

```json
{
  "action": "webdav_test",
  "message": "开始测试远程 WebDAV 连接",
  "context": {
    "endpoint": "https://example.com/remote.php/dav/files/user",
    "username": "myuser"
  }
}
```

### 查看日志

日志文件位置：
```
/path/to/typecho/usr/plugins/MediaLibrary/logs/media-library.log
```

查看最近的日志：
```bash
# Linux
tail -f /path/to/typecho/usr/plugins/MediaLibrary/logs/media-library.log

# 查看 WebDAV 相关日志
grep "webdav" /path/to/typecho/usr/plugins/MediaLibrary/logs/media-library.log

# 查看错误日志
grep "error" /path/to/typecho/usr/plugins/MediaLibrary/logs/media-library.log
```

## 🔍 测试连接功能

### 使用 AJAX 测试

发送 POST 请求到：
```
/action/media-library?action=webdav_test
```

#### 请求方式

**使用浏览器控制台**：
```javascript
fetch('/action/media-library?action=webdav_test', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    }
})
.then(response => response.json())
.then(data => console.log(data));
```

**使用 cURL**：
```bash
curl -X POST "https://your-site.com/action/media-library?action=webdav_test"
```

#### 返回结果示例

**成功（本地和远程都正常）**：
```json
{
  "success": true,
  "message": "WebDAV 配置测试通过",
  "local": {
    "success": true,
    "path": "/var/www/html/usr/uploads/webdav",
    "exists": true,
    "readable": true,
    "writable": true,
    "message": "本地路径测试成功"
  },
  "remote": {
    "success": true,
    "configured": true,
    "connected": true,
    "endpoint": "https://example.com/remote.php/dav/files/user",
    "message": "远程连接测试成功"
  }
}
```

**失败（本地路径不存在）**：
```json
{
  "success": false,
  "message": "测试失败 - 本地路径: 目录不存在",
  "local": {
    "success": false,
    "path": "/var/www/html/usr/uploads/webdav",
    "exists": false,
    "readable": false,
    "writable": false,
    "message": "目录不存在"
  },
  "remote": {
    "success": false,
    "configured": false,
    "message": "未配置远程 WebDAV 服务器"
  }
}
```

**失败（远程连接失败）**：
```json
{
  "success": false,
  "message": "测试失败 - 远程连接: 无法连接到远程 WebDAV 服务器",
  "local": {
    "success": true,
    "path": "/var/www/html/usr/uploads/webdav",
    "exists": true,
    "readable": true,
    "writable": true,
    "message": "本地路径测试成功"
  },
  "remote": {
    "success": false,
    "configured": true,
    "connected": false,
    "endpoint": "https://example.com/remote.php/dav/files/user",
    "message": "无法连接到远程 WebDAV 服务器"
  }
}
```

### 测试项目

#### 本地路径测试

1. **目录存在检查**：验证配置的路径是否存在
2. **可读性检查**：验证 PHP 进程是否可以读取目录
3. **可写性检查**：验证 PHP 进程是否可以写入目录
4. **文件创建测试**：尝试创建测试文件，验证实际写入权限
5. **权限记录**：记录目录的权限信息（如 0755）

#### 远程连接测试

1. **配置检查**：验证是否配置了远程服务器地址和用户名
2. **连接测试**：使用 PROPFIND 请求测试连接
3. **认证测试**：验证用户名和密码是否正确
4. **响应时间**：记录连接响应时间

## 🐛 常见问题诊断

### 问题 1：目录不存在

**日志**：
```json
{
  "level": "error",
  "action": "webdav_test",
  "message": "本地路径测试失败：目录不存在",
  "context": {
    "path": "/var/www/html/usr/uploads/webdav"
  }
}
```

**解决方案**：
```bash
# 创建目录
mkdir -p /var/www/html/usr/uploads/webdav
chmod 755 /var/www/html/usr/uploads/webdav
chown www-data:www-data /var/www/html/usr/uploads/webdav
```

### 问题 2：目录不可写

**日志**：
```json
{
  "level": "error",
  "action": "webdav_test",
  "message": "本地路径测试失败：目录不可写，请检查权限",
  "context": {
    "path": "/var/www/html/usr/uploads/webdav",
    "permissions": "0644"
  }
}
```

**解决方案**：
```bash
# 修改权限
chmod 755 /var/www/html/usr/uploads/webdav

# 检查所有者
ls -ld /var/www/html/usr/uploads/webdav

# 修改所有者（如果需要）
chown www-data:www-data /var/www/html/usr/uploads/webdav
```

### 问题 3：远程连接失败

**日志**：
```json
{
  "level": "error",
  "action": "webdav_test",
  "message": "远程 WebDAV 连接失败",
  "context": {
    "endpoint": "https://example.com/remote.php/dav/files/user"
  }
}
```

**解决方案**：
1. 检查 WebDAV 服务器地址是否正确
2. 验证用户名和密码
3. 测试服务器是否可访问：
```bash
curl -u username:password https://example.com/remote.php/dav/files/user
```
4. 检查防火墙和 SSL 证书

### 问题 4：文件上传失败

**日志**：
```json
{
  "level": "error",
  "action": "webdav_sync",
  "message": "文件上传失败: Connection timeout",
  "context": {
    "file": "large-file.mp4",
    "remote_path": "/typecho/large-file.mp4",
    "error": "Connection timeout"
  }
}
```

**解决方案**：
1. 检查网络连接
2. 增加 PHP 超时时间：
```php
// 在 php.ini 中
max_execution_time = 300
```
3. 检查文件大小限制

## 📈 性能监控

### 同步性能日志

查看同步性能：
```bash
# 查看上传时间
grep "duration_ms" /path/to/logs/media-library.log

# 示例输出
{
  "action": "webdav_sync",
  "message": "文件上传成功",
  "context": {
    "file": "test.jpg",
    "size": 102400,
    "duration_ms": 250.5
  }
}
```

### 性能指标

- **小文件（< 1MB）**：通常 < 500ms
- **中等文件（1-10MB）**：通常 1-5s
- **大文件（> 10MB）**：取决于网络速度

如果性能不佳：
1. 检查网络带宽
2. 检查服务器响应时间
3. 考虑使用更快的 WebDAV 服务器
4. 使用 CDN 或对象存储

## 🔧 开发调试

### 启用详细日志

在插件设置中启用日志记录（如果未启用）。

### 手动触发测试

```php
// 在 Typecho 主题或插件中
$config = Helper::options()->plugin('MediaLibrary');
$sync = new MediaLibrary_WebDAVSync([
    'webdavLocalPath' => $config->webdavLocalPath,
    'webdavEndpoint' => $config->webdavEndpoint,
    'webdavUsername' => $config->webdavUsername,
    'webdavPassword' => $config->webdavPassword,
]);

// 测试本地路径
$localResult = $sync->testLocalPath();
var_dump($localResult);

// 测试远程连接
$remoteResult = $sync->testRemoteConnection();
var_dump($remoteResult);
```

## 💡 最佳实践

1. **定期查看日志**：定期检查日志文件，及时发现问题
2. **监控同步状态**：关注同步失败的日志
3. **测试新配置**：修改配置后使用测试功能验证
4. **备份元数据**：定期备份 `.webdav-sync-metadata.json`
5. **监控磁盘空间**：确保服务器有足够的存储空间

## 📞 获取帮助

如果遇到问题：
1. 查看日志文件获取详细错误信息
2. 使用测试连接功能诊断问题
3. 检查服务器配置和权限
4. 在项目 Issues 中提问，附上相关日志
