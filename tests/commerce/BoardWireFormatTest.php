<?php

namespace Bootpay\ServerPhp\Test\Commerce;

use Bootpay\ServerPhp\Test\RecordingCommerceApi;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../RecordingCommerceApi.php';

/**
 * Ruby SDK parity (144be88, 커머스 게시판 API) — 요청 URL·헤더·바디 규약 검증 (네트워크 미사용)
 *
 * 공지사항 · FAQ · 1:1 문의 · 상품문의 · 상품평 다섯 게시판의 공통 규약을 함께 고정한다.
 *  - 조회/삭제는 supervisor 파라미터로 BOOTPAY-ROLE 이 user / supervisor 로 갈린다
 *  - 쓰기는 게시판별로 역할이 고정이다 (공지·FAQ 는 supervisor, 문의·상품평은 user, 답변/답글은 supervisor)
 *  - Bootpay-User-JWT 는 user_jwt 가 있을 때만 붙는다
 *  - Idempotency-Key 는 항상 붙고, idempotency_key 를 주면 그 값이 쓰인다
 */
class BoardWireFormatTest extends TestCase
{
    /** @var RecordingCommerceApi */
    private $api;

    protected function setUp(): void
    {
        $this->api = new RecordingCommerceApi('ck', 'sk', 'development');
    }

    private function lastCall()
    {
        return $this->api->lastCall();
    }

    private function headerValue($name)
    {
        return RecordingCommerceApi::headerValue($this->lastCall(), $name);
    }

    /** 게시판 공통 규약: method/url 이 맞고 BOOTPAY-ROLE 과 Idempotency-Key 가 붙는다 */
    private function assertBoardCall($method, $url, $role)
    {
        $call = $this->lastCall();
        $this->assertSame($method, $call['method']);
        $this->assertSame($url, $call['url']);
        $this->assertSame($role, $this->headerValue('BOOTPAY-ROLE'));
        $this->assertNotNull($this->headerValue('Idempotency-Key'));
    }

    // ---------- 1. 공지사항 ----------

    public function testNoticeListAppliesDefaultsWithUserRole()
    {
        $this->api->notice->getList();
        $this->assertBoardCall('GET', 'notices?page=1&limit=20', 'user');
        $this->assertNull($this->headerValue('Bootpay-User-JWT'));
    }

    public function testNoticeListSupervisorModeSendsViewAll()
    {
        $this->api->notice->getList(array('view' => 'all', 'supervisor' => true, 'keyword' => '배송'));
        $this->assertBoardCall('GET', 'notices?page=1&limit=20&keyword=%EB%B0%B0%EC%86%A1&view=all', 'supervisor');
    }

    public function testNoticeDetailAcceptsPlainId()
    {
        $this->api->notice->detail('NOTICE_ID');
        $this->assertBoardCall('GET', 'notices/NOTICE_ID', 'user');
    }

    public function testNoticeDetailSupervisorMode()
    {
        $this->api->notice->detail(array('notice_id' => 'NOTICE_ID', 'supervisor' => true));
        $this->assertBoardCall('GET', 'notices/NOTICE_ID', 'supervisor');
    }

    public function testNoticeCreateUsesSupervisorRoleAndKeepsFalseFlags()
    {
        $this->api->notice->create(array(
            'title' => '추석 배송 안내',
            'content' => '9/29 ~ 10/2 배송이 쉽니다.',
            'is_notice' => true,
            'is_display' => false,
            'images' => null,
            'idempotency_key' => 'fixed-key'
        ));
        $this->assertBoardCall('POST', 'notices', 'supervisor');
        $this->assertSame(array(
            'title' => '추석 배송 안내',
            'content' => '9/29 ~ 10/2 배송이 쉽니다.',
            'is_notice' => true,
            'is_display' => false
        ), $this->lastCall()['data']);
        $this->assertSame('fixed-key', $this->headerValue('Idempotency-Key'));
    }

