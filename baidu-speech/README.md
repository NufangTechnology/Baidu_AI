集成了 [百度AI](http://ai.baidu.com/) 官方的Api接口。

## 安装

 1. 安装包文件

	``` bash
	$ composer require jormin/baidu-speech
	```

## 说明

 1. 返回结果说明

	接口返回数据已经经过打包处理，为数组格式，包含三个字段:
    
    | 参数  | 类型  | 说明  | 可为空  |
    | ------------ | ------------ | ------------ | ------------ |
    | success | bool | 是否成功 | N |
    | msg | String | 结果说明 | N |
    | data | array | 百度语音接口返回数据，字段详细见 [百度官方文档](https://cloud.baidu.com/doc/SPEECH/TTS-Online-PHP-SDK.html). | Y |

## 使用

1. 语音识别
    
    ```php
       $baiduSpeech = new NufangTechnology\Baidu_AI\BaiduSpeech($appID, $apiKey, $secretKey);
       $baiduSpeech->recognize($filePath, $url, $callback, $userID, $format, $rate, $lan);
    ```
     
    接口字段：
    
    | 参数  | 类型  | 说明  | 可为空  |
    | ------------ | ------------ | ------------ | ------------ |
    | filePath | String | 语音文件本地路径，该字段和url字段二选一，优先使用此项 | Y |
    | url | String | 语音文件URL路径，该字段和filePath字段二选一 | Y |
    | callback | String | 回调地址 | Y |
    | userID | String | 用户唯一标识 | Y |
    | format | String | 语音文件格式，可选值 ['pcm', 'wav', 'opus', 'speex', 'amr']，默认为wav | Y |
    | rate | Integer | 采样率，可选值 [8000, 16000]，默认为16000 | Y |
    | lan | String | 语言，可选值 ['zh', 'ct', 'en']，默认为zh | Y |

2. 语音合成
    
    ```php
       $baiduSpeech = new NufangTechnology\Baidu_AI\BaiduSpeech($appID, $apiKey, $secretKey);
       $baiduSpeech->combine($storagePath, $text, $userID, $lan, $speed, $pitch, $volume, $person);
    ```
         
    接口字段：
    
    | 参数  | 类型  | 说明  | 可为空  |
    | ------------ | ------------ | ------------ | ------------ |
    | storagePath | String | 文件存储路径，需是绝对路径 | N |
    | text | String | 合成的文本 | N |
    | userID | String | 用户唯一标识 | Y |
    | lan | String | 语言，可选值 ['zh']，默认为zh | Y |
    | speed | Integer | 语速，取值0-9，默认为5中语速 | Y |
    | pitch | Integer | 音调，取值0-9，默认为5中语调 | Y |
    | volume | Integer | 音量，取值0-15，默认为5中音量 | Y |
    | person | Integer | 发音人选择, 0为女声，1为男声，3为情感合成-度逍遥，4为情感合成-度丫丫，默认为普通女 | Y |

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
