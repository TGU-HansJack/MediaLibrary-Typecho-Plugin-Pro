# WebDAV 同步存储使用指南

## 概述

MediaLibrary 插件的 WebDAV 功能采用**本地优先**的设计理念：

- 📁 **本地 WebDAV 文件夹**：作为主要存储，所有文件先保存到本地
- ☁️ **远程 WebDAV 服务器**：作为备份，自动同步本地文件
- 🔄 **智能同步**：参考 flymd 项目实现，支持增量同步、冲突处理、哈希缓存等高级特性

## 核心特性

### ✅ 本地优先架构

1. **本地 WebDAV 文件夹管理**
   - 直接读取本地服务器的 WebDAV 文件夹
   - 快速访问，无需网络请求
   - 文件上传直接保存到本地
   - 支持目录导航和文件管理

2. **自动远程同步**
   - 上传时自动同步到远程（可配置）
   - 批量同步所有本地文件
   - 增量同步，只同步变化的文件
   - 支持手动、自动、定时三种同步模式

3. **智能同步机制**（参考 flymd）
   - 文件哈希计算（SHA-256）
   - 元数据缓存（避免重复计算）
   - 哈希复用优化（文件大小未变时复用）
   - 同步时间戳跟踪

4. **冲突处理策略**
   - `newest`：使用最新文件（比较修改时间）
   - `local`：本地文件优先（总是上传本地文件）
   - `remote`：远程文件优先（总是保留远程文件）

5. **删除同步策略**
   - `auto`：自动同步删除（删除本地时同步删除远程）
   - `keep`：保留远程文件（仅删除本地）
   - `manual`：手动处理（删除时询问）

## 配置步骤

### 1. 启用 WebDAV 同步存储

在插件配置页面的 **WebDAV 同步存储** 部分：

#### 本地 WebDAV 文件夹

1. **本地 WebDAV 文件夹路径**
   - 填写服务器上的绝对路径
   - Windows 示例：`E:\webdav` 或 `D:\www\webdav`
   - Linux 示例：`/var/www/webdav` 或 `/home/user/webdav`
   - 确保目录存在且有读写权限（755）

#### 远程 WebDAV 服务器

2. **WebDAV 服务地址**
   - 远程 WebDAV 服务器 URL
   - 示例：`https://example.com/remote.php/dav/files/username`

3. **远程同步路径**
   - 在远程服务器上的目标路径
   - 示例：`/typecho` 或 `/uploads`

4. **WebDAV 用户名**：远程服务器认证用户名

5. **WebDAV 密码**：远程服务器认证密码

6. **SSL 验证**：生产环境建议启用，自签名证书可取消

#### 同步策略

7. **启用自动同步**：勾选以启用自动同步功能

8. **同步模式**：
   - **手动同步**：通过管理面板手动触发
   - **上传时自动同步**：上传文件时立即同步到远程（推荐）
   - **定时同步**：需要配置系统定时任务

9. **冲突处理策略**：
   - **使用最新文件**：比较修改时间，选择最新的（推荐）
   - **本地文件优先**：总是上传本地文件
   - **远程文件优先**：总是保留远程文件

10. **删除同步策略**：
    - **自动同步删除**：删除本地文件时同步删除远程（推荐）
    - **保留远程文件**：仅删除本地，不影响远程
    - **手动处理**：删除时询问

### 2. 创建本地 WebDAV 文件夹

配置前需要先创建本地 WebDAV 文件夹：

```bash
# Linux 示例
sudo mkdir -p /var/www/webdav
sudo chmod 755 /var/www/webdav
sudo chown www-data:www-data /var/www/webdav

# Windows 示例（在文件管理器中创建）
# E:\webdav
```

### 3. 保存并测试

保存配置后，系统会自动验证：
- ✅ 本地文件夹是否存在和可访问
- ✅ 远程 WebDAV 服务器连接（如果配置了）

## 使用方法

### 在媒体库中查看 WebDAV 文件

1. 进入**媒体库管理**页面
2. 在左侧边栏的**存储类型**中选择 **WebDAV**
3. 系统会显示本地 WebDAV 文件夹中的所有文件
4. 可以像管理本地文件一样操作这些文件

### 上传文件到 WebDAV

#### 方法一：通过媒体库上传

1. 在媒体库管理页面，选择存储类型为 **WebDAV**
2. 点击上传按钮，选择文件
3. 文件会保存到本地 WebDAV 文件夹
4. 如果启用了"上传时自动同步"，文件会立即同步到远程

#### 方法二：直接操作本地文件夹