    public function testNoticeUpdateSendsOnlyGivenFieldsAndKeepsEmptyImages()
    {
        $this->api->notice->update(array(
            'notice_id' => 'NOTICE_ID',
            'content' => '일정이 바뀌었습니다.',
            'images' => array()
        ));
        $this->assertBoardCall('PUT', 'notices/NOTICE_ID', 'supervisor');
        $this->assertSame(
            array('content' => '일정이 바뀌었습니다.', 'images' => array()),
            $this->lastCall()['data']
        );
    }

    public function testNoticeDeleteUsesSupervisorRole()
    {
        $this->api->notice->delete('NOTICE_ID');
        $this->assertBoardCall('DELETE', 'notices/NOTICE_ID', 'supervisor');
        $this->assertNull($this->lastCall()['data']);
    }

    public function testNoticeRequiresId()
    {
        $this->expectException(\Exception::class);
        $this->api->notice->update(array('content' => 'x'));
    }

    // ---------- 2. FAQ ----------

    public function testFaqListAppliesDefaults()
    {
        $this->api->faq->getList();
        $this->assertBoardCall('GET', 'faqs?page=1&limit=20', 'user');
    }

    public function testFaqListSupervisorMode()
    {
        $this->api->faq->getList(array('view' => 'all', 'supervisor' => true, 'page' => 2, 'limit' => 50));
        $this->assertBoardCall('GET', 'faqs?page=2&limit=50&view=all', 'supervisor');
    }

    public function testFaqDetailAndDelete()
    {
        $this->api->faq->detail('FAQ_ID');
        $this->assertBoardCall('GET', 'faqs/FAQ_ID', 'user');

        $this->api->faq->delete(array('faq_id' => 'FAQ_ID', 'idempotency_key' => 'fixed-key'));
        $this->assertBoardCall('DELETE', 'faqs/FAQ_ID', 'supervisor');
        $this->assertSame('fixed-key', $this->headerValue('Idempotency-Key'));
    }

    public function testFaqCreateSendsSupervisorPayload()
    {
        $this->api->faq->create(array(
            'title' => '교환/반품은 어떻게 하나요?',
            'content' => '마이페이지 > 주문내역에서 신청하실 수 있습니다.',
            'is_display' => true
        ));
        $this->assertBoardCall('POST', 'faqs', 'supervisor');
        $this->assertSame(array(
            'title' => '교환/반품은 어떻게 하나요?',
            'content' => '마이페이지 > 주문내역에서 신청하실 수 있습니다.',
            'is_display' => true
        ), $this->lastCall()['data']);
    }

    public function testFaqCreateRequiresTitleAndContent()
    {
        $this->expectException(\Exception::class);
        $this->api->faq->create(array('title' => '제목만 있음'));
    }

    // ---------- 3. 1:1 문의 ----------

    public function testInquiryListSendsMemberFilterAndDefaults()
    {
        $this->api->inquiry->getList(array('user_id' => 'gosomi84'));
        $this->assertBoardCall('GET', 'inquiries?user_id=gosomi84&page=1&limit=20', 'user');
    }

    public function testInquiryListSendsAnsweredAsStringAndJwtHeader()
    {
        $this->api->inquiry->getList(array('answered' => false, 'user_jwt' => 'JWT'));
        $this->assertBoardCall('GET', 'inquiries?answered=false&page=1&limit=20', 'user');
        $this->assertSame('JWT', $this->headerValue('Bootpay-User-JWT'));
    }

    public function testInquiryListSupervisorMode()
    {
        $this->api->inquiry->getList(array('supervisor' => true, 'answered' => true));
        $this->assertBoardCall('GET', 'inquiries?answered=true&page=1&limit=20', 'supervisor');
    }

    public function testInquiryDetailSendsMemberAsQuery()
    {
        $this->api->inquiry->detail(array('inquiry_id' => 'INQUIRY_ID', 'login_id' => 'gosomi84'));
        $this->assertBoardCall('GET', 'inquiries/INQUIRY_ID?login_id=gosomi84', 'user');
    }

