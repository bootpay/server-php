<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use Bootpay\ServerPhp\BootpayCommerceApi;

// 알림톡 v1 API — development 전용 라이브 스크립트
// ⚠️ 발송·등록 계열은 실제로 카카오톡이 나가고 과금된다(샌드박스 없음). 조회만 기본 실행하고
//    부작용이 있는 호출은 주석으로 남긴다.
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
    // 1. 발신프로필(카카오채널) — 조회
    print_r($bootpay->alimtalkSender->categories());
    print_r($bootpay->alimtalkSender->getList());
    // print_r($bootpay->alimtalkSender->detail('KSP_ID', true));       // sync=true 는 벤더 재조회라 느리다
    // ⚠️ OTP 는 채널 관리자폰으로 실제 문자가 나간다
    // print_r($bootpay->alimtalkSender->otp(array('yellow_id' => '@bootpay', 'phone' => '01012345678')));
    // ⚠️ 카카오에 발신프로필이 실제 등록된다
    // print_r($bootpay->alimtalkSender->create(array(
    //     'otp' => '123456', 'yellow_id' => '@bootpay', 'phone' => '01012345678', 'category_code' => '001001'
    // )));
    // print_r($bootpay->alimtalkSender->variableExamples('KSP_ID', array('user_name' => '홍길동')));
    // print_r($bootpay->alimtalkSender->release('KSP_ID'));

    // 2. 공식 템플릿 카탈로그 — 전부 조회 계열이라 부작용이 없다
    print_r($bootpay->alimtalkOfficial->getList(array('keyword' => '주문', 'per' => 5)));
    // print_r($bootpay->alimtalkOfficial->recommend(array('text' => '주문이 완료되었습니다', 'limit' => 3)));
    // print_r($bootpay->alimtalkOfficial->detail('OFFICIAL_CODE'));

    // 3. 자체 템플릿
    print_r($bootpay->alimtalkTemplate->getList(array('sort' => 'latest')));
    // ⚠️ register 를 false 로 주지 않으면 생성 즉시 대행사·카카오에 실제 등록된다
    // $created = $bootpay->alimtalkTemplate->create(array(
    //     'ksp_id' => 'KSP_ID',
    //     'name' => '주문완료 안내',
    //     'content' => "#{user_name}님, 주문이 완료되었습니다.",
    //     'register' => false
    // ));
    // print_r($bootpay->alimtalkTemplate->detail('TEMPLATE_ID', false));  // 초안은 sync=false 권장
    // print_r($bootpay->alimtalkTemplate->update('TEMPLATE_ID', array('name' => '주문완료 안내', 'content' => '...')));
    // print_r($bootpay->alimtalkTemplate->register('TEMPLATE_ID'));       // ⚠️ 대행사 실제 등록
    // print_r($bootpay->alimtalkTemplate->inspect('TEMPLATE_ID'));        // ⚠️ 카카오 검수 요청 (취소 불가)
    // print_r($bootpay->alimtalkTemplate->delete('TEMPLATE_ID'));
    // print_r($bootpay->alimtalkTemplate->image(__DIR__ . '/sample.png'));            // 500px 이상 · 2:1
    // print_r($bootpay->alimtalkTemplate->highlightImage(__DIR__ . '/thumb.png'));    // 108px 이상 · 1:1

    // 내보내기 — csv 는 파싱하지 않은 원문({ body, content_type, status })으로 돌아온다
    print_r($bootpay->alimtalkTemplate->export(array('scope' => 'private')));
    // $csv = $bootpay->alimtalkTemplate->export(array('format' => 'csv', 'scope' => 'private'));
    // echo $csv->body;

    // 4. 발송 — ⚠️ 실제로 카카오톡이 발송되고 과금된다
    // print_r($bootpay->alimtalkSend->send(array(
    //     'template_code' => 'TEMPLATE_CODE',
    //     'to' => '01012345678',
    //     'variables' => array('user_name' => '홍길동'),
    //     'ref_id' => 'order-0001',   // 멱등 키
    //     'fallback' => false,        // null(미지정)은 프로젝트 기본값, false 는 명시적으로 끔
    //     'webhook_url' => 'https://example.com/hooks/alimtalk'  // 이 건의 결과 웹훅만 받을 주소(https)
    // )));
    // print_r($bootpay->alimtalkSend->sendBulk(array(
    //     'template_code' => 'TEMPLATE_CODE',
    //     'recipients' => array(
    //         array('to' => '01012345678', 'ref_id' => 'bulk-0001', 'variables' => array('user_name' => '홍길동'))
    //     ),
    //     'webhook_url' => 'https://example.com/hooks/alimtalk'  // 요청 단위 하나
    // )));
    // print_r($bootpay->alimtalkSend->cancel('RECEIPT_ID'));  // 예약(READY) 건만 취소 가능

    // 5. 발송내역·집계
    print_r($bootpay->alimtalkMessage->getList(array('limit' => 5)));
    print_r($bootpay->alimtalkMessage->stats());
    // print_r($bootpay->alimtalkMessage->detail('RECEIPT_ID'));

    // 6. 수신거부
    print_r($bootpay->alimtalkOptout->getList());
    print_r($bootpay->alimtalkOptout->check(array('phones' => array('01012345678'))));
    // print_r($bootpay->alimtalkOptout->create(array('phone' => '01012345678', 'reason' => 'CRM 동기화')));
    // print_r($bootpay->alimtalkOptout->release('01012345678'));  // 전역 차단은 해제되지 않는다

    // 7. 웹훅 — 주문·구독 통합 웹훅과 별개다
    print_r($bootpay->alimtalkWebhook->detail());
    print_r($bootpay->alimtalkWebhook->deliveries(array('limit' => 5)));
    // print_r($bootpay->alimtalkWebhook->update(array(
    //     'url' => 'https://example.com/hooks/alimtalk',   // https 만 허용
    //     'events' => array(301, 302, 303, 304, 310, 311),
    //     'enabled' => true
    // )));
    // print_r($bootpay->alimtalkWebhook->test());          // ⚠️ 설정된 URL 로 실제 요청이 나간다
    // print_r($bootpay->alimtalkWebhook->rotateSecret());  // ⚠️ 이 응답에서만 secret 원문을 준다
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
