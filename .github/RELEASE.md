# GitHub Actions 自动发布说明

本项目已配置 GitHub Actions 自动构建和发布流程。

## 功能特性

- ✅ 自动打包插件为 ZIP 文件
- ✅ 自动生成 Release 版本
- ✅ 自动生成更新日志（基于 commit 历史）
- ✅ 生成 SHA256 校验和文件
- ✅ 支持手动触发和自动触发

## 使用方法

### 方式一：推送标签触发（推荐）

1. 确保代码已提交并推送到远程仓库
2. 创建并推送 tag：

```bash
# 创建 tag
git tag v1.0.0

# 推送 tag 到远程仓库
git push origin v1.0.0
```

3. GitHub Actions 会自动触发，完成以下操作：
   - 打包插件文件
   - 生成更新日志
   - 创建 Release
   - 上传 ZIP 文件和 SHA256 校验和

### 方式二：手动触发

1. 访问项目的 GitHub Actions 页面
2. 选择 "Create Release" 工作流
3. 点击 "Run workflow"
4. 输入 tag 名称（例如：v1.0.0）
5. 点击运行

## 版本号规范

建议使用语义化版本号（Semantic Versioning）：

- `v1.0.0` - 主版本.次版本.修订号
- `v1.0.0-beta.1` - 预发布版本
- `v1.0.0-rc.1` - 候选发布版本

## 打包文件说明

工作流会自动打包以下文件：

- ✅ 所有 PHP 文件
- ✅ assets、lib、templates、includes 目录
- ✅ README.md 文档
- ❌ 排除 .git、.github 等开发文件
- ❌ 排除 .gitignore、.DS_Store 等系统文件

## 更新日志生成

更新日志会自动从 Git commit 历史生成，格式为：

```
## 更新内容

- commit 信息 1 (commit hash)
- commit 信息 2 (commit hash)
```

**建议**：编写清晰的 commit 信息，因为它们会直接显示在 Release 页面。

良好的 commit 信息示例：
```bash
git commit -m "feat: 添加视频压缩功能"
git commit -m "fix: 修复图片上传失败的问题"
git commit -m "docs: 更新安装说明"
```

## 校验和验证

每个发布都会包含 SHA256 校验和文件，用户可以验证下载的文件完整性：

```bash
# Linux/Mac
sha256sum -c MediaLibrary-v1.0.0.zip.sha256

# Windows PowerShell
Get-FileHash MediaLibrary-v1.0.0.zip -Algorithm SHA256
```

## 注意事项

1. Tag 名称必须以 `v` 开头（例如：v1.0.0）
2. 每个 tag 只能发布一次，重复的 tag 会导致失败
3. 确保 `GITHUB_TOKEN` 有足够的权限（默认已配置）
4. 如果需要修改打包内容，编辑 [.github/workflows/release.yml](.github/workflows/release.yml#L69) 文件的 `rsync` 排除规则

## 工作流文件

工作流配置文件位置：[.github/workflows/release.yml](.github/workflows/release.yml)
