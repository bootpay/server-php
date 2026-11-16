<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

class OrderModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 주문 목록 조회
     * ⚠️ 날짜 키는 search_date_from/to 다 (서버는 css_at/cse_at 도 별칭으로 받는다).
     * status / payment_status / order_subscription_ids 는 배열·문자열을 모두 받아 콤마로 이어 보내고,
     * 값이 비면 아예 보내지 않는다 (status= 같은 빈 값은 서버가 무시하지만 노이즈다).
     * @param array|null $params 조회 파라미터 (page/limit/keyword/user_id/user_group_id/cs_type/
     *                           search_date_from/search_date_to/status/payment_status/
     *                           order_subscription_ids/subscription_billing_type)
     * @return object
     */
    public function getList($params = null)
    {
        $queryString = $this->buildQueryString($params);
        return $this->bootpay->get('orders' . $queryString, array('BOOTPAY-ROLE: user'));
    }

    /**
     * 주문 상세 조회
     * @param string $orderId 주문 ID
     * @return object
     */
    public function detail($orderId)
    {
        return $this->bootpay->get("orders/{$orderId}", array('BOOTPAY-ROLE: user'));
    }

    /**
     * 월별 주문 조회
     * @param string $userGroupId 사용자 그룹 ID
     * @param string $searchDate 검색 날짜 (YYYY-MM 형식)
     * @return object
     */
    public function month($userGroupId, $searchDate)
    {
        $query = http_build_query(array(
            'user_group_id' => $userGroupId,
            'search_date' => $searchDate
        ));
        return $this->bootpay->get("orders/month?{$query}", array('BOOTPAY-ROLE: user'));
    }

    /**
     * 발송 정보 조회 — 발주(배송) 단위 운송장·택배사·배송추적 상태·이력
     * GET /v1/orders/{order_number}/purchases
     * 주문번호 기준(order_id 아님). BOOTPAY-ROLE 은 user 로 고정한다.
     * @param string $orderNumber 주문번호
     * @param string|null $idempotencyKey 멱등키 (미지정시 자동 생성)
     * @return object
     */
    public function purchases($orderNumber, $idempotencyKey = null)
    {
        $url = 'orders/' . rawurlencode($orderNumber) . '/purchases';
        return $this->bootpay->get($url, $this->userHeaders($idempotencyKey));
    }

    /**
     * 발송처리 — 발주 단위 상태·운송장 갱신
     * PUT /v1/orders/{order_number}/purchases
     * 경로 주문에 딸리지 않은 order_purchase_number 가 섞이면 요청 전체가 ORDER_PURCHASE_NOT_FOUND 로 거절된다.
     * 한 번에 최대 50건. 주문번호·멱등키는 본문에 싣지 않는다.
     * @param string $orderNumber 주문번호
     * @param array $purchases 발주 배열. 각 항목: order_purchase_number(필수), status(필수, int),
     *                         chosen_product_option_id / delivery_type / delivery_company_code / tracking_number(선택)
     * @param string|null $idempotencyKey 멱등키 (미지정시 자동 생성)
     * @return object
     */
    public function updatePurchases($orderNumber, $purchases, $idempotencyKey = null)
    {
        $url = 'orders/' . rawurlencode($orderNumber) . '/purchases';
        return $this->bootpay->put($url, array('purchases' => $purchases), $this->userHeaders($idempotencyKey));
    }

    /**
     * 발송 정보 요청 헤더 — BOOTPAY-ROLE 은 user 로 고정, Idempotency-Key 는 미지정시 자동 생성
     */
    private function userHeaders($idempotencyKey = null)
    {
        return array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: user'
        );
    }

    /**
     * 배열 또는 단일 값을 콤마 문자열로 잇는다. 값이 비어 있으면 null 을 돌려 쿼리에서 빠지게 한다.
     */
    private function joinList($value)
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            $value = array($value);
        }
        $joined = implode(',', $value);
        return $joined === '' ? null : $joined;
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
        if (isset($params['user_id'])) {
            $query['user_id'] = $params['user_id'];
        }
        if (isset($params['user_group_id'])) {
            $query['user_group_id'] = $params['user_group_id'];
        }
        if (isset($params['cs_type'])) {
            $query['cs_type'] = $params['cs_type'];
        }
        if (isset($params['search_date_from'])) {
            $query['search_date_from'] = $params['search_date_from'];
        }
        if (isset($params['search_date_to'])) {
            $query['search_date_to'] = $params['search_date_to'];
        }
        if (isset($params['css_at'])) {
            $query['css_at'] = $params['css_at'];
        }
        if (isset($params['cse_at'])) {
            $query['cse_at'] = $params['cse_at'];
        }
        if (isset($params['subscription_billing_type'])) {
            $query['subscription_billing_type'] = $params['subscription_billing_type'];
        }
        $status = $this->joinList(isset($params['status']) ? $params['status'] : null);
        if ($status !== null) {
            $query['status'] = $status;
        }
        $paymentStatus = $this->joinList(isset($params['payment_status']) ? $params['payment_status'] : null);
        if ($paymentStatus !== null) {
            $query['payment_status'] = $paymentStatus;
        }
        $orderSubscriptionIds = $this->joinList(
            isset($params['order_subscription_ids']) ? $params['order_subscription_ids'] : null
        );
        if ($orderSubscriptionIds !== null) {
            $query['order_subscription_ids'] = $orderSubscriptionIds;
        }

        if (empty($query)) {
            return '';
        }

        return '?' . http_build_query($query);
    }
}
