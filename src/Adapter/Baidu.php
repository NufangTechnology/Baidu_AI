<?php
namespace NFTech\ASR\Adapter;

use NFTech\ASR\AsrInterface;
use NFTech\ASR\Exception;
use Swoole\Coroutine\Http\Client;

class Baidu implements AsrInterface
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
     * @param $userID string 用户唯一标识
     * @param $format string 语音文件格式 ['pcm', 'wav', 'opus', 'speex', 'amr']
     * @param $rate integer 采样率 [8000, 16000]
     * @param int $dev_pid string 模型id 1537:普通话(纯中文识别)  1536：    普通话(支持简单的英文识别) 1737：英语 1637:粤语  1837：四川话
     * @return array
     * @throws BaiduException
     */
    public function recognize(string $filePath, $format = 'wav', $rate = 16000, $dev_pid = 1537)
    {
        if (!is_file($filePath)) {
            throw new BaiduException('语音文件不存在');
        }
        if (!in_array($format, ['pcm', 'wav', 'opus', 'speex', 'amr'])) {
            throw new BaiduException('语音文件格式错误,当前支持以下格式:pcm（不压缩）、wav、opus、speex、amr');
        }
        if (!in_array($rate, [8000, 16000])) {
            throw new BaiduException('采样率错误，当前支持8000或者16000');
        }

        // 异步读取文件
//        $content = Coroutine::readFile($filePath);
        $content = file_get_contents($filePath);
        if ($content == false) {
            throw new BaiduException('音频文件[' . $filePath . ']读取失败');
        }

        // 身份认证
        $this->identityAuth();

        // 发起转换请求
        return $this->request(
            [
                'dev_pid' => $dev_pid,
                'format'  => $format,
                'rate'    => $rate,
                'channel' => 1,
                'speech'  => base64_encode($content),
                'len'     => strlen($content),
                'cuid'    => md5($this->authInfo['access_token']),
                'token'   => $this->authInfo['access_token']
            ]
        );
    }

    /**
     * 执行网络请求
     *
     * @param array $options
     * @return mixed
     * @throws Exception
     */
    protected function request(array $options)
    {
        if ($this->isCloudUser) {
            $options['access_token'] = $this->authInfo['access_token'];
        }

        // 特殊处理
        $client = new Client('vop.baidu.com');
        $client->set(
                 [
                 'buffer_output_size' => 32 * 1024 * 1024,
                 'package_max_length' => 1024 * 1024 * 2,
                 'socket_buffer_size' => 1024 * 1024 * 2, //2M缓存区
                 ]
                    );
        $client->setHeaders(
                        [
                        'Content-Type' => 'application/json'
                        ]
                           );
        $client->post('/server_api', json_encode($options));
        $client->close();

        // 提取结果
        $result = json_decode($client->body, true);

        // 获取结果失败
        if (!is_array($result)) {
            throw new Exception('获取音频转换结果失败');
        }
        // 转换出错
        if ($result['err_no'] > 0) {
            throw new Exception($result['err_msg'], $result['err_no']);
        }

        return $result['result'];
    }

    /**
     * 账户身份认证
     *
     * @return array
     * @throws Exception
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
            $client = new Client('aip.baidubce.com');
            $client->get('/oauth/2.0/token?' . $queryStr);
            $client->close();

            // 授权信息
            $result = json_decode($client->body, true);

            // 认证失败
            if (isset($result['error'])) {
                throw new Exception('身份认证失败：' . $result['error_description']);
            }
            //
            if (stripos('brain_all_scope', $result['scope'])) {
                $this->isCloudUser = true;
            }

            // 用户授权信息
            $this->authInfo = $result;
            // 计算过期时间（每 25 天重新获取一次）
            $this->freshAuthTimestamp = $time + 86400 * 25;
        }

        return $this->authInfo;
    }

    /**
     * 处理请求参数
     * @param string $url
     * @param array $params
     * @param array $data
     * @param array $headers
     */
    protected function processRequest(string $url, array &$params, array &$data, $headers)
    {
        $token        = isset($params['access_token']) ? $params['access_token'] : '';
        $data['cuid'] = md5($token);

        if ($url === $this->asrUrl) {
            $data['token'] = $token;
            $data          = json_encode($data);
        } else {
            $data['tok'] = $token;
        }

        unset($params['access_token']);
    }

    /**
     * @param  string $method HTTP method
     * @param  string $url
     * @param  array $param 参数
     * @return array
     */
    private function getAuthHeaders($method, $url, $params = array(), $headers = array())
    {

        //不是云的老用户则不用在header中签名 认证
        if ($this->isCloudUser === false) {
            return $headers;
        }

        $obj = parse_url($url);
        if (!empty($obj['query'])) {
            foreach (explode('&', $obj['query']) as $kv) {
                if (!empty($kv)) {
                    list($k, $v) = explode('=', $kv, 2);
                    $params[$k] = $v;
                }
            }
        }

        //UTC 时间戳
        $timestamp             = gmdate('Y-m-d\TH:i:s\Z');
        $headers['Host']       = isset($obj['port']) ? sprintf('%s:%s', $obj['host'], $obj['port']) : $obj['host'];
        $headers['x-bce-date'] = $timestamp;

        //签名
        $headers['authorization'] = AipSampleSigner::sign(array(
            'ak' => $this->apiKey,
            'sk' => $this->secretKey,
        ), $method, $obj['path'], $headers, $params, array(
            'timestamp'     => $timestamp,
            'headersToSign' => array_keys($headers),
        ));

        return $headers;
    }
}
