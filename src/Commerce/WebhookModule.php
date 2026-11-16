<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

class WebhookModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 테스트 웹훅 발송
     * POST /v1/webhook/test
     * 등록된 웹훅 URL 로 테스트 페이로드를 보내 연동을 확인할 때 쓴다.
     * @param array|null $params 발송 파라미터 (header_content_type 미지정시 전송하지 않는다,
     *                           idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function sendTest($params = null)
    {
        $payload = $params === null ? array() : $params;
        $idempotencyKey = isset($payload['idempotency_key']) ? $payload['idempotency_key'] : null;
        unset($payload['idempotency_key']);

        return $this->bootpay->post('webhook/test', $this->compact($payload), array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: supervisor'
        ));
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
}
