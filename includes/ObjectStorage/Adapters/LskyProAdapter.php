<?php
namespace MediaLibrary_ObjectStorage\Adapters;

/**
 * LskyPro适配器
 * 通过API接口实现，无需额外SDK
 */
class LskyProAdapter extends AbstractAdapter
{
    private $apiUrl;
    private $token;

    protected function init()
    {
        $this->apiUrl = rtrim($this->getConfig('api_url'), '/');
        $this->token = $this->getConfig('token');
        $strategyId = $this->getConfig('strategy_id');

        if (!$this->apiUrl || !$this->token) {
            throw new \Exception('LskyPro配置不完整');
        }
    }

    public function upload($localPath, $remotePath)
    {
        try {
            $strategyId = $this->getConfig('strategy_id');

            $ch = curl_init();
            $headers = [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
            ];

            $file = new \CURLFile($localPath);
            $postData = ['file' => $file];

            if ($strategyId) {
                $postData['strategy_id'] = $strategyId;
            }

            curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/api/v1/upload');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new \Exception('上传失败，HTTP状态码: ' . $httpCode);
            }

            $result = json_decode($response, true);

            if (!$result || !isset($result['status']) || !$result['status']) {
                throw new \Exception('上传失败: ' . ($result['message'] ?? '未知错误'));
            }

            $url = $result['data']['links']['url'] ?? '';

            return [
                'success' => true,
                'url' => $url,
                'error' => '',
                'key' => $result['data']['key'] ?? ''
            ];
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return [
                'success' => false,
                'url' => '',
                'error' => $e->getMessage()
            ];
        }
    }

    public function delete($remotePath)
    {
        try {
            // LskyPro使用图片key而不是路径
            $ch = curl_init();
            $headers = [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
            ];

            curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/api/v1/images/' . $remotePath);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new \Exception('删除失败，HTTP状态码: ' . $httpCode);
            }

            $result = json_decode($response, true);

            if (!$result || !isset($result['status']) || !$result['status']) {
                throw new \Exception('删除失败: ' . ($result['message'] ?? '未知错误'));
            }

            return ['success' => true, 'error' => ''];
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function exists($remotePath)
    {
        try {
            $ch = curl_init();
            $headers = [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
            ];

            curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/api/v1/images/' . $remotePath);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUrl($remotePath)
    {
        // LskyPro上传后直接返回完整URL，这里不需要拼接
        return $remotePath;
    }

    public function testConnection()
    {
        try {
            $ch = curl_init();
            $headers = [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
            ];

            curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/api/v1/profile');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return [
                    'success' => true,
                    'message' => '连接成功'
                ];
            } else {
                throw new \Exception('HTTP状态码: ' . $httpCode);
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '连接失败: ' . $e->getMessage()
            ];
        }
    }

    public function getName()
    {
        return 'LskyPro';
    }
}
