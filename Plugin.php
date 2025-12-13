<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/LogAction.php';
require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/WebDAVPresets.php';

/**
 * 媒体库管理插件，可以在后台对整体文件信息的查看和编辑、上传和删除，图片压缩和隐私检测，多媒体预览，文章编辑器中预览和插入的简单媒体库
 * 
 * @package MediaLibrary
 * @author HansJack
 * @version pro_0.1.7
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
        Helper::addPanel(3, 'MediaLibrary/editor-media-ajax.php', '媒体库编辑器', '编辑器媒体库', 'administrator', true);
        Helper::addAction('medialibrary-log', 'MediaLibrary_LogAction');
        Helper::addAction('media-library', 'MediaLibrary_Action');

        // 添加写作页面的媒体库组件
        Typecho_Plugin::factory('admin/write-post.php')->bottom = array('MediaLibrary_Plugin', 'addMediaLibraryToWritePage');
        Typecho_Plugin::factory('admin/write-page.php')->bottom = array('MediaLibrary_Plugin', 'addMediaLibraryToWritePage');

        // 创建 WebDAV 目录
        self::createWebDAVDirectory();

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
        Helper::removePanel(3, 'MediaLibrary/editor-media-ajax.php');
        Helper::removeAction('medialibrary-log');
        Helper::removeAction('media-library');

        return '媒体库插件已禁用！';
    }
    
    /**
     * 在写作页面添加媒体库
     */
    public static function addMediaLibraryToWritePage()
    {
        if (defined('MEDIALIBRARY_INLINE_RENDERED') && MEDIALIBRARY_INLINE_RENDERED) {
            return;
        }

        define('MEDIALIBRARY_INLINE_RENDERED', true);

        echo '<div id="media-library-container">';
        include __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/write-post-media.php';
        echo '</div>';
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

    // 对象存储配置动态显示/隐藏
    function toggleStorageOptions() {
        var selectedType = $("select[name=\\"storageType\\"]").val();
        console.log("选中的存储类型:", selectedType);

        // 查找所有带有 data-storage-type 属性的输入框
        var $inputs = $("input[data-storage-type]");
        console.log("找到的输入框数量:", $inputs.length);

        // 隐藏所有存储配置项
        $inputs.each(function() {
            var $input = $(this);
            var storageType = $input.attr("data-storage-type");
            // 尝试多种选择器找到父级 li 元素
            var $parent = $input.closest("li");
            if (!$parent.length) {
                $parent = $input.parent().closest("li");
            }
            if ($parent.length) {
                $parent.hide();
                console.log("隐藏配置项:", storageType, $parent[0]);
            }
        });

        // 显示选中的存储配置项
        if (selectedType) {
            $("input[data-storage-type=\\"" + selectedType + "\\"]").each(function() {
                var $input = $(this);
                var $parent = $input.closest("li");
                if (!$parent.length) {
                    $parent = $input.parent().closest("li");
                }
                if ($parent.length) {
                    $parent.show();
                    console.log("显示配置项:", selectedType, $parent[0]);
                }
            });
        }
    }

    // 监听存储类型下拉框变化
    $("select[name=\\"storageType\\"]").on("change", function() {
        console.log("存储类型改变");
        toggleStorageOptions();
    });

    // 页面加载时稍微延迟执行，确保DOM完全加载
    setTimeout(function() {
        console.log("初始化对象存储配置显示");
        toggleStorageOptions();
    }, 100);

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
        
        // 加载优化设置
        $enableLoadOptimization = new Typecho_Widget_Helper_Form_Element_Checkbox('enableLoadOptimization',
            array('1' => '启用图标模式（推荐低带宽环境）'),
            array(),
            '加载优化',
            '启用后图片显示为图标而非缩略图，鼠标悬停时才异步加载预览。适用于1M等低带宽服务器，页面加载几乎不消耗带宽，且加载过程不阻塞界面操作。');
        $form->addInput($enableLoadOptimization);

        // 优先存储位置设置
        $preferredStorage = new Typecho_Widget_Helper_Form_Element_Select('preferredStorage',
            array(
                'local' => '本地存储（默认）',
                'object_storage' => '对象存储（需先启用）',
                'webdav' => 'WebDAV 存储（需先启用）'
            ),
            'local',
            '优先存储位置',
            '拖拽上传文件时的默认存储位置。选择对象存储或 WebDAV 前，请确保已在下方启用并正确配置相应的存储服务。');
        $form->addInput($preferredStorage);

        // 添加其他配置选项
        self::addImageProcessingOptions($form, $envInfo);
        self::addVideoProcessingOptions($form, $envInfo);
        self::addObjectStorageOptions($form);
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
     * 添加对象存储配置选项
     */
    private static function addObjectStorageOptions($form)
    {
        // 启用对象存储
        $enableObjectStorage = new Typecho_Widget_Helper_Form_Element_Checkbox('enableObjectStorage',
            array('1' => '启用对象存储'),
            array(),
            '对象存储功能',
            '启用后可以将文件上传到云对象存储服务（腾讯云COS、阿里云OSS、七牛云Kodo等）');
        $form->addInput($enableObjectStorage);

        // 存储类型选择
        $storageType = new Typecho_Widget_Helper_Form_Element_Select('storageType',
            array(
                'tencent_cos' => '腾讯云COS',
                'aliyun_oss' => '阿里云OSS',
                'qiniu_kodo' => '七牛云Kodo',
                'upyun_uss' => '又拍云USS',
                'baidu_bos' => '百度云BOS',
                'huawei_obs' => '华为云OBS',
                'lskypro' => 'LskyPro'
            ),
            'tencent_cos',
            '对象存储类型',
            '选择要使用的对象存储服务类型');
        $form->addInput($storageType);

        // 腾讯云COS配置
        $cosSecretId = new Typecho_Widget_Helper_Form_Element_Text('cosSecretId', NULL, '',
            '腾讯云COS SecretId',
            '请前往<a target="_blank" href="https://console.cloud.tencent.com/capi">腾讯云控制台</a>获取');
        $cosSecretId->input->setAttribute('data-storage-type', 'tencent_cos');
        $form->addInput($cosSecretId);

        $cosSecretKey = new Typecho_Widget_Helper_Form_Element_Text('cosSecretKey', NULL, '',
            '腾讯云COS SecretKey',
            '请前往<a target="_blank" href="https://console.cloud.tencent.com/capi">腾讯云控制台</a>获取');
        $cosSecretKey->input->setAttribute('data-storage-type', 'tencent_cos');
        $form->addInput($cosSecretKey);

        $cosRegion = new Typecho_Widget_Helper_Form_Element_Text('cosRegion', NULL, '',
            '腾讯云COS地域',
            '例如：ap-beijing（北京）、ap-shanghai（上海）、ap-guangzhou（广州）');
        $cosRegion->input->setAttribute('data-storage-type', 'tencent_cos');
        $form->addInput($cosRegion);

        $cosBucket = new Typecho_Widget_Helper_Form_Element_Text('cosBucket', NULL, '',
            '腾讯云COS存储桶名称',
            '格式为 xxxxx-xxxxxx，请在<a target="_blank" href="https://console.cloud.tencent.com/cos/bucket">COS控制台</a>获取');
        $cosBucket->input->setAttribute('data-storage-type', 'tencent_cos');
        $form->addInput($cosBucket);

        $cosDomain = new Typecho_Widget_Helper_Form_Element_Text('cosDomain', NULL, '',
            '腾讯云COS访问域名',
            '留空则使用默认域名，也可填写自定义CDN域名（需包含 http:// 或 https://）');
        $cosDomain->input->setAttribute('data-storage-type', 'tencent_cos');
        $form->addInput($cosDomain);

        // 阿里云OSS配置
        $ossAccessKeyId = new Typecho_Widget_Helper_Form_Element_Text('ossAccessKeyId', NULL, '',
            '阿里云OSS AccessKey ID',
            '请前往<a target="_blank" href="https://ram.console.aliyun.com/manage/ak">阿里云控制台</a>获取');
        $ossAccessKeyId->input->setAttribute('data-storage-type', 'aliyun_oss');
        $form->addInput($ossAccessKeyId);

        $ossAccessKeySecret = new Typecho_Widget_Helper_Form_Element_Text('ossAccessKeySecret', NULL, '',
            '阿里云OSS AccessKey Secret',
            '请前往<a target="_blank" href="https://ram.console.aliyun.com/manage/ak">阿里云控制台</a>获取');
        $ossAccessKeySecret->input->setAttribute('data-storage-type', 'aliyun_oss');
        $form->addInput($ossAccessKeySecret);

        $ossEndpoint = new Typecho_Widget_Helper_Form_Element_Text('ossEndpoint', NULL, '',
            '阿里云OSS Endpoint',
            '例如：oss-cn-hangzhou.aliyuncs.com');
        $ossEndpoint->input->setAttribute('data-storage-type', 'aliyun_oss');
        $form->addInput($ossEndpoint);

        $ossBucket = new Typecho_Widget_Helper_Form_Element_Text('ossBucket', NULL, '',
            '阿里云OSS Bucket名称',
            '请填写阿里云OSS存储空间名称');
        $ossBucket->input->setAttribute('data-storage-type', 'aliyun_oss');
        $form->addInput($ossBucket);

        $ossDomain = new Typecho_Widget_Helper_Form_Element_Text('ossDomain', NULL, '',
            '阿里云OSS访问域名',
            '留空则使用默认域名，也可填写自定义域名（需包含 http:// 或 https://）');
        $ossDomain->input->setAttribute('data-storage-type', 'aliyun_oss');
        $form->addInput($ossDomain);

        // 七牛云Kodo配置
        $qiniuAccessKey = new Typecho_Widget_Helper_Form_Element_Text('qiniuAccessKey', NULL, '',
            '七牛云Kodo AccessKey',
            '请前往<a target="_blank" href="https://portal.qiniu.com/user/key">七牛云控制台</a>获取');
        $qiniuAccessKey->input->setAttribute('data-storage-type', 'qiniu_kodo');
        $form->addInput($qiniuAccessKey);

        $qiniuSecretKey = new Typecho_Widget_Helper_Form_Element_Text('qiniuSecretKey', NULL, '',
            '七牛云Kodo SecretKey',
            '请前往<a target="_blank" href="https://portal.qiniu.com/user/key">七牛云控制台</a>获取');
        $qiniuSecretKey->input->setAttribute('data-storage-type', 'qiniu_kodo');
        $form->addInput($qiniuSecretKey);

        $qiniuBucket = new Typecho_Widget_Helper_Form_Element_Text('qiniuBucket', NULL, '',
            '七牛云Kodo Bucket名称',
            '请填写七牛云存储空间名称');
        $qiniuBucket->input->setAttribute('data-storage-type', 'qiniu_kodo');
        $form->addInput($qiniuBucket);

        $qiniuDomain = new Typecho_Widget_Helper_Form_Element_Text('qiniuDomain', NULL, '',
            '七牛云Kodo访问域名',
            '必填项，请填写七牛云绑定的域名（需包含 http:// 或 https://）');
        $qiniuDomain->input->setAttribute('data-storage-type', 'qiniu_kodo');
        $form->addInput($qiniuDomain);

        // 又拍云USS配置
        $upyunBucketName = new Typecho_Widget_Helper_Form_Element_Text('upyunBucketName', NULL, '',
            '又拍云USS服务名称',
            '请填写又拍云云存储服务名称');
        $upyunBucketName->input->setAttribute('data-storage-type', 'upyun_uss');
        $form->addInput($upyunBucketName);

        $upyunOperatorName = new Typecho_Widget_Helper_Form_Element_Text('upyunOperatorName', NULL, '',
            '又拍云USS操作员名称',
            '请填写又拍云操作员名称');
        $upyunOperatorName->input->setAttribute('data-storage-type', 'upyun_uss');
        $form->addInput($upyunOperatorName);

        $upyunOperatorPassword = new Typecho_Widget_Helper_Form_Element_Text('upyunOperatorPassword', NULL, '',
            '又拍云USS操作员密码',
            '请填写又拍云操作员密码');
        $upyunOperatorPassword->input->setAttribute('data-storage-type', 'upyun_uss');
        $form->addInput($upyunOperatorPassword);

        $upyunDomain = new Typecho_Widget_Helper_Form_Element_Text('upyunDomain', NULL, '',
            '又拍云USS访问域名',
            '留空则使用默认域名，也可填写自定义域名（需包含 http:// 或 https://）');
        $upyunDomain->input->setAttribute('data-storage-type', 'upyun_uss');
        $form->addInput($upyunDomain);

        // 百度云BOS配置
        $bosAccessKeyId = new Typecho_Widget_Helper_Form_Element_Text('bosAccessKeyId', NULL, '',
            '百度云BOS AccessKey ID',
            '请前往<a target="_blank" href="https://console.bce.baidu.com/iam/#/iam/accesslist">百度云控制台</a>获取');
        $bosAccessKeyId->input->setAttribute('data-storage-type', 'baidu_bos');
        $form->addInput($bosAccessKeyId);

        $bosSecretAccessKey = new Typecho_Widget_Helper_Form_Element_Text('bosSecretAccessKey', NULL, '',
            '百度云BOS SecretAccessKey',
            '请前往<a target="_blank" href="https://console.bce.baidu.com/iam/#/iam/accesslist">百度云控制台</a>获取');
        $bosSecretAccessKey->input->setAttribute('data-storage-type', 'baidu_bos');
        $form->addInput($bosSecretAccessKey);

        $bosEndpoint = new Typecho_Widget_Helper_Form_Element_Text('bosEndpoint', NULL, '',
            '百度云BOS Endpoint',
            '例如：bj.bcebos.com');
        $bosEndpoint->input->setAttribute('data-storage-type', 'baidu_bos');
        $form->addInput($bosEndpoint);

        $bosBucket = new Typecho_Widget_Helper_Form_Element_Text('bosBucket', NULL, '',
            '百度云BOS Bucket名称',
            '请填写百度云BOS存储桶名称');
        $bosBucket->input->setAttribute('data-storage-type', 'baidu_bos');
        $form->addInput($bosBucket);

        $bosDomain = new Typecho_Widget_Helper_Form_Element_Text('bosDomain', NULL, '',
            '百度云BOS访问域名',
            '留空则使用默认域名，也可填写自定义域名（需包含 http:// 或 https://）');
        $bosDomain->input->setAttribute('data-storage-type', 'baidu_bos');
        $form->addInput($bosDomain);

        // 华为云OBS配置
        $obsAccessKey = new Typecho_Widget_Helper_Form_Element_Text('obsAccessKey', NULL, '',
            '华为云OBS AccessKey',
            '请前往<a target="_blank" href="https://console.huaweicloud.com/iam">华为云控制台</a>获取');
        $obsAccessKey->input->setAttribute('data-storage-type', 'huawei_obs');
        $form->addInput($obsAccessKey);

        $obsSecretKey = new Typecho_Widget_Helper_Form_Element_Text('obsSecretKey', NULL, '',
            '华为云OBS SecretKey',
            '请前往<a target="_blank" href="https://console.huaweicloud.com/iam">华为云控制台</a>获取');
        $obsSecretKey->input->setAttribute('data-storage-type', 'huawei_obs');
        $form->addInput($obsSecretKey);

        $obsEndpoint = new Typecho_Widget_Helper_Form_Element_Text('obsEndpoint', NULL, '',
            '华为云OBS Endpoint',
            '例如：obs.cn-north-4.myhuaweicloud.com');
        $obsEndpoint->input->setAttribute('data-storage-type', 'huawei_obs');
        $form->addInput($obsEndpoint);

        $obsBucket = new Typecho_Widget_Helper_Form_Element_Text('obsBucket', NULL, '',
            '华为云OBS Bucket名称',
            '请填写华为云OBS桶名称');
        $obsBucket->input->setAttribute('data-storage-type', 'huawei_obs');
        $form->addInput($obsBucket);

        $obsDomain = new Typecho_Widget_Helper_Form_Element_Text('obsDomain', NULL, '',
            '华为云OBS访问域名',
            '留空则使用默认域名，也可填写自定义域名（需包含 http:// 或 https://）');
        $obsDomain->input->setAttribute('data-storage-type', 'huawei_obs');
        $form->addInput($obsDomain);

        // LskyPro配置
        $lskyproApiUrl = new Typecho_Widget_Helper_Form_Element_Text('lskyproApiUrl', NULL, '',
            'LskyPro API地址',
            '请填写LskyPro API地址，例如：https://your-lskypro.com');
        $lskyproApiUrl->input->setAttribute('data-storage-type', 'lskypro');
        $form->addInput($lskyproApiUrl);

        $lskyproToken = new Typecho_Widget_Helper_Form_Element_Text('lskyproToken', NULL, '',
            'LskyPro Token',
            '请在LskyPro后台获取API Token');
        $lskyproToken->input->setAttribute('data-storage-type', 'lskypro');
        $form->addInput($lskyproToken);

        $lskyproStrategyId = new Typecho_Widget_Helper_Form_Element_Text('lskyproStrategyId', NULL, '',
            'LskyPro 储存策略ID',
            '可选，留空则使用默认储存策略');
        $lskyproStrategyId->input->setAttribute('data-storage-type', 'lskypro');
        $form->addInput($lskyproStrategyId);

        // 通用配置
        $storagePathPrefix = new Typecho_Widget_Helper_Form_Element_Text('storagePathPrefix', NULL, 'uploads/',
            '对象存储路径前缀',
            '设置文件在对象存储中的路径前缀，默认为 uploads/');
        $form->addInput($storagePathPrefix);

        $storageLocalSave = new Typecho_Widget_Helper_Form_Element_Checkbox('storageLocalSave',
            array('1' => '同时保存到本地'),
            array(),
            '本地备份',
            '上传到对象存储的同时，也在本地保存一份副本');
        $form->addInput($storageLocalSave);

        $storageSyncDelete = new Typecho_Widget_Helper_Form_Element_Checkbox('storageSyncDelete',
            array('1' => '同步删除'),
            array(),
            '删除时同步',
            '在媒体库删除文件时，同步删除对象存储中的文件');
        $form->addInput($storageSyncDelete);
    }

    /**
     * 添加 WebDAV 配置选项
     */
    private static function addWebDAVOptions($form)
    {
        $optionConfig = self::getPluginOptionConfig();

        // 默认值
        $defaultLocalPath = isset($optionConfig->webdavLocalPath) ? $optionConfig->webdavLocalPath : '';
        // 如果未设置，提供推荐路径
        if (empty($defaultLocalPath)) {
            $defaultLocalPath = __TYPECHO_ROOT_DIR__ . '/usr/uploads/webdav';
        }

        $defaultEndpoint = isset($optionConfig->webdavEndpoint) ? $optionConfig->webdavEndpoint : '';
        $defaultRemotePath = isset($optionConfig->webdavRemotePath) ? $optionConfig->webdavRemotePath : '/typecho';
        $defaultUsername = isset($optionConfig->webdavUsername) ? $optionConfig->webdavUsername : '';
        $defaultPassword = isset($optionConfig->webdavPassword) ? $optionConfig->webdavPassword : '';
        $defaultVerify = !isset($optionConfig->webdavVerifySSL) || $optionConfig->webdavVerifySSL;
        $defaultSyncEnabled = isset($optionConfig->webdavSyncEnabled) && $optionConfig->webdavSyncEnabled;
        $defaultSyncMode = isset($optionConfig->webdavSyncMode) ? $optionConfig->webdavSyncMode : 'onupload';
        $defaultConflictStrategy = isset($optionConfig->webdavConflictStrategy) ? $optionConfig->webdavConflictStrategy : 'newest';
        $defaultDeleteStrategy = isset($optionConfig->webdavDeleteStrategy) ? $optionConfig->webdavDeleteStrategy : 'auto';
        $defaultSyncDelete = isset($optionConfig->webdavSyncDelete) && $optionConfig->webdavSyncDelete;
        $defaultUploadMode = isset($optionConfig->webdavUploadMode) ? $optionConfig->webdavUploadMode : 'local-cache';
        $defaultExternalDomain = isset($optionConfig->webdavExternalDomain) ? trim($optionConfig->webdavExternalDomain) : '';
        $presets = MediaLibrary_WebDAVPresets::getPresets();
        $defaultPresetKey = isset($optionConfig->webdavPreset) ? (string)$optionConfig->webdavPreset : 'custom';
        if (!isset($presets[$defaultPresetKey])) {
            $defaultPresetKey = 'custom';
        }
        $activePreset = $presets[$defaultPresetKey];

        $webdavSection = new Typecho_Widget_Helper_Layout('div', ['class' => 'typecho-option']);
        $webdavSection->html('<h3 style="margin-top:30px">WebDAV 同步存储</h3><p style="color:#666;margin-top:5px">本地 WebDAV 文件夹作为缓存，自动同步到远程 WebDAV 服务器</p>');
        $form->addItem($webdavSection);

        $enableWebDAV = new Typecho_Widget_Helper_Form_Element_Checkbox('enableWebDAV',
            array('1' => '启用 WebDAV 同步存储'),
            isset($optionConfig->enableWebDAV) && $optionConfig->enableWebDAV ? array('1') : array(),
            '启用 WebDAV',
            '启用后，上传文件将保存到本地 WebDAV 文件夹并自动同步到远程 WebDAV 服务器');
        $form->addInput($enableWebDAV);

        $presetOptions = array();
        foreach ($presets as $key => $presetInfo) {
            $presetOptions[$key] = $presetInfo['name'];
        }
        $webdavPresetField = new Typecho_Widget_Helper_Form_Element_Select('webdavPreset', $presetOptions, $defaultPresetKey,
            'WebDAV 服务模板',
            '为常见 WebDAV 服务提供示例地址和账号说明，选择模板后仍可根据需要修改各字段。');
        $form->addInput($webdavPresetField);

        // 本地配置
        $localSection = new Typecho_Widget_Helper_Layout('div', ['class' => 'typecho-option']);
        $localSection->html('<h4 style="margin-top:20px;padding-top:20px;border-top:1px solid #e8eaed">本地 WebDAV 文件夹</h4>');
        $form->addItem($localSection);

        $webdavLocalPath = new Typecho_Widget_Helper_Form_Element_Text('webdavLocalPath', null, $defaultLocalPath,
            '本地 WebDAV 文件夹路径',
            '服务器上的 WebDAV 文件夹绝对路径。<br>
            推荐路径：<code>' . __TYPECHO_ROOT_DIR__ . '/usr/uploads/webdav</code><br>
            Linux 示例：<code>/var/www/html/usr/uploads/webdav</code><br>
            Windows Server 示例：<code>C:\www\usr\uploads\webdav</code><br>
            文件夹不存在时会自动创建（需要目录写入权限）');
        $form->addInput($webdavLocalPath);

        // 远程配置
        $remoteSection = new Typecho_Widget_Helper_Layout('div', ['class' => 'typecho-option']);
        $remoteSection->html('<h4 style="margin-top:20px;padding-top:20px;border-top:1px solid #e8eaed">远程 WebDAV 服务器</h4>');
        $form->addItem($remoteSection);

        $endpointDesc = '远程 WebDAV 服务器地址，例如 <code>https://example.com/remote.php/dav/files/username</code>';
        if (!empty($activePreset['endpointHelp'])) {
            $endpointDesc .= '<br><strong>模板提示：</strong>' . $activePreset['endpointHelp'];
        }
        $webdavEndpoint = new Typecho_Widget_Helper_Form_Element_Text('webdavEndpoint', null, $defaultEndpoint,
            'WebDAV 服务地址',
            $endpointDesc);
        $form->addInput($webdavEndpoint);
        if (!empty($activePreset['endpointPlaceholder'])) {
            $webdavEndpoint->input->setAttribute('placeholder', $activePreset['endpointPlaceholder']);
        }

        $remoteDesc = '在远程 WebDAV 服务器上的目标路径，例如 <code>/typecho</code> 或 <code>/uploads</code>';
        if (!empty($activePreset['remotePathHelp'])) {
            $remoteDesc .= '<br><strong>模板提示：</strong>' . $activePreset['remotePathHelp'];
        }
        $webdavRemotePath = new Typecho_Widget_Helper_Form_Element_Text('webdavRemotePath', null, $defaultRemotePath,
            '远程同步路径',
            $remoteDesc);
        $form->addInput($webdavRemotePath);
        if (!empty($activePreset['remotePathPlaceholder'])) {
            $webdavRemotePath->input->setAttribute('placeholder', $activePreset['remotePathPlaceholder']);
        }

        if (!empty($activePreset['description'])) {
            $presetDesc = new Typecho_Widget_Helper_Layout('div', ['class' => 'typecho-option']);
            $presetDesc->html('<p style="margin:5px 0;color:#666;">' . $activePreset['description'] . '</p>');
            $form->addItem($presetDesc);
        }

        $usernameHelp = '用于远程 WebDAV 服务器认证的用户名';
        if (!empty($activePreset['usernameHelp'])) {
            $usernameHelp .= '<br><strong>模板提示：</strong>' . $activePreset['usernameHelp'];
        }
        $webdavUsername = new Typecho_Widget_Helper_Form_Element_Text('webdavUsername', null, $defaultUsername,
            'WebDAV 用户名',
            $usernameHelp);
        $form->addInput($webdavUsername);

        $passwordHelp = '用于远程 WebDAV 服务器认证的密码';
        if (!empty($activePreset['passwordHelp'])) {
            $passwordHelp .= '<br><strong>模板提示：</strong>' . $activePreset['passwordHelp'];
        }
        $webdavPassword = new Typecho_Widget_Helper_Form_Element_Password('webdavPassword', null, $defaultPassword,
            'WebDAV 密码',
            $passwordHelp);
        $form->addInput($webdavPassword);

        $externalDomainDesc = '如果填写，将使用该域名生成 WebDAV 文件外链（用于复制/预览）。示例：<code>https://cdn.example.com/webdav</code>';
        $webdavExternalDomain = new Typecho_Widget_Helper_Form_Element_Text('webdavExternalDomain', null, $defaultExternalDomain,
            'WebDAV 外链域名',
            $externalDomainDesc);
        $form->addInput($webdavExternalDomain);

        $webdavVerifySSL = new Typecho_Widget_Helper_Form_Element_Checkbox('webdavVerifySSL',
            array('1' => '验证 SSL 证书'),
            $defaultVerify ? array('1') : array(),
            'SSL 验证',
            '如果 WebDAV 服务使用自签名证书，可取消勾选以跳过 SSL 验证（不推荐）');
        $form->addInput($webdavVerifySSL);

        // 同步配置
        $syncSection = new Typecho_Widget_Helper_Layout('div', ['class' => 'typecho-option']);
        $syncSection->html('<h4 style="margin-top:20px;padding-top:20px;border-top:1px solid #e8eaed">同步策略</h4>');
        $form->addItem($syncSection);

        $enableSync = new Typecho_Widget_Helper_Form_Element_Checkbox('webdavSyncEnabled',
            array('1' => '启用自动同步'),
            $defaultSyncEnabled ? array('1') : array(),
            '自动同步',
            '启用后，根据同步模式自动同步文件到远程 WebDAV 服务器');
        $form->addInput($enableSync);

        $syncMode = new Typecho_Widget_Helper_Form_Element_Radio('webdavSyncMode',
            array(
                'manual' => '手动同步（通过管理面板手动触发）',
                'onupload' => '上传时自动同步（推荐）',
                'scheduled' => '定时同步（需要配置系统定时任务）'
            ),
            $defaultSyncMode,
            '同步模式',
            '选择同步触发方式：手动、上传时自动同步、或定时同步');
        $form->addInput($syncMode);

        $conflictStrategy = new Typecho_Widget_Helper_Form_Element_Radio('webdavConflictStrategy',
            array(
                'newest' => '使用最新文件（比较修改时间）',
                'local' => '本地文件优先（总是上传本地文件）',
                'remote' => '远程文件优先（总是保留远程文件）'
            ),
            $defaultConflictStrategy,
            '冲突处理策略',
            '当本地和远程都存在同名文件且内容不同时的处理方式');
        $form->addInput($conflictStrategy);

        $deleteStrategy = new Typecho_Widget_Helper_Form_Element_Radio('webdavDeleteStrategy',
            array(
                'auto' => '自动同步删除（删除本地文件时同步删除远程文件）',
                'keep' => '保留远程文件（仅删除本地文件，不影响远程）',
                'manual' => '手动处理（删除时询问）'
            ),
            $defaultDeleteStrategy,
            '删除同步策略',
            '删除本地 WebDAV 文件夹中的文件时如何处理远程文件');
        $form->addInput($deleteStrategy);

        $webdavSyncDelete = new Typecho_Widget_Helper_Form_Element_Checkbox('webdavSyncDelete',
            array('1' => '自动同步删除远程缺失的文件'),
            $defaultSyncDelete ? array('1') : array(),
            '同步删除选项',
            '启用后，在同步过程中如果检测到本地已移除的文件会尝试删除 WebDAV 上的同名文件。');
        $form->addInput($webdavSyncDelete);

        $webdavUploadMode = new Typecho_Widget_Helper_Form_Element_Radio('webdavUploadMode',
            array(
                'local-cache' => '先保存到本地缓存再同步（默认）',
                'remote-only' => '直接上传至 WebDAV（仅保留元数据 JSON）'
            ),
            $defaultUploadMode,
            '上传模式',
            '直接上传模式下不再在本地 WebDAV 缓存目录保留文件，只记录元数据。适合磁盘空间敏感的站点。');
        $form->addInput($webdavUploadMode);

        // 定时同步配置
        $syncInterval = new Typecho_Widget_Helper_Form_Element_Text('webdavSyncInterval',
            null,
            '3600',
            '定时同步间隔（秒）',
            '定时同步的最小间隔时间（秒），默认 3600 秒（1小时）。仅在选择"定时同步"模式时生效。');
        $form->addInput($syncInterval);

        $cronKey = new Typecho_Widget_Helper_Form_Element_Text('webdavCronKey',
            null,
            md5(uniqid(mt_rand(), true)),
            'Cron 任务密钥',
            '用于保护 cron 任务的密钥。如果通过 URL 触发同步任务，需要提供此密钥。例如：<br>' .
            '<code>curl "' . Helper::options()->siteUrl . 'usr/plugins/MediaLibrary/cron-webdav-sync.php?key=YOUR_KEY"</code>');
        $form->addInput($cronKey);

        // 测试连接按钮
        $testSection = new Typecho_Widget_Helper_Layout('div', ['class' => 'typecho-option']);
        $testSection->html('
            <h4 style="margin-top:20px;padding-top:20px;border-top:1px solid #e8eaed">测试连接</h4>
            <div style="margin-top:10px;">
                <button type="button" id="webdav-test-btn" class="btn primary" style="margin-right:10px;">
                    <i class="i-check"></i> 测试 WebDAV 配置
                </button>
                <span id="webdav-test-loading" style="display:none;color:#999;">
                    <i class="i-loading"></i> 测试中...
                </span>
            </div>
            <div id="webdav-test-result" style="margin-top:15px;padding:10px;border-radius:4px;display:none;">
                <!-- 测试结果将显示在这里 -->
            </div>
            <script>
            (function() {
                var testBtn = document.getElementById("webdav-test-btn");
                var loading = document.getElementById("webdav-test-loading");
                var resultDiv = document.getElementById("webdav-test-result");

                if (testBtn) {
                    testBtn.addEventListener("click", function() {
                        testBtn.disabled = true;
                        loading.style.display = "inline";
                        resultDiv.style.display = "none";

                        fetch("/action/media-library?action=webdav_test", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json"
                            }
                        })
                        .then(function(response) {
                            return response.json();
                        })
                        .then(function(data) {
                            testBtn.disabled = false;
                            loading.style.display = "none";
                            resultDiv.style.display = "block";

                            var html = "";

                            if (data.success) {
                                resultDiv.style.backgroundColor = "#d4edda";
                                resultDiv.style.borderColor = "#c3e6cb";
                                resultDiv.style.color = "#155724";
                                html = "<strong>✅ " + data.message + "</strong>";
                            } else {
                                resultDiv.style.backgroundColor = "#f8d7da";
                                resultDiv.style.borderColor = "#f5c6cb";
                                resultDiv.style.color = "#721c24";
                                html = "<strong>❌ " + data.message + "</strong>";
                            }

                            // 本地路径测试结果
                            if (data.local) {
                                html += "<div style=\"margin-top:10px;padding:10px;background:#fff;border-radius:3px;\">";
                                html += "<strong>本地路径测试：</strong>";
                                if (data.local.success) {
                                    html += "<span style=\"color:#28a745;\">✓ 通过</span>";
                                    html += "<ul style=\"margin:5px 0 0 20px;\">";
                                    html += "<li>路径: " + data.local.path + "</li>";
                                    html += "<li>存在: " + (data.local.exists ? "是" : "否") + "</li>";
                                    html += "<li>可读: " + (data.local.readable ? "是" : "否") + "</li>";
                                    html += "<li>可写: " + (data.local.writable ? "是" : "否") + "</li>";
                                    html += "</ul>";
                                } else {
                                    html += "<span style=\"color:#dc3545;\">✗ 失败</span>";
                                    html += "<div style=\"margin-top:5px;color:#721c24;\">" + data.local.message + "</div>";
                                    if (data.local.path) {
                                        html += "<div style=\"margin-top:5px;font-size:12px;color:#666;\">路径: " + data.local.path + "</div>";
                                    }
                                }
                                html += "</div>";
                            }

                            // 远程连接测试结果
                            if (data.remote) {
                                html += "<div style=\"margin-top:10px;padding:10px;background:#fff;border-radius:3px;\">";
                                html += "<strong>远程连接测试：</strong>";
                                if (data.remote.configured) {
                                    if (data.remote.success) {
                                        html += "<span style=\"color:#28a745;\">✓ 通过</span>";
                                        html += "<div style=\"margin-top:5px;font-size:12px;color:#666;\">服务器: " + data.remote.endpoint + "</div>";
                                    } else {
                                        html += "<span style=\"color:#dc3545;\">✗ 失败</span>";
                                        html += "<div style=\"margin-top:5px;color:#721c24;\">" + data.remote.message + "</div>";
                                        if (data.remote.endpoint) {
                                            html += "<div style=\"margin-top:5px;font-size:12px;color:#666;\">服务器: " + data.remote.endpoint + "</div>";
                                        }
                                    }
                                } else {
                                    html += "<span style=\"color:#999;\">未配置</span>";
                                    html += "<div style=\"margin-top:5px;font-size:12px;color:#666;\">如果不需要远程同步，可以忽略此项</div>";
                                }
                                html += "</div>";
                            }

                            resultDiv.innerHTML = html;
                        })
                        .catch(function(error) {
                            testBtn.disabled = false;
                            loading.style.display = "none";
                            resultDiv.style.display = "block";
                            resultDiv.style.backgroundColor = "#f8d7da";
                            resultDiv.style.borderColor = "#f5c6cb";
                            resultDiv.style.color = "#721c24";
                            resultDiv.innerHTML = "<strong>❌ 测试失败</strong><div style=\"margin-top:5px;\">请求失败: " + error.message + "</div>";
                        });
                    });
                }
            })();
            </script>
        ');
        $form->addItem($testSection);
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
     * 创建 WebDAV 目录
     */
    private static function createWebDAVDirectory()
    {
        $webdavDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/webdav';

        try {
            if (!is_dir($webdavDir)) {
                // 递归创建目录
                if (!mkdir($webdavDir, 0755, true)) {
                    // 目录创建失败，记录警告但不中断插件激活
                    error_log('[MediaLibrary] Failed to create WebDAV directory: ' . $webdavDir);
                    return false;
                }

                // 创建 .htaccess 文件保护目录
                $htaccess = $webdavDir . '/.htaccess';
                $htaccessContent = "# WebDAV directory\n# Access controlled by WebDAV authentication\nOrder Allow,Deny\nAllow from all\n";
                @file_put_contents($htaccess, $htaccessContent);

                // 创建 README.md 说明文件
                $readme = $webdavDir . '/README.md';
                $readmeContent = "# WebDAV 存储目录\n\n";
                $readmeContent .= "这是 MediaLibrary 插件的 WebDAV 本地缓存目录。\n\n";
                $readmeContent .= "## 用途\n\n";
                $readmeContent .= "- 用于缓存从 WebDAV 服务器同步的文件\n";
                $readmeContent .= "- 作为本地备份和快速访问的媒体文件存储\n\n";
                $readmeContent .= "## 注意事项\n\n";
                $readmeContent .= "- 请勿手动删除或修改此目录中的文件\n";
                $readmeContent .= "- 文件管理应通过媒体库插件的 WebDAV 管理界面进行\n";
                @file_put_contents($readme, $readmeContent);
            }

            return true;
        } catch (Exception $e) {
            error_log('[MediaLibrary] Exception while creating WebDAV directory: ' . $e->getMessage());
            return false;
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
