<?php

namespace Bootpay\ServerPhp\Test\Commerce;

use Bootpay\ServerPhp\Test\RecordingCommerceApi;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../RecordingCommerceApi.php';

/**
 * 입점사 지급 요청 규약 검증 (네트워크 미사용)
 * 경로·동사·supervisor role·멱등키·본문/쿼리 compact·응답 배열을 고정한다.
 */
class PayoutWireFormatTest extends TestCase
{
    private $api;

    protected function setUp(): void
    {
        $this->api = new RecordingCommerceApi('ck', 'sk', 'development');
        $this->api->asManager();
    }

    private function assertPayoutCall($method, $url, $idempotencyKey = null)
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
        return $call;
    }

    public function testCreateUsesPostAndCompactsBody()
    {
        $this->api->payout->create(array(
            'sub_seller_id' => 'ss-1',
            'amount' => 150000,
            'merchant_payout_id' => 'mp-1',
            'order_number' => null,
            'payout_date' => '2026-11-20',
            'idempotency_key' => 'create-key',
            'unknown' => 'drop-me'
        ));

        $call = $this->assertPayoutCall('POST', 'sdk/payouts', 'create-key');
        $this->assertSame(array(
            'sub_seller_id' => 'ss-1',
            'amount' => 150000,
            'merchant_payout_id' => 'mp-1',
            'payout_date' => '2026-11-20'
        ), $call['data']);
    }

    public function testCreateSendsAllOptionalFields()
    {
        $params = array(
            'sub_seller_id' => 'ss-2',
            'amount' => 1,
            'merchant_payout_id' => 'mp-2',
            'order_number' => 'ORDER-2',
            'payout_date' => '2026-11-21'
        );
        $this->api->payout->create($params);

        $call = $this->assertPayoutCall('POST', 'sdk/payouts');
        $this->assertSame($params, $call['data']);
    }

    public function testCreateRequiresContractFields()
    {
        $params = array('sub_seller_id' => 'ss-3', 'amount' => 1000, 'merchant_payout_id' => 'mp-3');
        foreach (array_keys($params) as $key) {
            $missing = $params;
            unset($missing[$key]);
            try {
                $this->api->payout->create($missing);
                $this->fail('Missing required field: ' . $key);
            } catch (\Exception $e) {
                $this->assertSame($key . ' is required', $e->getMessage());
            }
        }
        $this->assertSame(array(), $this->api->calls);
    }

    public function testDetailEncodesTheWholeIdentifierAndAcceptsExplicitKey()
    {
        $this->api->payout->detail('a/b c?d#e한', 'detail-key');

        $call = $this->assertPayoutCall(
            'GET',
            'sdk/payouts/a%2Fb%20c%3Fd%23e%ED%95%9C',
            'detail-key'
        );
        $this->assertNull($call['data']);
    }

    public function testListWithoutParamsHasNoQueryOrInjectedDefaults()
    {
        $this->api->payout->getList();

        $call = $this->assertPayoutCall('GET', 'sdk/payouts');
        $this->assertNull($call['data']);
        $this->assertStringNotContainsString('?', $call['url']);
    }

    public function testListSendsEverySupportedQueryAndExplicitKey()
    {
        $this->api->payout->getList(array(
            'status' => 'ready',
            'sub_seller_id' => 'ss-4',
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'page' => 2,
            'limit' => 50,
            'idempotency_key' => 'list-key',
            'unknown' => 'drop-me'
        ));

        $call = $this->assertPayoutCall(
            'GET',
            'sdk/payouts?status=ready&sub_seller_id=ss-4&from=2026-11-01&to=2026-11-30&page=2&limit=50',
            'list-key'
        );
        $this->assertNull($call['data']);
    }

    public function testListOmitsUnsetAndNullQueries()
    {
        $this->api->payout->getList(array(
            'status' => null,
            'sub_seller_id' => null,
            'from' => null,
            'to' => null,
            'page' => null,
            'limit' => null
        ));

        $this->assertPayoutCall('GET', 'sdk/payouts');
    }

    public function testAutomaticKeysAreDifferentForEveryCall()
    {
        $this->api->payout->create(array(
            'sub_seller_id' => 'ss-5', 'amount' => 1000, 'merchant_payout_id' => 'mp-5'
        ));
        $this->assertPayoutCall('POST', 'sdk/payouts');
        $this->api->payout->detail('mp-5');
        $this->assertPayoutCall('GET', 'sdk/payouts/mp-5');
        $this->api->payout->getList();
        $this->assertPayoutCall('GET', 'sdk/payouts');

        $keys = array();
        foreach ($this->api->calls as $call) {
            $keys[] = RecordingCommerceApi::headerValue($call, 'Idempotency-Key');
        }
        $this->assertCount(3, array_unique($keys));
        $this->assertSame('manager', $this->api->getRole());
    }

    public function testBareListResponseRemainsAnArray()
    {
        $response = array((object)array(
            'id' => 'payout-id',
            'merchant_payout_id' => 'mp-6',
            'sub_seller_id' => 'ss-6',
            'amount' => 1000,
            'status' => 'ready',
            'payout_date' => '2026-11-20',
            'order_number' => null,
            'last_code' => null,
            'last_message' => null,
            'created_at' => '2026-11-12T10:00:00+09:00',
            'updated_at' => '2026-11-12T10:00:00+09:00'
        ));
        $this->api->nextResponse = $response;

        $actual = $this->api->payout->getList();

        $this->assertIsArray($actual);
        $this->assertSame($response, $actual);
    }
}
