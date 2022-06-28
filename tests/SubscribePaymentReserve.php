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
        $response = BootpayApi::subscribePaymentReserve(array(
            'billing_key' => '62b41f88cf9f6d001ad212ad',
            'order_name' => '테스트결제',
            'price' => 1000,
            'order_id' => time(),
            'user' => array(
                'phone' => '01000000000',
                'username' => '홍길동',
                'email' => 'test@bootpay.co.kr'
            ),
            'reserve_execute_at' => date("Y-m-d H:i:s \U\T\C", time() + 5)
        ));
    } catch (Exception $e) {
        echo($e->getMessage());
    }
    //62591a5dd01c7e002219e255
    var_dump($response);
}