<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use Bootpay\ServerPhp\BootpayCommerceApi;

// 입점사 지급 요청 — development 전용 라이브 스크립트
// ⚠️ create() 는 실제 지급 원장 항목을 만들므로 기본 실행하지 않는다.
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
    // 1. 지급 항목 조회 — merchant_payout_id 또는 지급 항목 id
    print_r($bootpay->payout->detail('MERCHANT_PAYOUT_ID'));

    // 2. 지급 목록 — 응답은 전체 개수 없는 지급 항목 배열
    print_r($bootpay->payout->getList(array(
        'status' => 'ready',
        'sub_seller_id' => 'SUB_SELLER_ID',
        'page' => 1,
        'limit' => 20
    )));

    // ⚠️ 실제 지급 원장 항목을 만들므로 의도적으로 주석 처리한다
    // print_r($bootpay->payout->create(array(
    //     'sub_seller_id' => 'SUB_SELLER_ID',
    //     'amount' => 150000,
    //     'merchant_payout_id' => 'PAYOUT_20261112_001',
    //     'order_number' => 'ORDER_1234',
    //     'payout_date' => '2026-11-20'
    // )));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
