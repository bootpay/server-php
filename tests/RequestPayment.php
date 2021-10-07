<?php
/*
 * 결제링크 생성하기 관련 예제입니다. 
 */
require_once '../vendor/autoload.php';
use Bootpay\BackendPhp\BootpayApi; 

$bootpay = BootpayApi::setConfig(
    '5b8f6a4d396fa665fdc2b5ea',
    'rm6EYECr6aroQVG2ntW0A6LpWnkTgP4uQ3H18sDDUYw='
);

$response = $bootpay->requestAccessToken();

if ($response->status === 200) {

    $params = [
        'callback1' => '그대로 콜백받을 변수 1',
        'callback2' => '그대로 콜백받을 변수 2',
        'callback3' => '그대로 콜백받을 변수 3'
    ];

    $optional = [
        'pg' => 'nicepay',
        // 'method' => 'card',
        'methods' => ['card', 'phone', 'bank', 'vbank'],
        'params' => json_encode($params),
        'extra' => [
            'raw_data' => 1
        ]
    ];

    $result = $bootpay->requestPayment(
        time(),
        '테스트 부트페이 상품',
        1000,
        $optional
    );  
    var_dump($result);
}