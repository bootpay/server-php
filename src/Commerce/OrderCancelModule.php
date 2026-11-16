<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

class OrderCancelModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 취소 요청 목록 조회
     * GET /v1/order/cancel
     * order_number 또는 order_id 로 필터한다. 둘 다 없으면 전체.
     * approve / reject / withdraw 에 넘길 order_cancellation_request_id 를 여기서 얻는다.
     * @param array|null $params 조회 파라미터 (idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function getList($params = null)
    {
        $params = $params === null ? array() : $params;
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        $queryString = $this->buildQueryString($params);
        return $this->bootpay->get('order/cancel' . $queryString, $this->userHeaders($idempotencyKey));
    }

    /**
     * 취소 요청
     * @param array $params 취소 요청 파라미터
     * @return object
     */
    public function request($params)
    {
        return $this->bootpay->post('order/cancel', $params, array('BOOTPAY-ROLE: ' . $this->bootpay->userOrSupervisorRole()));
    }

    /**
     * (구매자) 취소 요청 철회
     * PUT /v1/order/cancel/{order_cancellation_request_id}/withdraw
     * @param array|string $params 취소 요청 이력 ID (문자열로 바로 넘겨도 된다.
     *                             배열이면 order_cancellation_request_id — 구 이름 order_cancel_request_history_id 도 지원)
     * @return object
     * @throws \Exception
     */
    public function withdraw($params)
    {
        $normalized = is_string($params) ? array('order_cancellation_request_id' => $params) : $params;
        $cancellationId = $this->cancellationId($normalized);
        if (empty($cancellationId)) {
            throw new \Exception('order_cancellation_request_id is required');
        }
        $idempotencyKey = isset($normalized['idempotency_key']) ? $normalized['idempotency_key'] : null;
        return $this->bootpay->put(
            "order/cancel/{$cancellationId}/withdraw",
            new \stdClass(),
            $this->userHeaders($idempotencyKey)
        );
    }

    /**
     * (관리자) 취소 승인
     * PUT /v1/order/cancel/{order_cancellation_request_id}/approve
     * @param array $params 취소 승인 파라미터 (order_cancellation_request_id 필수 —
     *                      구 이름 order_cancel_request_history_id 도 지원. 서버가 읽는 body 값은 message 다)
     * @return object
     * @throws \Exception
     */
    public function approve($params)
    {
        $cancellationId = $this->cancellationId($params);
        if (empty($cancellationId)) {
            throw new \Exception('order_cancellation_request_id is required');
        }
        return $this->bootpay->put(
            "order/cancel/{$cancellationId}/approve",
            $this->actionPayload($params),
            $this->supervisorHeaders(isset($params['idempotency_key']) ? $params['idempotency_key'] : null)
        );
    }

    /**
     * (관리자) 취소 거절
     * PUT /v1/order/cancel/{order_cancellation_request_id}/reject
     * @param array $params 취소 거절 파라미터 (order_cancellation_request_id 필수 —
     *                      구 이름 order_cancel_request_history_id 도 지원. 서버가 읽는 body 값은 message 다)
     * @return object
     * @throws \Exception
     */
    public function reject($params)
    {
        $cancellationId = $this->cancellationId($params);
        if (empty($cancellationId)) {
            throw new \Exception('order_cancellation_request_id is required');
        }
        return $this->bootpay->put(
            "order/cancel/{$cancellationId}/reject",
            $this->actionPayload($params),
            $this->supervisorHeaders(isset($params['idempotency_key']) ? $params['idempotency_key'] : null)
        );
    }

    /**
     * 취소 요청 이력 ID 를 뽑는다.
     * 정식 이름은 order_cancellation_request_id 이며, 구 이름 order_cancel_request_history_id 도 계속 받는다.
     */
    private function cancellationId($params)
    {
        if (isset($params['order_cancellation_request_id']) && !empty($params['order_cancellation_request_id'])) {
            return $params['order_cancellation_request_id'];
        }
        if (isset($params['order_cancel_request_history_id']) && !empty($params['order_cancel_request_history_id'])) {
            return $params['order_cancel_request_history_id'];
        }
        return null;
    }

    /**
     * 승인/거절 payload — ID/idempotency_key 를 제외하고 null 값을 제거한다.
     */
    private function actionPayload($params)
    {
        unset(
            $params['order_cancellation_request_id'],
            $params['order_cancel_request_history_id'],
            $params['idempotency_key']
        );
        $result = array();
        foreach ((array)$params as $key => $value) {
            if ($value !== null) {
                $result[$key] = $value;
            }
        }
        // 빈 배열은 JSON 인코딩 시 [] 가 되므로 {} 로 전송되도록 객체로 변환
        return empty($result) ? new \stdClass() : $result;
    }

    /**
     * 구매자 scope 요청 헤더
     * Idempotency-Key 는 미지정시 매 호출마다 생성된다.
     */
    private function userHeaders($idempotencyKey = null)
    {
        return array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: user'
        );
    }

    /**
     * 관리자(승인/거절) scope 요청 헤더
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
        if (isset($params['order_id'])) {
            $query['order_id'] = $params['order_id'];
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
