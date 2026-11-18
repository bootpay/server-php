<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

/**
 * V1 OrderSubscription Request 조회/승인 모듈
 *
 * 본인 모드 (user role): project_id 없이 호출 -> 본인 요청 목록/단건
 * 슈퍼바이저 모드 (supervisor role): project_id 포함 -> 프로젝트 전체 + update (승인/거절)
 *
 * 구매자측 요청 생성 (pause/resume/purchase/termination/transfer) 은
 * `orderSubscription.requestIng.*` 모듈을 사용한다.
 *
 * ⚠️ 경로가 order-subscription-requests — 하이픈이다.
 *    order_subscriptions · order_subscription_bills 는 언더스코어라 복사해 고칠 때 가장 흔히 틀리는 지점.
 */
class OrderSubscriptionRequestModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 요청 목록 조회 (user / supervisor 공용)
     * GET /v1/order-subscription-requests
     * project_id 를 주면 supervisor 모드(프로젝트 전체 검색), 없으면 본인 요청만 조회한다.
     * page/limit 미지정시 각각 1 / 20 이 적용된다.
     * @param array|null $params 조회 파라미터 (idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function getList($params = null)
    {
        $params = $params === null ? array() : $params;
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        $projectId = isset($params['project_id']) ? $params['project_id'] : null;

        $query = array();
        if ($projectId !== null && $projectId !== '') {
            $query['project_id'] = $projectId;
        }
        if (isset($params['order_subscription_id'])) {
            $query['order_subscription_id'] = $params['order_subscription_id'];
        }
        $query['page'] = isset($params['page']) ? $params['page'] : 1;
        $query['limit'] = isset($params['limit']) ? $params['limit'] : 20;
        if (isset($params['keyword'])) {
            $query['keyword'] = $params['keyword'];
        }
        if (isset($params['s_at'])) {
            $query['s_at'] = $params['s_at'];
        }
        if (isset($params['e_at'])) {
            $query['e_at'] = $params['e_at'];
        }
        if (isset($params['status'])) {
            $query['status'] = $params['status'];
        }
        if (isset($params['request_type'])) {
            $query['request_type'] = $params['request_type'];
        }
        if (isset($params['user_id'])) {
            $query['user_id'] = $params['user_id'];
        }
        if (isset($params['user_group_id'])) {
            $query['user_group_id'] = $params['user_group_id'];
        }

        $queryString = http_build_query($query);
        return $this->bootpay->get(
            "order-subscription-requests?{$queryString}",
            $this->requestHeaders($projectId, $idempotencyKey)
        );
    }

    /**
     * 요청 단건 조회 (user / supervisor 공용)
     * GET /v1/order-subscription-requests/{id}
     * @param string $orderSubscriptionRequestHistoryId 요청 이력 ID
     * @param string|null $projectId supervisor 모드에서 사용
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function detail($orderSubscriptionRequestHistoryId, $projectId = null, $idempotencyKey = null)
    {
        $query = array();
        if ($projectId !== null && $projectId !== '') {
            $query['project_id'] = $projectId;
        }
        $queryString = empty($query) ? '' : '?' . http_build_query($query);
        return $this->bootpay->get(
            "order-subscription-requests/{$orderSubscriptionRequestHistoryId}" . $queryString,
            $this->requestHeaders($projectId, $idempotencyKey)
        );
    }

    /**
     * 요청 승인/거절 (supervisor 전용)
     * PUT /v1/order-subscription-requests/{id}
     * ⚠️ 승인과 거절은 별도 액션이 아니다. approval: 'approve' | 'reject' 파라미터로 갈린다.
     *    서버가 params[:action] 을 Rails 예약어로 쓰기 때문에 키 이름이 approval 이다.
     * @param array $params 승인/거절 파라미터 (order_subscription_request_history_id 필수 /
     *                      idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     * @throws \Exception
     */
    public function update($params)
    {
        if (!isset($params['order_subscription_request_history_id']) || empty($params['order_subscription_request_history_id'])) {
            throw new \Exception('order_subscription_request_history_id is required');
        }
        $historyId = $params['order_subscription_request_history_id'];
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['order_subscription_request_history_id'], $params['idempotency_key']);
        return $this->bootpay->put(
            "order-subscription-requests/{$historyId}",
            $this->compact($params),
            $this->supervisorHeaders($idempotencyKey)
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
     * 조회 요청 헤더 — project_id 가 있으면 supervisor, 없으면 user scope 다.
     * Idempotency-Key 는 미지정시 매 호출마다 생성된다.
     */
    private function requestHeaders($projectId = null, $idempotencyKey = null)
    {
        $role = ($projectId !== null && $projectId !== '') ? 'supervisor' : 'user';
        return array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: ' . $role
        );
    }

    /**
     * 승인/거절 요청 헤더 — 서버가 supervisor scope 를 요구한다.
     * Idempotency-Key 는 미지정시 매 호출마다 생성된다.
     */
    private function supervisorHeaders($idempotencyKey = null)
    {
        return array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: supervisor'
        );
    }
}
