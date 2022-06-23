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
        $response = BootpayApi::requestUserToken(array(
            'user_id' => 'gosomi1',
            'phone' => '01012345678'
        ));
        var_dump($response);
    } catch (Exception $e) {
        echo($e->getMessage());
    }
    //62591a5dd01c7e002219e255
}