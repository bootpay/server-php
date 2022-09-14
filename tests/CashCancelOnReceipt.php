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

//BootpayApi::setConfiguration(
//    '59bfc738e13f337dbd6ca48a',
//    'pDc0NwlkEX3aSaHTp/PPL/i8vn5E/CqRChgyEp/gHD0=',
//    'development'
//);

$token = BootpayApi::getAccessToken();
// var_dump($token);

if (!isset($token->error_code)) {
    $response = BootpayApi::cashReceiptCancelOnReceipt(
        array(
            'receipt_id' => '62e0f11f1fc192036b1b3c92',
            'cancel_username' => '관리자',
            'cancel_message' => '일반적인 취소'
        )
    );
    try {
        $response = BootpayApi::cashReceiptCancelOnReceipt(
            array(
                'receipt_id' => '62e0f11f1fc192036b1b3c92',
                'cancel_username' => '관리자',
                'cancel_message' => '일반적인 취소'
            )
        );
        var_dump($response);
    } catch (Exception $e) {
        print($e->getMessage());
    }
}