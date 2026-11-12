<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

/**
 * 뱅크알람 입금대기 — /v1/sdk/deposits 계열
 *
 * 입금 알림의 금액·입금자명이 맞는 주문에 입금을 확인하고 입금 웹훅으로 알린다.
 * 전부 supervisor 전용이며, 인스턴스 role 과 무관하게 요청별 헤더로 지정한다.
 * 응답은 기존 커머스 API 와 같은 객체로 반환한다.
 */
class DepositModule
{
    const BANK_DEPOSIT_WEBHOOK_EVENT_RECEIVED = 400;
    const BANK_DEPOSIT_WEBHOOK_EVENT_MATCHED = 401;
    const BANK_DEPOSIT_WEBHOOK_EVENT_AMBIGUOUS = 402;
    const BANK_DEPOSIT_WEBHOOK_EVENT_UNMATCHED = 403;
    const BANK_DEPOSIT_WEBHOOK_EVENT_CONFIRMED_MANUALLY = 404;
    const BANK_DEPOSIT_WEBHOOK_EVENT_EXPIRED = 405;
    const BANK_DEPOSIT_WEBHOOK_EVENT_CANCELLED = 406;
    const BANK_DEPOSIT_WEBHOOK_EVENT_REVERTED = 407;

    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 판매자 입금 계좌 목록
     * GET /v1/sdk/deposits/accounts
     * @return object API 응답; data.accounts: [{id, bank_name, account_no, holder, primary}] — 첫 계좌가 대표 계좌다
     */
    public function accounts()
    {
        return $this->bootpay->get('sdk/deposits/accounts', $this->supervisorHeaders());
    }

    /**
     * 웹훅 서명 비밀값 발급
     * POST /v1/sdk/deposits/webhook_secret
     * ⚠️ 원문은 이 응답에서만 준다. 다시 부르면 바뀐다(이미 만든 웹훅은 이전 비밀값으로 서명된다).
     * X-Bootpay-Signature: sha256=HMAC_SHA256(secret, "{X-Bootpay-Timestamp}.{raw_body}")
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object API 응답; data.secret: 웹훅 서명 비밀값
     */
    public function webhookSecret($idempotencyKey = null)
    {
        return $this->bootpay->post('sdk/deposits/webhook_secret', new \stdClass(), $this->supervisorHeaders($idempotencyKey));
    }

    /**
     * 입금대기 등록
     * POST /v1/sdk/deposits
     * 같은 merchant_order_id 로 금액·입금자명·계좌가 같은 재호출은 기존 주문을 돌려준다(하나라도 다르면 18110).
     * webhook_url 은 비밀값 발급이 먼저 필요하다(18131). notify 는 유료이며 준비되지 않으면 18133.
     * @param array $params merchant_order_id(string) / price(number) / depositor(string) 필수
     *                      order_name / memo / buyer_name / buyer_phone / buyer_email / account_id / webhook_url 선택
     *                      notify: {deposit_guide(bool), deposit_paid(bool), link(https)}
     *                      idempotency_key 는 Idempotency-Key 헤더로 전송된다
     * @return object API 응답; data: 입금대기 주문 (merchant_order_id, order_number, order_name, amount, status,
     *                buyer_name, depositor, account, deadline, deadline_label, paid_at, notify, deposit)
     *                status: awaiting / paid / expired / cancelled.
     *                account: {id, bank_name, account_no, holder, primary?} 또는 null.
     *                notify: {deposit_guide, deposit_paid} 또는 null.
     *                deposit: {key, bank_name, account_tail, depositor, amount, received_at} 또는 null (웹훅 전용).
     * @throws \Exception
     */
    public function create($params)
    {
        foreach (array('merchant_order_id', 'price', 'depositor') as $key) {
            if (!is_array($params) || !isset($params[$key]) || $params[$key] === '') {
                throw new \Exception("{$key} is required");
            }
        }
        $payload = $this->pick($params, array(
            'merchant_order_id', 'price', 'depositor', 'order_name', 'memo',
            'buyer_name', 'buyer_phone', 'buyer_email', 'account_id', 'webhook_url', 'notify'
        ));
        if (isset($payload['notify'])) {
            $notify = $this->pick($payload['notify'], array('deposit_guide', 'deposit_paid', 'link'));
            $payload['notify'] = empty($notify) ? new \stdClass() : $notify;
        }
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;

        return $this->bootpay->post('sdk/deposits', $payload, $this->supervisorHeaders($idempotencyKey));
    }

    /**
     * 입금대기 조회
     * GET /v1/sdk/deposits/{id}
     * 기한이 지났으면 조회 시점에 만료(expired)로 닫힌다.
     * @param string $depositId 가맹점 주문번호 또는 부트페이 주문번호
     * @return object API 응답; data: 입금대기 주문 (create 응답과 동일)
     */
    public function detail($depositId)
    {
        return $this->bootpay->get('sdk/deposits/' . rawurlencode($depositId), $this->supervisorHeaders());
    }

    /**
     * 입금대기 취소
     * DELETE /v1/sdk/deposits/{id}
     * 기한이 지났으면 만료로 닫고, 이미 닫힌 주문은 그대로 돌려준다. 입금 확인된 주문은 18108.
     * @param string $depositId 가맹점 주문번호 또는 부트페이 주문번호
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object API 응답; data: 입금대기 주문 (create 응답과 동일)
     */
    public function cancel($depositId, $idempotencyKey = null)
    {
        return $this->bootpay->delete('sdk/deposits/' . rawurlencode($depositId), $this->supervisorHeaders($idempotencyKey));
    }

    /**
     * supervisor scope(order_deposit_*) 고정. Idempotency-Key 는 미지정시 매 호출마다 생성한다.
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
}
