<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

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
        
        // 系统环境检测
        $envInfo = MediaLibrary_EnvironmentCheck::checkEnvironment();
        
        // 环境状态显示
        self::displayEnvironmentInfo($form, $envInfo);
        
        // 添加配置选项
        self::addConfigOptions($form, $envInfo);
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
