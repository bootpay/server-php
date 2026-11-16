<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

class OrderSubscriptionBillModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 정기구독 빌(회차) 목록 조회
     * GET /v1/order_subscription_bills
     * ⚠️ 경로가 order_subscription_bills — 언더스코어다 (하이픈 아님).
     * page/limit 미지정시 각각 1 / 20 이 적용된다.
     * @param array|null $params 조회 파라미터 (idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function getList($params = null)
    {
        $params = $params === null ? array() : $params;
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;

        $query = array();
        if (isset($params['order_subscription_id'])) {
            $query['order_subscription_id'] = $params['order_subscription_id'];
        }
        $query['page'] = isset($params['page']) ? $params['page'] : 1;
        $query['limit'] = isset($params['limit']) ? $params['limit'] : 20;
        if (isset($params['keyword'])) {
            $query['keyword'] = $params['keyword'];
        }
        if (isset($params['status']) && is_array($params['status'])) {
            $query['status'] = implode(',', $params['status']);
        }

        $queryString = http_build_query($query);
        return $this->bootpay->get("order_subscription_bills?{$queryString}", $this->userHeaders($idempotencyKey));
    }

    /**
     * 정기구독 청구 상세 조회
     * @param string $orderSubscriptionBillId 청구 ID
     * @return object
     */
    public function detail($orderSubscriptionBillId)
    {
        return $this->bootpay->get("order_subscription_bills/{$orderSubscriptionBillId}", array('BOOTPAY-ROLE: ' . $this->bootpay->userOrSupervisorRole()));
    }

    /**
     * 정기구독 청구 수정
     * @param array $orderSubscriptionBill 청구 정보
     * @return object
     * @throws \Exception
     */
    public function update($orderSubscriptionBill)
    {
        if (!isset($orderSubscriptionBill['order_subscription_bill_id']) || empty($orderSubscriptionBill['order_subscription_bill_id'])) {
            throw new \Exception('order_subscription_bill_id is required');
        }
        $billId = $orderSubscriptionBill['order_subscription_bill_id'];
        return $this->bootpay->put("order_subscription_bills/{$billId}", $orderSubscriptionBill, array('BOOTPAY-ROLE: supervisor'));
    }

    /**
     * 빌 조회 요청 헤더
     * Idempotency-Key 는 미지정시 매 호출마다 생성된다.
     */
    private function userHeaders($idempotencyKey = null)
    {
        return array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: ' . $this->bootpay->userOrSupervisorRole()
        );
    }
}
