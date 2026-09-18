<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

/**
 * 알림톡 발송결과·검수결과 웹훅 설정 — /alimtalk/webhook 계열
 *
 * ⚠️ **주문·구독 통합 웹훅과 완전히 별개다.** 알림톡 이벤트를 기존 주문 웹훅 URL 로 태우면
 *    그 수신 서버가 모르는 payload 를 받아 기존 연동이 깨진다. 그래서 수신 URL 을 따로 둔다.
 *    (`webhook->sendTest()` 는 주문 웹훅용이다 — 이 모듈의 `test()` 와 혼동하지 말 것)
 *
 * ## 서명 검증
 * 요청에 다음 헤더가 붙는다.
 *   X-Bootpay-Signature: sha256=HMAC_SHA256(secret, "{X-Bootpay-Timestamp}.{raw_body}")
 * 타임스탬프가 5분 이상 지난 요청은 거부한다(replay 방지).
 */
class AlimtalkWebhookModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 웹훅 설정 조회
     * GET /alimtalk/webhook
     * 시크릿은 앞 12자만 노출된다. 미설정이면 { configured: false } 로 온다.
     * @return object
     */
    public function detail()
    {
        return $this->bootpay->get('alimtalk/webhook', $this->userHeaders());
    }

    /**
     * 웹훅 설정 저장
     * PUT /alimtalk/webhook
     * url 은 **https 만** 허용한다(아니면 3028). 최초 저장 시 서명 시크릿이 자동 발급된다.
     *
     * events: 구독할 이벤트 코드. 목록에 없는 값은 저장 시 조용히 버려진다(유령 구독 방지).
     *   300 발송 접수(기본 미구독) / 301 전달 성공 / 302 전달 실패 / 303 예약 취소 /
     *   304 문자(LMS) 대체발송 전환 / 310 검수 승인 / 311 검수 반려 / 320 수신거부 등록(기본 미구독)
     * events 를 비우면 기본 구독셋(301 · 302 · 303 · 304 · 310 · 311)이 적용된다.
     *
     * ⚠️ enabled 는 false 도 그대로 전송된다(미지정 null 과 다르다).
     * @param array|null $params url / events / enabled
     * @return object
     */
    public function update($params = null)
    {
        $payload = $this->pick($params, array('url', 'events', 'enabled'));

        return $this->bootpay->put('alimtalk/webhook', $this->emptyToObject($payload), $this->userHeaders());
    }

    /**
     * 테스트 이벤트 1건 발송
     * POST /alimtalk/webhook/test
     * ⚠️ **설정된 URL 로 실제 HTTP 요청이 나간다.** 구독 여부와 무관하게 보낸다.
     * 웹훅이 설정돼 있지 않으면 3029. 응답: { delivery_id, url, queued }
     * @return object
     */
    public function test()
    {
        return $this->bootpay->post('alimtalk/webhook/test', new \stdClass(), $this->userHeaders());
    }

    /**
     * 서명 시크릿 재발급
     * POST /alimtalk/webhook/secret
     * ⚠️ **이 응답에서만 secret 원문을 돌려준다**(이후 조회는 마스킹된다).
     * ⚠️ 이미 큐에 있는 전송 건은 발송 당시 시크릿으로 서명된다.
     * @return object
     */
    public function rotateSecret()
    {
        return $this->bootpay->post('alimtalk/webhook/secret', new \stdClass(), $this->userHeaders());
    }

    /**
     * 웹훅 전송 이력 조회
     * GET /alimtalk/webhook/deliveries
     * 성공·실패를 모두 남긴다. limit 은 서버 기본 20, 최대 100.
     *
     * 응답: { list: [{ delivery_id, event, event_code, url, status, retry_count, max_retry, tags, created_at }],
     *        count, page, per }
     * @param array|null $params page / limit
     * @return object
     */
    public function deliveries($params = null)
    {
        $queryString = $this->buildQueryString($this->pick($params, array('page', 'limit')));

        return $this->bootpay->get('alimtalk/webhook/deliveries' . $queryString, $this->userHeaders());
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
     * ⚠️ isset() 이 아니라 array_key_exists() 로 본다 — enabled 의 false 를 살려 보내야 하기 때문이다.
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
     * 빈 배열은 JSON 인코딩 시 [] 가 되므로 {} 로 전송되도록 객체로 변환한다.
     */
    private function emptyToObject($payload)
    {
        return empty($payload) ? new \stdClass() : $payload;
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
