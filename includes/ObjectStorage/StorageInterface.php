<?php
namespace MediaLibrary_ObjectStorage;

/**
 * 对象存储接口
 * 定义所有对象存储适配器必须实现的方法
 */
interface StorageInterface
{
    /**
     * 上传文件到对象存储
     * @param string $localPath 本地文件路径
     * @param string $remotePath 远程存储路径
     * @return array 返回结果 ['success' => bool, 'url' => string, 'error' => string]
     */
    public function upload($localPath, $remotePath);

    /**
     * 删除对象存储中的文件
     * @param string $remotePath 远程文件路径
     * @return array 返回结果 ['success' => bool, 'error' => string]
     */
    public function delete($remotePath);

    /**
     * 检查文件是否存在
     * @param string $remotePath 远程文件路径
     * @return bool
     */
    public function exists($remotePath);

    /**
     * 获取文件访问URL
     * @param string $remotePath 远程文件路径
     * @return string 文件访问URL
     */
    public function getUrl($remotePath);

    /**
     * 测试连接
     * @return array 返回结果 ['success' => bool, 'message' => string]
     */
    public function testConnection();

    /**
     * 获取存储类型名称
     * @return string
     */
    public function getName();
}
