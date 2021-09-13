<?php
/*
 * 카드 정기 결제 예약
 */
require_once '../vendor/autoload.php';
use Bootpay\BackendPhp\BootpayApi;  

$bootpay = BootpayApi::setConfig(
    '59bfc738e13f337dbd6ca48a',
    'pDc0NwlkEX3aSaHTp/PPL/i8vn5E/CqRChgyEp/gHD0='
);

$response = $bootpay->requestAccessToken();

$billingKey = '613af2600199430027b5cb83';

if ($response->status === 200) {
    $result = $bootpay->subscribeCardBillingReserve(
        $billingKey,
        time(),
        '정기결제 테스트 아이템',
        1000,
        time() + 10
    ); 

    var_dump($result);
}