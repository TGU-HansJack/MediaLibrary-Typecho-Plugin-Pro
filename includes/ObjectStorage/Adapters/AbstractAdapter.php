<?php
namespace MediaLibrary_ObjectStorage\Adapters;

use MediaLibrary_ObjectStorage\StorageInterface;

/**
 * 对象存储适配器基类
 * 提供通用功能实现
 */
abstract class AbstractAdapter implements StorageInterface
{
    /**
     * @var array 配置参数
     */
    protected $config;

    /**
     * @var string 错误信息
     */
    protected $lastError = '';

    /**
     * 构造函数
     * @param array $config 配置参数
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->init();
    }

    /**
     * 初始化方法，子类可重写
     */
    protected function init()
    {
    }

    /**
     * 获取最后一次错误信息
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * 设置错误信息
     * @param string $error 错误信息
     */
    protected function setError($error)
    {
        $this->lastError = $error;
    }

    /**
     * 获取配置项
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function getConfig($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * 生成远程路径
     * @param string $path 本地路径
     * @return string
     */
    protected function generateRemotePath($path)
    {
        $prefix = $this->getConfig('path_prefix', '');
        $prefix = rtrim($prefix, '/');
        $path = ltrim($path, '/');
        return $prefix ? "{$prefix}/{$path}" : $path;
    }

    /**
     * 规范化路径
     * @param string $path 路径
     * @return string
     */
    protected function normalizePath($path)
    {
        // 移除多余的斜杠
        $path = preg_replace('#/+#', '/', $path);
        // 移除开头的斜杠
        $path = ltrim($path, '/');
        return $path;
    }

    /**
     * 记录日志
     * @param string $action 操作名称
     * @param string $message 消息内容
     * @param array $context 上下文信息
     * @param string $level 日志级别 info/warning/error
     */
    protected function log($action, $message, array $context = [], $level = 'info')
    {
        // 获取存储类型名称
        $storageName = method_exists($this, 'getName') ? $this->getName() : get_class($this);

        // 添加存储类型到上下文
        $context['storage_type'] = $storageName;

        // 调用全局日志记录器
        if (class_exists('MediaLibrary_Logger')) {
            \MediaLibrary_Logger::log($action, $message, $context, $level);
        }
    }

    /**
     * 记录上传操作日志
     * @param string $localPath 本地路径
     * @param string $remotePath 远程路径
     * @param bool $success 是否成功
     * @param string $error 错误信息
     * @param array $additionalContext 额外上下文信息
     */
    protected function logUpload($localPath, $remotePath, $success, $error = '', $additionalContext = [])
    {
        $context = array_merge([
            'local_path' => $localPath,
            'remote_path' => $remotePath,
            'file_size' => file_exists($localPath) ? filesize($localPath) : 0,
            'file_name' => basename($localPath)
        ], $additionalContext);

        if ($success) {
            $this->log('object_storage_upload', '文件上传成功', $context, 'info');
        } else {
            $context['error'] = $error;
            $this->log('object_storage_upload', '文件上传失败: ' . $error, $context, 'error');
        }
    }

    /**
     * 记录删除操作日志
     * @param string $remotePath 远程路径
     * @param bool $success 是否成功
     * @param string $error 错误信息
     * @param array $additionalContext 额外上下文信息
     */
    protected function logDelete($remotePath, $success, $error = '', $additionalContext = [])
    {
        $context = array_merge([
            'remote_path' => $remotePath
        ], $additionalContext);

        if ($success) {
            $this->log('object_storage_delete', '文件删除成功', $context, 'info');
        } else {
            $context['error'] = $error;
            $this->log('object_storage_delete', '文件删除失败: ' . $error, $context, 'error');
        }
    }

    /**
     * 记录连接测试日志
     * @param bool $success 是否成功
     * @param string $message 消息内容
     * @param array $additionalContext 额外上下文信息
     */
    protected function logConnectionTest($success, $message, $additionalContext = [])
    {
        $level = $success ? 'info' : 'error';
        $this->log('object_storage_connection_test', $message, $additionalContext, $level);
    }
}