    public function testInquiryCreateUsesUserRole()
    {
        $this->api->inquiry->create(array(
            'user_id' => 'USER_ID',
            'title' => '배송 문의',
            'content' => '언제 출발하나요?',
            'order_id' => 'ORDER_ID'
        ));
        $this->assertBoardCall('POST', 'inquiries', 'user');
        $this->assertSame(array(
            'user_id' => 'USER_ID',
            'title' => '배송 문의',
            'content' => '언제 출발하나요?',
            'order_id' => 'ORDER_ID'
        ), $this->lastCall()['data']);
    }

    public function testInquiryUpdateKeepsEmptyTitleToClearIt()
    {
        $this->api->inquiry->update(array(
            'inquiry_id' => 'INQUIRY_ID',
            'user_id' => 'USER_ID',
            'title' => '',
            'content' => '내용만 남깁니다.'
        ));
        $this->assertBoardCall('PUT', 'inquiries/INQUIRY_ID', 'user');
        $this->assertSame(array(
            'user_id' => 'USER_ID',
            'title' => '',
            'content' => '내용만 남깁니다.'
        ), $this->lastCall()['data']);
    }

    public function testInquiryDeleteSupervisorModeSendsNoBody()
    {
        $this->api->inquiry->delete(array('inquiry_id' => 'INQUIRY_ID', 'supervisor' => true));
        $this->assertBoardCall('DELETE', 'inquiries/INQUIRY_ID', 'supervisor');
        $this->assertNull($this->lastCall()['data']);
    }

    public function testInquiryAnswerUsesSupervisorRoleAndContentOnlyBody()
    {
        $this->api->inquiry->answer(array(
            'inquiry_id' => 'INQUIRY_ID',
            'content' => '오늘 출고됩니다.',
            'user_id' => 'IGNORED'
        ));
        $this->assertBoardCall('PUT', 'inquiries/INQUIRY_ID/answer', 'supervisor');
        $this->assertSame(array('content' => '오늘 출고됩니다.'), $this->lastCall()['data']);
    }

    // ---------- 4. 상품문의 ----------

    public function testProductQnaListSendsProductIdFirstWithDefaults()
    {
        $this->api->productQna->getList(array('product_id' => 'PRODUCT_ID'));
        $this->assertBoardCall('GET', 'product-qnas?product_id=PRODUCT_ID&page=1&limit=20', 'user');
    }

    public function testProductQnaListSupervisorViewAll()
    {
        $this->api->productQna->getList(array('view' => 'all', 'supervisor' => true, 'login_id' => 'gosomi84'));
        $this->assertBoardCall('GET', 'product-qnas?view=all&page=1&limit=20&login_id=gosomi84', 'supervisor');
    }

    public function testProductQnaCreateGuestKeepsGuestFields()
    {
        $this->api->productQna->create(array(
            'product_id' => 'PRODUCT_ID',
            'title' => '재입고 문의',
            'content' => '품절된 색상 재입고 예정이 있나요?',
            'is_secret' => true,
            'guest_name' => '비회원',
            'guest_password' => '1234'
        ));
        $this->assertBoardCall('POST', 'product-qnas', 'user');
        $this->assertSame(array(
            'product_id' => 'PRODUCT_ID',
            'title' => '재입고 문의',
            'content' => '품절된 색상 재입고 예정이 있나요?',
            'is_secret' => true,
            'guest_name' => '비회원',
            'guest_password' => '1234'
        ), $this->lastCall()['data']);
    }

    public function testProductQnaUpdateUsesUserRole()
    {
        $this->api->productQna->update(array(
            'product_qna_id' => 'QNA_ID',
            'content' => '수정된 내용',
            'is_secret' => false
        ));
        $this->assertBoardCall('PUT', 'product-qnas/QNA_ID', 'user');
        $this->assertSame(
            array('content' => '수정된 내용', 'is_secret' => false),
            $this->lastCall()['data']
        );
    }

    public function testProductQnaDeleteSendsGuestPasswordInBodyNotQuery()
    {
        $this->api->productQna->delete(array(
            'product_qna_id' => 'QNA_ID',
            'guest_password' => '1234'
        ));
        $this->assertBoardCall('DELETE', 'product-qnas/QNA_ID', 'user');
        $this->assertSame(array('guest_password' => '1234'), $this->lastCall()['data']);
        $this->assertStringNotContainsString('guest_password', $this->lastCall()['url']);
    }

