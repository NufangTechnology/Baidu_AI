<?php

namespace NFTech\ASR\Adapter;

use NFTech\ASR\AsrInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;

/**
 * 百度语音转文字
 *
 * @package NFTech\ASR\Adapter
 */
class BaiduNlp implements AsrInterface
{
    /**
     * @var string
     */
    private $appID;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var bool
     */
    private $isCloudUser = false;

    /**
     * @var array
     */
    private $authInfo;

    /**
     * @var int
     */
    private $freshAuthTimestamp = 0;

    /**
     * Speech constructor.
     *
     * @param string $appID
     * @param string $apiKey
     * @param string $secretKey
     */
    public function __construct(string $appID, string $apiKey, string $secretKey)
    {
        $this->appID     = $appID;
        $this->apiKey    = $apiKey;
        $this->secretKey = $secretKey;
    }

    /**
     * 语音识别
     *
     * @param $filePath string 语音文件本地路径,优先使用此项
     * @param $format string 语音文件格式 ['pcm', 'wav', 'opus', 'speex', 'amr']
     * @param $rate integer 采样率 [8000, 16000]
     * @param int $dev_pid string 模型id 1537:普通话(纯中文识别)  1536：    普通话(支持简单的英文识别) 1737：英语 1637:粤语  1837：四川话
     * @return array
     * @throws BaiduException
     */
    public function sentiment_classify($text)
    {

        // 身份认证
        $this->identityAuth();

        // 发起转换请求
        return $this->request(
            [
                'text'    =>   $text,
                'access_token'   => $this->authInfo['access_token']
            ]
        );
    }

    /**
     * 执行网络请求
     *
     * @param array $options
     * @return mixed
     * @throws BaiduException
     */
    protected function request(array $options)
    {
        if ($this->isCloudUser) {
            $options['access_token'] = $this->authInfo['access_token'];
        }

        // 特殊处理
        $client = new Client('aip.baidubce.com',443,true);
        $client->set(
            [
                'timeout'            => 10, // 请求超时时间(10秒)
            ]
        );
        $client->setHeaders(
            [
                'Content-Type' => 'application/json'
            ]
        );
        $data['text']=$options['text'];
        $data=mb_convert_encoding(json_encode($data), 'GBK', 'UTF8');

        $client->post('/rpc/2.0/nlp/v1/sentiment_classify?access_token='.$options['access_token'], $data);
        $client->close();

        // 提取结果
        $result = json_decode(mb_convert_encoding($client->body, 'UTF8', 'GBK'), true);
        if (!is_array($result)) {
            throw new BaiduException('获取结果失败：statusCode - ' . $client->statusCode . ' | body - ' . $client->body . ' | errorMsg - ' . $client->errMsg, 500900);
        }
        // 转换出错
        if ($result['err_no'] > 0) {
            throw new BaiduException('情感识别失败，请重试：' . $result['err_no'] . ' - ' . $result['err_msg'], 500900);
        }
//
        return $result;
    }

    /**
     * 账户身份认证
     *
     * @return array
     * @throws BaiduException
     */
    private function identityAuth()
    {
        $time = time();

        if ($time > $this->freshAuthTimestamp) {
            // 发起认证请求
            // 构建 get 参数
            $queryStr = http_build_query(
                [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->apiKey,
                    'client_secret' => $this->secretKey,
                ]
            );
            $client   = new Client('aip.baidubce.com');
            $client->set(
                [
                    'timeout' => 3, // 请求超时时间
                ]
            );
            $client->get('/oauth/2.0/token?' . $queryStr);
            $client->close();

            // 授权信息
            $result = json_decode($client->body, true);

            // 身份认证失败
            if (isset($result['error'])) {
                throw new BaiduException('身份认证失败：' . $result['error_description']);
            }
            // 应该是所有权限的意思（百度文档没有描述）
            if (stripos($result['scope'], 'brain_all_scope') === false) {
                $this->isCloudUser = true;
            }

            // 用户授权信息
            $this->authInfo = $result;
            // 计算过期时间（每 25 天重新获取一次）
            $this->freshAuthTimestamp = $time + (86400 * 25);
        }

        return $this->authInfo;
    }
}

