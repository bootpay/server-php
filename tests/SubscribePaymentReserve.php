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
        $response = BootpayApi::subscribePaymentReserve(array(
            'billing_key' => '[ 빌링키 ]',
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