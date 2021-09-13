<?php
/*
 * 카드 정기 결제 예약
 */
require_once '../vendor/autoload.php';
use Bootpay\BackendPhp\BootpayApi; 

$bootpay = BootpayApi::setConfig(
    '5b8f6a4d396fa665fdc2b5ea',
    'rm6EYECr6aroQVG2ntW0A6LpWnkTgP4uQ3H18sDDUYw='
);

$response = $bootpay->requestAccessToken();

if ($response->status === 200) {
    $result = $bootpay->getUserToken(
        'user1234', # 필수
        '01012341234', # 선택
        'rupy1014@gmail.com', # 선택
        '홍길동', # 선택
        1, # 선택
        '861014' # 선택
    );

    // $result = $bootpay->getUserToken([
    //     'user_id' => '[[ 회원 아이디 ]]', # 필수
    //     'email' => '[[ 이메일 ]]', # 선택
    //     'name' => '[[ 회원 이름 ]]', # 선택
    //     'gender' => '[[ 회원 성별, 0 - 여자, 1 - 남자 ]]', # 선택
    //     'birth' => '[[ 회원 생년월일 6자리 ]]', # 선택
    //     'phone' => '[[ 회원의 연락가능한 전화번호 ]]' # 페이앱의 경우만 필수, 나머지 선택
    // ]);
    var_dump($result);
}