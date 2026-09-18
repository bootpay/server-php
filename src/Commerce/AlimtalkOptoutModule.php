<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

/**
 * 알림톡 수신거부 — /alimtalk/optouts 계열 (가맹점 CRM 수신거부 동기화용)
 *
 * 발송 판정과 **같은 기준**으로 다룬다 — 부트페이 전역(global) + 내 프로젝트.
 * ⚠️ 전역 건은 **조회는 되지만 해제할 수 없다**(releasable: false).
 *    이걸 노출하지 않으면 "화면엔 수신거부가 아닌데 발송은 3021 로 막히는" 상태가 된다.
 */
class AlimtalkOptoutModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 수신거부 목록 조회
     * GET /alimtalk/optouts
     * phone 은 숫자만 남겨 **부분일치**로 찾는다(정확 매칭이 아니다). 50건 단위로 페이징된다.
     *
     * 응답: { list: [{ id, phone, scope, global, releasable, source, reason, opted_out_at, created_at }],
     *        count, page }
     * @param array|null $params phone / page
     * @return object
     */
    public function getList($params = null)
    {
        $queryString = $this->buildQueryString($this->pick($params, array('phone', 'page')));

        return $this->bootpay->get('alimtalk/optouts' . $queryString, $this->userHeaders());
    }

    /**
     * 수신거부 등록
     * POST /alimtalk/optouts
     * 내 프로젝트 스코프로 등록된다(source: api). 같은 번호를 다시 등록해도 멱등이다.
     * @param array $params phone(필수) / reason
     * @return object
     * @throws \Exception
     */
    public function create($params)
    {
        $payload = $this->pick($params, array('phone', 'reason'));
        if (!isset($payload['phone']) || $payload['phone'] === '') {
            throw new \Exception('phone is required');
        }

        return $this->bootpay->post('alimtalk/optouts', $payload, $this->userHeaders());
    }

    /**
     * 발송 전 수신거부 사전 확인
     * POST /alimtalk/optouts/check
     * 발송 판정과 **같은 축**으로 대조하므로, 벌크에서 skipped 로 낭비될 건을 미리 뺄 수 있다.
     * 단건(phone) · 다건(phones) 모두 받는다.
     * ⚠️ 1회 최대 1,000건이고 넘으면 -48 이다(중복은 서버가 제거).
     *
     * 응답: { list: [{ phone, opted_out, global, releasable, opted_out_at }], count, opted_out_count }
     * @param array $params phones(배열) / phone(단건) — 둘 중 하나는 있어야 한다
     * @return object
     * @throws \Exception
     */
    public function check($params)
    {
        $payload = $this->pick($params, array('phones', 'phone'));
        if (empty($payload)) {
            throw new \Exception('phones or phone is required');
        }

        return $this->bootpay->post('alimtalk/optouts/check', $payload, $this->userHeaders());
    }

    /**
     * 수신거부 해제
     * DELETE /alimtalk/optouts/{phone}
     * 내 프로젝트 스코프 건만 해제되며 멱등이다(없어도 성공).
     * ⚠️ 전역 차단은 해제되지 않고 global_blocked: true 로 알려 준다 —
     *    "지웠는데 여전히 막히는" 상태를 응답으로 드러내기 위함이다.
     *
     * 응답: { phone, released, global_blocked }
     * @param string $phone 수신번호
     * @return object
     */
    public function release($phone)
    {
        return $this->bootpay->delete("alimtalk/optouts/{$phone}", $this->userHeaders());
    }

    /**
     * 알림톡 전용 요청 헤더 (BOOTPAY-ROLE: user 고정, Idempotency-Key 미부착).
     */
    private function userHeaders()
    {
        return array('BOOTPAY-ROLE: user');
    }

    /**
     * 화이트리스트 키만 뽑는다. null 은 제외한다 (Ruby SDK 의 .compact 와 동일 동작).
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

    /**
     * 쿼리스트링을 만든다. null 은 빼고, bool 은 'true'/'false' 로 보낸다.
     */
    private function buildQueryString($query)
    {
        $normalized = array();
        foreach ((array)$query as $key => $value) {
            if ($value === null) {
                continue;
            }
            $normalized[$key] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }
        return empty($normalized) ? '' : '?' . http_build_query($normalized);
    }
}
