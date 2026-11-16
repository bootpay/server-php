<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

class OrderSubscriptionModule
{
    private $bootpay;
    public $requestIng;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
        $this->requestIng = new OrderSubscriptionRequestIngModule($bootpay);
    }

    /**
     * 정기구독 목록 조회
     * ⚠️ 날짜 키는 search_date_from/to (또는 s_at/e_at) 다 — order->getList 의 css_at/cse_at 와 다르다.
     * order_number 로 주문번호 역조회도 된다.
     * @param array|null $params 조회 파라미터 (page/limit/keyword/search_date_from/search_date_to/
     *                           s_at/e_at/request_type/user_group_id/status/user_id/order_number)
     * @return object
     */
    public function getList($params = null)
    {
        $queryString = $this->buildQueryString($params);
        return $this->bootpay->get('order_subscriptions' . $queryString, array('BOOTPAY-ROLE: user'));
    }

    /**
     * 정기구독 상세 조회
     * @param string $orderSubscriptionId 정기구독 ID
     * @return object
     */
    public function detail($orderSubscriptionId)
    {
        return $this->bootpay->get("order_subscriptions/{$orderSubscriptionId}", array('BOOTPAY-ROLE: user'));
    }

    /**
     * 구독 계약 내용 변경 (supervisor 전용)
     * PUT /v1/order_subscriptions/{order_subscription_id}
     * 바뀐 값만 보내면 된다 (나머지는 서버가 그대로 유지한다).
     *
     * price 는 회차별 결제 금액의 **기준금액**이다. 바꾸면 결제예정(READY) 회차의 청구액이
     * 즉시 다시 계산되고, 이후 회차도 이 금액으로 만들어진다. 이미 결제된 회차는 그대로다.
     * 0 이하는 받지 않는다. 특정 회차만 가감하려면 orderSubscriptionAdjustment->create 를 쓴다.
     * (관리자 화면의 금액 변경과 같은 구현을 탄다)
     *
     * memo 는 변경이력(SUBSCRIPTION_ACTION_UPDATE)에 남길 사유다.
     *
     * @param array $params 수정 파라미터 (product_id/product_option_id/order_name/total_subscription_duration/
     *                      quantity/address_id/username/phone/email/use_free_trial/free_trial_day/
     *                      service_start_at/service_end_at/price/memo
     *                      / idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     * @throws \Exception
     */
    public function update($params)
    {
        if (!isset($params['order_subscription_id']) || empty($params['order_subscription_id'])) {
            throw new \Exception('order_subscription_id is required');
        }
        $orderSubscriptionId = $params['order_subscription_id'];
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['order_subscription_id'], $params['idempotency_key']);
        return $this->bootpay->put(
            "order_subscriptions/{$orderSubscriptionId}",
            $this->compact($params),
            $this->supervisorHeaders($idempotencyKey)
        );
    }

    /**
     * 관리자 정기구독 승인
     * PUT /v1/order_subscriptions/{order_subscription_id}/approve
     * ⚠️ 서버가 supervisor scope 를 요구한다 (scope_invalid!).
     * @param string $orderSubscriptionId 정기구독 ID
     * @param array $params 승인 파라미터 (idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function supervisorApprove($orderSubscriptionId, $params = array())
    {
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['idempotency_key']);
        return $this->bootpay->put(
            "order_subscriptions/{$orderSubscriptionId}/approve",
            $this->compact($params),
            $this->supervisorHeaders($idempotencyKey)
        );
    }

    /**
     * 관리자 정기구독 거절
     * PUT /v1/order_subscriptions/{order_subscription_id}/reject
     * ⚠️ 서버가 supervisor scope 를 요구한다 (scope_invalid!).
     * @param string $orderSubscriptionId 정기구독 ID
     * @param array $params 거절 파라미터 (idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function supervisorReject($orderSubscriptionId, $params = array())
    {
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['idempotency_key']);
        return $this->bootpay->put(
            "order_subscriptions/{$orderSubscriptionId}/reject",
            $this->compact($params),
            $this->supervisorHeaders($idempotencyKey)
        );
    }

    /**
     * 관리자 정기구독 해지
     * PUT /v1/order_subscriptions/{order_subscription_id}/terminate
     * ⚠️ 서버가 supervisor scope 를 요구한다 (scope_invalid!).
     * @param string $orderSubscriptionId 정기구독 ID
     * @param array $params 해지 파라미터 (idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function supervisorTerminate($orderSubscriptionId, $params = array())
    {
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['idempotency_key']);
        return $this->bootpay->put(
            "order_subscriptions/{$orderSubscriptionId}/terminate",
            $this->compact($params),
            $this->supervisorHeaders($idempotencyKey)
        );
    }

    /**
     * 관리자 정기구독 일시정지
     * PUT /v1/order_subscriptions/{order_subscription_id}/pause
     * ⚠️ 서버가 supervisor scope 를 요구한다 (scope_invalid!).
     * @param string $orderSubscriptionId 정기구독 ID
     * @param array $params 일시정지 파라미터 (idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function supervisorPause($orderSubscriptionId, $params = array())
    {
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['idempotency_key']);
        return $this->bootpay->put(
            "order_subscriptions/{$orderSubscriptionId}/pause",
            $this->compact($params),
            $this->supervisorHeaders($idempotencyKey)
        );
    }

    /**
     * 관리자 정기구독 재개
     * PUT /v1/order_subscriptions/{order_subscription_id}/resume
     * ⚠️ 서버가 supervisor scope 를 요구한다 (scope_invalid!).
     * @param string $orderSubscriptionId 정기구독 ID
     * @param array $params 재개 파라미터 (idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function supervisorResume($orderSubscriptionId, $params = array())
    {
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['idempotency_key']);
        return $this->bootpay->put(
            "order_subscriptions/{$orderSubscriptionId}/resume",
            $this->compact($params),
            $this->supervisorHeaders($idempotencyKey)
        );
    }

    /**
     * 수시결제(온디맨드) charge_key 즉시 결제 (supervisor 전용)
     * POST /v1/order_subscriptions/charge
     * ⚠️ charge_key 는 body 로만 전송한다 (URL/query 금지 — 액세스 로그 노출 방지)
     * @param array $params 결제 파라미터 (charge_key, price 필수 / idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function supervisorCharge($params)
    {
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['idempotency_key']);
        return $this->bootpay->post(
            'order_subscriptions/charge',
            $this->compact($params),
            $this->supervisorHeaders($idempotencyKey)
        );
    }

    /**
     * 수시결제(온디맨드) charge_key 해지 (supervisor 전용)
     * DELETE /v1/order_subscriptions/charge
     * 해지 이후 해당 키로의 재결제는 불가능하다.
     * ⚠️ charge_key 는 body 로만 전송한다 (URL/query 금지)
     * @param array $params 해지 파라미터 (charge_key 필수 / idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function supervisorChargeRevoke($params)
    {
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['idempotency_key']);
        return $this->bootpay->delete(
            'order_subscriptions/charge',
            $this->supervisorHeaders($idempotencyKey),
            $this->compact($params)
        );
    }

    /**
     * null 값을 제거한다. (NodeJS SDK 의 compact 와 동일 동작)
     */
    private function compact($payload)
    {
        $result = array();
        foreach ((array)$payload as $key => $value) {
            if ($value !== null) {
                $result[$key] = $value;
            }
        }
        // 빈 배열은 JSON 인코딩 시 [] 가 되므로 {} 로 전송되도록 객체로 변환
        return empty($result) ? new \stdClass() : $result;
    }

    /**
     * supervisor 전용 요청 헤더
     * Idempotency-Key 는 미지정시 매 호출마다 생성된다.
     */
    private function supervisorHeaders($idempotencyKey = null)
    {
        return array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: supervisor'
        );
    }

    private function buildQueryString($params)
    {
        if ($params === null || empty($params)) {
            return '';
        }

        $query = array();
        if (isset($params['page'])) {
            $query['page'] = $params['page'];
        }
        if (isset($params['limit'])) {
            $query['limit'] = $params['limit'];
        }
        if (isset($params['keyword'])) {
            $query['keyword'] = $params['keyword'];
        }
        if (isset($params['search_date_from'])) {
            $query['search_date_from'] = $params['search_date_from'];
        }
        if (isset($params['search_date_to'])) {
            $query['search_date_to'] = $params['search_date_to'];
        }
        if (isset($params['s_at'])) {
            $query['s_at'] = $params['s_at'];
        }
        if (isset($params['e_at'])) {
            $query['e_at'] = $params['e_at'];
        }
        if (isset($params['request_type'])) {
            $query['request_type'] = $params['request_type'];
        }
        if (isset($params['user_group_id'])) {
            $query['user_group_id'] = $params['user_group_id'];
        }
        if (isset($params['status'])) {
            $query['status'] = $params['status'];
        }
        if (isset($params['user_id'])) {
            $query['user_id'] = $params['user_id'];
        }
        if (isset($params['order_number'])) {
            $query['order_number'] = $params['order_number'];
        }

        if (empty($query)) {
            return '';
        }

        return '?' . http_build_query($query);
    }
}
