<?php

namespace Bootpay\ServerPhp\Test\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;
use Bootpay\ServerPhp\Test\WireEchoTestCase;
use ReflectionProperty;

require_once __DIR__ . '/../WireEchoTestCase.php';

/**
 * Commerce 공통 계층(curl)이 실제로 전송하는 wire 검증 — 로컬 echo 서버 사용, 외부 호출 없음.
 * mock(CommerceWireFormatTest)이 못 보는 영역을 커버한다:
 * 최종 헤더 병합 결과, DELETE body 실전송, multipart boundary 유지와 images[n] 인덱싱.
 */
class CommerceHttpWireTest extends WireEchoTestCase
{
    /** @var array 원본 API_URL (테스트 후 복원) */
    private static $originalApiUrl;

    /** @var array 원본 MESSAGE_API_URL (테스트 후 복원) */
    private static $originalMessageApiUrl;

    /** @var BootpayCommerceApi */
    private $api;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $prop = self::apiUrlProperty();
        self::$originalApiUrl = $prop->getValue();
        $redirected = self::$originalApiUrl;
        $redirected['development'] = self::echoServerUrl('/v1');
        $prop->setValue(null, $redirected);

        // 알림톡은 메시지 API 호스트(/v1 없음)로 나간다 — 같은 echo 서버의 루트로 돌린다
        $messageProp = self::apiUrlProperty('MESSAGE_API_URL');
        self::$originalMessageApiUrl = $messageProp->getValue();
        $messageRedirected = self::$originalMessageApiUrl;
        $messageRedirected['development'] = self::echoServerUrl('');
        $messageProp->setValue(null, $messageRedirected);
    }

    public static function tearDownAfterClass(): void
    {
        self::apiUrlProperty()->setValue(null, self::$originalApiUrl);
        self::apiUrlProperty('MESSAGE_API_URL')->setValue(null, self::$originalMessageApiUrl);
        parent::tearDownAfterClass();
    }

    private static function apiUrlProperty($name = 'API_URL')
    {
        $prop = new ReflectionProperty(BootpayCommerceApi::class, $name);
        if (PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }
        return $prop;
    }

    protected function setUp(): void
    {
        $this->api = new BootpayCommerceApi('ck', 'sk', 'development');
    }

    public function testDefaultRequestSendsBasicAuthAndUserRole()
    {
        $res = $this->api->user->getList();

        $this->assertEquals('GET', $res->method);
        $this->assertEquals('/v1/users', $res->uri);
        $this->assertEquals('Basic ' . base64_encode('ck:sk'), self::echoHeader($res, 'Authorization'));
        $this->assertEquals('user', self::echoHeader($res, 'BOOTPAY-ROLE'));
        $this->assertEquals('application/json', self::echoHeader($res, 'Content-Type'));
        // 버전 리터럴을 박지 않는다 — 헤더가 composer.json 의 패키지 버전을 따라간다는 규약만 고정한다.
        $composer = json_decode(file_get_contents(__DIR__ . '/../../composer.json'), true);
        $this->assertEquals($composer['version'], self::echoHeader($res, 'BOOTPAY-SDK-VERSION'));
        $this->assertEquals('303', self::echoHeader($res, 'BOOTPAY-SDK-TYPE'));
    }

    public function testStoredTokenDoesNotReplaceBasicAuthorization()
    {
        $this->api->setToken('stored-token');

        $res = $this->api->user->getList();

        $this->assertEquals('stored-token', $this->api->getToken());
        $this->assertEquals('Basic ' . base64_encode('ck:sk'), self::echoHeader($res, 'Authorization'));
    }

    public function testRequestScopedSupervisorRoleWinsOnTheWire()
    {
        $res = $this->api->mallSetting->getMallSetting('ms-key');

        $this->assertEquals('/v1/mall-setting', $res->uri);
        $this->assertEquals('supervisor', self::echoHeader($res, 'BOOTPAY-ROLE'));
        $this->assertEquals('ms-key', self::echoHeader($res, 'Idempotency-Key'));
    }

    public function testChargeRevokeTransmitsDeleteBody()
    {
        $res = $this->api->orderSubscription->supervisorChargeRevoke(array('charge_key' => 'ckey-1'));

        $this->assertEquals('DELETE', $res->method);
        $this->assertEquals('/v1/order_subscriptions/charge', $res->uri);
        $this->assertEquals(array('charge_key' => 'ckey-1'), json_decode($res->raw_body, true));
        $this->assertEquals('supervisor', self::echoHeader($res, 'BOOTPAY-ROLE'));
    }

    public function testAdjustmentDeleteTransmitsIdInBodyNotQuery()
    {
        $res = $this->api->orderSubscriptionAdjustment->delete('os-1', 'adj-1');

        $this->assertEquals('DELETE', $res->method);
        $this->assertEquals('/v1/order_subscriptions/os-1/adjustments', $res->uri);
        $this->assertEquals(array('order_subscription_adjustment_id' => 'adj-1'), json_decode($res->raw_body, true));
    }

    public function testUserGroupLimitTransmitsPurchaseLimits()
    {
        $res = $this->api->userGroup->limit(array(
            'user_group_id' => 'ug-1',
            'use_limit' => true,
            'limit_month_purchase' => 100000,
            'limit_week_purchase' => 30000
        ));

        $this->assertEquals('PUT', $res->method);
        $this->assertEquals('/v1/user-groups/ug-1/limit', $res->uri);
        $this->assertEquals(
            array('use_limit' => true, 'limit_month_purchase' => 100000, 'limit_week_purchase' => 30000),
            json_decode($res->raw_body, true)
        );
        $this->assertEquals('manager', self::echoHeader($res, 'BOOTPAY-ROLE'));
    }

    public function testProductCreateWithoutImagesTransmitsJson()
    {
        $res = $this->api->product->create(array('name' => '상품', 'display_price' => 1000, 'memo' => null));

        $this->assertEquals('POST', $res->method);
        $this->assertEquals('/v1/products', $res->uri);
        $this->assertEquals('application/json', self::echoHeader($res, 'Content-Type'));
        $this->assertEquals(array('name' => '상품', 'display_price' => 1000), json_decode($res->raw_body, true));
        $this->assertEquals('user', self::echoHeader($res, 'BOOTPAY-ROLE'));
    }

    public function testProductCreateWithImagesTransmitsMultipartWithBoundaryAndIndexedImages()
    {
        $dir = sys_get_temp_dir() . '/bootpay-php-wire-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/img0.png', 'png-0');
        file_put_contents($dir . '/img1.png', 'png-1');

        try {
            $res = $this->api->product->create(
                array('name' => '상품', 'display_price' => 1000, 'option' => array('color' => 'red')),
                array($dir . '/img0.png', $dir . '/img1.png'),
                'pc-key'
            );

            $this->assertEquals('POST', $res->method);
            $this->assertEquals('/v1/products', $res->uri);

            // boundary 가 유실되면 서버가 본문을 null 로 읽는다
            $contentType = self::echoHeader($res, 'Content-Type');
            $this->assertStringStartsWith('multipart/form-data', $contentType);
            $this->assertStringContainsString('boundary=', $contentType);

            // 필드는 문자열/JSON 문자열로, 이미지는 images[0], images[1] ... 인덱싱으로 전송된다
            $post = (array)$res->post;
            $this->assertEquals('상품', $post['name']);
            $this->assertEquals('1000', $post['display_price']);
            $this->assertEquals(array('color' => 'red'), json_decode($post['option'], true));

            $images = (array)$res->files->images;
            $this->assertCount(2, $images);
            $this->assertArrayHasKey(0, $images);
            $this->assertArrayHasKey(1, $images);

            $this->assertEquals('user', self::echoHeader($res, 'BOOTPAY-ROLE'));
            $this->assertEquals('pc-key', self::echoHeader($res, 'Idempotency-Key'));
            $this->assertEquals('Basic ' . base64_encode('ck:sk'), self::echoHeader($res, 'Authorization'));
        } finally {
            @unlink($dir . '/img0.png');
            @unlink($dir . '/img1.png');
            @rmdir($dir);
        }
    }

    public function testProductCreateMultipartSerializesBooleansAsLowercaseStrings()
    {
        $dir = sys_get_temp_dir() . '/bootpay-php-wire-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/img0.png', 'png-0');

        try {
            $res = $this->api->product->create(
                array('name' => '상품', 'use_stock' => true, 'is_hidden' => false),
                array($dir . '/img0.png')
            );

            // curl 기본 직렬화(true→"1", false→"")면 false 가 서버에서 nil 로 읽힌다 —
            // nodejs String(bool) 과 동일한 'true'/'false' 문자열로 도착해야 한다
            $post = (array)$res->post;
            $this->assertSame('true', $post['use_stock']);
            $this->assertSame('false', $post['is_hidden']);
        } finally {
            @unlink($dir . '/img0.png');
            @rmdir($dir);
        }
    }

    public function testInvoiceListTransmitsDefaultLimit24()
    {
        $res = $this->api->invoice->getList(array('cs_type' => 'month'));

        $this->assertEquals('/v1/invoices?page=1&limit=24&cs_type=month', $res->uri);
        $this->assertEquals('user', self::echoHeader($res, 'BOOTPAY-ROLE'));
    }

    public function testOrderCancelApproveLegacyArgumentStillWorksOnTheWire()
    {
        $res = $this->api->orderCancel->approve(array(
            'order_cancel_request_history_id' => 'ocr-legacy',
            'message' => '승인'
        ));

        $this->assertEquals('PUT', $res->method);
        $this->assertEquals('/v1/order/cancel/ocr-legacy/approve', $res->uri);
        $this->assertEquals(array('message' => '승인'), json_decode($res->raw_body, true));
        $this->assertEquals('supervisor', self::echoHeader($res, 'BOOTPAY-ROLE'));
    }

    /**
     * 알림톡 템플릿 이미지: 파일 필드명이 images[n] 이 아니라 image 로 나가야 한다.
     * (상품 이미지와 필드명이 다르다 — images[0] 으로 보내면 서버가 이미지를 못 찾는다)
     */
    public function testAlimtalkTemplateImageTransmitsNamedImageField()
    {
        $dir = sys_get_temp_dir() . '/bootpay-php-alimtalk-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/tpl.png', 'png-bytes');

        try {
            $res = $this->api->alimtalkTemplate->image($dir . '/tpl.png', 'https://cdn.bootpay.co.kr/old.png');

            $this->assertEquals('POST', $res->method);
            $this->assertEquals('/alimtalk/templates/image', $res->uri);

            $contentType = self::echoHeader($res, 'Content-Type');
            $this->assertStringStartsWith('multipart/form-data', $contentType);
            $this->assertStringContainsString('boundary=', $contentType);

            $files = (array)$res->files;
            $this->assertArrayHasKey('image', $files);
            $this->assertArrayNotHasKey('images', $files);
            $this->assertEquals('tpl.png', $files['image']);

            $post = (array)$res->post;
            $this->assertEquals('https://cdn.bootpay.co.kr/old.png', $post['replace_url']);

            $this->assertEquals('user', self::echoHeader($res, 'BOOTPAY-ROLE'));
            $this->assertNull(
                self::echoHeader($res, 'Idempotency-Key'),
                '알림톡 API 는 Idempotency-Key 를 읽지 않는다'
            );
        } finally {
            @unlink($dir . '/tpl.png');
            @rmdir($dir);
        }
    }

    /**
     * 템플릿 내보내기 csv: 파싱하지 않는 원문 경로로 나가고 본문을 문자열 그대로 돌려준다.
     * 공용 request() 를 타면 csv 가 json_decode 를 통과하지 못해 성공한 요청이 null 로 보인다.
     */
    public function testAlimtalkTemplateExportCsvReturnsRawBody()
    {
        $res = $this->api->alimtalkTemplate->export(array('format' => 'csv', 'scope' => 'private'));

        $this->assertIsString($res->body);
        $this->assertStringContainsString('application/json', $res->content_type); // echo 서버의 응답 타입
        $this->assertEquals(200, $res->status);

        $echoed = json_decode($res->body);
        $this->assertEquals('GET', $echoed->method);
        $this->assertEquals('/alimtalk/templates/export?format=csv&scope=private', $echoed->uri);
        $this->assertEquals('*/*', self::echoHeader($echoed, 'Accept'));
        $this->assertEquals('user', self::echoHeader($echoed, 'BOOTPAY-ROLE'));
        $this->assertEquals('Basic ' . base64_encode('ck:sk'), self::echoHeader($echoed, 'Authorization'));
    }

    /** json(기본) 내보내기는 공용 request() 를 타고 파싱된 객체를 돌려준다 */
    public function testAlimtalkTemplateExportJsonUsesParsedRequest()
    {
        $res = $this->api->alimtalkTemplate->export();

        $this->assertEquals('/alimtalk/templates/export?format=json', $res->uri);
        $this->assertEquals('application/json', self::echoHeader($res, 'Content-Type'));
    }

    public function testAlimtalkSendTransmitsFalseFallbackInBody()
    {
        $res = $this->api->alimtalkSend->send(array(
            'template_code' => 'TPL-1',
            'to' => '01012345678',
            'fallback' => false
        ));

        $this->assertEquals('POST', $res->method);
        $this->assertEquals('/alimtalk/send', $res->uri);
        $this->assertSame(
            array('template_code' => 'TPL-1', 'to' => '01012345678', 'fallback' => false),
            json_decode($res->raw_body, true)
        );
        $this->assertEquals('user', self::echoHeader($res, 'BOOTPAY-ROLE'));
    }

    /**
     * 26-09-21: 건별 결과 웹훅 주소(webhook_url)가 요청 바디에 그대로 실려 나간다.
     */
    public function testAlimtalkSendTransmitsWebhookUrlInBody()
    {
        $res = $this->api->alimtalkSend->send(array(
            'template_code' => 'TPL-1',
            'to' => '01012345678',
            'webhook_url' => 'https://example.com/hooks/alimtalk'
        ));

        $this->assertEquals('POST', $res->method);
        $this->assertEquals('/alimtalk/send', $res->uri);
        $this->assertSame(
            array(
                'template_code' => 'TPL-1',
                'to' => '01012345678',
                'webhook_url' => 'https://example.com/hooks/alimtalk'
            ),
            json_decode($res->raw_body, true)
        );
    }

    /**
     * 26-09-18: 알림톡(/alimtalk/*)만 메시지 API 호스트(/v1 없음)로 가고, 그 외는 기존 /v1 호스트 그대로다.
     */
    public function testAlimtalkRoutesToMessageHostWithoutV1()
    {
        $alimtalk = $this->api->alimtalkSender->getList();
        $this->assertEquals('GET', $alimtalk->method);
        $this->assertEquals('/alimtalk/senders', $alimtalk->uri);

        $commerce = $this->api->user->getList();
        $this->assertEquals('/v1/users', $commerce->uri);
    }

    /** 메시지 API 기본 주소 — mode 별 표와 알 수 없는 mode 의 production 폴백 */
    public function testMessageApiBaseUrlTable()
    {
        $resolve = new \ReflectionMethod(BootpayCommerceApi::class, 'resolveBaseUrl');
        if (PHP_VERSION_ID < 80100) {
            $resolve->setAccessible(true);
        }
        $original = self::$originalMessageApiUrl;
        $this->assertEquals('https://dev-m.bootapi.com', $original['development']);
        $this->assertEquals('https://stage-m.bootapi.com', $original['stage']);
        $this->assertEquals('https://message.bootapi.com', $original['production']);

        $this->assertEquals('https://stage-m.bootapi.com', $resolve->invoke(null, 'stage', 'alimtalk/send'));
        $this->assertEquals('https://stage-m.bootapi.com', $resolve->invoke(null, 'stage', '/alimtalk/send'));
        $this->assertEquals('https://stage-api.bootapi.com/v1', $resolve->invoke(null, 'stage', 'users'));
        $this->assertEquals('https://message.bootapi.com', $resolve->invoke(null, 'production', 'alimtalk/messages'));
    }

    public function testMallUserSessionTransmitsJwtHeader()
    {
        $res = $this->api->user->userSession('jwt-1');

        $this->assertEquals('GET', $res->method);
        $this->assertEquals('/v1/users/session', $res->uri);
        $this->assertEquals('jwt-1', self::echoHeader($res, 'Bootpay-User-JWT'));
    }
}
