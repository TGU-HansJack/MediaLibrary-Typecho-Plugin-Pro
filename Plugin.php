<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/LogAction.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVServerAction.php';

/**
 * 媒体库管理插件，可以在后台对整体文件信息的查看和编辑、上传和删除，图片压缩和隐私检测，多媒体预览，文章编辑器中预览和插入的简单媒体库
 * 
 * @package MediaLibrary
 * @author HansJack
 * @version pro_0.1.1
 * @link http://www.hansjack.com/
 */
class MediaLibrary_Plugin implements Typecho_Plugin_Interface
{

    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        // 添加控制台菜单
        Helper::addPanel(3, 'MediaLibrary/panel.php', '媒体库', '媒体库管理', 'administrator');
        Helper::addAction('medialibrary-log', 'MediaLibrary_LogAction');

        // 注册 WebDAV 服务器 Action
        Helper::addAction('medialibrary-webdav', 'MediaLibrary_WebDAVServerAction');
        Helper::addRoute(
            'medialibrary-webdav',
            '/action/medialibrary-webdav',
            'MediaLibrary_WebDAVServerAction',
            'action',
            'do'
        );

        // 添加写作页面的媒体库组件
        Typecho_Plugin::factory('admin/write-post.php')->bottom = array('MediaLibrary_Plugin', 'addMediaLibraryToWritePage');
        Typecho_Plugin::factory('admin/write-page.php')->bottom = array('MediaLibrary_Plugin', 'addMediaLibraryToWritePage');

        return '媒体库插件激活成功！';
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        // 移除控制台菜单
        Helper::removePanel(3, 'MediaLibrary/panel.php');
        Helper::removeAction('medialibrary-log');

        // 移除 WebDAV 服务器 Action
        Helper::removeAction('medialibrary-webdav');
        Helper::removeRoute('medialibrary-webdav');

