<?php
/*
 * 취소 테스트 예제 입니다.
 */
require_once 'vendor/autoload.php'; 
use \Bootpay\BackendPhp\BootpayApi; 

$receiptId = '[[ receipt_id ]]';

$bootpay = BootpayApi::setConfig(
    '59bfc738e13f337dbd6ca48a',
    'pDc0NwlkEX3aSaHTp/PPL/i8vn5E/CqRChgyEp/gHD0=',
    'development'
);

$response = $bootpay->requestAccessToken();

if ($response->status === 200) {
    $result = $bootpay->certificate($receiptId);
    var_dump($result);
}