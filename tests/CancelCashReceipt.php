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
        $response = BootpayApi::cancelCashReceipt(
            array(
                'receipt_id' => '632131ce1fc192036bac62da',
                'cancel_username' => '시스템',
                'cancel_message' => '테스트'
            )
        );
        var_dump($response);
    } catch (Exception $e) {
        echo($e->getMessage());
    }
}