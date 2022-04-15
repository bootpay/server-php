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
    $response = BootpayApi::receiptPayment('61b009aaec81b4057e7f6ecd');
    var_dump($response);
}