        return '媒体库插件已禁用！';
    }
    
    /**
     * 在写作页面添加媒体库
     */
    public static function addMediaLibraryToWritePage()
    {
        $pluginUrl = Helper::options()->pluginUrl . '/MediaLibrary';
        echo '<div id="media-library-container"></div>';
        echo '<script>
        if (typeof jQuery !== "undefined") {
            jQuery(document).ready(function($) {
                $.get("' . $pluginUrl . '/write-post-media.php", function(data) {
                    $("#media-library-container").html(data);
                });
            });
        }
        </script>';
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/EnvironmentCheck.php';
        require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/PluginUpdater.php';
        require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/Logger.php';
        // 显示版本信息和更新检测
        self::displayVersionInfo($form);

        // 系统环境检测
        $envInfo = MediaLibrary_EnvironmentCheck::checkEnvironment();

        // 环境状态显示
        self::displayEnvironmentInfo($form, $envInfo);

        // 显示详细检测信息（默认折叠）
        self::displayDetailedChecks($form);

        // 添加配置选项
        self::addConfigOptions($form, $envInfo);

        // 日志查看器
        self::displayLogViewer();

        // 添加 JavaScript 和 CSS
        self::addConfigPageAssets();
    }

    /**
     * 显示版本信息和更新检测
     */
    private static function displayVersionInfo($form)
    {
        $currentVersion = MediaLibrary_EnvironmentCheck::getCurrentVersion();
        $repoUrl = MediaLibrary_PluginUpdater::getRepoUrl();

        $versionHtml = '<div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:4px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">';
        $versionHtml .= '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">';

        // PHP 语言徽章
        $versionHtml .= '<img src="https://img.shields.io/badge/PHP-777BB4?logo=php&logoColor=white" alt="PHP" style="height:16px;display:block;">';

        // 版本号徽章
        $versionHtml .= '<img src="https://img.shields.io/badge/version-' . urlencode($currentVersion) . '-blue" alt="Version" style="height:16px;display:block;">';

        // GitHub 仓库徽章
        $versionHtml .= '<a href="' . htmlspecialchars($repoUrl) . '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;text-decoration:none;line-height:1;">';
        $versionHtml .= '<img src="https://img.shields.io/badge/GitHub-MediaLibrary-181717?logo=github&logoColor=white" alt="GitHub Repository" style="height:16px;display:block;">';
        $versionHtml .= '</a>';

        // License 徽章
        $versionHtml .= '<img src="https://img.shields.io/badge/license-MIT-green" alt="License" style="height:16px;display:block;">';

        $versionHtml .= '</div>';
        $versionHtml .= '</div>';

        echo $versionHtml;
    }

    /**
     * 显示详细检测信息
     */
    private static function displayDetailedChecks($form)
    {
        $detailHtml = '<div style="margin-bottom:20px;">';

        // 添加折叠按钮
        $detailHtml .= '<button type="button" id="toggle-detailed-checks" class="btn btn-s" style="margin-bottom:10px;">显示详细检测信息</button>';

        // 详细检测信息容器（默认隐藏）
        $detailHtml .= '<div id="detailed-checks-container" style="display:none;">';

        // 系统信息
        $systemInfo = MediaLibrary_EnvironmentCheck::getSystemInfo();
        $detailHtml .= '<div class="ml-info-box" data-section="system-info" style="background:#f9f9f9;padding:15px;border:1px solid #ddd;border-radius:4px;margin-bottom:15px;">';
        $detailHtml .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">';
        $detailHtml .= '<h4 style="margin:0;color:#333;">📊 系统信息</h4>';
        $detailHtml .= '<button type="button" class="ml-info-copy-btn" data-target="system-info" title="复制系统信息">Copy</button>';
        $detailHtml .= '</div>';
        $detailHtml .= '<table style="width:100%;border-collapse:collapse;">';
        foreach ($systemInfo as $name => $value) {
            $detailHtml .= '<tr>';
            $detailHtml .= '<td style="padding:5px 0;border-bottom:1px solid #eee;width:180px;font-weight:500;">' . htmlspecialchars($name) . '</td>';
            $detailHtml .= '<td style="padding:5px 0;border-bottom:1px solid #eee;color:#666;">' . htmlspecialchars($value) . '</td>';
            $detailHtml .= '</tr>';
        }
        $detailHtml .= '</table></div>';

        // PHP 扩展检测
        $extensions = MediaLibrary_EnvironmentCheck::checkPHPExtensions();
        $detailHtml .= '<div class="ml-info-box" data-section="php-extensions" style="background:#f9f9f9;padding:15px;border:1px solid #ddd;border-radius:4px;margin-bottom:15px;">';
        $detailHtml .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">';
        $detailHtml .= '<h4 style="margin:0;color:#333;">🔌 PHP 扩展检测</h4>';
        $detailHtml .= '<button type="button" class="ml-info-copy-btn" data-target="php-extensions" title="复制PHP扩展信息">Copy</button>';
        $detailHtml .= '</div>';
        $detailHtml .= '<table style="width:100%;border-collapse:collapse;">';
        $detailHtml .= '<thead><tr style="background:#e9ecef;">';
        $detailHtml .= '<th style="padding:8px;text-align:left;border-bottom:2px solid #ddd;">扩展名称</th>';
        $detailHtml .= '<th style="padding:8px;text-align:left;border-bottom:2px solid #ddd;">描述</th>';
        $detailHtml .= '<th style="padding:8px;text-align:center;border-bottom:2px solid #ddd;width:80px;">必需</th>';
        $detailHtml .= '<th style="padding:8px;text-align:center;border-bottom:2px solid #ddd;width:80px;">状态</th>';
        $detailHtml .= '<th style="padding:8px;text-align:center;border-bottom:2px solid #ddd;width:100px;">版本</th>';
        $detailHtml .= '</tr></thead><tbody>';

        foreach ($extensions as $ext) {
            $statusIcon = $ext['status'] ? '<span style="color:#46b450;">✓</span>' : '<span style="color:#dc3232;">✗</span>';
            $requiredText = $ext['required'] ? '<span style="color:#dc3232;">是</span>' : '<span style="color:#666;">否</span>';
            $version = $ext['version'] ? $ext['version'] : '-';

            $detailHtml .= '<tr>';
            $detailHtml .= '<td style="padding:8px;border-bottom:1px solid #eee;font-weight:500;">' . htmlspecialchars($ext['name']) . '</td>';
            $detailHtml .= '<td style="padding:8px;border-bottom:1px solid #eee;color:#666;font-size:13px;">' . htmlspecialchars($ext['description']) . '</td>';
            $detailHtml .= '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center;">' . $requiredText . '</td>';
            $detailHtml .= '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center;font-size:16px;">' . $statusIcon . '</td>';
            $detailHtml .= '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center;color:#666;font-size:12px;">' . htmlspecialchars($version) . '</td>';
            $detailHtml .= '</tr>';
        }
        $detailHtml .= '</tbody></table></div>';

        // PHP 函数检测
        $functions = MediaLibrary_EnvironmentCheck::checkPHPFunctions();
        $detailHtml .= '<div class="ml-info-box" data-section="php-functions" style="background:#f9f9f9;padding:15px;border:1px solid #ddd;border-radius:4px;margin-bottom:15px;">';
        $detailHtml .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">';
        $detailHtml .= '<h4 style="margin:0;color:#333;">⚙️ PHP 函数检测</h4>';
        $detailHtml .= '<button type="button" class="ml-info-copy-btn" data-target="php-functions" title="复制PHP函数信息">Copy</button>';
        $detailHtml .= '</div>';
        $detailHtml .= '<table style="width:100%;border-collapse:collapse;">';
        $detailHtml .= '<thead><tr style="background:#e9ecef;">';
        $detailHtml .= '<th style="padding:8px;text-align:left;border-bottom:2px solid #ddd;">函数名称</th>';
        $detailHtml .= '<th style="padding:8px;text-align:left;border-bottom:2px solid #ddd;">描述</th>';
        $detailHtml .= '<th style="padding:8px;text-align:center;border-bottom:2px solid #ddd;width:80px;">必需</th>';
        $detailHtml .= '<th style="padding:8px;text-align:center;border-bottom:2px solid #ddd;width:80px;">状态</th>';
        $detailHtml .= '</tr></thead><tbody>';

        foreach ($functions as $func) {
            $statusIcon = $func['status'] ? '<span style="color:#46b450;">✓</span>' : '<span style="color:#dc3232;">✗</span>';
            $requiredText = $func['required'] ? '<span style="color:#dc3232;">是</span>' : '<span style="color:#666;">否</span>';

            $detailHtml .= '<tr>';
            $detailHtml .= '<td style="padding:8px;border-bottom:1px solid #eee;font-family:monospace;font-size:13px;">' . htmlspecialchars($func['name']) . '</td>';
            $detailHtml .= '<td style="padding:8px;border-bottom:1px solid #eee;color:#666;font-size:13px;">' . htmlspecialchars($func['description']) . '</td>';
            $detailHtml .= '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center;">' . $requiredText . '</td>';
            $detailHtml .= '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center;font-size:16px;">' . $statusIcon . '</td>';
            $detailHtml .= '</tr>';
        }
        $detailHtml .= '</tbody></table></div>';

        // 文件完整性检测
        $fileIntegrity = MediaLibrary_EnvironmentCheck::checkFileIntegrity();
        $integrityStatus = $fileIntegrity['found'] === $fileIntegrity['total'];
        $integrityColor = $integrityStatus ? '#46b450' : '#dc3232';

        $detailHtml .= '<div class="ml-info-box" data-section="file-integrity" style="background:#f9f9f9;padding:15px;border:1px solid #ddd;border-radius:4px;margin-bottom:15px;">';
        $detailHtml .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">';
        $detailHtml .= '<h4 style="margin:0;color:#333;">📁 文件完整性检测</h4>';
        $detailHtml .= '<button type="button" class="ml-info-copy-btn" data-target="file-integrity" title="复制文件完整性信息">Copy</button>';
        $detailHtml .= '</div>';
        $detailHtml .= '<p style="margin:0 0 10px 0;color:' . $integrityColor . ';font-weight:bold;">';
        $detailHtml .= '发现 ' . $fileIntegrity['found'] . ' / ' . $fileIntegrity['total'] . ' 个文件';
        if (!empty($fileIntegrity['missing'])) {
            $detailHtml .= ' (缺失 ' . count($fileIntegrity['missing']) . ' 个)';
        }
        $detailHtml .= '</p>';

        if (!empty($fileIntegrity['missing'])) {
            $detailHtml .= '<p style="margin:10px 0;color:#dc3232;"><strong>缺失的文件:</strong></p>';
            $detailHtml .= '<ul style="margin:5px 0;padding-left:20px;color:#dc3232;">';
            foreach ($fileIntegrity['missing'] as $missing) {
                $detailHtml .= '<li style="font-family:monospace;font-size:12px;">' . htmlspecialchars($missing) . '</li>';
            }
            $detailHtml .= '</ul>';
        }

        $detailHtml .= '<details style="margin-top:10px;"><summary style="cursor:pointer;color:#0073aa;">查看所有文件列表</summary>';
        $detailHtml .= '<table style="width:100%;border-collapse:collapse;margin-top:10px;">';
        foreach ($fileIntegrity['files'] as $file) {
            $statusIcon = $file['exists'] ? '<span style="color:#46b450;">✓</span>' : '<span style="color:#dc3232;">✗</span>';
            $size = $file['exists'] ? number_format($file['size'] / 1024, 2) . ' KB' : '-';

            $detailHtml .= '<tr>';
            $detailHtml .= '<td style="padding:5px;border-bottom:1px solid #eee;text-align:center;width:30px;">' . $statusIcon . '</td>';
            $detailHtml .= '<td style="padding:5px;border-bottom:1px solid #eee;font-family:monospace;font-size:12px;">' . htmlspecialchars($file['path']) . '</td>';
            $detailHtml .= '<td style="padding:5px;border-bottom:1px solid #eee;color:#666;font-size:12px;">' . htmlspecialchars($file['description']) . '</td>';
            $detailHtml .= '<td style="padding:5px;border-bottom:1px solid #eee;text-align:right;color:#666;font-size:12px;width:100px;">' . $size . '</td>';
            $detailHtml .= '</tr>';
        }
        $detailHtml .= '</table></details>';

        $detailHtml .= '</div>';

        $detailHtml .= '</div>'; // 结束 detailed-checks-container
        $detailHtml .= '</div>';

        echo $detailHtml;
    }

    /**
     * 显示日志查看器
     */
    private static function displayLogViewer()
    {
        $logFile = MediaLibrary_Logger::getLogFile();
        $isReadable = $logFile && is_file($logFile) && is_readable($logFile);
        $rawContent = '';
        $security = Helper::security();
        $clearLogUrl = $security->getIndex('/action/medialibrary-log');
        $emptyLogText = '暂无日志内容。';

        if ($isReadable) {
            $rawContent = (string) @file_get_contents($logFile);
        }

        $logSize = $isReadable ? self::formatLogSizeKiB(filesize($logFile)) : null;
        $logUpdated = $isReadable ? date('Y-m-d H:i:s', filemtime($logFile)) : null;
        $logMetaParts = array();

        if ($logUpdated) {
            $logMetaParts[] = '最后更新：' . $logUpdated;
        }
        if ($logSize) {
            $logMetaParts[] = '大小：' . $logSize;
        }

        $logMetaText = $logMetaParts ? implode(' ｜ ', $logMetaParts) : '日志文件尚未生成或无法读取。';
        $displayContent = trim($rawContent) !== '' ? htmlspecialchars($rawContent) : htmlspecialchars($emptyLogText);

        $logHtml = '<div class="ml-log-viewer">';
        $logHtml .= '<div class="ml-log-head">';
        $logHtml .= '<div><h4 style="margin:0 0 6px 0;">处理流程日志</h4>';
        $logHtml .= '<p style="margin:0;color:#666;font-size:13px;">以下内容来自日志文件，可直接滚动查看。</p></div>';
        $logHtml .= '<button type="button" class="ml-log-copy-btn" id="ml-copy-log-btn" title="复制日志内容">Copy</button>';
        $logHtml .= '</div>';
        $logHtml .= '<div class="ml-log-meta">日志文件位置：<code style="font-size:12px;">' . htmlspecialchars($logFile) . '</code>';
        $logHtml .= '<div class="ml-log-meta-extra" id="ml-log-meta-text">' . htmlspecialchars($logMetaText) . '</div></div>';
        $logHtml .= '<div class="ml-log-raw-wrap">';
        $logHtml .= '<pre class="ml-log-raw" data-empty-text="' . htmlspecialchars($emptyLogText, ENT_QUOTES) . '">' . $displayContent . '</pre>';
        $logHtml .= '</div>';
        $logHtml .= '</div>';

        echo $logHtml;
    }

    /**
     * 以 KiB 单位格式化日志大小
     *
     * @param int|float $bytes
     * @return string
     */
    private static function formatLogSizeKiB($bytes)
    {
        $bytes = max(0, (float) $bytes);
        return $bytes > 0
            ? number_format($bytes / 1024, 2) . ' KiB'
            : '0 KiB';
    }

    /**
     * 添加配置页面的 JavaScript 和 CSS
     */
    private static function addConfigPageAssets()
    {
        ob_start();
        Helper::options()->adminStaticUrl('js', 'jquery.js');
        $jquerySource = trim(ob_get_clean());

        if (!empty($jquerySource)) {
            echo '<script src="' . $jquerySource . '"></script>';
        }

        echo '<style>
.ml-log-viewer{background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;margin:20px 0 30px;box-shadow:0 1px 3px rgba(0,0,0,0.05);}
.ml-log-head{display:flex;justify-content:space-between;align-items:flex-start;gap:15px;flex-wrap:wrap;margin-bottom:10px;}
.ml-log-copy-btn{background:#0073aa;border:1px solid #0073aa;color:#fff;padding:6px 16px;border-radius:4px;font-size:13px;cursor:pointer;transition:all .2s;white-space:nowrap;}
.ml-log-copy-btn:hover{background:#005a87;border-color:#005a87;}
.ml-log-copy-btn.success{background:#46b450;border-color:#46b450;}
.ml-log-copy-btn[disabled]{opacity:.6;cursor:not-allowed;}
.ml-info-copy-btn{background:#0073aa;border:1px solid #0073aa;color:#fff;padding:4px 12px;border-radius:3px;font-size:12px;cursor:pointer;transition:all .2s;white-space:nowrap;}
.ml-info-copy-btn:hover{background:#005a87;border-color:#005a87;}
.ml-info-copy-btn.success{background:#46b450;border-color:#46b450;}
.ml-info-copy-btn[disabled]{opacity:.6;cursor:not-allowed;}
.ml-log-meta{font-size:12px;color:#777;margin-bottom:10px;line-height:1.6;}
.ml-log-meta code{font-size:12px;}
.ml-log-meta-extra{margin-top:4px;}
.ml-log-raw-wrap{border:1px solid #eee;background:#0f172a;color:#e2e8f0;border-radius:6px;max-height:420px;overflow:auto;padding:16px;font-family:SFMono-Regular,Consolas,\"Liberation Mono\",Menlo,monospace;font-size:13px;}
.ml-log-raw{margin:0;white-space:pre-wrap;word-break:break-word;}
.ml-log-raw-wrap::-webkit-scrollbar{width:8px;height:8px;}
.ml-log-raw-wrap::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.2);border-radius:4px;}
.ml-log-raw-wrap:hover::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.35);}
</style>';

        echo '<script>
jQuery(function($) {
    var $toggleBtn = $("#toggle-detailed-checks");
    if ($toggleBtn.length) {
        $toggleBtn.on("click", function() {
            var $container = $("#detailed-checks-container");
            if ($container.is(":visible")) {
                $container.slideUp();
                $toggleBtn.text("显示详细检测信息");
            } else {
                $container.slideDown();
                $toggleBtn.text("隐藏详细检测信息");
            }
        });
    }

    var $copyBtn = $("#ml-copy-log-btn");
    if ($copyBtn.length) {
        var originalText = $copyBtn.text();
        $copyBtn.on("click", function() {
            if ($copyBtn.prop("disabled")) {
                return;
            }

            var $logContent = $(".ml-log-raw");
            var logText = $logContent.text();

            // 使用 Clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(logText).then(function() {
                    $copyBtn.text("✓").addClass("success");
                    setTimeout(function() {
                        $copyBtn.text(originalText).removeClass("success");
                    }, 2000);
                }).catch(function(err) {
                    console.error("Failed to copy: ", err);
                    fallbackCopy(logText);
                });
            } else {
                fallbackCopy(logText);
            }
        });

        function fallbackCopy(text) {
            var $temp = $("<textarea>");
            $("body").append($temp);
            $temp.val(text).select();
            try {
                var successful = document.execCommand("copy");
                if (successful) {
                    $copyBtn.text("✓").addClass("success");
                    setTimeout(function() {
                        $copyBtn.text(originalText).removeClass("success");
                    }, 2000);
                } else {
                    alert("复制失败，请手动复制");
                }
            } catch (err) {
                alert("复制失败，请手动复制");
            }
            $temp.remove();
        }
    }

    // 信息框复制按钮
    $(".ml-info-copy-btn").on("click", function() {
        var $btn = $(this);
        if ($btn.prop("disabled")) {
            return;
        }

        var target = $btn.data("target");
        var $box = $(".ml-info-box[data-section=\"" + target + "\"]");
        if (!$box.length) {
            return;
        }

        var originalText = $btn.text();

        // 提取纯文本内容
        var textContent = extractTextFromBox($box);

        // 使用 Clipboard API
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(textContent).then(function() {
                $btn.text("✓").addClass("success");
                setTimeout(function() {
                    $btn.text(originalText).removeClass("success");
                }, 2000);
            }).catch(function(err) {
                console.error("Failed to copy: ", err);
                fallbackCopyInfo(textContent, $btn, originalText);
            });
        } else {
            fallbackCopyInfo(textContent, $btn, originalText);
        }
    });

    function extractTextFromBox($box) {
        var lines = [];
        var title = $box.find("h4").first().text().trim();
        lines.push(title);
        lines.push("=".repeat(title.length));
        lines.push("");

        // 提取表格内容
        $box.find("table tr").each(function() {
            var $row = $(this);
            var cells = [];
            $row.find("td, th").each(function() {
                var $cell = $(this);
                // 移除HTML标签，只保留文本
                var text = $cell.clone()
                    .find("span")
                    .replaceWith(function() { return $(this).text(); })
                    .end()
                    .text()
                    .trim();
                cells.push(text);
            });
            if (cells.length > 0) {
                lines.push(cells.join(" | "));
            }
        });

        // 提取缺失文件列表
        $box.find("ul li").each(function() {
            lines.push("- " + $(this).text().trim());
        });

        return lines.join("\\n");
    }

    function fallbackCopyInfo(text, $btn, originalText) {
        var $temp = $("<textarea>");
        $("body").append($temp);
        $temp.val(text).select();
        try {
            var successful = document.execCommand("copy");
            if (successful) {
                $btn.text("✓").addClass("success");
                setTimeout(function() {
                    $btn.text(originalText).removeClass("success");
                }, 2000);
            } else {
                alert("复制失败，请手动复制");
            }
        } catch (err) {
            alert("复制失败，请手动复制");
        }
        $temp.remove();
    }
});
</script>';
    }

    /**
     * 显示环境信息
     */
    private static function displayEnvironmentInfo($form, $envInfo)
    {
        $envHtml = '<div class="ml-info-box" data-section="environment" style="background:#f9f9f9;padding:15px;border:1px solid #ddd;border-radius:4px;margin-bottom:20px;">';
        $envHtml .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">';
        $envHtml .= '<h4 style="margin:0;color:#333;">系统环境检测</h4>';
        $envHtml .= '<button type="button" class="ml-info-copy-btn" data-target="environment" title="复制环境信息">Copy</button>';
        $envHtml .= '</div>';
        $envHtml .= '<table style="width:100%;border-collapse:collapse;">';

        foreach ($envInfo as $name => $status) {
            $statusText = $status ? '<span style="color:#46b450;">✓ 可用</span>' : '<span style="color:#dc3232;">✗ 不可用</span>';
            $envHtml .= '<tr><td style="padding:5px 0;border-bottom:1px solid #eee;width:150px;">' . $name . '</td><td style="padding:5px 0;border-bottom:1px solid #eee;">' . $statusText . '</td></tr>';
        }

        $envHtml .= '</table></div>';

        echo $envHtml;
    }

    /**
     * 添加配置选项
     */
    private static function addConfigOptions($form, $envInfo)
    {
        // 日志记录开关
        $enableLogging = new Typecho_Widget_Helper_Form_Element_Checkbox('enableLogging',
            array('1' => '启用日志记录'),
            array(),
            '日志记录',
            '启用后将记录插件的操作日志（默认关闭，只保留最新10条日志）');
        $form->addInput($enableLogging);

        // GetID3 功能
        $enableGetID3 = new Typecho_Widget_Helper_Form_Element_Checkbox('enableGetID3',
            array('1' => '启用 GetID3 库'),
            array(),
            '音视频文件信息读取',
            '读取音频、视频文件的详细信息（时长、比特率等）');
        
        if (!$envInfo['GetID3 库']) {
            $enableGetID3->input->setAttribute('disabled', 'disabled');
            $enableGetID3->label->setAttribute('style', 'color: #999; cursor: not-allowed;');
        }
        $form->addInput($enableGetID3);
        
        // EXIF 功能
        $exifAvailable = $envInfo['ExifTool 库'] || $envInfo['EXIF 扩展'];
        $exifDescription = '检测图片中的隐私信息（GPS位置、设备信息等）。';

        if ($envInfo['ExifTool 库'] && $envInfo['EXIF 扩展']) {
            $exifDescription .= '检测使用 EXIF 扩展，清除EXIF信息使用 ExifTool 库。';
        } elseif ($envInfo['ExifTool 库']) {
            $exifDescription .= '使用 ExifTool 库进行检测和清除。';
        } elseif ($envInfo['EXIF 扩展']) {
            $exifDescription .= '使用 EXIF 扩展检测，但无法清除EXIF信息（需要ExifTool库和命令行工具）。';
        } else {
            $exifDescription .= '<br><strong style="color: #dc3232;">需要安装 exiftool 命令行工具：</strong><br>';
            $exifDescription .= '• Ubuntu/Debian: <code>sudo apt-get install exiftool</code><br>';
            $exifDescription .= '• CentOS/RHEL: <code>sudo yum install perl-Image-ExifTool</code><br>';
            $exifDescription .= '• macOS: <code>brew install exiftool</code>';
        }
        
        $enableExif = new Typecho_Widget_Helper_Form_Element_Checkbox('enableExif', 
            array('1' => '启用 EXIF 功能'), 
            array(), 
            '图片隐私信息检测', 
            $exifDescription);
        
        if (!$exifAvailable) {
            $enableExif->input->setAttribute('disabled', 'disabled');
            $enableExif->label->setAttribute('style', 'color: #999; cursor: not-allowed;');
        }
        $form->addInput($enableExif);
        
        // 添加其他配置选项
        self::addImageProcessingOptions($form, $envInfo);
        self::addVideoProcessingOptions($form, $envInfo);
        self::addWebDAVOptions($form);
    }

    /**
     * 添加图像处理选项
     */
    private static function addImageProcessingOptions($form, $envInfo)
    {
        // GD 图片压缩功能
        $enableGD = new Typecho_Widget_Helper_Form_Element_Checkbox('enableGD', 
            array('1' => '启用 GD 库压缩'), 
            array(), 
            'GD 库图片压缩', 
            '使用 GD 库压缩图片文件');
        
        if (!$envInfo['GD 库']) {
            $enableGD->input->setAttribute('disabled', 'disabled');
            $enableGD->label->setAttribute('style', 'color: #999; cursor: not-allowed;');
        }
        $form->addInput($enableGD);
        
        // ImageMagick 功能
        $enableImageMagick = new Typecho_Widget_Helper_Form_Element_Checkbox('enableImageMagick', 
            array('1' => '启用 ImageMagick 压缩'), 
            array(), 
            'ImageMagick 图片压缩', 
            '使用 ImageMagick 压缩图片文件，支持更多格式');
        
        if (!$envInfo['ImageMagick']) {
            $enableImageMagick->input->setAttribute('disabled', 'disabled');
            $enableImageMagick->label->setAttribute('style', 'color: #999; cursor: not-allowed;');
        }
        $form->addInput($enableImageMagick);
        
        // 压缩质量设置
        $gdQuality = new Typecho_Widget_Helper_Form_Element_Text('gdQuality', NULL, '80', 
            '默认图片压缩质量', 
            '设置默认图片压缩质量，范围 10-100');
        $form->addInput($gdQuality);
    }

    /**
     * 添加视频处理选项
     */
    private static function addVideoProcessingOptions($form, $envInfo)
    {
        // FFmpeg 功能
        $enableFFmpeg = new Typecho_Widget_Helper_Form_Element_Checkbox('enableFFmpeg', 
            array('1' => '启用 FFmpeg 压缩'), 
            array(), 
            'FFmpeg 压缩', 
            '使用 FFmpeg 压缩视频和图片文件');
        
        if (!$envInfo['FFmpeg']) {
            $enableFFmpeg->input->setAttribute('disabled', 'disabled');
            $enableFFmpeg->label->setAttribute('style', 'color: #999; cursor: not-allowed;');
        }
        $form->addInput($enableFFmpeg);
        
        // 视频压缩功能
        $enableVideoCompress = new Typecho_Widget_Helper_Form_Element_Checkbox('enableVideoCompress', 
            array('1' => '启用视频压缩功能'), 
            array(), 
            '视频压缩', 
            '启用后可以使用FFmpeg压缩视频文件');
        
        if (!$envInfo['FFmpeg']) {
            $enableVideoCompress->input->setAttribute('disabled', 'disabled');
            $enableVideoCompress->label->setAttribute('style', 'color: #999; cursor: not-allowed;');
        }
        $form->addInput($enableVideoCompress);
        
        // 视频压缩质量设置
        $videoQuality = new Typecho_Widget_Helper_Form_Element_Text('videoQuality', NULL, '23', 
            '默认视频压缩质量', 
            '视频压缩质量，范围0-51，数值越小质量越高，推荐18-28');
        $form->addInput($videoQuality);
        
        // 视频编码器选择
        $videoCodec = new Typecho_Widget_Helper_Form_Element_Select('videoCodec', 
            array(
                'libx264' => 'H.264 (兼容性好)',
                'libx265' => 'H.265 (压缩率高)',
                'libvpx-vp9' => 'VP9 (开源)',
                'libaom-av1' => 'AV1 (最新标准)'
            ), 
            'libx264', 
            '默认视频编码器', 
            '选择视频压缩使用的编码器');
        $form->addInput($videoCodec);
    }

    /**
     * 添加 WebDAV 配置选项
     */
    private static function addWebDAVOptions($form)
    {
        $optionConfig = self::getPluginOptionConfig();
        $defaultEndpoint = isset($optionConfig->webdavEndpoint) ? $optionConfig->webdavEndpoint : '';
        $defaultBasePath = isset($optionConfig->webdavBasePath) ? $optionConfig->webdavBasePath : '/';
        $defaultUsername = isset($optionConfig->webdavUsername) ? $optionConfig->webdavUsername : '';
        $defaultPassword = isset($optionConfig->webdavPassword) ? $optionConfig->webdavPassword : '';
        $defaultVerify = !isset($optionConfig->webdavVerifySSL) || $optionConfig->webdavVerifySSL;

        $webdavSection = new Typecho_Widget_Helper_Layout('div', ['class' => 'typecho-option']);
        $webdavSection->html('<h3 style="margin-top:30px">WebDAV 存储</h3>');
        $form->addItem($webdavSection);

        $enableWebDAV = new Typecho_Widget_Helper_Form_Element_Checkbox('enableWebDAV',
            array('1' => '启用 WebDAV 文件管理'),
            array(),
            '启用 WebDAV',
            '启用后可在媒体库中管理远程 WebDAV 文件（列出、上传、删除等操作）');
        $form->addInput($enableWebDAV);

        $webdavEndpoint = new Typecho_Widget_Helper_Form_Element_Text('webdavEndpoint', null, $defaultEndpoint,
            'WebDAV 服务地址',
            '完整的 WebDAV 根地址，例如 <code>https://example.com/remote.php/dav/files/username</code>');
        $form->addInput($webdavEndpoint);

        $webdavBasePath = new Typecho_Widget_Helper_Form_Element_Text('webdavBasePath', null, $defaultBasePath ?: '/',
            '默认子路径',
            '可选填，默认为根目录，填写后将作为 WebDAV 面板的起始目录（例如 <code>/typecho</code>）');
        $form->addInput($webdavBasePath);

        $webdavUsername = new Typecho_Widget_Helper_Form_Element_Text('webdavUsername', null, $defaultUsername,
            'WebDAV 用户名',
            '用于 Basic Auth 的用户名');
        $form->addInput($webdavUsername);

        $webdavPassword = new Typecho_Widget_Helper_Form_Element_Password('webdavPassword', null, $defaultPassword,
            'WebDAV 密码',
            '用于 Basic Auth 的密码');
        $form->addInput($webdavPassword);

        $webdavVerifySSL = new Typecho_Widget_Helper_Form_Element_Checkbox('webdavVerifySSL',
            array('1' => '验证 SSL 证书'),
            $defaultVerify ? array('1') : array(),
            'SSL 验证',
            '如果 WebDAV 服务使用自签名证书，可取消勾选以跳过 SSL 验证（不推荐）');
        $form->addInput($webdavVerifySSL);
    }

    /**
     * 获取插件配置，首次启用时没有配置会返回空对象
     */
    private static function getPluginOptionConfig()
    {
        try {
            return Helper::options()->plugin('MediaLibrary');
        } catch (Exception $e) {
            if (class_exists('Typecho_Config')) {
                return new Typecho_Config();
            }
            return (object)[];
        }
    }

    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
}
