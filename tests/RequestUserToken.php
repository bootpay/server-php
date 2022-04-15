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
        $response = BootpayApi::requestUserToken(array(
            'user_id' => 'gosomi1'
        ));
    } catch (Exception $e) {
        echo($e->getMessage());
    }
    //62591a5dd01c7e002219e255
    var_dump($response);
}