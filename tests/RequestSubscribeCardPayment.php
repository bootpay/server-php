<?php
/*
 * Access Token 요청 예제입니다.
 */
require_once '../vendor/autoload.php';
// require_once __DIR__.'/../src/BootpayApi.php'; 

use Bootpay\ServerPhp\BootpayApi;


BootpayApi::setConfiguration(
    '5b8f6a4d396fa665fdc2b5ea',
    'rm6EYECr6aroQVG2ntW0A6LpWnkTgP4uQ3H18sDDUYw='
);

$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    try {
        $response = BootpayApi::requestSubscribeCardPayment(array(
            'billing_key' => '62b41f88cf9f6d001ad212ad',
            'order_name' => '테스트결제',
            'price' => 1000,
            'order_id' => time()
        ));
        var_dump($response);
    } catch (Exception $e) {
        echo($e->getMessage());
    } 
}