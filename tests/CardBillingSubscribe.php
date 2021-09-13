<?php
/*
 * 카드 정기 결제
 */
require_once '../vendor/autoload.php';
use Bootpay\BackendPhp\BootpayApi; 

$billingKey = '613f0fe50d681b003fe6f1f9';

$bootpay = BootpayApi::setConfig(
    '5b8f6a4d396fa665fdc2b5ea',
    'rm6EYECr6aroQVG2ntW0A6LpWnkTgP4uQ3H18sDDUYw='
);

$response = $bootpay->requestAccessToken();

if ($response->status === 200) {
    $result = $bootpay->subscribeCardBilling(
        $billingKey,
        time(),
        '정기결제 테스트 아이템',
        1000
    );
    var_dump($result);
}