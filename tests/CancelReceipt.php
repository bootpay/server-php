<?php
/*
 * Access Token 요청 예제입니다.
 */
require_once '../vendor/autoload.php';

use Bootpay\BackendPhp\BootpayApi;

BootpayApi::setConfiguration(
    '59b731f084382614ebf72215',
    'WwDv0UjfwFa04wYG0LJZZv1xwraQnlhnHE375n52X0U='
);

$token = BootpayApi::getAccessToken();
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