1. 直接将文件复制到本地 WebDAV 文件夹
2. 在媒体库中刷新即可看到新文件
3. 使用手动同步或等待定时同步

### 同步文件到远程

#### 手动同步

1. 进入媒体库管理页面
2. 找到需要同步的文件
3. 点击"同步"按钮（如果提供）
4. 或使用批量同步功能

#### 批量同步

```php
// 可以通过代码触发批量同步
$config = MediaLibrary_PanelHelper::getPluginConfig();
$sync = new MediaLibrary_WebDAVSync($config);
$result = $sync->syncAllToRemote(function($current, $total, $file) {
    echo "同步进度: {$current}/{$total} - {$file}\n";
});
```

### 删除文件

1. 在媒体库中选择要删除的 WebDAV 文件
2. 点击删除按钮
3. 系统会：
   - 删除本地 WebDAV 文件夹中的文件
   - 根据删除策略处理远程文件：
     - `auto`：自动删除远程文件
     - `keep`：保留远程文件
     - `manual`：弹窗询问

## 目录结构

```
[本地 WebDAV 文件夹]
├── .webdav-sync-metadata.json  # 同步元数据（哈希、时间戳等）
├── file1.jpg                   # 媒体文件
├── file2.png                   # 媒体文件
└── subfolder/                  # 子目录
    └── file3.pdf               # 子目录中的文件
```

## 同步元数据

系统会在本地 WebDAV 文件夹中创建 `.webdav-sync-metadata.json` 文件，记录：

```json
{
  "files": {
    "image.jpg": {
      "hash": "abc123...",      // SHA-256 哈希
      "size": 102400,           // 文件大小
      "mtime": 1234567890,      // 本地修改时间
      "syncTime": 1234567890    // 同步时间
    }
  },
  "lastSyncTime": 1234567890    // 最后同步时间
}
```

**优化特性**：
- 如果文件大小未变，复用上次的哈希值
- 避免每次都重新计算哈希，提升性能
- 跟踪同步状态，实现增量同步

## 同步流程

### 上传文件流程

```
用户上传文件
    ↓
保存到本地 WebDAV 文件夹
    ↓
记录文件元数据（哈希、大小、时间）
    ↓
[如果启用"上传时自动同步"]
    ↓
检查远程目录是否存在
    ↓
上传文件到远程 WebDAV
    ↓
更新同步时间戳
```

### 删除文件流程

```
用户删除文件
    ↓
删除本地 WebDAV 文件夹中的文件
    ↓
[根据删除策略]
    ↓
├─ auto: 自动删除远程文件
├─ keep: 保留远程文件（不操作）
└─ manual: 询问用户是否删除远程
```

### 批量同步流程

```
用户触发批量同步
    ↓
加载同步元数据
    ↓
扫描本地 WebDAV 文件夹
    ↓
计算文件哈希（复用缓存）
    ↓
对比元数据，识别变化的文件
    ↓
上传新增或修改的文件
    ↓
跳过已同步的文件
    ↓
更新元数据
    ↓
显示同步结果统计
```

## 技术实现

### 核心类

#### MediaLibrary_WebDAVSync

同步管理类，参考 flymd 项目实现：

```php
// 列出本地文件
$items = $sync->listLocalFiles($subPath);

// 保存上传的文件到本地
$result = $sync->saveUploadedFile($file, $subPath);

// 同步单个文件到远程
$sync->syncFileToRemote($relativePath);

// 删除本地文件
$sync->deleteLocalFile($relativePath);

// 删除远程文件
$sync->deleteRemoteFile($relativePath);

// 批量同步所有文件
$result = $sync->syncAllToRemote($progressCallback);
```

**关键方法**：
- `scanLocalFiles()` - 递归扫描本地文件
- `calculateFileHash()` - 计算文件 SHA-256 哈希
- `loadMetadata()` / `saveMetadata()` - 管理同步元数据
- `ensureRemoteDirectory()` - 确保远程目录存在

#### MediaLibrary_WebDAVClient

WebDAV 客户端类，用于远程操作：

```php
// 上传文件到远程
$client->uploadFile($remotePath, $localFile, $mime);

// 删除远程文件
$client->delete($remotePath);

// 创建远程目录
$client->createDirectory($path);
```

### 前端处理

WebDAV 文件的列表请求会返回本地文件夹的内容：

```javascript
// AJAX 请求
MediaLibraryAjax.request('webdav_list', {
    path: '/subfolder'
}, function(response) {
    // response.data.items 包含本地文件列表
});
```

## 性能优化

### 1. 哈希缓存

