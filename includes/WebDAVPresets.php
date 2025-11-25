<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 提供常见 WebDAV 服务的配置模板，方便快速匹配不同厂商的要求
 */
class MediaLibrary_WebDAVPresets
{
    /**
     * 获取所有内置模板
     *
     * @return array
     */
    public static function getPresets()
    {
        return [
            'custom' => [
                'name' => '自定义/其他',
                'endpointPlaceholder' => 'https://example.com/webdav',
                'remotePathPlaceholder' => '/typecho',
                'endpointHelp' => '填写服务商提供的完整 WebDAV 根地址。',
                'remotePathHelp' => '用于存放 Typecho 文件的远程文件夹，可自定义。',
                'usernameHelp' => '',
                'passwordHelp' => '',
                'description' => '完全自定义的 WebDAV 连接，请根据服务商提供的地址、账号与目录填写。'
            ],
            'nextcloud' => [
                'name' => 'Nextcloud / ownCloud',
                'endpointPlaceholder' => 'https://cloud.example.com/remote.php/dav/files/用户名',
                'remotePathPlaceholder' => '/typecho',
                'endpointHelp' => '包含 remote.php/dav/files/<用户名> 完整路径，不能省略 /files/ 部分。',
                'remotePathHelp' => '在远程根目录创建一个文件夹保存 Typecho 媒体，例如 /typecho。',
                'usernameHelp' => 'Nextcloud/ownCloud 登录用户名',
                'passwordHelp' => '建议使用 Nextcloud 专用 App Password',
                'description' => '在 Nextcloud 「设置 > 安全」中创建专用应用密码，并填写完整的 files/{username} WebDAV 地址。'
            ],
            'jianguoyun' => [
                'name' => '坚果云',
                'endpointPlaceholder' => 'https://dav.jianguoyun.com/dav',
                'remotePathPlaceholder' => '/typecho',
                'endpointHelp' => '保持 https://dav.jianguoyun.com/dav，坚果云会自动映射到个人网盘根目录。',
                'remotePathHelp' => '可填写 /typecho 或其他自定义子目录，首次同步会自动创建。',
                'usernameHelp' => '坚果云账号邮箱',
                'passwordHelp' => '坚果云「安全选项」中生成的「第三方应用专用密码」',
                'description' => '在坚果云网页端开启第三方应用连接，生成专用密码后即可通过 WebDAV 访问。默认根目录是 /，可以自定义子目录存放 Typecho 媒体文件。'
            ],
            'alist' => [
                'name' => 'AList / Alist Drive',
                'endpointPlaceholder' => 'https://your-alist-domain/dav',
                'remotePathPlaceholder' => '/typecho',
                'endpointHelp' => 'AList 通常以 /dav 作为 WebDAV 根路径，若自定义请保持与面板一致。',
                'remotePathHelp' => 'AList 会将该路径映射到选定的存储驱动，可根据需要自定义。',
                'usernameHelp' => 'AList 账号（或专用 WebDAV 账号）',
                'passwordHelp' => '对应账号密码或令牌',
                'description' => 'AList 将不同网盘封装成统一的 WebDAV 接口。若使用需要认证的 AList，请确保已启用 WebDAV 并创建可用账号。'
            ]
        ];
    }

    /**
     * 根据 key 获取单个模板
     *
     * @param string $key
     * @return array|null
     */
    public static function getPreset($key)
    {
        $presets = self::getPresets();
        return $presets[$key] ?? null;
    }
}
