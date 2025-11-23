# 媒体库插件 - 系统检测与更新功能说明

## 新增功能

### 1. 版本信息与更新检测

**位置**: 插件设置页面顶部

**功能**:
- 显示当前插件版本
- 一键检查 GitHub 更新
- 查看更新日志和发布信息
- 自动下载和安装更新（带备份机制）
- 快速访问 GitHub 仓库

**使用方法**:
1. 进入插件设置页面
2. 点击"检查更新"按钮
3. 如果有新版本，会显示详细的更新信息
4. 点击"立即更新"按钮自动安装
5. 更新完成后会自动刷新页面

### 2. 系统环境检测（基础）

**位置**: 插件设置页面

**检测内容**:
- GetID3 库（音视频信息读取）
- ExifTool 库（EXIF信息处理）
- EXIF 扩展（图片元数据读取）
- Fileinfo 扩展（文件类型检测）
- GD 库（图片处理）
- ImageMagick（高级图片处理）
- FFmpeg（视频处理）

### 3. 详细系统检测（可折叠）

**位置**: 点击"显示详细检测信息"按钮展开

#### 3.1 系统信息
显示以下信息：
- PHP 版本
- 操作系统
- 服务器软件
- Typecho 版本
- 插件版本
- 内存限制
- 最大上传大小
- POST 最大大小
- 最大执行时间

#### 3.2 PHP 扩展检测
详细检测以下扩展：
| 扩展名 | 描述 | 是否必需 | 状态 | 版本 |
|--------|------|----------|------|------|
| GD 库 | 图片处理库，用于裁剪、水印等功能 | 是 | ✓/✗ | 版本号 |
| ImageMagick | 高级图片处理库，支持更多格式 | 否 | ✓/✗ | 版本号 |
| EXIF 扩展 | 读取图片 EXIF 信息 | 否 | ✓/✗ | 版本号 |
| Fileinfo 扩展 | 检测文件类型 | 是 | ✓/✗ | 版本号 |
| Mbstring 扩展 | 多字节字符串处理，支持中文 | 是 | ✓/✗ | 版本号 |
| cURL 扩展 | HTTP 请求库，用于检查更新 | 否 | ✓/✗ | 版本号 |
| Zip 扩展 | 压缩文件处理，用于插件更新 | 否 | ✓/✗ | 版本号 |

#### 3.3 PHP 函数检测
检测关键 PHP 函数是否可用：
- exec() - 执行外部命令，用于 FFmpeg/ExifTool
- shell_exec() - 执行 shell 命令
- imagecreatefromjpeg() - GD 库 JPEG 支持
- imagecreatefrompng() - GD 库 PNG 支持
- imagecreatefromwebp() - GD 库 WebP 支持
- imagettftext() - GD 库 TrueType 字体支持，用于水印
- exif_read_data() - 读取 EXIF 数据
- file_get_contents() - 读取文件内容
- file_put_contents() - 写入文件内容

#### 3.4 文件完整性检测
检测插件核心文件是否完整：
- 显示发现的文件数 / 总文件数
- 列出缺失的文件（如果有）
- 可展开查看所有文件列表（包括文件大小）

检测的文件包括：
- `/Plugin.php` - 插件主文件
- `/panel.php` - 管理面板文件
- `/includes/AjaxHandler.php` - AJAX 处理器
- `/includes/ImageEditor.php` - 图片编辑器
- `/includes/PanelHelper.php` - 面板助手
- `/includes/EnvironmentCheck.php` - 环境检测
- `/assets/js/media-library.js` - 主要 JavaScript 文件
- `/assets/js/image-editor.js` - 图片编辑器 JavaScript
- `/assets/css/media-library.css` - 主样式文件

## 技术实现

### 核心文件

1. **includes/EnvironmentCheck.php**
   - 系统环境检测
   - PHP 扩展和函数检测
   - 文件完整性检测
   - 系统信息收集

2. **includes/PluginUpdater.php**
   - GitHub API 集成
   - 版本比较
   - 更新下载和安装
   - 自动备份机制

3. **update-handler.php**
   - AJAX 请求处理
   - 权限验证
   - 更新操作执行

4. **Plugin.php** (修改)
   - 添加详细检测界面
   - 集成 JavaScript 交互
   - 美化显示效果

## 更新机制

### 自动更新流程：

1. **检查更新**
   - 连接 GitHub API
   - 获取最新 Release 信息
   - 比较版本号

2. **下载更新**
   - 从 GitHub 下载 ZIP 文件
   - 保存到临时目录

3. **备份当前版本**
   - 将当前插件目录重命名为备份

4. **安装新版本**
   - 解压 ZIP 文件
   - 复制文件到插件目录

5. **清理和完成**
   - 删除临时文件和旧备份
   - 刷新页面显示新版本

### 安全措施：

- ✅ 管理员权限验证
- ✅ 下载地址验证（仅允许 GitHub）
- ✅ 自动备份机制
- ✅ 失败时自动恢复

## 配置要求

### 必需的扩展：
- GD 库
- Fileinfo 扩展
- Mbstring 扩展

### 可选的扩展：
- cURL 扩展（用于检查更新）
- Zip 扩展（用于自动更新）
- ImageMagick（高级图片处理）
- EXIF 扩展（EXIF 信息读取）

### 必需的函数：
- imagecreatefromjpeg()
- imagecreatefrompng()
- imagettftext()
- file_get_contents()
- file_put_contents()

## GitHub 仓库配置

**仓库地址**: https://github.com/TGU-HansJack/MediaLibrary-Typecho-Plugin-Pro

**更新机制**:
- 基于 GitHub Releases
- 使用 GitHub API 获取最新版本
- 支持版本号比较
- 显示完整的更新日志

## 故障排除

### 无法检查更新
**原因**: cURL 扩展未安装或 allow_url_fopen 被禁用
**解决方案**: 启用 cURL 扩展或在 php.ini 中设置 allow_url_fopen = On

### 无法自动更新
**原因**: Zip 扩展未安装
**解决方案**: 安装 PHP Zip 扩展或手动下载更新包安装

### 文件权限错误
**原因**: Web 服务器没有写入权限
**解决方案**: 设置插件目录权限为 755 或 777

### 更新失败后无法恢复
**原因**: 备份机制失败
**解决方案**: 检查是否有足够的磁盘空间，确保插件目录可写

## 使用建议

1. **定期检查更新**: 建议每周检查一次更新，及时获取新功能和安全修复

2. **更新前备份**: 虽然有自动备份，但建议在重要更新前手动备份整个插件目录

3. **测试环境验证**: 如果可能，先在测试环境中验证新版本

4. **查看更新说明**: 更新前仔细阅读 Release Notes，了解新功能和可能的兼容性问题

5. **系统检测**: 定期查看系统检测信息，确保所有必需的扩展和函数都可用

## 版本历史

- **free_version**: 当前版本，添加完整的系统检测和自动更新功能