    public function testProductQnaDeleteSupervisorModeSendsEmptyObjectBody()
    {
        $this->api->productQna->delete(array('product_qna_id' => 'QNA_ID', 'supervisor' => true));
        $this->assertBoardCall('DELETE', 'product-qnas/QNA_ID', 'supervisor');
        $this->assertEquals(new \stdClass(), $this->lastCall()['data']);
    }

    public function testProductQnaAnswerUsesSupervisorRole()
    {
        $this->api->productQna->answer(array('product_qna_id' => 'QNA_ID', 'content' => '다음 주 입고 예정입니다.'));
        $this->assertBoardCall('PUT', 'product-qnas/QNA_ID/answer', 'supervisor');
        $this->assertSame(array('content' => '다음 주 입고 예정입니다.'), $this->lastCall()['data']);
    }

    // ---------- 5. 상품평 ----------

    public function testReviewListAppliesDefaults()
    {
        $this->api->productReview->getList(array('user_id' => 'USER_ID'));
        $this->assertBoardCall('GET', 'reviews?page=1&limit=20&user_id=USER_ID', 'user');
    }

    public function testReviewListSupervisorModeFiltersProduct()
    {
        $this->api->productReview->getList(array('supervisor' => true, 'product_id' => 'PRODUCT_ID', 'view' => 'all'));
        $this->assertBoardCall('GET', 'reviews?page=1&limit=20&product_id=PRODUCT_ID&view=all', 'supervisor');
    }

    public function testReviewCreateSendsFullPayloadWithUserRole()
    {
        $this->api->productReview->create(array(
            'user_id' => 'USER_ID',
            'order_id' => 'ORDER_ID',
            'product_id' => 'PRODUCT_ID',
            'rating' => 5,
            'content' => '배송도 빠르고 품질도 좋아요.',
            'images' => array('https://example.com/review-1.jpg')
        ));
        $this->assertBoardCall('POST', 'reviews', 'user');
        $this->assertSame(array(
            'user_id' => 'USER_ID',
            'order_id' => 'ORDER_ID',
            'product_id' => 'PRODUCT_ID',
            'rating' => 5,
            'content' => '배송도 빠르고 품질도 좋아요.',
            'images' => array('https://example.com/review-1.jpg')
        ), $this->lastCall()['data']);
    }

    public function testReviewCreateRequiresOrderAndProduct()
    {
        $this->expectException(\Exception::class);
        $this->api->productReview->create(array('rating' => 5, 'content' => '좋아요'));
    }

    public function testReviewUpdateKeepsEmptyImagesToClearThem()
    {
        $this->api->productReview->update(array(
            'product_review_id' => 'REVIEW_ID',
            'user_id' => 'USER_ID',
            'images' => array()
        ));
        $this->assertBoardCall('PUT', 'reviews/REVIEW_ID', 'user');
        $this->assertSame(
            array('user_id' => 'USER_ID', 'images' => array()),
            $this->lastCall()['data']
        );
    }

    public function testReviewDeleteSupervisorModeSendsReasonAsQuery()
    {
        $this->api->productReview->delete(array(
            'product_review_id' => 'REVIEW_ID',
            'supervisor' => true,
            'reason' => 'spam'
        ));
        $this->assertBoardCall('DELETE', 'reviews/REVIEW_ID?reason=spam', 'supervisor');
        $this->assertNull($this->lastCall()['data']);
    }

    public function testReviewReplyUsesSupervisorRole()
    {
        $this->api->productReview->reply(array(
            'product_review_id' => 'REVIEW_ID',
            'content' => '소중한 후기 감사합니다. 앞으로도 잘 부탁드립니다.'
        ));
        $this->assertBoardCall('PUT', 'reviews/REVIEW_ID/reply', 'supervisor');
        $this->assertSame(
            array('content' => '소중한 후기 감사합니다. 앞으로도 잘 부탁드립니다.'),
            $this->lastCall()['data']
        );
    }
}
