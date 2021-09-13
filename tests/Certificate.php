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

$receiptId = '[[ receipt_id ]]';

if ($response->status === 200) {
    $result = $bootpay->certificate($receiptId);
    var_dump($result);
}