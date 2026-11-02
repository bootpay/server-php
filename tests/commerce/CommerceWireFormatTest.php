<?php

namespace Bootpay\ServerPhp\Test\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;
use Bootpay\ServerPhp\Test\RecordingCommerceApi;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once __DIR__ . '/../RecordingCommerceApi.php';

/**
 * NodeJS SDK 2.9.0 parity — 요청 URL·헤더·바디 규약 검증 (네트워크 미사용)
 */
class CommerceWireFormatTest extends TestCase
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

    private function invokeCreateHeaders($headers)
    {
        $ref = new ReflectionClass(BootpayCommerceApi::class);
        $createHeaders = $ref->getMethod('createHeaders');
        if (PHP_VERSION_ID < 80100) {
            $createHeaders->setAccessible(true);
        }
        return $createHeaders->invoke($this->api, $headers);
    }

    // ---------- 공통 계층 ----------

    public function testGenerateIdempotencyKeyIsUuidV4()
    {
        $uuid = BootpayCommerceApi::generateIdempotencyKey();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
        $this->assertNotEquals($uuid, BootpayCommerceApi::generateIdempotencyKey());
    }

    public function testCreateHeadersDoesNotDuplicateRequestScopedRole()
    {
        $headers = $this->invokeCreateHeaders(array('BOOTPAY-ROLE: supervisor'));

        $roles = array_values(array_filter($headers, function ($header) {
            return stripos($header, 'BOOTPAY-ROLE:') === 0;
        }));
        $this->assertCount(1, $roles, 'BOOTPAY-ROLE 헤더가 중복 전송되면 안 된다');
        $this->assertEquals('BOOTPAY-ROLE: supervisor', $roles[0]);
    }

    public function testCreateHeadersKeepsDefaultRoleWhenNotOverridden()
    {
        $headers = $this->invokeCreateHeaders(array('Idempotency-Key: fixed'));

        $this->assertContains('BOOTPAY-ROLE: user', $headers);
        $this->assertContains('Idempotency-Key: fixed', $headers);
        $this->assertContains('Content-Type: application/json', $headers);
    }

    // ---------- PG parity 항목 1 은 tests/pg/SequentialBillingKeyTest.php ----------

    // ---------- 2. MallSetting ----------

    public function testMallSettingGet()
    {
        $this->api->mallSetting->getMallSetting();
        $call = $this->lastCall();

        $this->assertEquals('GET', $call['method']);
        $this->assertEquals('mall-setting', $call['url']);
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));
        $this->assertNotNull($this->headerValue('Idempotency-Key'));
    }

    public function testMallSettingUpdateCompactsNullAndHonorsIdempotencyKey()
    {
        $this->api->mallSetting->updateMallSetting(array(
            'name' => 'my mall',
            'use_cart' => false,
            'description' => null
        ), 'fixed-key');
        $call = $this->lastCall();

        $this->assertEquals('PUT', $call['method']);
        $this->assertEquals('mall-setting', $call['url']);
        $this->assertEquals(array('name' => 'my mall', 'use_cart' => false), $call['data']);
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));
        $this->assertEquals('fixed-key', $this->headerValue('Idempotency-Key'));
    }

    public function testMallSettingAliases()
    {
        $this->api->mallSetting->detail();
        $this->assertEquals('GET', $this->lastCall()['method']);
        $this->api->mallSetting->update(array('name' => 'n'));
        $this->assertEquals('PUT', $this->lastCall()['method']);
    }

    // ---------- 3. Webhook ----------

    public function testWebhookSendTest()
    {
        $this->api->webhook->sendTest(array('header_content_type' => 1));
        $call = $this->lastCall();

        $this->assertEquals('POST', $call['method']);
        $this->assertEquals('webhook/test', $call['url']);
        $this->assertEquals(array('header_content_type' => 1), $call['data']);
        $this->assertNotNull($this->headerValue('Idempotency-Key'));
        $this->assertNull($this->headerValue('BOOTPAY-ROLE'));
    }

    public function testWebhookSendTestWithoutParamsSendsEmptyObjectBody()
    {
        $this->api->webhook->sendTest();
        $call = $this->lastCall();

        $this->assertEquals('{}', json_encode($call['data']));
    }

    public function testWebhookSendTestMovesIdempotencyKeyToHeader()
    {
        $this->api->webhook->sendTest(array('idempotency_key' => 'wh-key'));
        $call = $this->lastCall();

        $this->assertEquals('wh-key', $this->headerValue('Idempotency-Key'));
        $this->assertEquals('{}', json_encode($call['data']));
    }

    // ---------- 4. OrderSubscription supervisorCharge / supervisorChargeRevoke ----------

    public function testSupervisorChargeSendsChargeKeyInBodyOnly()
    {
        $this->api->orderSubscription->supervisorCharge(array(
            'charge_key' => 'ck-123',
            'price' => 1000,
            'tax_free_price' => null
        ));
        $call = $this->lastCall();

        $this->assertEquals('POST', $call['method']);
        $this->assertEquals('order_subscriptions/charge', $call['url']);
        $this->assertStringNotContainsString('charge_key', $call['url']);
        $this->assertEquals(array('charge_key' => 'ck-123', 'price' => 1000), $call['data']);
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));
        $this->assertNotNull($this->headerValue('Idempotency-Key'));
    }

    public function testSupervisorChargeHonorsExplicitIdempotencyKey()
    {
        $this->api->orderSubscription->supervisorCharge(array(
            'charge_key' => 'ck-123',
            'price' => 1000,
            'idempotency_key' => 'charge-key-1'
        ));
        $call = $this->lastCall();

        $this->assertEquals('charge-key-1', $this->headerValue('Idempotency-Key'));
        $this->assertArrayNotHasKey('idempotency_key', $call['data']);
    }

    public function testSupervisorChargeRevokeSendsDeleteWithBody()
    {
        $this->api->orderSubscription->supervisorChargeRevoke(array('charge_key' => 'ck-123'));
        $call = $this->lastCall();

        $this->assertEquals('DELETE', $call['method']);
        $this->assertEquals('order_subscriptions/charge', $call['url']);
        $this->assertEquals(array('charge_key' => 'ck-123'), $call['data']);
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));
    }

    // ---------- 5. User Mall API ----------

    public function testUserLoginUsesPluralRouteAndDefaultsCorporateType()
    {
        $this->api->user->userLogin(array('login_id' => 'tester', 'password' => 'pw'));
        $call = $this->lastCall();

        $this->assertEquals('POST', $call['method']);
        $this->assertEquals('users/login', $call['url']);
        $this->assertEquals(array('login_id' => 'tester', 'password' => 'pw', 'corporate_type' => 0), $call['data']);
        $this->assertNotNull($this->headerValue('Idempotency-Key'));
        $this->assertNull($this->headerValue('Bootpay-User-JWT'));
    }

    public function testUserSessionAttachesJwtOnlyWhenPresent()
    {
        $this->api->user->userSession('jwt-token');
        $this->assertEquals('GET', $this->lastCall()['method']);
        $this->assertEquals('users/session', $this->lastCall()['url']);
        $this->assertEquals('jwt-token', $this->headerValue('Bootpay-User-JWT'));

        $this->api->user->userSession();
        $this->assertNull($this->headerValue('Bootpay-User-JWT'));
    }

    public function testUserLogoutDeletesSession()
    {
        $this->api->user->userLogout('jwt-token');
        $call = $this->lastCall();

        $this->assertEquals('DELETE', $call['method']);
        $this->assertEquals('users/session', $call['url']);
        $this->assertEquals('jwt-token', $this->headerValue('Bootpay-User-JWT'));
    }

    public function testUserJoinCompactsNullValues()
    {
        $this->api->user->userJoin(array('login_id' => 'tester', 'password' => 'pw', 'phone' => null));
        $call = $this->lastCall();

        $this->assertEquals('POST', $call['method']);
        $this->assertEquals('users/join', $call['url']);
        $this->assertEquals(array('login_id' => 'tester', 'password' => 'pw', 'corporate_type' => 0), $call['data']);
    }

    public function testUserJoinCheckAndUidExist()
    {
        $this->api->user->userJoinCheck('email-exist', 'a@b.com');
        $this->assertEquals('users/join/email-exist?pk=' . urlencode('a@b.com'), $this->lastCall()['url']);

        $this->api->user->uidExist('uid 001');
        $call = $this->lastCall();
        $this->assertEquals('users/join/uid-exist?pk=' . urlencode('uid 001'), $call['url']);
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
    }

    // ---------- 6. Product Mall API ----------

    public function testProductsAppliesDefaultsAndJwtHeader()
    {
        $this->api->product->products(array('category_id' => 'cat-1', 'sort' => '-created_at', 'user_jwt' => 'jwt-1'));
        $call = $this->lastCall();

        $this->assertEquals('GET', $call['method']);
        $this->assertEquals('products?page=1&limit=20&category_id=cat-1&sort=-created_at', $call['url']);
        $this->assertEquals('jwt-1', $this->headerValue('Bootpay-User-JWT'));
    }

    public function testProductListSendsServerReadFilters()
    {
        // 서버(v1/products_controller#index)가 읽는 category_id / ex_uid / sort 를 실어야 한다
        $this->api->product->getList(array(
            'page'        => 1,
            'limit'       => 10,
            'keyword'     => 'coffee',
            'category_id' => 'cat-1',
            'ex_uid'      => 'EX-1',
            'sort'        => '-price'
        ));
        $call = $this->lastCall();

        $this->assertEquals('GET', $call['method']);
        foreach (array('page=1', 'limit=10', 'keyword=coffee', 'category_id=cat-1', 'ex_uid=EX-1', 'sort=-price') as $fragment) {
            $this->assertStringContainsString($fragment, $call['url']);
        }
    }

    public function testProductsWithoutParamsSendsDefaults()
    {
        $this->api->product->products();
        $this->assertEquals('products?page=1&limit=20', $this->lastCall()['url']);
        $this->assertNull($this->headerValue('Bootpay-User-JWT'));
    }

    public function testProductDetailMallVariant()
    {
        $this->api->product->productDetail('prod-1', 'jwt-1');
        $call = $this->lastCall();

        $this->assertEquals('products/prod-1', $call['url']);
        $this->assertEquals('jwt-1', $this->headerValue('Bootpay-User-JWT'));
        $this->assertNotNull($this->headerValue('Idempotency-Key'));
    }

    // ---------- 7. requestIng purchase / transfer ----------

    public function testRequestIngPurchase()
    {
        $this->api->orderSubscription->requestIng->purchase(array('order_number' => 'ON-1', 'price' => 500));
        $call = $this->lastCall();

        $this->assertEquals('POST', $call['method']);
        $this->assertEquals('order_subscriptions/requests/ing/purchase', $call['url']);
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
        $this->assertNotNull($this->headerValue('Idempotency-Key'));
    }

    public function testRequestIngTransfer()
    {
        $this->api->orderSubscription->requestIng->transfer(array('order_subscription_id' => 'os-1', 'new_user_id' => 'u-2'));
        $call = $this->lastCall();

        $this->assertEquals('POST', $call['method']);
        $this->assertEquals('order_subscriptions/requests/ing/transfer', $call['url']);
        $this->assertEquals(array('order_subscription_id' => 'os-1', 'new_user_id' => 'u-2'), $call['data']);
    }

    public function testCalculateTerminationFeeSendsBothParamsTogether()
    {
        // nodejs HEAD 는 두 인자를 동시에 보낼 수 있다 (구버전의 elseif 단독 전송 회귀 방지)
        $this->api->orderSubscription->requestIng->calculateTerminationFee('os-1', 'ON-1', 'fee-key');
        $call = $this->lastCall();

        $this->assertEquals('GET', $call['method']);
        $this->assertEquals(
            'order_subscriptions/requests/ing/calculate_termination_fee?order_subscription_id=os-1&order_number=ON-1',
            $call['url']
        );
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
        $this->assertEquals('fee-key', $this->headerValue('Idempotency-Key'));
    }

    public function testCalculateTerminationFeeSingleParamVariants()
    {
        $this->api->orderSubscription->requestIng->calculateTerminationFee('os-1');
        $this->assertEquals(
            'order_subscriptions/requests/ing/calculate_termination_fee?order_subscription_id=os-1',
            $this->lastCall()['url']
        );

        $this->api->orderSubscription->requestIng->calculateTerminationFeeByOrderNumber('ON-1');
        $this->assertEquals(
            'order_subscriptions/requests/ing/calculate_termination_fee?order_number=ON-1',
            $this->lastCall()['url']
        );
    }

    public function testRequestIngPauseMovesIdempotencyKeyToHeader()
    {
        $this->api->orderSubscription->requestIng->pause(array('order_number' => 'ON-1', 'idempotency_key' => 'p-key'));
        $call = $this->lastCall();

        $this->assertEquals('p-key', $this->headerValue('Idempotency-Key'));
        $this->assertEquals(array('order_number' => 'ON-1'), $call['data']);
    }

    // ---------- 8. Product create — JSON / multipart 분기 ----------

    public function testProductCreateWithoutImagesSendsJson()
    {
        $this->api->product->create(array('name' => 'product', 'price' => 1000, 'memo' => null));
        $call = $this->lastCall();

        $this->assertEquals('json', $call['type']);
        $this->assertEquals('POST', $call['method']);
        $this->assertEquals('products', $call['url']);
        $this->assertEquals(array('name' => 'product', 'price' => 1000), $call['data']);
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
        $this->assertNotNull($this->headerValue('Idempotency-Key'));
    }

    public function testProductCreateWithImagesSendsMultipartWithHeaders()
    {
        $this->api->product->create(array('name' => 'product'), array('/tmp/a.png', '/tmp/b.png'), 'pc-key');
        $call = $this->lastCall();

        $this->assertEquals('multipart', $call['type']);
        $this->assertEquals('products', $call['url']);
        $this->assertEquals(array('/tmp/a.png', '/tmp/b.png'), $call['files']);
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
        $this->assertEquals('pc-key', $this->headerValue('Idempotency-Key'));
    }

    public function testProductStatusStripsProductIdFromBody()
    {
        $this->api->product->status(array('product_id' => 'prod-1', 'status' => 1));
        $call = $this->lastCall();

        $this->assertEquals('products/prod-1/status', $call['url']);
        $this->assertEquals(array('status' => 1), $call['data']);
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
    }

    public function testProductUpdateUsesUserRole()
    {
        $this->api->product->update(array('product_id' => 'prod-1', 'name' => 'renamed'), 'pu-key');
        $call = $this->lastCall();

        $this->assertEquals('PUT', $call['method']);
        $this->assertEquals('products/prod-1', $call['url']);
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
        $this->assertEquals('pu-key', $this->headerValue('Idempotency-Key'));
    }

    public function testProductDeleteUsesUserRole()
    {
        $this->api->product->delete('prod-1');
        $this->assertEquals('DELETE', $this->lastCall()['method']);
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
    }

    // ---------- 9. Invoice ----------

    public function testInvoiceListAppliesDefaultsAndNewParams()
    {
        $this->api->invoice->getList(array(
            'cs_type' => 'month',
            'user_id' => 'u-1',
            'product_type' => 2,
            'css_at' => '2026-01-01',
            'cse_at' => '2026-12-31'
        ));
        $call = $this->lastCall();

        $this->assertEquals(
            'invoices?page=1&limit=24&cs_type=month&user_id=u-1&product_type=2&css_at=2026-01-01&cse_at=2026-12-31',
            $call['url']
        );
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
        $this->assertNotNull($this->headerValue('Idempotency-Key'));
    }

    public function testInvoiceNotifyWithoutSendTypesSendsEmptyObject()
    {
        $this->api->invoice->notify('inv-1');
        $call = $this->lastCall();

        $this->assertEquals('invoices/inv-1/notify', $call['url']);
        $this->assertEquals('{}', json_encode($call['data']));
    }

    public function testInvoiceNotifyWithSendTypes()
    {
        $this->api->invoice->notify('inv-1', array(1, 2), 'n-key');
        $call = $this->lastCall();

        $this->assertEquals(array('send_types' => array(1, 2)), $call['data']);
        $this->assertEquals('n-key', $this->headerValue('Idempotency-Key'));
    }

    // ---------- 10. OrderCancel — 인자명 통일 + 하위호환 ----------

    public function testOrderCancelApproveWithNewArgumentName()
    {
        $this->api->orderCancel->approve(array(
            'order_cancellation_request_id' => 'ocr-1',
            'message' => 'ok'
        ));
        $call = $this->lastCall();

        $this->assertEquals('PUT', $call['method']);
        $this->assertEquals('order/cancel/ocr-1/approve', $call['url']);
        $this->assertEquals(array('message' => 'ok'), $call['data']);
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));
    }

    public function testOrderCancelApproveWithLegacyArgumentName()
    {
        $this->api->orderCancel->approve(array(
            'order_cancel_request_history_id' => 'ocr-legacy',
            'message' => 'ok'
        ));
        $call = $this->lastCall();

        $this->assertEquals('order/cancel/ocr-legacy/approve', $call['url']);
        $this->assertEquals(array('message' => 'ok'), $call['data']);
    }

    public function testOrderCancelRejectUsesSupervisorRole()
    {
        $this->api->orderCancel->reject(array('order_cancellation_request_id' => 'ocr-1'));
        $call = $this->lastCall();

        $this->assertEquals('order/cancel/ocr-1/reject', $call['url']);
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));
    }

    public function testOrderCancelWithdrawAcceptsPlainString()
    {
        $this->api->orderCancel->withdraw('ocr-1');
        $call = $this->lastCall();

        $this->assertEquals('order/cancel/ocr-1/withdraw', $call['url']);
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
    }

    public function testOrderCancelWithdrawAcceptsArrayWithNewName()
    {
        $this->api->orderCancel->withdraw(array('order_cancellation_request_id' => 'ocr-2'));
        $this->assertEquals('order/cancel/ocr-2/withdraw', $this->lastCall()['url']);
    }

    public function testOrderCancelListUsesUserRole()
    {
        $this->api->orderCancel->getList(array('order_number' => 'ON-1'));
        $call = $this->lastCall();

        $this->assertEquals('order/cancel?order_number=ON-1', $call['url']);
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
    }

    // ---------- 11. OrderSubscriptionAdjustment ----------

    public function testAdjustmentDeleteSendsIdInBody()
    {
        $this->api->orderSubscriptionAdjustment->delete('os-1', 'adj-1');
        $call = $this->lastCall();

        $this->assertEquals('DELETE', $call['method']);
        $this->assertEquals('order_subscriptions/os-1/adjustments', $call['url']);
        $this->assertStringNotContainsString('order_subscription_adjustment_id', $call['url']);
        $this->assertEquals(array('order_subscription_adjustment_id' => 'adj-1'), $call['data']);
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));
    }

    public function testAdjustmentCreateAppliesDefaults()
    {
        $this->api->orderSubscriptionAdjustment->create('os-1', array('title' => 'setup'));
        $call = $this->lastCall();

        $this->assertEquals('order_subscriptions/os-1/adjustments', $call['url']);
        $this->assertEquals(array('price' => 0, 'duration' => 1, 'tax_free_price' => 0, 'title' => 'setup'), $call['data']);
    }

    public function testAdjustmentCreateSupportsDurationRange()
    {
        $this->api->orderSubscriptionAdjustment->create('os-1', array(
            'name' => '3~7회차 할인',
            'price' => -1000,
            'duration_from' => 3,
            'duration_to' => 7
        ));
        $call = $this->lastCall();

        $this->assertEquals('POST', $call['method']);
        $this->assertEquals('order_subscriptions/os-1/adjustments', $call['url']);
        $this->assertEquals(
            array(
                'price' => -1000,
                'duration' => 1,
                'tax_free_price' => 0,
                'name' => '3~7회차 할인',
                'duration_from' => 3,
                'duration_to' => 7
            ),
            $call['data']
        );
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));
    }

    public function testAdjustmentCreateSupportsUnlimitedRange()
    {
        $this->api->orderSubscriptionAdjustment->create('os-1', array(
            'price' => 500,
            'duration_from' => 3,
            'is_unlimited' => true
        ));
        $call = $this->lastCall();

        // is_unlimited=false 도 살아있어야 한다 (compact 는 null 만 제거)
        $this->assertArrayHasKey('is_unlimited', $call['data']);
        $this->assertTrue($call['data']['is_unlimited']);
        $this->assertEquals(3, $call['data']['duration_from']);
        $this->assertArrayNotHasKey('duration_to', $call['data']);
    }

    public function testAdjustmentCreateKeepsFalseUnlimitedFlag()
    {
        $this->api->orderSubscriptionAdjustment->create('os-1', array('duration' => 5, 'is_unlimited' => false));
        $call = $this->lastCall();

        $this->assertArrayHasKey('is_unlimited', $call['data']);
        $this->assertFalse($call['data']['is_unlimited']);
        $this->assertEquals(5, $call['data']['duration']);
    }

    public function testAdjustmentUpdateSupportsAdjustmentsArray()
    {
        $this->api->orderSubscriptionAdjustment->update(array(
            'order_subscription_id' => 'os-1',
            'duration' => 2,
            'adjustments' => array(array('price' => 100, 'title' => 'a'))
        ));
        $call = $this->lastCall();

        $this->assertEquals('PUT', $call['method']);
        $this->assertEquals('order_subscriptions/os-1/adjustments', $call['url']);
        $this->assertEquals(
            array('duration' => 2, 'adjustments' => array(array('price' => 100, 'title' => 'a'))),
            $call['data']
        );
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));
    }

    // ---------- 12. 파라미터 확장 ----------

    public function testUserGroupLimitSendsPurchaseLimitsWithManagerRole()
    {
        $this->api->userGroup->limit(array(
            'user_group_id' => 'ug-1',
            'use_limit' => true,
            'limit_month_purchase' => 100000,
            'limit_week_purchase' => 30000
        ));
        $call = $this->lastCall();

        $this->assertEquals('user-groups/ug-1/limit', $call['url']);
        $this->assertEquals(
            array('use_limit' => true, 'limit_month_purchase' => 100000, 'limit_week_purchase' => 30000),
            $call['data']
        );
        $this->assertEquals('manager', $this->headerValue('BOOTPAY-ROLE'));
    }

    public function testOrderListSupportsSearchDateRange()
    {
        $this->api->order->getList(array(
            'search_date_from' => '2026-01-01',
            'search_date_to' => '2026-01-31',
            'css_at' => '2026-01-01'
        ));
        $this->assertEquals(
            'orders?search_date_from=2026-01-01&search_date_to=2026-01-31&css_at=2026-01-01',
            $this->lastCall()['url']
        );
    }

    public function testOrderSubscriptionListSupportsSearchDateAndStatus()
    {
        $this->api->orderSubscription->getList(array(
            'search_date_from' => '2026-01-01',
            'search_date_to' => '2026-01-31',
            'status' => 1
        ));
        $this->assertEquals(
            'order_subscriptions?search_date_from=2026-01-01&search_date_to=2026-01-31&status=1',
            $this->lastCall()['url']
        );
    }

    public function testOrderSubscriptionUpdateUsesSupervisorRoleAndStripsId()
    {
        $this->api->orderSubscription->update(array('order_subscription_id' => 'os-1', 'price' => 900));
        $call = $this->lastCall();

        $this->assertEquals('order_subscriptions/os-1', $call['url']);
        $this->assertEquals(array('price' => 900), $call['data']);
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));
    }

    public function testOrderSubscriptionUpdateSendsBasePriceWithOtherFields()
    {
        $this->api->orderSubscription->update(array(
            'order_subscription_id' => 'os-1',
            'order_name' => '프리미엄 구독',
            'price' => 19900,
            'quantity' => null
        ));
        $call = $this->lastCall();

        $this->assertEquals('PUT', $call['method']);
        $this->assertEquals('order_subscriptions/os-1', $call['url']);
        // price 는 회차 기준금액 — 바디에 그대로 실려야 하고 null 값은 빠져야 한다
        $this->assertEquals(array('order_name' => '프리미엄 구독', 'price' => 19900), $call['data']);
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));
    }

    public function testOrderSubscriptionBillListAppliesDefaultsAndUserRole()
    {
        $this->api->orderSubscriptionBill->getList(array('order_subscription_id' => 'os-1'));
        $call = $this->lastCall();

        $this->assertEquals('order_subscription_bills?order_subscription_id=os-1&page=1&limit=20', $call['url']);
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
        $this->assertNotNull($this->headerValue('Idempotency-Key'));
    }

    public function testOrderSubscriptionRequestListSupportsNewFilters()
    {
        $this->api->orderSubscriptionRequest->getList(array(
            'order_subscription_id' => 'os-1',
            'user_id' => 'u-1',
            'user_group_id' => 'ug-1'
        ));
        $this->assertEquals(
            'order-subscription-requests?order_subscription_id=os-1&page=1&limit=20&user_id=u-1&user_group_id=ug-1',
            $this->lastCall()['url']
        );
    }

    public function testOrderListSupportsSubscriptionFilters()
    {
        $this->api->order->getList(array(
            'order_subscription_ids' => array('os-1', 'os-2'),
            'subscription_billing_type' => 1,
            'status' => array(1, 2)
        ));
        $this->assertEquals(
            'orders?subscription_billing_type=1&status=1%2C2&order_subscription_ids=os-1%2Cos-2',
            $this->lastCall()['url']
        );
    }

    public function testOrderListAcceptsScalarListFilters()
    {
        // 배열뿐 아니라 콤마 문자열·단일 값도 그대로 실린다
        $this->api->order->getList(array(
            'status' => 1,
            'payment_status' => '1,2',
            'order_subscription_ids' => 'os-1'
        ));
        $this->assertEquals(
            'orders?status=1&payment_status=1%2C2&order_subscription_ids=os-1',
            $this->lastCall()['url']
        );
    }

    public function testOrderListOmitsEmptyListFilters()
    {
        // 빈 배열이면 status= 같은 빈 값을 실어 보내지 않는다
        $this->api->order->getList(array(
            'page' => 1,
            'status' => array(),
            'payment_status' => array(),
            'order_subscription_ids' => array()
        ));
        $this->assertEquals('orders?page=1', $this->lastCall()['url']);
    }

    public function testOrderSubscriptionListSupportsOrderNumber()
    {
        // 주문번호 역조회 — 서버(#index)가 params[:order_number] 를 읽는다
        $this->api->orderSubscription->getList(array('order_number' => 'ON-1'));
        $this->assertEquals('order_subscriptions?order_number=ON-1', $this->lastCall()['url']);
    }

    public function testOrderSubscriptionUpdateSendsMemo()
    {
        // memo 는 변경이력에 남길 사유로 바디에 그대로 실려야 한다
        $this->api->orderSubscription->update(array(
            'order_subscription_id' => 'os-1',
            'price' => 19900,
            'memo' => '고객 요청 금액 변경'
        ));
        $call = $this->lastCall();

        $this->assertEquals('order_subscriptions/os-1', $call['url']);
        $this->assertEquals(array('price' => 19900, 'memo' => '고객 요청 금액 변경'), $call['data']);
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));
    }

    public function testProductsSupportsExUid()
    {
        $this->api->product->products(array('ex_uid' => 'EX-1'));
        $this->assertEquals('products?page=1&limit=20&ex_uid=EX-1', $this->lastCall()['url']);
    }

    public function testProductDetailAliasAcceptsUserJwt()
    {
        // detail 도 productDetail 과 같이 회원 컨텍스트 조회를 지원한다
        $this->api->product->detail('prod-1', 'jwt-1');
        $call = $this->lastCall();

        $this->assertEquals('products/prod-1', $call['url']);
        $this->assertEquals('jwt-1', $this->headerValue('Bootpay-User-JWT'));
        $this->assertNotNull($this->headerValue('Idempotency-Key'));

        $this->api->product->detail('prod-1');
        $this->assertEquals('products/prod-1', $this->lastCall()['url']);
        $this->assertNull($this->headerValue('Bootpay-User-JWT'));
    }

    public function testUserListSendsMembershipTypeAndKeepsMemberTypeAlias()
    {
        // 서버가 읽는 키는 membership_type — member_type 으로 넘겨도 같은 키로 나가야 한다
        $this->api->user->getList(array('membership_type' => 1));
        $this->assertEquals('users?membership_type=1', $this->lastCall()['url']);

        $this->api->user->getList(array('member_type' => 2));
        $this->assertEquals('users?membership_type=2', $this->lastCall()['url']);
    }

    // ---------- 13. BOOTPAY-ROLE scope ----------

    public function testOrderSubscriptionRequestListRoleDependsOnProjectId()
    {
        $this->api->orderSubscriptionRequest->getList(array('project_id' => 'pj-1'));
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));

        $this->api->orderSubscriptionRequest->getList();
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
    }

    public function testOrderSubscriptionRequestDetailRoleDependsOnProjectId()
    {
        $this->api->orderSubscriptionRequest->detail('req-1', 'pj-1');
        $this->assertEquals('order-subscription-requests/req-1?project_id=pj-1', $this->lastCall()['url']);
        $this->assertEquals('supervisor', $this->headerValue('BOOTPAY-ROLE'));

        $this->api->orderSubscriptionRequest->detail('req-1');
        $this->assertEquals('order-subscription-requests/req-1', $this->lastCall()['url']);
        $this->assertEquals('user', $this->headerValue('BOOTPAY-ROLE'));
    }

    public function testStoreLookupsAttachIdempotencyKey()
    {
        $this->api->store->getStore('s-key');
        $this->assertEquals('store', $this->lastCall()['url']);
        $this->assertEquals('s-key', $this->headerValue('Idempotency-Key'));

        $this->api->store->getStoreDetail();
        $this->assertEquals('store/detail', $this->lastCall()['url']);
        $this->assertNotNull($this->headerValue('Idempotency-Key'));

        // 기존 별칭도 동일 동작
        $this->api->store->info();
        $this->assertEquals('store', $this->lastCall()['url']);
        $this->api->store->detail();
        $this->assertEquals('store/detail', $this->lastCall()['url']);
    }
}
