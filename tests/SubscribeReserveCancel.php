<?php
/*
 * 카드 정기 결제 예약
 */
require_once '../vendor/autoload.php';
use Bootpay\BackendPhp\BootpayApi;  

$bootpay = BootpayApi::setConfig(
    '5b8f6a4d396fa665fdc2b5ea',
    'rm6EYECr6aroQVG2ntW0A6LpWnkTgP4uQ3H18sDDUYw='
);

$response = $bootpay->requestAccessToken();

$reserveId = '613af2600199430027b5cb83';

if ($response->status === 200) { 
    $result = $bootpay->subscribeCardBillingReserveCancel($reserveId);
    var_dump($result);
}