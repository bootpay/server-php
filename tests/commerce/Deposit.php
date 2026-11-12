<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use Bootpay\ServerPhp\BootpayCommerceApi;

// 뱅크알람 입금대기 — development 전용 라이브 스크립트
// ⚠️ 등록·취소는 실제 주문을 만들거나 닫고, 웹훅 비밀값 발급은 부를 때마다 비밀값을 바꾼다.
//    조회만 기본 실행하고 부작용이 있는 호출은 주석으로 남긴다.
if (CURRENT_ENV !== 'development') {
    echo "BOOTPAY_ENV=development 에서만 실행합니다 (production 호출 금지). 현재: " . CURRENT_ENV . "\n";
    exit(0);
}

$keys = getCommerceKeys();
$bootpay = new BootpayCommerceApi(
    $keys['client_key'],
    $keys['secret_key'],
    $keys['mode']
);

try {
    // 1. 판매자 입금 계좌 목록 — 첫 계좌가 대표 계좌(primary)다
    print_r($bootpay->deposit->accounts());

    // 2. 입금대기 조회 — 가맹점 주문번호 또는 부트페이 주문번호
    print_r($bootpay->deposit->detail('MERCHANT_ORDER_ID'));

    // ⚠️ 아래는 실제로 비밀값이 바뀌거나 주문이 생기고 닫힌다
    // 3. 웹훅 서명 비밀값 발급 — 원문은 이 응답에서만 온다
    // print_r($bootpay->deposit->webhookSecret());
    // 4. 입금대기 등록 — 같은 merchant_order_id 로 금액·입금자명·계좌가 같으면 기존 주문을 돌려준다(다르면 18110)
    // print_r($bootpay->deposit->create(array(
    //     'merchant_order_id' => 'ORDER_1234', 'price' => 15000, 'depositor' => '홍길동'
    // )));
    // 5. 입금대기 취소 — 이미 닫힌 주문은 그대로 돌려준다
    // print_r($bootpay->deposit->cancel('ORDER_1234'));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
