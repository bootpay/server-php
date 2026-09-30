<?php

namespace Bootpay\ServerPhp\Test\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;
use Bootpay\ServerPhp\Test\RecordingCommerceApi;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../RecordingCommerceApi.php';

/**
 * Ruby SDK parity (8f1ee1e, 알림톡 v1 API 35종) — 요청 URL·헤더·바디 규약 검증 (네트워크 미사용)
 *
 * 알림톡 계열의 공통 규약 2가지를 함께 고정한다.
 *  - BOOTPAY-ROLE 은 항상 user (서버 스코프가 전부 user:alimtalk_*)
 *  - Idempotency-Key 를 **붙이지 않는다** (알림톡 API 는 이 헤더를 읽지 않는다 —
 *    멱등은 발송 payload 의 ref_id 로만 성립하므로, 헤더를 붙이면 없는 보장을 주는 셈이 된다)
 */
class AlimtalkWireFormatTest extends TestCase
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

    /** 알림톡 공통 규약: BOOTPAY-ROLE=user + Idempotency-Key 미부착 */
    private function assertAlimtalkCall($method, $url)
    {
        $call = $this->lastCall();
        $this->assertSame($method, $call['method']);
        $this->assertSame($url, $call['url']);
        $this->assertSame('user', $this->headerValue('BOOTPAY-ROLE'));
        $this->assertNull(
            $this->headerValue('Idempotency-Key'),
            '알림톡 API 는 Idempotency-Key 를 읽지 않는다 — 붙이면 없는 멱등 보장을 주는 셈이다'
        );
    }

    // ---------- 1. 발신프로필(카카오채널) ----------

    public function testSenderCategories()
    {
        $this->api->alimtalkSender->categories();
        $this->assertAlimtalkCall('GET', 'alimtalk/categories');
    }

    public function testSenderOtp()
    {
        $this->api->alimtalkSender->otp(array('yellow_id' => '@bootpay', 'phone' => '01012345678'));
        $this->assertAlimtalkCall('POST', 'alimtalk/senders/otp');
        $this->assertSame(
            array('yellow_id' => '@bootpay', 'phone' => '01012345678'),
            $this->lastCall()['data']
        );
    }

    public function testSenderOtpRequiresYellowIdAndPhone()
    {
        $this->expectException(\Exception::class);
        $this->api->alimtalkSender->otp(array('yellow_id' => '@bootpay'));
    }

    public function testSenderCreateSendsOnlyContractedKeys()
    {
        $this->api->alimtalkSender->create(array(
            'otp' => '123456',
            'yellow_id' => '@bootpay',
            'phone' => '01012345678',
            'category_code' => '001001',
            'unknown' => 'drop-me'
        ));
        $this->assertAlimtalkCall('POST', 'alimtalk/senders');
        $this->assertSame(
            array('otp' => '123456', 'yellow_id' => '@bootpay', 'phone' => '01012345678', 'category_code' => '001001'),
            $this->lastCall()['data']
        );
    }

    public function testSenderCreateRequiresCategoryCode()
    {
        $this->expectException(\Exception::class);
        $this->api->alimtalkSender->create(array(
            'otp' => '123456',
            'yellow_id' => '@bootpay',
            'phone' => '01012345678'
        ));
    }

    public function testSenderList()
    {
        $this->api->alimtalkSender->getList();
        $this->assertAlimtalkCall('GET', 'alimtalk/senders');
    }

    public function testSenderDetailWithAndWithoutSync()
    {
        $this->api->alimtalkSender->detail('ksp-1');
        $this->assertAlimtalkCall('GET', 'alimtalk/senders/ksp-1');

        $this->api->alimtalkSender->detail('ksp-1', true);
        $this->assertAlimtalkCall('GET', 'alimtalk/senders/ksp-1?sync=true');
    }

    /** bool 은 http_build_query 기본값(1/'')이 아니라 'true'/'false' 로 나가야 한다 */
    public function testSenderDetailSendsFalseSyncAsLiteralFalse()
    {
        $this->api->alimtalkSender->detail('ksp-1', false);
        $this->assertSame('alimtalk/senders/ksp-1?sync=false', $this->lastCall()['url']);
    }

    public function testSenderRelease()
    {
        $this->api->alimtalkSender->release('ksp-1');
        $this->assertAlimtalkCall('DELETE', 'alimtalk/senders/ksp-1');
    }

    public function testSenderVariableExamples()
    {
        $this->api->alimtalkSender->variableExamples('ksp-1', array('user_name' => '홍길동'));
        $this->assertAlimtalkCall('PUT', 'alimtalk/senders/ksp-1/variable_examples');
        $this->assertSame(
            array('examples' => array('user_name' => '홍길동')),
            $this->lastCall()['data']
        );
    }

    // ---------- 2. 자체 템플릿 ----------

    public function testTemplateList()
    {
        $this->api->alimtalkTemplate->getList(array('ins' => 'APR', 'sort' => 'code', 'keyword' => '주문'));
        $this->assertAlimtalkCall('GET', 'alimtalk/templates?ins=APR&sort=code&' . http_build_query(array('keyword' => '주문')));

        $this->api->alimtalkTemplate->getList();
        $this->assertAlimtalkCall('GET', 'alimtalk/templates');
    }

    /** register=false 는 "초안만 만든다" 는 의사표시다 — compact 에서 살아남아야 한다 */
    public function testTemplateCreateKeepsFalseRegisterAndPassesUnknownKeysThrough()
    {
        $this->api->alimtalkTemplate->create(array(
            'ksp_id' => 'ksp-1',
            'name' => '주문완료',
            'content' => '#{user_name}님 주문이 완료되었습니다.',
            'register' => false,
            'memo' => null,
            'custom_attr' => 'passthrough'
        ));
        $this->assertAlimtalkCall('POST', 'alimtalk/templates');
        $this->assertSame(
            array(
                'ksp_id' => 'ksp-1',
                'name' => '주문완료',
                'content' => '#{user_name}님 주문이 완료되었습니다.',
                'register' => false,
                'custom_attr' => 'passthrough'
            ),
            $this->lastCall()['data']
        );
    }

    public function testTemplateCreateRequiresKspId()
    {
        $this->expectException(\Exception::class);
        $this->api->alimtalkTemplate->create(array('name' => '주문완료'));
    }

    public function testTemplateDetailSyncFlag()
    {
        $this->api->alimtalkTemplate->detail('tpl-1');
        $this->assertAlimtalkCall('GET', 'alimtalk/templates/tpl-1');

        $this->api->alimtalkTemplate->detail('tpl-1', false);
        $this->assertAlimtalkCall('GET', 'alimtalk/templates/tpl-1?sync=false');
    }

    public function testTemplateUpdate()
    {
        $this->api->alimtalkTemplate->update('tpl-1', array('name' => '수정', 'content' => '본문', 'memo' => null));
        $this->assertAlimtalkCall('PUT', 'alimtalk/templates/tpl-1');
        $this->assertSame(array('name' => '수정', 'content' => '본문'), $this->lastCall()['data']);
    }

    public function testTemplateDelete()
    {
        $this->api->alimtalkTemplate->delete('tpl-1');
        $this->assertAlimtalkCall('DELETE', 'alimtalk/templates/tpl-1');
    }

    public function testTemplateRegisterAndInspectSendEmptyJsonObject()
    {
        $this->api->alimtalkTemplate->register('tpl-1');
        $this->assertAlimtalkCall('POST', 'alimtalk/templates/tpl-1/register');
        $this->assertSame('{}', json_encode($this->lastCall()['data']));

        $this->api->alimtalkTemplate->inspect('tpl-1');
        $this->assertAlimtalkCall('POST', 'alimtalk/templates/tpl-1/inspect');
        $this->assertSame('{}', json_encode($this->lastCall()['data']));
    }

    /** 서버 기본은 csv 지만 SDK 는 json 을 기본으로 둔다 — csv 본문은 json_decode 를 통과하지 못한다 */
    public function testTemplateExportDefaultsToJsonFormat()
    {
        $this->api->alimtalkTemplate->export();
        $call = $this->lastCall();

        $this->assertSame('json', $call['type']);
        $this->assertAlimtalkCall('GET', 'alimtalk/templates/export?format=json');
    }

    public function testTemplateExportPassesFilters()
    {
        $this->api->alimtalkTemplate->export(array(
            'scope' => 'official',
            'ksp_id' => 'ksp-1',
            'status' => 'APR',
            'include_content' => false
        ));
        $this->assertAlimtalkCall(
            'GET',
            'alimtalk/templates/export?format=json&scope=official&ksp_id=ksp-1&status=APR&include_content=false'
        );
    }

    /** csv 는 파싱하지 않는 원문 경로(requestRaw)로 나가야 한다 */
    public function testTemplateExportCsvUsesRawRequest()
    {
        $this->api->alimtalkTemplate->export(array('format' => 'csv', 'scope' => 'all'));
        $call = $this->lastCall();

        $this->assertSame('raw', $call['type']);
        $this->assertAlimtalkCall('GET', 'alimtalk/templates/export?format=csv&scope=all');
    }

    public function testTemplateImageUploadsSingleNamedFileField()
    {
        $path = $this->makeTempImage();
        try {
            $this->api->alimtalkTemplate->image($path, 'https://cdn.bootpay.co.kr/old.png');
            $call = $this->lastCall();

            $this->assertSame('multipart', $call['type']);
            $this->assertAlimtalkCall('POST', 'alimtalk/templates/image');
            // 파일 필드명은 상품의 images[n] 이 아니라 image 다
            $this->assertSame(array('image' => $path), $call['files']);
            $this->assertSame(array('replace_url' => 'https://cdn.bootpay.co.kr/old.png'), $call['data']);
        } finally {
            @unlink($path);
        }
    }

    public function testTemplateHighlightImageUsesItsOwnEndpoint()
    {
        $path = $this->makeTempImage();
        try {
            $this->api->alimtalkTemplate->highlightImage($path);
            $call = $this->lastCall();

            $this->assertAlimtalkCall('POST', 'alimtalk/templates/highlight_image');
            $this->assertSame(array('image' => $path), $call['files']);
            $this->assertSame(array(), $call['data']);
        } finally {
            @unlink($path);
        }
    }

    public function testTemplateImageRejectsMissingFile()
    {
        $this->expectException(\Exception::class);
        $this->api->alimtalkTemplate->image('/tmp/no-such-bootpay-image.png');
    }

    // ---------- 3. 발송 ----------

    public function testSendSingle()
    {
        $this->api->alimtalkSend->send(array(
            'template_code' => 'TPL-1',
            'to' => '01012345678',
            'variables' => array('user_name' => '홍길동'),
            'ref_id' => 'order-1'
        ));
        $this->assertAlimtalkCall('POST', 'alimtalk/send');
        $this->assertSame(
            array(
                'template_code' => 'TPL-1',
                'to' => '01012345678',
                'variables' => array('user_name' => '홍길동'),
                'ref_id' => 'order-1'
            ),
            $this->lastCall()['data']
        );
    }

    /**
     * webhook_url (05ea556) — 건별 결과 웹훅 주소. 주면 그대로 바디에 실려야 한다.
     * 지정하지 않으면 키 자체가 없어야 한다 (프로젝트 웹훅 설정을 따르는 것과
     * 빈 주소를 보내는 것은 서버에서 다르게 해석된다).
     */
    public function testSendWithWebhookUrl()
    {
        $this->api->alimtalkSend->send(array(
            'template_code' => 'TPL-1',
            'to' => '01012345678',
            'webhook_url' => 'https://example.com/hooks/alimtalk/order-1'
        ));
        $this->assertAlimtalkCall('POST', 'alimtalk/send');
        $this->assertSame(
            array(
                'template_code' => 'TPL-1',
                'to' => '01012345678',
                'webhook_url' => 'https://example.com/hooks/alimtalk/order-1'
            ),
            $this->lastCall()['data']
        );

        $this->api->alimtalkSend->send(array('template_code' => 'TPL-1', 'to' => '01012345678'));
        $this->assertArrayNotHasKey('webhook_url', $this->lastCall()['data']);

        $this->api->alimtalkSend->send(
            array('template_code' => 'TPL-1', 'to' => '01012345678', 'webhook_url' => null)
        );
        $this->assertArrayNotHasKey('webhook_url', $this->lastCall()['data']);
    }

    /**
     * fallback 은 미지정(null)과 false 의 의미가 다르다 —
     * null 은 프로젝트 기본값을 따르고 false 는 명시적으로 끈다. false 가 잘려 나가면 안 된다.
     */
    public function testSendKeepsExplicitFalseFallbackButDropsNull()
    {
        $this->api->alimtalkSend->send(array('template_code' => 'TPL-1', 'to' => '01012345678', 'fallback' => false));
        $data = $this->lastCall()['data'];
        $this->assertArrayHasKey('fallback', $data);
        $this->assertFalse($data['fallback']);

        $this->api->alimtalkSend->send(array('template_code' => 'TPL-1', 'to' => '01012345678', 'fallback' => null));
        $this->assertArrayNotHasKey('fallback', $this->lastCall()['data']);
    }

    public function testSendRequiresTemplateCodeAndTo()
    {
        $this->expectException(\Exception::class);
        $this->api->alimtalkSend->send(array('template_code' => 'TPL-1'));
    }

    public function testSendBulk()
    {
        $recipients = array(
            array('to' => '01012345678', 'ref_id' => 'bulk-0001', 'variables' => array('user_name' => '홍길동')),
            array('to' => '01087654321', 'ref_id' => 'bulk-0002')
        );
        $this->api->alimtalkSend->sendBulk(array(
            'template_code' => 'TPL-1',
            'recipients' => $recipients,
            'reserved_at' => '2026-09-01T10:00:00+09:00',
            'sender_key' => 'sender-key-1'
        ));
        $this->assertAlimtalkCall('POST', 'alimtalk/send/bulk');
        $this->assertSame(
            array(
                'template_code' => 'TPL-1',
                'recipients' => $recipients,
                'reserved_at' => '2026-09-01T10:00:00+09:00',
                'sender_key' => 'sender-key-1'
            ),
            $this->lastCall()['data']
        );
    }

    /** webhook_url (05ea556) — 벌크는 요청 단위로 하나만 받는다 */
    public function testSendBulkWithWebhookUrl()
    {
        $recipients = array(array('to' => '01012345678', 'ref_id' => 'bulk-0001'));
        $this->api->alimtalkSend->sendBulk(array(
            'template_code' => 'TPL-1',
            'recipients' => $recipients,
            'webhook_url' => 'https://example.com/hooks/alimtalk/bulk'
        ));
        $this->assertAlimtalkCall('POST', 'alimtalk/send/bulk');
        $this->assertSame(
            array(
                'template_code' => 'TPL-1',
                'recipients' => $recipients,
                'webhook_url' => 'https://example.com/hooks/alimtalk/bulk'
            ),
            $this->lastCall()['data']
        );

        $this->api->alimtalkSend->sendBulk(array('template_code' => 'TPL-1', 'recipients' => $recipients));
        $this->assertArrayNotHasKey('webhook_url', $this->lastCall()['data']);
    }

    public function testSendBulkRequiresRecipients()
    {
        $this->expectException(\Exception::class);
        $this->api->alimtalkSend->sendBulk(array('template_code' => 'TPL-1', 'recipients' => array()));
    }

    public function testSendCancel()
    {
        $this->api->alimtalkSend->cancel('rcp-1');
        $this->assertAlimtalkCall('DELETE', 'alimtalk/send/rcp-1');
    }

    // ---------- 4. 발송내역·집계 ----------

    public function testMessageList()
    {
        $this->api->alimtalkMessage->getList(array(
            'template_code' => 'TPL-1',
            'status' => 'success',
            'ref_id' => 'order-1',
            'to' => '01012345678',
            's_at' => '2026-08-01',
            'e_at' => '2026-08-27',
            'page' => 2,
            'limit' => 50
        ));
        $this->assertAlimtalkCall(
            'GET',
            'alimtalk/messages?template_code=TPL-1&status=success&ref_id=order-1&to=01012345678'
            . '&s_at=2026-08-01&e_at=2026-08-27&page=2&limit=50'
        );
    }

    public function testMessageStats()
    {
        $this->api->alimtalkMessage->stats(array('s_at' => '2026-08-01', 'e_at' => '2026-08-27'));
        $this->assertAlimtalkCall('GET', 'alimtalk/messages/stats?s_at=2026-08-01&e_at=2026-08-27');

        $this->api->alimtalkMessage->stats();
        $this->assertAlimtalkCall('GET', 'alimtalk/messages/stats');
    }

    public function testMessageDetail()
    {
        $this->api->alimtalkMessage->detail('rcp-1');
        $this->assertAlimtalkCall('GET', 'alimtalk/messages/rcp-1');
    }

    // ---------- 5. 수신거부 ----------

    public function testOptoutList()
    {
        $this->api->alimtalkOptout->getList(array('phone' => '1234', 'page' => 2));
        $this->assertAlimtalkCall('GET', 'alimtalk/optouts?phone=1234&page=2');
    }

    public function testOptoutCreate()
    {
        $this->api->alimtalkOptout->create(array('phone' => '01012345678', 'reason' => 'CRM 동기화'));
        $this->assertAlimtalkCall('POST', 'alimtalk/optouts');
        $this->assertSame(
            array('phone' => '01012345678', 'reason' => 'CRM 동기화'),
            $this->lastCall()['data']
        );
    }

    public function testOptoutCreateRequiresPhone()
    {
        $this->expectException(\Exception::class);
        $this->api->alimtalkOptout->create(array('reason' => '사유만'));
    }

    public function testOptoutCheckAcceptsSingleAndBulk()
    {
        $this->api->alimtalkOptout->check(array('phones' => array('01012345678', '01087654321')));
        $this->assertAlimtalkCall('POST', 'alimtalk/optouts/check');
        $this->assertSame(array('phones' => array('01012345678', '01087654321')), $this->lastCall()['data']);

        $this->api->alimtalkOptout->check(array('phone' => '01012345678'));
        $this->assertSame(array('phone' => '01012345678'), $this->lastCall()['data']);
    }

    public function testOptoutCheckRequiresPhoneOrPhones()
    {
        $this->expectException(\Exception::class);
        $this->api->alimtalkOptout->check(array());
    }

    public function testOptoutRelease()
    {
        $this->api->alimtalkOptout->release('01012345678');
        $this->assertAlimtalkCall('DELETE', 'alimtalk/optouts/01012345678');
    }

    // ---------- 6. 공식 템플릿 카탈로그 ----------

    /** 서버는 q 를 먼저 보고 없으면 keyword 를 본다 — 정본 키인 q 로 보낸다 */
    public function testOfficialListMapsKeywordToQ()
    {
        $this->api->alimtalkOfficial->getList(array('keyword' => '주문', 'msg_type' => 'BA', 'per' => 50));
        $call = $this->lastCall();

        $this->assertStringStartsWith('alimtalk/official?', $call['url']);
        parse_str(substr($call['url'], strpos($call['url'], '?') + 1), $query);
        $this->assertSame('주문', $query['q']);
        $this->assertArrayNotHasKey('keyword', $query);
        $this->assertSame('BA', $query['msg_type']);
        $this->assertSame('50', $query['per']);
        $this->assertSame('user', $this->headerValue('BOOTPAY-ROLE'));
    }

    public function testOfficialListPrefersExplicitQ()
    {
        $this->api->alimtalkOfficial->getList(array('q' => '정본', 'keyword' => '별칭'));
        parse_str(substr($this->lastCall()['url'], strpos($this->lastCall()['url'], '?') + 1), $query);

        $this->assertSame('정본', $query['q']);
        $this->assertArrayNotHasKey('keyword', $query);
    }

    public function testOfficialRecommend()
    {
        $this->api->alimtalkOfficial->recommend(array('text' => '주문이 완료되었습니다', 'limit' => 3));
        $this->assertAlimtalkCall('POST', 'alimtalk/official/recommend');
        $this->assertSame(
            array('text' => '주문이 완료되었습니다', 'limit' => 3),
            $this->lastCall()['data']
        );
    }

    public function testOfficialRecommendRequiresText()
    {
        $this->expectException(\Exception::class);
        $this->api->alimtalkOfficial->recommend(array('limit' => 3));
    }

    public function testOfficialDetail()
    {
        $this->api->alimtalkOfficial->detail('OFC-1');
        $this->assertAlimtalkCall('GET', 'alimtalk/official/OFC-1');

        $this->api->alimtalkOfficial->detail('OFC-1', 'ksp-1');
        $this->assertAlimtalkCall('GET', 'alimtalk/official/OFC-1?ksp_id=ksp-1');
    }

    // ---------- 7. 웹훅 ----------

    public function testWebhookDetail()
    {
        $this->api->alimtalkWebhook->detail();
        $this->assertAlimtalkCall('GET', 'alimtalk/webhook');
    }

    public function testWebhookUpdateKeepsExplicitFalseEnabled()
    {
        $this->api->alimtalkWebhook->update(array(
            'url' => 'https://example.com/hook',
            'events' => array(301, 302),
            'enabled' => false
        ));
        $this->assertAlimtalkCall('PUT', 'alimtalk/webhook');
        $this->assertSame(
            array('url' => 'https://example.com/hook', 'events' => array(301, 302), 'enabled' => false),
            $this->lastCall()['data']
        );
    }

    /** e424a9d — retry_count(건별 재시도 횟수 1~25, 기본 10)를 그대로 실어 보낸다 */
    public function testWebhookUpdateCarriesRetryCount()
    {
        $this->api->alimtalkWebhook->update(array(
            'url' => 'https://example.com/hook',
            'retry_count' => 25
        ));
        $this->assertAlimtalkCall('PUT', 'alimtalk/webhook');
        $this->assertSame(
            array('url' => 'https://example.com/hook', 'retry_count' => 25),
            $this->lastCall()['data']
        );
    }

    /** 미지정이면 키째로 빠진다 — 서버에 저장된 기존 재시도 횟수가 유지되어야 하기 때문이다 */
    public function testWebhookUpdateOmitsRetryCountWhenNull()
    {
        $this->api->alimtalkWebhook->update(array(
            'url' => 'https://example.com/hook',
            'retry_count' => null
        ));
        $this->assertArrayNotHasKey('retry_count', $this->lastCall()['data']);
    }

    public function testWebhookUpdateWithoutParamsSendsEmptyJsonObject()
    {
        $this->api->alimtalkWebhook->update();
        $this->assertSame('{}', json_encode($this->lastCall()['data']));
    }

    public function testWebhookTestAndRotateSecret()
    {
        $this->api->alimtalkWebhook->test();
        $this->assertAlimtalkCall('POST', 'alimtalk/webhook/test');

        $this->api->alimtalkWebhook->rotateSecret();
        $this->assertAlimtalkCall('POST', 'alimtalk/webhook/secret');
    }

    public function testWebhookDeliveries()
    {
        $this->api->alimtalkWebhook->deliveries(array('page' => 3, 'limit' => 100));
        $this->assertAlimtalkCall('GET', 'alimtalk/webhook/deliveries?page=3&limit=100');

        $this->api->alimtalkWebhook->deliveries();
        $this->assertAlimtalkCall('GET', 'alimtalk/webhook/deliveries');
    }

    // ---------- 8. 모듈 배선 · 표면 ----------

    public function testAlimtalkModulesAreWiredUp()
    {
        $api = new BootpayCommerceApi('ck', 'sk', 'development');
        $expected = array(
            'alimtalkSender' => 'Bootpay\ServerPhp\Commerce\AlimtalkSenderModule',
            'alimtalkTemplate' => 'Bootpay\ServerPhp\Commerce\AlimtalkTemplateModule',
            'alimtalkSend' => 'Bootpay\ServerPhp\Commerce\AlimtalkSendModule',
            'alimtalkMessage' => 'Bootpay\ServerPhp\Commerce\AlimtalkMessageModule',
            'alimtalkOptout' => 'Bootpay\ServerPhp\Commerce\AlimtalkOptoutModule',
            'alimtalkOfficial' => 'Bootpay\ServerPhp\Commerce\AlimtalkOfficialModule',
            'alimtalkWebhook' => 'Bootpay\ServerPhp\Commerce\AlimtalkWebhookModule'
        );

        foreach ($expected as $property => $fqcn) {
            $this->assertInstanceOf($fqcn, $api->{$property});
            // PSR-4: 각 모듈이 자기 파일에서 단독으로 autoload 되어야 한다
            $file = (new \ReflectionClass($fqcn))->getFileName();
            $this->assertSame(substr(strrchr($fqcn, '\\'), 1) . '.php', basename($file));
        }
    }

    /** Ruby SDK 8f1ee1e 의 35종이 모두 있는지 (누락 회귀 방지) */
    public function testAllThirtyFiveMethodsExist()
    {
        $api = new BootpayCommerceApi('ck', 'sk', 'development');
        $contract = array(
            'alimtalkSender' => array('categories', 'otp', 'create', 'getList', 'detail', 'release', 'variableExamples'),
            'alimtalkTemplate' => array(
                'getList', 'create', 'detail', 'update', 'delete',
                'register', 'inspect', 'export', 'image', 'highlightImage'
            ),
            'alimtalkSend' => array('send', 'sendBulk', 'cancel'),
            'alimtalkMessage' => array('getList', 'stats', 'detail'),
            'alimtalkOptout' => array('getList', 'create', 'check', 'release'),
            'alimtalkOfficial' => array('getList', 'recommend', 'detail'),
            'alimtalkWebhook' => array('detail', 'update', 'test', 'rotateSecret', 'deliveries')
        );

        $count = 0;
        foreach ($contract as $property => $methods) {
            foreach ($methods as $method) {
                $this->assertTrue(
                    method_exists($api->{$property}, $method),
                    get_class($api->{$property}) . "::{$method} 이 있어야 한다"
                );
                $count++;
            }
        }
        $this->assertSame(35, $count);
    }

    private function makeTempImage()
    {
        $path = tempnam(sys_get_temp_dir(), 'bootpay-alimtalk-') . '.png';
        file_put_contents($path, 'png-bytes');
        return $path;
    }
}
