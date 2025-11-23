<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/MediaLibrary/includes/LogAction.php';

/**
 * 媒体库管理插件，可以在后台对整体文件信息的查看和编辑、上传和删除，图片压缩和隐私检测，多媒体预览，文章编辑器中预览和插入的简单媒体库
 * 
 * @package MediaLibrary
 * @author HansJack
 * @version free_version
 * @link http://bbs.tiango.wiki/
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
        Helper::addAction('medialibraryLogs', 'MediaLibrary_LogAction');
        
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
        Helper::removeAction('medialibraryLogs');
        
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
        Helper::addAction('medialibraryLogs', 'MediaLibrary_LogAction');
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
        $versionHtml .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">';
        $versionHtml .= '<div>';
        $versionHtml .= '<h3 style="margin:0 0 5px 0;color:#333;">媒体库管理插件</h3>';
        $versionHtml .= '<p style="margin:0;color:#666;">当前版本: <strong>' . htmlspecialchars($currentVersion) . '</strong></p>';
        $versionHtml .= '</div>';
        $versionHtml .= '<div>';
        $versionHtml .= '<a href="' . htmlspecialchars($repoUrl) . '" target="_blank" rel="noopener" style="display:inline-block;text-decoration:none;">';
        $versionHtml .= '<img src="https://img.shields.io/badge/GitHub-Repo-181717?logo=github&logoColor=white" alt="GitHub Repository" style="height:28px;">';
        $versionHtml .= '</a>';
        $versionHtml .= '</div>';
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
        $detailHtml .= '<div style="background:#f9f9f9;padding:15px;border:1px solid #ddd;border-radius:4px;margin-bottom:15px;">';
        $detailHtml .= '<h4 style="margin:0 0 10px 0;color:#333;">📊 系统信息</h4>';
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
        $detailHtml .= '<div style="background:#f9f9f9;padding:15px;border:1px solid #ddd;border-radius:4px;margin-bottom:15px;">';
        $detailHtml .= '<h4 style="margin:0 0 10px 0;color:#333;">🔌 PHP 扩展检测</h4>';
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
        $detailHtml .= '<div style="background:#f9f9f9;padding:15px;border:1px solid #ddd;border-radius:4px;margin-bottom:15px;">';
        $detailHtml .= '<h4 style="margin:0 0 10px 0;color:#333;">⚙️ PHP 函数检测</h4>';
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

        $detailHtml .= '<div style="background:#f9f9f9;padding:15px;border:1px solid #ddd;border-radius:4px;margin-bottom:15px;">';
        $detailHtml .= '<h4 style="margin:0 0 10px 0;color:#333;">📁 文件完整性检测</h4>';
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
        $logHtml = '<div class="ml-log-viewer">';
        $logHtml .= '<div class="ml-log-head">';
        $logHtml .= '<div><h4 style="margin:0 0 6px 0;">处理流程日志</h4>';
        $logHtml .= '<p style="margin:0;color:#666;font-size:13px;">查看所有操作的实时记录，点击每行可展开详细信息</p></div>';
        $logHtml .= '<div class="ml-log-actions">';
        $logHtml .= '<button type="button" class="btn btn-s" id="ml-refresh-logs">刷新日志</button>';
        $logHtml .= '<button type="button" class="btn btn-s" id="ml-clear-logs" style="background:#dc3232;color:#fff;margin-left:10px;">清空日志</button>';
        $logHtml .= '</div></div>';
        $logHtml .= '<div class="ml-log-meta">日志文件位置：<code style="font-size:12px;">' . htmlspecialchars($logFile) . '</code></div>';
        $logHtml .= '<div id="ml-log-status" class="ml-log-status"></div>';
        $logHtml .= '<div id="ml-log-list" class="ml-log-list"><div class="ml-log-empty">正在加载日志...</div></div>';
        $logHtml .= '</div>';

        echo $logHtml;
    }

    /**
     * 添加配置页面的 JavaScript 和 CSS
     */
    private static function addConfigPageAssets()
    {
        $pluginUrl = Helper::options()->pluginUrl . '/MediaLibrary';

        ob_start();
        Helper::options()->adminStaticUrl('js', 'jquery.js');
        $jquerySource = trim(ob_get_clean());

        if (!empty($jquerySource)) {
            echo '<script src="' . $jquerySource . '"></script>';
        }

        echo '<style>
.ml-log-viewer{background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;margin:20px 0 30px;box-shadow:0 1px 3px rgba(0,0,0,0.05);}
.ml-log-head{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:10px;}
.ml-log-actions button{margin-left:0;}
.ml-log-meta{font-size:12px;color:#777;margin-bottom:8px;}
.ml-log-list{border-top:1px solid #eee;}
.ml-log-item{border-bottom:1px solid #f3f3f3;}
.ml-log-summary{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:12px 0;cursor:pointer;}
.ml-log-summary:hover{color:#0073aa;}
.ml-log-time{font-weight:600;color:#333;}
.ml-log-level{padding:2px 6px;border-radius:4px;font-size:11px;text-transform:uppercase;letter-spacing:.4px;}
.ml-log-level.level-info{background:#e3f6ed;color:#24613f;}
.ml-log-level.level-warning{background:#fff4e5;color:#7c4a03;}
.ml-log-level.level-error{background:#fdecea;color:#a82812;}
.ml-log-level.level-debug{background:#eef2ff;color:#1d3d8f;}
.ml-log-action{font-weight:600;color:#555;}
.ml-log-message{color:#555;flex:1;min-width:160px;}
.ml-log-user{margin-left:auto;color:#666;font-size:12px;}
.ml-log-detail{display:none;background:#f7f9fb;padding:12px;border-radius:4px;margin-bottom:12px;font-size:12px;line-height:1.4;white-space:pre-wrap;word-break:break-word;}
.ml-log-empty{padding:30px;text-align:center;color:#888;}
.ml-log-status{min-height:18px;font-size:12px;margin-bottom:10px;}
.ml-log-status.is-loading{color:#555;}
.ml-log-status.is-success{color:#2b6c4b;}
.ml-log-status.is-error{color:#b32700;}
</style>';

        $logEndpoint = addslashes(Typecho_Common::url('action/medialibraryLogs', Helper::options()->index));

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

    var logEndpoint = "' . $logEndpoint . '";
    var $logList = $("#ml-log-list");
    var $status = $("#ml-log-status");
    var statusTimer = null;

    function setStatus(message, type) {
        if (statusTimer) {
            clearTimeout(statusTimer);
            statusTimer = null;
        }
        if (!message) {
            $status.text("").removeClass("is-loading is-success is-error").show();
            return;
        }
        $status
            .removeClass("is-loading is-success is-error")
            .addClass("is-" + type)
            .text(message)
            .show();

        if (type !== "loading") {
            statusTimer = setTimeout(function() {
                $status.fadeOut(200, function() {
                    $(this).text("").removeClass("is-loading is-success is-error").show();
                });
            }, 2500);
        }
    }

    function setLogButtonsDisabled(disabled) {
        $("#ml-refresh-logs, #ml-clear-logs").prop("disabled", disabled);
    }

    function renderLogs(logs) {
        $logList.empty();
        if (!logs || !logs.length) {
            $logList.append("<div class=\"ml-log-empty\">暂无日志记录</div>");
            return;
        }

        logs.forEach(function(log) {
            var $item = $("<div>").addClass("ml-log-item");
            var $summary = $("<div>").addClass("ml-log-summary");
            var level = (log.level || "info").toLowerCase();
            var levelText = level.toUpperCase();
            var userLabel = "系统";
            if (log.user && (log.user.screenName || log.user.name)) {
                userLabel = log.user.screenName || log.user.name;
                if (log.user.group) {
                    userLabel += " · " + log.user.group;
                }
            }

            $("<span>").addClass("ml-log-time").text(log.timestamp || "-").appendTo($summary);
            $("<span>").addClass("ml-log-level level-" + level).text(levelText).appendTo($summary);
            $("<span>").addClass("ml-log-action").text("[" + (log.action || "unknown") + "]").appendTo($summary);
            $("<span>").addClass("ml-log-message").text(log.message || "").appendTo($summary);
            $("<span>").addClass("ml-log-user").text(userLabel).appendTo($summary);

            var $detail = $("<pre>").addClass("ml-log-detail");
            $detail.text(JSON.stringify(log, null, 2));
            $detail.hide();

            $summary.on("click", function() {
                $detail.slideToggle(140);
                $item.toggleClass("is-open");
            });

            $item.append($summary).append($detail);
            $logList.append($item);
        });
    }

    function fetchLogs() {
        setLogButtonsDisabled(true);
        setStatus("正在加载日志...", "loading");
        $.get(logEndpoint, { action: "get_logs", limit: 200 }, function(res) {
            if (res.success) {
                renderLogs(res.logs || []);
                setStatus("日志已更新", "success");
            } else {
                setStatus(res.message || "无法获取日志", "error");
            }
        }).fail(function() {
            setStatus("请求日志失败，请稍后重试", "error");
        }).always(function() {
            setLogButtonsDisabled(false);
        });
    }

    $("#ml-refresh-logs").on("click", function() {
        fetchLogs();
    });

    $("#ml-clear-logs").on("click", function() {
        if (!confirm("确定要清空所有日志吗？该操作不可恢复。")) {
            return;
        }
        setLogButtonsDisabled(true);
        setStatus("正在清空日志...", "loading");
        $.post(logEndpoint, { action: "clear_logs" }, function(res) {
            if (res.success) {
                renderLogs([]);
                setStatus(res.message || "日志已清空", "success");
            } else {
                setStatus(res.message || "清空失败", "error");
            }
        }).fail(function() {
            setStatus("请求失败，请稍后再试", "error");
        }).always(function() {
            setLogButtonsDisabled(false);
        });
    });

    fetchLogs();
});
</script>';
    }

    /**
     * 显示环境信息
     */
    private static function displayEnvironmentInfo($form, $envInfo)
    {
        $envHtml = '<div style="background:#f9f9f9;padding:15px;border:1px solid #ddd;border-radius:4px;margin-bottom:20px;">';
        $envHtml .= '<h4 style="margin:0 0 10px 0;color:#333;">系统环境检测</h4>';
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
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
}
