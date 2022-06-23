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
        $response = BootpayApi::requestSubscribeBillingKey(array(
            'pg' => '나이스페이',
            'order_name' => '테스트결제', 
            'subscription_id' => time(),
            'card_no' => '5570420456641074', //카드번호 
            'card_pw' => '83', //카드 비밀번호 2자리 
            'card_identity_no' => '861014',  //카드 소유주 생년월일 6자리 
            'card_expire_year' => '26',  //카드 유효기간 년 2자리 
            'card_expire_month' => '12', //카드 유효기간 월 2자리 
            'user' => array(
                'phone' => '01000000000',
                'username' => '홍길동',
                'email' => 'test@bootpay.co.kr'
            ),
            'reserve_execute_at' => date("Y-m-d H:i:s \U\T\C", time() + 5)
        ));
        var_dump($response);
    } catch (Exception $e) {
        echo($e->getMessage());
    }
}