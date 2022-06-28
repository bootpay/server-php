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
        $response = BootpayApi::shippingStart(
            array(
                'receipt_id' => "62b4200acf9f6d001ad212b1",
                'tracking_number' => '3982983',
                'delivery_corp' => 'CJ대한통운',
                'user' => array(
                    'username' => '테스트',
                    'phone' => '01000000000',
                    'zipcode' => '099382',
                    'address' => '서울특별시 종로구'
                )
            )
        );
        var_dump($response);
    } catch (Exception $e) {
        echo($e->getMessage());
    }
}