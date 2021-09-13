<?php
/*
 * 카드 정기 결제 요청 REST 방식
 */
require_once '../vendor/autoload.php';
use Bootpay\BackendPhp\BootpayApi; 

$bootpay = BootpayApi::setConfig(
    '5b8f6a4d396fa665fdc2b5ea',
    'rm6EYECr6aroQVG2ntW0A6LpWnkTgP4uQ3H18sDDUYw='
);

$response = $bootpay->requestAccessToken();

if ($response->status === 200) { 
    $result = $bootpay->getSubscribeBillingKey(
        'nicepay',
        time(),
        '30일 정기권 결제', 
        '카드 번호',
        '카드 비밀번호 앞에 2자리',
        '카드 만료 연도 2자리',
        '카드 만료 월 2자리',
        '주민등록번호 또는 사업자번호'
    ); 

    var_dump($result); 
}
