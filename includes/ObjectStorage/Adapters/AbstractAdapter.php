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
}
