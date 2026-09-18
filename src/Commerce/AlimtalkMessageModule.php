<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

/**
 * 알림톡 발송내역·집계 — GET /alimtalk/messages 계열
 *
 * **유료** 알림톡만 조회된다(무료 커머스 알림톡은 포함되지 않는다).
 * 상태는 벤더 결과 동기화로 확정되므로 접수 직후에는 requested 로 보인다.
 */
class AlimtalkMessageModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 발송내역 목록 조회
     * GET /alimtalk/messages
     *
     * - status: requested / success / failed / canceled
     * - to: 수신번호(하이픈 무관, 정확 매칭) / ref_id: 발송 시 넘긴 멱등키
     * - limit: 서버 기본 20, 최대 100
     * ⚠️ 기간 기본값은 최근 30일이고 최대 조회 폭은 92일이다 — 초과분은 거부하지 않고 시작일을 당겨 잘라낸다.
     *    실제 적용된 구간은 응답의 period 로 확인한다.
     *
     * 응답: { list: [...], count, page, per, period: { from, to } }
     * @param array|null $params template_code / status / ref_id / to / s_at / e_at / page / limit
     * @return object
     */
    public function getList($params = null)
    {
        $queryString = $this->buildQueryString($this->pick($params, array(
            'template_code', 'status', 'ref_id', 'to', 's_at', 'e_at', 'page', 'limit'
        )));

        return $this->bootpay->get('alimtalk/messages' . $queryString, $this->userHeaders());
    }

    /**
     * 기간 집계 조회
     * GET /alimtalk/messages/stats
     * 일자별 집계 원장에서 읽으므로 응답이 빠르다.
     *
     * 응답: { period, totals: { sent, success, failed, fallback, opted_out_hit, rejected, canceled, success_rate },
     *        daily: [...], billing: { billable_count, unit_price, fallback_count, ..., amount } }
     * ⚠️ billing.unit_price_source 가 'default' 면 **잠정 단가**다(확정 청구액이 아니다).
     * ⚠️ billable_count 는 성공 − 폴백이다 — 폴백분은 LMS 단가로 따로 계산된다.
     * @param array|null $params s_at / e_at
     * @return object
     */
    public function stats($params = null)
    {
        $queryString = $this->buildQueryString($this->pick($params, array('s_at', 'e_at')));

        return $this->bootpay->get('alimtalk/messages/stats' . $queryString, $this->userHeaders());
    }

    /**
     * 단건 발송 결과 조회
     * GET /alimtalk/messages/{receipt_id}
     * 실패 사유는 error_code · error_message 에 담긴다.
     * fallback_type 은 폴백이 꺼진 건이면 null, 켜진 건이면 LMS 다.
     * 다른 프로젝트의 건이거나 없으면 404(3025).
     * @param string $receiptId 발송 접수 id
     * @return object
     */
    public function detail($receiptId)
    {
        return $this->bootpay->get("alimtalk/messages/{$receiptId}", $this->userHeaders());
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
     * ⚠️ isset() 이 아니라 array_key_exists() 로 본다 — false 를 살려 보내야 하기 때문이다.
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
