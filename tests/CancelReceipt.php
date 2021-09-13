<?php namespace Bootpay\BackendPhp\Tests;
/*
 * 취소 테스트 예제 입니다.
 */
// require_once '../src/BootpayApi.php'; 
// require_once '../src/Singleton.php'; 
require_once '../autoload.php'; 
use Bootpay\BackendPhp\BootpayApi; 

var_dump('2134');

$receiptId = '주문번호';

$bootpay = BootpayApi::setConfig(
    '5b8f6a4d396fa665fdc2b5ea',
    'rm6EYECr6aroQVG2ntW0A6LpWnkTgP4uQ3H18sDDUYw='
);

$response = $bootpay->requestAccessToken();

if ($response->status === 200) { 
    $result = $bootpay->cancel($receiptId);
    var_dump($result);
}