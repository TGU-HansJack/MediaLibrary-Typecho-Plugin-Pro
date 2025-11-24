# WebDAV 定时同步配置指南

本文档详细说明如何配置 WebDAV 定时同步功能。

## 📋 目录

- [概述](#概述)
- [配置步骤](#配置步骤)
- [方式一：Linux Crontab](#方式一linux-crontab)
- [方式二：Windows 任务计划](#方式二windows-任务计划)
- [方式三：URL 触发](#方式三url-触发)
- [日志查看](#日志查看)
- [常见问题](#常见问题)

---

## 概述

定时同步功能允许系统自动在指定时间间隔执行 WebDAV 同步任务，无需手动触发。这对于需要定期备份文件到远程服务器的场景非常有用。

### 工作原理

```
定时任务触发 → 检查同步间隔 → 执行批量同步 → 记录日志 → 更新同步时间
```

### 同步间隔控制

- 可在插件设置中配置最小同步间隔（默认 3600 秒 = 1 小时）
- 即使 cron 任务频繁执行，也会根据间隔时间智能跳过
- 防止过于频繁的同步造成服务器负载

---

## 配置步骤

### 1. 启用定时同步模式

在 Typecho 后台 → 插件管理 → MediaLibrary 设置：

1. **启用 WebDAV 功能**：勾选"启用 WebDAV"
2. **启用自动同步**：勾选"启用自动同步"
3. **选择同步模式**：选择"定时同步（需要配置系统定时任务）"
4. **设置同步间隔**：例如 `3600`（1小时）
5. **复制 Cron 密钥**：记录"Cron 任务密钥"，稍后会用到

### 2. 配置系统定时任务

根据服务器操作系统选择对应方式：

---

## 方式一：Linux Crontab

### 基本配置

```bash
# 编辑 crontab
crontab -e

# 添加以下任一配置
```

### 示例配置

#### 每小时执行一次
```bash
0 * * * * /usr/bin/php /var/www/html/usr/plugins/MediaLibrary/cron-webdav-sync.php >> /var/www/html/usr/plugins/MediaLibrary/logs/cron-sync.log 2>&1
```

#### 每天凌晨 2 点执行
```bash
0 2 * * * /usr/bin/php /var/www/html/usr/plugins/MediaLibrary/cron-webdav-sync.php >> /var/www/html/usr/plugins/MediaLibrary/logs/cron-sync.log 2>&1
```

#### 每 30 分钟执行一次
```bash
*/30 * * * * /usr/bin/php /var/www/html/usr/plugins/MediaLibrary/cron-webdav-sync.php >> /var/www/html/usr/plugins/MediaLibrary/logs/cron-sync.log 2>&1
```

#### 每周一凌晨 3 点执行
```bash
0 3 * * 1 /usr/bin/php /var/www/html/usr/plugins/MediaLibrary/cron-webdav-sync.php >> /var/www/html/usr/plugins/MediaLibrary/logs/cron-sync.log 2>&1
```

### Crontab 时间格式说明

```
┌───────────── 分钟 (0 - 59)
│ ┌───────────── 小时 (0 - 23)
│ │ ┌───────────── 日期 (1 - 31)
│ │ │ ┌───────────── 月份 (1 - 12)
│ │ │ │ ┌───────────── 星期 (0 - 7，0 和 7 都表示周日)
│ │ │ │ │
* * * * * 命令
```

### 常用 Crontab 表达式

| 表达式 | 说明 |
|--------|------|
| `0 * * * *` | 每小时执行 |
| `*/30 * * * *` | 每 30 分钟执行 |
| `0 2 * * *` | 每天凌晨 2 点执行 |
| `0 0 * * 0` | 每周日午夜执行 |
| `0 0 1 * *` | 每月 1 号午夜执行 |

### 验证 Crontab 配置

```bash
# 查看当前用户的 crontab
crontab -l

# 查看 cron 日志（不同系统路径可能不同）
tail -f /var/log/cron
# 或
tail -f /var/log/syslog | grep CRON
```

### 手动测试

```bash
# 直接执行脚本测试
php /var/www/html/usr/plugins/MediaLibrary/cron-webdav-sync.php

# 查看输出
cat /var/www/html/usr/plugins/MediaLibrary/logs/cron-sync.log
```

---

## 方式二：Windows 任务计划

### 使用任务计划程序

#### 1. 打开任务计划程序
- 按 `Win + R`，输入 `taskschd.msc`，回车

#### 2. 创建基本任务
- 点击右侧"创建基本任务"
- 名称：`WebDAV Sync`
- 描述：`MediaLibrary WebDAV 定时同步任务`

#### 3. 设置触发器
- 选择"每天"、"每周"或"每小时"
- 设置开始时间

#### 4. 设置操作
- 选择"启动程序"
- 程序或脚本：
  ```
  C:\php\php.exe
  ```
- 添加参数：
  ```
  E:\www\typecho\usr\plugins\MediaLibrary\cron-webdav-sync.php
  ```
- 起始于（可选）：
  ```
  E:\www\typecho
  ```

#### 5. 完成设置
- 勾选"完成时打开此任务的属性对话框"
- 在"常规"选项卡：
  - 勾选"不管用户是否登录都要运行"
  - 勾选"使用最高权限运行"

### 使用 schtasks 命令

```cmd
REM 每天 2:00 AM 执行
schtasks /create /tn "WebDAV Sync" /tr "C:\php\php.exe E:\www\typecho\usr\plugins\MediaLibrary\cron-webdav-sync.php" /sc daily /st 02:00

REM 每小时执行
schtasks /create /tn "WebDAV Sync Hourly" /tr "C:\php\php.exe E:\www\typecho\usr\plugins\MediaLibrary\cron-webdav-sync.php" /sc hourly

REM 查看任务
schtasks /query /tn "WebDAV Sync"

REM 手动运行任务
schtasks /run /tn "WebDAV Sync"

REM 删除任务
schtasks /delete /tn "WebDAV Sync" /f
```

### 手动测试

```cmd
REM 在命令提示符中执行
cd /d E:\www\typecho
C:\php\php.exe usr\plugins\MediaLibrary\cron-webdav-sync.php

REM 查看日志
type usr\plugins\MediaLibrary\logs\cron-sync.log
```

---

## 方式三：URL 触发

### 适用场景

- 无法配置系统 cron 的虚拟主机
- 需要从外部触发同步
- 使用第三方定时任务服务

### 触发 URL

```
https://your-site.com/usr/plugins/MediaLibrary/cron-webdav-sync.php?key=YOUR_CRON_KEY
```

**注意**：
- 将 `your-site.com` 替换为实际域名
- 将 `YOUR_CRON_KEY` 替换为插件设置中的"Cron 任务密钥"

### 使用 curl 定时触发

#### Linux Crontab
```bash
# 每小时执行一次
0 * * * * curl -s "https://your-site.com/usr/plugins/MediaLibrary/cron-webdav-sync.php?key=YOUR_KEY" >> /var/log/webdav-sync.log 2>&1
```

#### Windows 任务计划
```cmd
curl -s "https://your-site.com/usr/plugins/MediaLibrary/cron-webdav-sync.php?key=YOUR_KEY"
```

### 使用在线 Cron 服务

推荐的免费服务：
- **cron-job.org** - https://cron-job.org
- **EasyCron** - https://www.easycron.com
- **Uptime Robot** - https://uptimerobot.com（监控功能附带定时请求）

配置步骤（以 cron-job.org 为例）：
1. 注册账号
2. 创建新 Cron Job
3. URL：`https://your-site.com/usr/plugins/MediaLibrary/cron-webdav-sync.php?key=YOUR_KEY`
4. 设置执行间隔
5. 保存

---

## 日志查看

### 日志文件位置

```
/usr/plugins/MediaLibrary/logs/cron-sync.log       # 主日志
/usr/plugins/MediaLibrary/logs/last-sync-time.txt  # 最后同步时间戳
/usr/plugins/MediaLibrary/logs/medialibrary.log    # 详细同步日志
```

### 查看日志（Linux）

```bash
# 查看最新日志
tail -n 50 /path/to/usr/plugins/MediaLibrary/logs/cron-sync.log

# 实时查看日志
tail -f /path/to/usr/plugins/MediaLibrary/logs/cron-sync.log

# 查看最后同步时间
cat /path/to/usr/plugins/MediaLibrary/logs/last-sync-time.txt
date -d @$(cat /path/to/usr/plugins/MediaLibrary/logs/last-sync-time.txt)
```

### 查看日志（Windows）

```cmd
REM 查看日志
type E:\www\typecho\usr\plugins\MediaLibrary\logs\cron-sync.log

REM 查看最后 20 行
powershell Get-Content E:\www\typecho\usr\plugins\MediaLibrary\logs\cron-sync.log -Tail 20
```

### 日志格式

```
[2025-11-25 14:30:00] [INFO] ==================== WebDAV 定时同步任务开始 ====================
[2025-11-25 14:30:00] [INFO] 读取插件配置...
[2025-11-25 14:30:00] [INFO] 配置检查通过，开始同步...
[2025-11-25 14:30:00] [INFO] 本地路径: /var/www/webdav
[2025-11-25 14:30:00] [INFO] 远程地址: https://example.com/remote.php/dav/files/user
[2025-11-25 14:30:00] [INFO] 开始批量同步所有文件...
[2025-11-25 14:30:01] [INFO] 同步进度: [1/100] image1.jpg
[2025-11-25 14:30:02] [INFO] 同步进度: [2/100] image2.jpg
...
[2025-11-25 14:30:45] [INFO] ==================== 同步完成 ====================
[2025-11-25 14:30:45] [INFO] 总文件数: 100
[2025-11-25 14:30:45] [INFO] 已同步: 25
[2025-11-25 14:30:45] [INFO] 已跳过: 75
[2025-11-25 14:30:45] [INFO] 失败: 0
[2025-11-25 14:30:45] [INFO] 耗时: 45.32 秒
```

---

## 常见问题

### Q1: 定时任务没有执行？

**检查步骤**：

1. **确认同步模式**：
   ```bash
   # 查看插件配置
   mysql -u user -p database -e "SELECT value FROM typecho_options WHERE name='plugin:MediaLibrary';" | grep webdavSyncMode
   ```

2. **手动执行脚本**：
   ```bash
   php /path/to/cron-webdav-sync.php
   ```
   查看是否有错误输出

3. **检查 PHP 路径**：
   ```bash
   which php
   # 或
   whereis php
   ```

4. **检查文件权限**：
   ```bash
   chmod +x /path/to/cron-webdav-sync.php
   chmod 755 /path/to/usr/plugins/MediaLibrary/logs
   ```

5. **查看 cron 日志**：
   ```bash
   grep -i cron /var/log/syslog
   ```

### Q2: 同步间隔不生效？

**原因分析**：

定时任务会检查 `last-sync-time.txt` 文件，只有距离上次同步超过设定间隔才会执行。

**解决方法**：

```bash
# 删除最后同步时间记录，强制下次执行
rm /path/to/usr/plugins/MediaLibrary/logs/last-sync-time.txt

# 或修改时间戳为更早时间
echo "0" > /path/to/usr/plugins/MediaLibrary/logs/last-sync-time.txt
```

### Q3: URL 触发返回 403 Forbidden？

**可能原因**：

1. Cron 密钥错误
2. 未配置密钥

**解决方法**：

1. 检查插件设置中的"Cron 任务密钥"
2. 确保 URL 中的 `key` 参数与配置一致
3. 如果修改了密钥，需要更新 cron 任务

### Q4: 日志文件过大？

**清理日志**：

```bash
# 备份并清空日志
cp cron-sync.log cron-sync.log.bak
> cron-sync.log

# 或使用 logrotate（Linux）
cat > /etc/logrotate.d/medialibrary-webdav <<EOF
/path/to/usr/plugins/MediaLibrary/logs/cron-sync.log {
    weekly
    rotate 4
    compress
    missingok
    notifempty
}
EOF
```

### Q5: 权限错误？

**Linux**：

```bash
# 设置正确的所有者和权限
chown -R www-data:www-data /path/to/usr/plugins/MediaLibrary/logs
chmod -R 755 /path/to/usr/plugins/MediaLibrary/logs
chmod 644 /path/to/usr/plugins/MediaLibrary/logs/*.log
```

**Windows**：
- 右键 logs 文件夹 → 属性 → 安全
- 添加 PHP 运行用户的完全控制权限

### Q6: 时区不正确？

脚本中已设置时区为 `Asia/Shanghai`，如需修改：

```php
// 编辑 cron-webdav-sync.php
date_default_timezone_set('Asia/Shanghai'); // 改为你的时区
```

常用时区：
- `Asia/Shanghai` - 中国
- `America/New_York` - 美国东部
- `Europe/London` - 英国
- `Asia/Tokyo` - 日本

---

## 高级配置

### 多站点配置

如果有多个 Typecho 站点需要同步：

```bash
# Crontab 示例
0 * * * * /usr/bin/php /var/www/site1/usr/plugins/MediaLibrary/cron-webdav-sync.php
0 * * * * /usr/bin/php /var/www/site2/usr/plugins/MediaLibrary/cron-webdav-sync.php
```

### 错误通知

如果希望同步失败时发送邮件通知：

```bash
# 修改 crontab，添加 MAILTO
MAILTO=your-email@example.com
0 * * * * /usr/bin/php /path/to/cron-webdav-sync.php
```

或使用脚本封装：

```bash
#!/bin/bash
# sync-and-notify.sh

php /path/to/cron-webdav-sync.php
if [ $? -ne 0 ]; then
    echo "WebDAV 同步失败！" | mail -s "同步失败通知" your-email@example.com
fi
```

### 性能优化

如果文件数量巨大，可以考虑：

1. **增加 PHP 内存限制**：
   ```bash
   php -d memory_limit=512M /path/to/cron-webdav-sync.php
   ```

2. **增加执行时间限制**：
   ```bash
   php -d max_execution_time=0 /path/to/cron-webdav-sync.php
   ```

---

## 总结

定时同步功能提供了三种配置方式：

| 方式 | 优点 | 缺点 | 适用场景 |
|------|------|------|----------|
| **Linux Crontab** | 可靠、精确 | 需要 SSH 权限 | VPS、独立服务器 |
| **Windows 任务计划** | 原生支持 | 仅限 Windows | Windows 服务器 |
| **URL 触发** | 简单、跨平台 | 依赖外部服务 | 虚拟主机、无 SSH |

选择适合自己环境的方式，配合合理的同步间隔，即可实现自动化的 WebDAV 文件备份。

---

**相关文档**：
- [WEBDAV_README.md](WEBDAV_README.md) - WebDAV 功能完整文档
- [WEBDAV_TEST.md](WEBDAV_TEST.md) - 连接测试和故障排除

**技术支持**：
- GitHub Issues: https://github.com/your-repo/issues
- 官方文档: http://www.hansjack.com/
