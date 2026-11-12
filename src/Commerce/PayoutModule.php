<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

/**
 * 입점사 지급 요청 — /v1/sdk/payouts 계열
 *
 * 가맹점 지정 금액(manual) 정산 방식의 쇼핑몰이 입점사와 지급액을 지급 원장에 등록한다.
 * 실제 송금은 부트페이 배치가 처리한다. 전부 supervisor 전용이며 인스턴스 role 은 바꾸지 않는다.
 */
class PayoutModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 지급 요청 등록
     * POST /v1/sdk/payouts
     * 같은 merchant_payout_id 로 재호출하면 처음 만든 항목을 돌려준다.
     * @param array $params sub_seller_id(string) / amount(integer) / merchant_payout_id(string) 필수
     *                      order_number / payout_date(YYYY-MM-DD) 선택
     *                      idempotency_key 는 Idempotency-Key 헤더로 전송된다
     * @return object API 응답; id, merchant_payout_id, sub_seller_id, amount, status, payout_date,
     *                order_number, last_code, last_message, created_at, updated_at 필드의 지급 항목
     *                status: ready / held / sending / requested / processing / succeeded / failed / cancelled / unknown / void
     *                order_number / last_code / last_message 는 null 일 수 있다
     * @throws \Exception
     */
    public function create($params)
    {
        foreach (array('sub_seller_id', 'amount', 'merchant_payout_id') as $key) {
            if (!is_array($params) || !isset($params[$key]) || $params[$key] === '') {
                throw new \Exception("{$key} is required");
            }
        }
        $payload = $this->pick($params, array(
            'sub_seller_id', 'amount', 'merchant_payout_id', 'order_number', 'payout_date'
        ));
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;

        return $this->bootpay->post('sdk/payouts', $payload, $this->supervisorHeaders($idempotencyKey));
    }

    /**
     * 지급 항목 조회
     * GET /v1/sdk/payouts/{id}
     * @param string $payoutId merchant_payout_id 또는 지급 항목 id
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object API 응답; create 응답과 같은 지급 항목
     */
    public function detail($payoutId, $idempotencyKey = null)
    {
        return $this->bootpay->get(
            'sdk/payouts/' . rawurlencode($payoutId),
            $this->supervisorHeaders($idempotencyKey)
        );
    }

    /**
     * 지급 목록 조회 — 응답 data 가 아니라 API 원문의 지급 항목 배열을 그대로 반환한다.
     * GET /v1/sdk/payouts
     * @param array|null $params status / sub_seller_id / from / to / page / limit 선택
     *                           idempotency_key 는 Idempotency-Key 헤더로 전송된다
     * @return array 지급 항목 배열; 각 항목은 create 응답과 같은 형식
     */
    public function getList($params = null)
    {
        $params = $params === null ? array() : $params;
        $query = $this->pick($params, array('status', 'sub_seller_id', 'from', 'to', 'page', 'limit'));
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;

        return $this->bootpay->get(
            'sdk/payouts' . $this->buildQueryString($query),
            $this->supervisorHeaders($idempotencyKey)
        );
    }

    /**
     * supervisor scope(payout_*) 고정. Idempotency-Key 는 미지정시 매 호출마다 생성한다.
     */
    private function supervisorHeaders($idempotencyKey = null)
    {
        return array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: supervisor'
        );
    }

    /**
     * 화이트리스트 키만 뽑고 null 은 제외한다. false / 0 / '' 는 그대로 보낸다.
     */
    private function pick($params, $keys)
    {
        $picked = array();
        foreach ($keys as $key) {
            if (is_array($params) && array_key_exists($key, $params) && $params[$key] !== null) {
                $picked[$key] = $params[$key];
            }
        }
        return $picked;
    }

    private function buildQueryString($query)
    {
        return empty($query) ? '' : '?' . http_build_query($query);
    }
}
