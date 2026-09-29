<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use Bootpay\ServerPhp\BootpayCommerceApi;

// 커머스 게시판(공지사항·FAQ·1:1 문의·상품문의·상품평) — development 전용 라이브 스크립트
// ⚠️ 등록·수정·삭제는 실제 데이터가 바뀐다. 조회만 기본 실행하고 부작용이 있는 호출은 주석으로 남긴다.
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
    // 1. 공지사항 — 고객 모드는 몰 공지사항 사용여부가 꺼져 있으면 BOARD_FEATURE_DISABLED 다
    print_r($bootpay->notice->getList());
    print_r($bootpay->notice->getList(array('view' => 'all', 'supervisor' => true)));  // 비공개 공지까지
    // print_r($bootpay->notice->detail(array('notice_id' => 'NOTICE_ID', 'supervisor' => true)));
    // ⚠️ 아래 쓰기 3종은 supervisor 전용이고 실제로 글이 생기거나 지워진다
    // print_r($bootpay->notice->create(array(
    //     'title' => '추석 배송 안내', 'content' => '9/29 ~ 10/2 배송이 쉽니다.', 'is_notice' => true
    // )));
    // print_r($bootpay->notice->update(array('notice_id' => 'NOTICE_ID', 'is_display' => false)));
    // print_r($bootpay->notice->delete('NOTICE_ID'));

    // 2. FAQ — 구조는 공지사항과 같고 is_notice 만 없다
    print_r($bootpay->faq->getList());
    print_r($bootpay->faq->getList(array('view' => 'all', 'supervisor' => true)));
    // print_r($bootpay->faq->detail('FAQ_ID'));
    // print_r($bootpay->faq->create(array('title' => '교환/반품은 어떻게 하나요?', 'content' => '마이페이지에서 신청합니다.')));
    // print_r($bootpay->faq->update(array('faq_id' => 'FAQ_ID', 'content' => '내용이 바뀌었습니다.')));
    // print_r($bootpay->faq->delete('FAQ_ID'));

    // 3. 1:1 문의 — 회원 모드는 user_id / login_id / user_jwt 중 하나로 회원을 정한다
    print_r($bootpay->inquiry->getList(array('user_id' => 'USER_ID')));
    print_r($bootpay->inquiry->getList(array('supervisor' => true, 'answered' => false)));  // 미답변만
    // print_r($bootpay->inquiry->detail(array('inquiry_id' => 'INQUIRY_ID', 'user_id' => 'USER_ID')));
    // print_r($bootpay->inquiry->create(array(
    //     'user_id' => 'USER_ID', 'title' => '배송 문의', 'content' => '언제 출발하나요?', 'order_id' => 'ORDER_ID'
    // )));
    // print_r($bootpay->inquiry->update(array(   // ⚠️ 답변이 달리면 409 INQUIRY_ALREADY_ANSWERED
    //     'inquiry_id' => 'INQUIRY_ID', 'user_id' => 'USER_ID', 'content' => '내용을 고칩니다.'
    // )));
    // print_r($bootpay->inquiry->answer(array('inquiry_id' => 'INQUIRY_ID', 'content' => '오늘 출고됩니다.')));
    // print_r($bootpay->inquiry->delete(array('inquiry_id' => 'INQUIRY_ID', 'supervisor' => true)));

    // 4. 상품문의 — 고객 모드 목록은 product_id 가 필수다
    print_r($bootpay->productQna->getList(array('product_id' => 'PRODUCT_ID')));
    print_r($bootpay->productQna->getList(array('view' => 'all', 'supervisor' => true)));  // 몰 전체(숨김 포함)
    // print_r($bootpay->productQna->detail(array('product_qna_id' => 'QNA_ID', 'user_id' => 'USER_ID')));
    // 비회원 작성 — 몰이 비회원 작성을 허용할 때만 된다
    // print_r($bootpay->productQna->create(array(
    //     'product_id' => 'PRODUCT_ID', 'title' => '재입고 문의', 'content' => '재입고 예정이 있나요?',
    //     'is_secret' => true, 'guest_name' => '비회원', 'guest_password' => '1234'
    // )));
    // print_r($bootpay->productQna->update(array('product_qna_id' => 'QNA_ID', 'guest_password' => '1234', 'content' => '...')));
    // print_r($bootpay->productQna->answer(array('product_qna_id' => 'QNA_ID', 'content' => '다음 주 입고 예정입니다.')));
    // print_r($bootpay->productQna->delete(array('product_qna_id' => 'QNA_ID', 'guest_password' => '1234')));

    // 5. 상품평 — 상품 상세의 공개 목록은 이쪽이 아니라 products/{id}/reviews 다
    print_r($bootpay->productReview->getList(array('user_id' => 'USER_ID')));
    print_r($bootpay->productReview->getList(array('supervisor' => true, 'product_id' => 'PRODUCT_ID')));
    // print_r($bootpay->productReview->detail(array('product_review_id' => 'REVIEW_ID', 'user_id' => 'USER_ID')));
    // ⚠️ order_id 는 구매확정 주문이어야 하고, 작성시 적립금이 실제로 지급된다
    // print_r($bootpay->productReview->create(array(
    //     'user_id' => 'USER_ID', 'order_id' => 'ORDER_ID', 'product_id' => 'PRODUCT_ID',
    //     'rating' => 5, 'content' => '배송도 빠르고 품질도 좋아요.',
    //     'images' => array('https://example.com/review-1.jpg')   // URL 최대 5개 (파일 업로드는 미지원)
    // )));
    // print_r($bootpay->productReview->update(array(   // 작성 후 7일 이내만 (REVIEW_EDIT_TIME_EXPIRED)
    //     'product_review_id' => 'REVIEW_ID', 'user_id' => 'USER_ID', 'images' => array()   // 빈 배열이면 사진 전체 삭제
    // )));
    // print_r($bootpay->productReview->reply(array('product_review_id' => 'REVIEW_ID', 'content' => '소중한 후기 감사합니다.')));
    // ⚠️ 삭제하면 지급된 적립금 회수·상품 통계 차감이 함께 일어난다
    // print_r($bootpay->productReview->delete(array('product_review_id' => 'REVIEW_ID', 'supervisor' => true, 'reason' => 'spam')));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
