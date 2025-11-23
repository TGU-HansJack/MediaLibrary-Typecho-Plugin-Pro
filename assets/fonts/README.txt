媒体库插件 - 中文字体文件说明
=================================

为了在图片上添加中文水印，您需要在此目录下放置中文字体文件。

支持的字体文件格式：
- .ttf (TrueType Font)
- .ttc (TrueType Collection)
- .otf (OpenType Font)

推荐的中文字体：
1. 微软雅黑 (msyh.ttf 或 msyh.ttc)
2. 黑体 (simhei.ttf)
3. 楷体 (simkai.ttf)
4. 宋体 (simsun.ttc)
5. 思源黑体 (SourceHanSansSC-Regular.otf)
6. Noto Sans SC (NotoSansSC-Regular.otf)

获取字体文件的方法：
===================

方法一：从 Windows 系统复制
---------------------------
1. 打开 C:\Windows\Fonts 目录
2. 找到以下字体文件：
   - 微软雅黑: msyh.ttc
   - 黑体: simhei.ttf
   - 宋体: simsun.ttc
3. 复制字体文件到本目录

方法二：从 macOS 系统复制
-------------------------
1. 打开 /Library/Fonts 或 /System/Library/Fonts 目录
2. 找到中文字体文件（如 STHeiti Light.ttc）
3. 复制字体文件到本目录

方法三：从 Linux 系统复制
-------------------------
1. 打开 /usr/share/fonts 目录
2. 查找子目录，如：
   - /usr/share/fonts/truetype/wqy/
   - /usr/share/fonts/opentype/noto/
3. 复制字体文件到本目录

方法四：下载免费中文字体
-------------------------
1. 思源黑体 (Source Han Sans)
   下载地址: https://github.com/adobe-fonts/source-han-sans

2. Noto Sans CJK
   下载地址: https://www.google.com/get/noto/#sans-hans

3. 文泉驿微米黑
   下载地址: http://wenq.org/wqy2/index.cgi?MicroHei

安装后测试：
===========
1. 将字体文件放入本目录
2. 在媒体库中选择一张图片
3. 点击"添加水印"按钮
4. 选择文本水印并输入中文
5. 应用水印

注意事项：
=========
- 字体文件需要有读取权限
- 确保字体文件名正确（区分大小写）
- 如果仍然无法显示中文，请检查服务器的 GD 库是否支持 FreeType
- 系统也会自动尝试使用系统自带的中文字体作为备选

如果没有字体文件：
=================
插件会尝试使用系统字体，顺序如下：
1. 插件 fonts 目录下的字体
2. Windows 系统字体 (C:\Windows\Fonts)
3. Linux 系统字体 (/usr/share/fonts)
4. macOS 系统字体 (/Library/Fonts)

如果以上都找不到，添加中文水印时会提示错误信息。
