<?php
require dirname(__DIR__) . '/vendor/autoload.php';

$config = [
    'app_id'     => '10407725',
    'api_key'    => 'FLwijmOb1mL7wOePrwBuvpyy',
    'secret_key' => '5IXm5P8oyiFUWjKIctWdwMIcj7GRsZ5b',
];

//$ai = new \NufangTechnology\BaiduAi\Speech($config['app_id'], $config['api_key'], $config['secret_key']);
//$r = $ai->recognize(__DIR__ . '/1.wav');

go(function () use ($config) {
    $asr = new \NFTech\ASR\Adapter\Baidu($config['app_id'], $config['api_key'], $config['secret_key']);
    $r = $asr->recognize(__DIR__ . '/1.wav');

    file_put_contents(__DIR__ . '/result.log', json_encode($r));
});
