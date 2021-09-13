<?php
require_once '../vendor/autoload.php';
use Bootpay\BackendPhp\BootpayApi; 

$bootpay = BootpayApi::setConfig(
    '5b8f6a4d396fa665fdc2b5ea',
    'rm6EYECr6aroQVG2ntW0A6LpWnkTgP4uQ3H18sDDUYw='
);

$response = $bootpay->requestAccessToken();

if ($response->status === 200) {
    $result = $bootpay->requestPayment(
        time(),
        '테스트 부트페이 상품',
        1000,
        [
            'pg' => 'nicepay',
            // 'method' => 'card',
            'methods' => ['card', 'phone', 'bank', 'vbank'],
        ]
    );

    // $result = $bootpay->requestPayment([
    //     'methods' => ['card', 'phone'],
    //     'order_id' => time(),
    //     'price' => 1000,
    //     'name' => '테스트 부트페이 상품',
    //     # 결제 정보를 리턴받은 URL
    //     'return_url' => 'https://dev-api.bootpay.co.kr/callback',
    //     'extra' => [
    //         'expire' => 30
    //     ]
    // ]);
    var_dump($result);
}