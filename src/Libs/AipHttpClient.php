<?php
/*
* Copyright (c) 2017 Baidu.com, Inc. All Rights Reserved
*
* Licensed under the Apache License, Version 2.0 (the "License"); you may not
* use this file except in compliance with the License. You may obtain a copy of
* the License at
*
* Http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
* WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
* License for the specific language governing permissions and limitations under
* the License.
*/

namespace NufangTechnology\BaiduAi\Libs;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Http\Client;

/**
 * Http Client
 */
class AipHttpClient{

    /**
     * HttpClient
     * @param array $headers HTTP header
     */
    public function __construct($headers=array()){
        $this->headers = $this->buildHeaders($headers);
        $this->connectTimeout = 60000;
        $this->socketTimeout = 60000;
    }

    /**
     * 连接超时
     * @param int $ms 毫秒
     */
    public function setConnectionTimeoutInMillis($ms){
        $this->connectTimeout = $ms;
    }

    /**
     * 响应超时
     * @param int $ms 毫秒
     */
    public function setSocketTimeoutInMillis($ms){
        $this->socketTimeout = $ms;
    }    

    /**
     * @param  string $url
     * @param  array $data HTTP POST BODY
     * @param  array $param HTTP URL
     * @param  array $headers HTTP header
     * @return array
     */
    public function post($url, $data=array(), $params=array(), $headers=array()){
        $url = $this->buildUrl($url, $params);
        $headers = array_merge($this->headers, $this->buildHeaders($headers));
        
//        file_put_contents(__DIR__ . '/data.log', $data);

//        $client = new Client('vop.baidu.com');
//        $client = new Client('192.168.0.254', 8014);
////        $client = new Client('127.0.0.1', 8001);
//        $client->set(
//            [
//                'buffer_output_size' => 32 * 1024 *1024,
//                'package_max_length' => 1024 * 1024 * 2,
//            ]
//        );
////        $data = json_decode(file_get_contents(__DIR__ . '/data.log'), true);
////        unset($data['speech']);
////        $client->setData($data);
////        $client->post('/server_api', [$data => '']);
//        $client->setDefer();
//        $client->post('/demo/post', [$data => '']);
//        $body = $client->recv();
////        $client->post('/receive.php', [$data => '']);
//
//        print_r($client->statusCode);
//        print_r($client->errCode);
//        print_r($client->errMsg);
//
//        file_put_contents(__DIR__ . '/co-content.log', $client->body);

//        print_r($client);
        
//        exit();

        $file = __DIR__ . '/data.json';
        $cmd = "curl -X POST http://vop.baidu.com/server_api -H 'Content-Type: application/json' -H 'cache-control: no-cache' -d @$file";
        
        $body = Coroutine::exec($cmd);
//        print_r($body);

        return [
            'code'    => 0,
            'content' => $body['output'],
        ];
//        return json_decode($body['output'], true);
//        file_put_contents(__DIR__ . '/shell_exec.log', json_encode($body));
//        exit();



//        $chan = new Channel(1);

//        go(function () use ($chan, $url, $data, $headers) {

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'http://192.168.0.254:8014/demo/post');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->socketTimeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout);

            // 获取结果
            $content = curl_exec($ch);
            $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error   = curl_error($ch);
            
//            print_r($content);
            
            file_put_contents(__DIR__ . '/curl-content.log', $content);

            // 关闭连接
            curl_close($ch);
            
            exit();

            // 投递处理结果
//            $chan->push(
//                [
//                    'code'    => $code,
//                    'content' => $content,
//                    'error'   => $error
//                ]
//            );
//        });

        // 阻塞，等待上方投递数据结果
        $item = $chan->pop();
        // 请求失败
        if($item['code'] === 0){
            throw new Exception($item['error']);
        }

        return $item;
    }

    /**
     * @param  string $url
     * @param  array $datas HTTP POST BODY
     * @param  array $param HTTP URL
     * @param  array $headers HTTP header
     * @return array
     */
    public function multi_post($url, $datas=array(), $params=array(), $headers=array()){
        $url = $this->buildUrl($url, $params);
        $headers = array_merge($this->headers, $this->buildHeaders($headers));

        $chs = array();
        $result = array();
        $mh = curl_multi_init();
        foreach($datas as $data){        
            $ch = curl_init();
            $chs[] = $ch;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->socketTimeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout);
            curl_multi_add_handle($mh, $ch);
        }

        $running = null;
        do{
            curl_multi_exec($mh, $running);
            usleep(100);
        }while($running);

        foreach($chs as $ch){        
            $content = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result[] = array(
                'code' => $code,
                'content' => $content,
            );
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);
        
        return $result;
    }

    /**
     * @param  string $url
     * @param  array $param HTTP URL
     * @param  array $headers HTTP header
     * @return array
     */
    public function get($url, $params=array(), $headers=array()){
        $url = $this->buildUrl($url, $params);
        $headers = array_merge($this->headers, $this->buildHeaders($headers));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->socketTimeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout);
        $content = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if($code === 0){
            throw new Exception(curl_error($ch));
        }
        
        curl_close($ch);
        return array(
            'code' => $code,
            'content' => $content,
        );
    }

    /**
     * 构造 header
     * @param  array $headers
     * @return array
     */
    private function buildHeaders($headers){
        $result = array();
        foreach($headers as $k => $v){
            $result[] = sprintf('%s:%s', $k, $v);
        }
        return $result;
    }

    /**
     * 
     * @param  string $url
     * @param  array $params 参数
     * @return string
     */
    private function buildUrl($url, $params){
        if(!empty($params)){
            $str = http_build_query($params);
            return $url . (strpos($url, '?') === false ? '?' : '&') . $str;
        }else{
            return $url;
        }
    }
}
