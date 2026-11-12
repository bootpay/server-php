<?php

namespace Bootpay\ServerPhp\Test\Commerce;

use Bootpay\ServerPhp\Commerce\DepositModule;
use Bootpay\ServerPhp\Test\RecordingCommerceApi;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../RecordingCommerceApi.php';

/**
 * NodeJS 2.15.0 parity — 뱅크알람 입금대기 요청 규약 검증 (네트워크 미사용)
 * supervisor 고정·멱등키·본문 compact·가맹점 주문번호 인코딩을 고정한다.
 */
class DepositWireFormatTest extends TestCase
{
    private $api;

    protected function setUp(): void
    {
        $this->api = new RecordingCommerceApi('ck', 'sk', 'development');
        $this->api->asManager();
    }

    private function assertDepositCall($method, $url, $idempotencyKey = null)
    {
        $call = $this->api->lastCall();
        $this->assertSame($method, $call['method']);
        $this->assertSame($url, $call['url']);
        $this->assertSame('supervisor', RecordingCommerceApi::headerValue($call, 'BOOTPAY-ROLE'));
        $this->assertSame('manager', $this->api->getRole());
        $key = RecordingCommerceApi::headerValue($call, 'Idempotency-Key');
        if ($idempotencyKey === null) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $key);
        } else {
            $this->assertSame($idempotencyKey, $key);
        }
        $entrypoints = new \ReflectionMethod(\Bootpay\ServerPhp\BootpayCommerceApi::class, 'entrypoints');
        if (PHP_VERSION_ID < 80100) {
            $entrypoints->setAccessible(true);
        }
        $this->assertSame('https://dev-api.bootapi.com/v1/' . $url, $entrypoints->invoke($this->api, $url));
        return $call;
    }

    public function testAccounts()
    {
        $this->api->deposit->accounts();
        $call = $this->assertDepositCall('GET', 'sdk/deposits/accounts');
        $this->assertNull($call['data']);
    }

    public function testWebhookSecretSendsEmptyObjectAndSpecifiedKey()
    {
        $this->api->deposit->webhookSecret('secret-key');
        $call = $this->assertDepositCall('POST', 'sdk/deposits/webhook_secret', 'secret-key');
        $this->assertSame('{}', json_encode($call['data']));
    }

    public function testCreateCompactsBodyAndPreservesFalse()
    {
        $this->api->deposit->create(array(
            'merchant_order_id' => 'm-1', 'price' => 15000, 'depositor' => '홍길동',
            'account_id' => 'acc1', 'buyer_email' => null, 'memo' => null,
            'notify' => array('deposit_guide' => true, 'deposit_paid' => false, 'link' => 'https://example.com/o/m-1'),
            'idempotency_key' => 'idem-deposit', 'unknown' => 'drop-me'
        ));
        $call = $this->assertDepositCall('POST', 'sdk/deposits', 'idem-deposit');
        $this->assertSame(array(
            'merchant_order_id' => 'm-1', 'price' => 15000, 'depositor' => '홍길동', 'account_id' => 'acc1',
            'notify' => array('deposit_guide' => true, 'deposit_paid' => false, 'link' => 'https://example.com/o/m-1')
        ), $call['data']);
    }

    public function testCreateSendsAllOptionalFields()
    {
        $params = array(
            'merchant_order_id' => 'm-2', 'price' => 0, 'depositor' => '홍길동',
            'order_name' => '입금 주문', 'memo' => '', 'buyer_name' => '구매자',
            'buyer_phone' => '01012345678', 'buyer_email' => 'buyer@example.com',
            'account_id' => 'acc1', 'webhook_url' => 'https://example.com/hooks/deposit',
            'notify' => array('deposit_guide' => false, 'deposit_paid' => false)
        );
        $this->api->deposit->create($params);
        $call = $this->assertDepositCall('POST', 'sdk/deposits');
        $this->assertSame($params, $call['data']);
    }

    public function testCreateOmitsUnspecifiedAndNullFields()
    {
        $params = array('merchant_order_id' => 'm-3', 'price' => 1000, 'depositor' => '홍길동');
        $this->api->deposit->create(array_merge($params, array(
            'order_name' => null, 'memo' => null, 'buyer_name' => null, 'buyer_phone' => null,
            'buyer_email' => null, 'account_id' => null, 'webhook_url' => null, 'notify' => null
        )));
        $call = $this->assertDepositCall('POST', 'sdk/deposits');
        $this->assertSame($params, $call['data']);
    }

    public function testNotifyCompactsNullAndKeepsFalse()
    {
        $this->api->deposit->create(array(
            'merchant_order_id' => 'm-4', 'price' => 1000, 'depositor' => '홍길동',
            'notify' => array('deposit_guide' => null, 'deposit_paid' => false, 'link' => null)
        ));
        $this->assertSame(array('deposit_paid' => false), $this->api->lastCall()['data']['notify']);
    }

    public function testEmptyNotifyIsAnObject()
    {
        $this->api->deposit->create(array(
            'merchant_order_id' => 'm-5', 'price' => 1000, 'depositor' => '홍길동',
            'notify' => array('deposit_guide' => null)
        ));
        $this->assertSame('{}', json_encode($this->api->lastCall()['data']['notify']));
    }

    public function testCreateRequiresMerchantOrderIdPriceAndDepositor()
    {
        $params = array('merchant_order_id' => 'm-6', 'price' => 1000, 'depositor' => '홍길동');
        foreach (array_keys($params) as $key) {
            $missing = $params;
            unset($missing[$key]);
            try {
                $this->api->deposit->create($missing);
                $this->fail('Missing required field: ' . $key);
            } catch (\Exception $e) {
                $this->assertSame($key . ' is required', $e->getMessage());
            }
        }
        $this->assertSame(array(), $this->api->calls);
    }

    public function testDetailEncodesMerchantOrderId()
    {
        $this->api->deposit->detail('a/b');
        $call = $this->assertDepositCall('GET', 'sdk/deposits/a%2Fb');
        $this->assertNull($call['data']);
    }

    public function testCancelEncodesMerchantOrderIdAndSendsSpecifiedKey()
    {
        $this->api->deposit->cancel('a/b', 'cancel-key');
        $call = $this->assertDepositCall('DELETE', 'sdk/deposits/a%2Fb', 'cancel-key');
        $this->assertNull($call['data']);
    }

    public function testEveryCallGeneratesANewKey()
    {
        $keys = array();
        for ($i = 0; $i < 2; $i++) {
            $this->api->deposit->accounts();
            $this->assertDepositCall('GET', 'sdk/deposits/accounts');
            $this->api->deposit->webhookSecret();
            $this->assertDepositCall('POST', 'sdk/deposits/webhook_secret');
            $this->api->deposit->create(array('merchant_order_id' => 'm-7', 'price' => 1000, 'depositor' => '홍길동'));
            $this->assertDepositCall('POST', 'sdk/deposits');
            $this->api->deposit->detail('m-7');
            $this->assertDepositCall('GET', 'sdk/deposits/m-7');
            $this->api->deposit->cancel('m-7');
            $this->assertDepositCall('DELETE', 'sdk/deposits/m-7');
        }
        foreach ($this->api->calls as $call) {
            $keys[] = RecordingCommerceApi::headerValue($call, 'Idempotency-Key');
        }
        $this->assertCount(10, array_unique($keys));
    }

    public function testFinalHeadersOverrideManagerAndMatchPackageVersion()
    {
        $this->api->deposit->accounts();
        $createHeaders = new \ReflectionMethod(\Bootpay\ServerPhp\BootpayCommerceApi::class, 'createHeaders');
        if (PHP_VERSION_ID < 80100) {
            $createHeaders->setAccessible(true);
        }
        $headers = $createHeaders->invoke($this->api, $this->api->lastCall()['headers']);
        $roleHeaders = array_values(array_filter($headers, function ($header) {
            return stripos($header, 'BOOTPAY-ROLE:') === 0;
        }));
        $this->assertSame(array('BOOTPAY-ROLE: supervisor'), $roleHeaders);
        $composer = json_decode(file_get_contents(__DIR__ . '/../../composer.json'), true);
        $this->assertSame($composer['version'], RecordingCommerceApi::headerValue(array('headers' => $headers), 'BOOTPAY-SDK-VERSION'));
        $this->assertSame('manager', $this->api->getRole());
    }

    public function testResponsesPassThroughUnchanged()
    {
        $response = (object)array('merchant_order_id' => 'm-8', 'status' => 'awaiting');
        $this->api->nextResponse = $response;
        $this->assertSame($response, $this->api->deposit->accounts());
        $this->assertSame($response, $this->api->deposit->webhookSecret());
        $this->assertSame($response, $this->api->deposit->create(array('merchant_order_id' => 'm-8', 'price' => 1000, 'depositor' => '홍길동')));
        $this->assertSame($response, $this->api->deposit->detail('m-8'));
        $this->assertSame($response, $this->api->deposit->cancel('m-8'));
    }

    public function testWebhookEventConstants()
    {
        $this->assertSame(array(
            'BANK_DEPOSIT_WEBHOOK_EVENT_RECEIVED' => 400,
            'BANK_DEPOSIT_WEBHOOK_EVENT_MATCHED' => 401,
            'BANK_DEPOSIT_WEBHOOK_EVENT_AMBIGUOUS' => 402,
            'BANK_DEPOSIT_WEBHOOK_EVENT_UNMATCHED' => 403,
            'BANK_DEPOSIT_WEBHOOK_EVENT_CONFIRMED_MANUALLY' => 404,
            'BANK_DEPOSIT_WEBHOOK_EVENT_EXPIRED' => 405,
            'BANK_DEPOSIT_WEBHOOK_EVENT_CANCELLED' => 406,
            'BANK_DEPOSIT_WEBHOOK_EVENT_REVERTED' => 407
        ), (new \ReflectionClass(DepositModule::class))->getConstants());
    }
}
