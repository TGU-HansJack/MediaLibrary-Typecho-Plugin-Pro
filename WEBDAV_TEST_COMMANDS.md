# MediaLibrary WebDAV 测试命令

## 基本连接测试

```bash
# 测试 1: PROPFIND (列出根目录)
curl -X PROPFIND https://www.hansjack.com/action/medialibrary-webdav \
  -u "用户名:密码" \
  -H "Depth: 0" \
  -v

# 预期结果：HTTP 207 Multi-Status 或 HTTP 401 (需要认证)
```

## OPTIONS 请求测试

```bash
# 测试 2: OPTIONS (检查支持的方法)
curl -X OPTIONS https://www.hansjack.com/action/medialibrary-webdav \
  -u "用户名:密码" \
  -v

# 预期结果：返回 DAV: 1, 2 头和 Allow: GET HEAD PUT DELETE... 等
```

## 列出文件

```bash
# 测试 3: PROPFIND Depth=1 (列出所有文件)
curl -X PROPFIND https://www.hansjack.com/action/medialibrary-webdav \
  -u "用户名:密码" \
  -H "Depth: 1" \
  -H "Content-Type: application/xml" \
  -d '<?xml version="1.0"?>
<d:propfind xmlns:d="DAV:">
  <d:prop>
    <d:displayname/>
    <d:getcontentlength/>
    <d:getcontenttype/>
    <d:getlastmodified/>
    <d:resourcetype/>
  </d:prop>
</d:propfind>' \
  -v
```

## 上传文件

```bash
# 测试 4: PUT (上传文件)
echo "Test content" > test.txt
curl -X PUT https://www.hansjack.com/action/medialibrary-webdav/test.txt \
  -u "用户名:密码" \
  --data-binary @test.txt \
  -v

# 预期结果：HTTP 201 Created (新文件) 或 HTTP 204 No Content (覆盖)
```

## Windows 资源管理器

1. 打开"此电脑"
2. 右键 → "添加一个网络位置"
3. 输入地址：`https://www.hansjack.com/action/medialibrary-webdav`
4. 输入 Typecho 用户名和密码
5. 完成

## macOS Finder

1. 打开 Finder
2. 按 `Command + K`
3. 输入地址：`https://www.hansjack.com/action/medialibrary-webdav`
4. 选择"注册用户"
5. 输入 Typecho 用户名和密码
6. 点击"连接"

## 可能的问题

### 如果仍然出现 404 错误

1. **清除浏览器缓存**
2. **测试直接访问**：在浏览器中访问 https://www.hansjack.com/action/medialibrary-webdav
   - 应该弹出认证对话框
   - 输入账号密码后应该显示目录列表（HTML 格式）

### 如果出现 401 错误

说明连接成功，但认证失败：
- 检查用户名和密码是否正确
- 确认账号权限至少为"贡献者"

### 如果出现 500 错误

检查 PHP 错误日志，可能的原因：
- 数据库连接问题
- 文件权限问题
- PHP 版本不兼容

## 调试模式

如果需要调试，可以临时在 WebDAVServerAction.php 中添加日志：

```php
// 在 action() 方法开头添加
file_put_contents('/tmp/webdav-debug.log',
    date('Y-m-d H:i:s') . ' - Request: ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . "\n",
    FILE_APPEND
);
```

## 下一步

请尝试：

1. **直接在浏览器中访问**：https://www.hansjack.com/action/medialibrary-webdav
2. **运行上面的 curl 测试命令**
3. **告诉我结果**：返回什么状态码和内容？

这样我就能知道具体哪里有问题了！
