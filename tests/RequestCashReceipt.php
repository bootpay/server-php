<?php
/*
 * Access Token 요청 예제입니다.
 */
require_once '../vendor/autoload.php';
// require_once __DIR__.'/../src/BootpayApi.php';

use Bootpay\ServerPhp\BootpayApi;

BootpayApi::setConfiguration(
    '59bfc738e13f337dbd6ca48a',
    'pDc0NwlkEX3aSaHTp/PPL/i8vn5E/CqRChgyEp/gHD0=',
    'development'
);

$token = BootpayApi::getAccessToken();
// var_dump($token);

if (!$token->error_code) {
    try {
        $response = BootpayApi::requestCashReceipt(
            array(
                'pg' => '토스',
                'price' => 1000,
                'tax_free' => 0,
                'order_name' => '테스트',
                'cash_receipt_type' => '소득공제',
                'user' => array(
                    'username' => '부트페이',
                    'phone' => '01000000000',
                    'email' => 'bootpay@bootpay.co.kr'
                ),
                'identity_no' => '01000000000',
                'purchased_at' => date("Y-m-d H:i:s \U\T\C", time()),
                'order_id' => time()
            )
        );
        var_dump($response);
    } catch (Exception $e) {
        echo($e->getMessage());
    }
}