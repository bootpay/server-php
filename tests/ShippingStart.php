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
        $response = BootpayApi::shippingStart(
            array(
                'receipt_id' => "62a95891d01c7e001d7dc20b",
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