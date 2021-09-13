<?php
/*
 * Access Token 요청 예제입니다.
 */
require_once '../vendor/autoload.php';
use Bootpay\BackendPhp\BootpayApi; 

$bootpay = BootpayApi::setConfig(
    '5b8f6a4d396fa665fdc2b5ea',
    'rm6EYECr6aroQVG2ntW0A6LpWnkTgP4uQ3H18sDDUYw='
);

$response = $bootpay->requestAccessToken();

if ($response->status === 200) {
    print $response->data->token . "\n";
    print $response->data->server_time . "\n";
    print $response->data->expired_at . "\n";
} else {
    var_dump($response);
}