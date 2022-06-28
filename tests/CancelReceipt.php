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
// var_dump($token);

if (!$token->error_code) {
    try {
        $response = BootpayApi::cancelPayment(
            array(
                'receipt_id' => '62591cfcd01c7e001c19e259',
                'cancel_price' => 1000,
                'cancel_tax_free' => '0',
                'cancel_id' => null,
                'cancel_username' => 'test',
                'cancel_message' => '테스트 결제 취소',
                'refund' => array(
                    'bank_account' => '',
                    'bank_username' => '',
                    'bank_code' => ''
                )
            )
        );
        var_dump($response);
    } catch (Exception $e) {
        echo($e->getMessage());
    }
}