- 文件大小未变时复用上次的哈希
- 避免重复计算大文件的哈希
- 显著提升扫描速度

### 2. 增量同步

- 只同步变化的文件
- 跳过已同步且未修改的文件
- 通过哈希和时间戳判断

### 3. 元数据管理

- 使用 JSON 文件存储元数据
- 快速加载和更新
- 减少数据库查询

### 4. 批量操作

- 支持批量同步
- 显示进度反馈
- 可中断和恢复

## 安全注意事项

1. **文件夹权限**
   - 确保本地 WebDAV 文件夹有正确的权限（755）
   - PHP 进程用户需要读写权限

2. **密码存储**
   - WebDAV 密码存储在数据库中
   - 确保数据库安全

3. **SSL 证书**
   - 生产环境建议使用有效的 SSL 证书
   - 避免中间人攻击

4. **删除策略**
   - 谨慎使用"自动同步删除"
   - 避免误删远程备份

5. **元数据文件**
   - `.webdav-sync-metadata.json` 不应被外部访问
   - 可通过 `.htaccess` 保护

## 常见问题

### Q: 本地 WebDAV 文件夹路径如何填写？

A: 填写服务器上的**绝对路径**：
- Windows: `E:\webdav` 或 `D:\www\webdav`
- Linux: `/var/www/webdav` 或 `/home/user/webdav`

### Q: 同步失败怎么办？

A: 检查以下几点：
- 本地文件夹是否存在且有权限
- 远程 WebDAV 服务器配置是否正确
- 网络连接是否稳定
- 查看日志文件获取详细错误信息

### Q: 如何查看同步状态？

A: 查看本地 WebDAV 文件夹中的 `.webdav-sync-metadata.json` 文件，其中记录了所有文件的同步状态。

### Q: 可以手动编辑本地 WebDAV 文件夹吗？

A: 可以。可以直接添加、修改、删除文件，系统会自动识别变化并在下次同步时处理。

### Q: 同步冲突如何处理？

A: 根据配置的冲突策略：
- `newest`：比较文件修改时间，使用最新的
- `local`：总是使用本地文件
- `remote`：总是使用远程文件

### Q: 删除本地文件后如何恢复？

A:
- 如果启用了远程同步且删除策略为 `keep`，可以从远程重新下载
- 如果删除策略为 `auto`，远程文件也会被删除，无法恢复
- 建议定期备份重要文件

## 与 flymd 项目的对比

本实现参考了 flymd 项目的同步机制：

| 特性 | flymd (TypeScript) | MediaLibrary (PHP) |
|------|-------------------|-------------------|
| 文件哈希 | ✅ SHA-256 | ✅ SHA-256 |
| 哈希缓存 | ✅ 大小未变复用 | ✅ 大小未变复用 |
| 元数据管理 | ✅ JSON 文件 | ✅ JSON 文件 |
| 增量同步 | ✅ 支持 | ✅ 支持 |
| 冲突处理 | ✅ 多种策略 | ✅ 多种策略 |
| 重命名检测 | ✅ 哈希匹配 | ⏳ 计划中 |
| 目录剪枝 | ✅ 支持 | ⏳ 计划中 |
| 并发同步 | ✅ 5个并发 | ⏳ 计划中 |

## 开发计划

- [x] 本地 WebDAV 文件夹管理
- [x] 文件上传到本地
- [x] 自动同步到远程
- [x] 删除同步策略
- [x] 冲突处理策略
- [x] 哈希缓存优化
- [ ] 重命名检测（哈希匹配）
- [ ] 并发同步（提升大量文件同步速度）
- [ ] 同步进度 UI
- [ ] 同步历史记录
- [ ] 定时同步任务

## 更新日志

### v0.3.0 (2025-01-25) - 本地优先架构

- ✅ 重构为本地优先架构
- ✅ 本地 WebDAV 文件夹作为主要存储
- ✅ 远程 WebDAV 作为备份同步
- ✅ 参考 flymd 实现智能同步
- ✅ 支持文件哈希、元数据缓存
- ✅ 支持冲突处理和删除策略
- ✅ 移除左侧栏 WebDAV 操作
- ✅ 更新配置界面

### v0.2.0 (2025-01-24)

- ✅ 添加本地到 WebDAV 批量同步功能
- ✅ 添加同步配置选项

### v0.1.0 (2025-01-24)

- ✅ 实现 WebDAV 客户端基础功能
- ✅ 添加 WebDAV 管理界面

---

**参考项目**：[flymd](https://github.com/yourusername/flymd) - 基于 Tauri 的 Markdown 笔记应用，实现了优秀的 WebDAV 双向同步机制。
