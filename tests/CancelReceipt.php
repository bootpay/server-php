<?php 
/*
 * 취소 테스트 예제 입니다.
 */ 
require_once '../vendor/autoload.php';
use Bootpay\BackendPhp\BootpayApi; 


$bootpay = BootpayApi::setConfig(
    '5b8f6a4d396fa665fdc2b5ea',
    'rm6EYECr6aroQVG2ntW0A6LpWnkTgP4uQ3H18sDDUYw='
);

$response = $bootpay->requestAccessToken();

$receiptId = '613f101f0d681b0023e6e53f';

if ($response->status === 200) { 
    // $result = $bootpay->cancel($receiptId);
    $result = $bootpay->cancel(
        $receiptId, 
        null, 
        'API 관리자',
        'API에 의한 요청',
        time(),
        null,
        [
            'account' => '66569112432134',
            'accountholder' => '홍길동',
            'bankcode' => BootpayApi::BankCode['국민은행']
        ]
    );
    var_dump($result);
}