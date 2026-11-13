<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

class InvoiceModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 청구서 목록 조회
     * GET /v1/invoices
     * 응답 data 는 { list: [...], count: N } 구조다 ({ items, total } 아님).
     * limit 미지정시 서버 기본값과 동일한 24 를 보낸다.
     * @param array|null $params 조회 파라미터 (page, limit, keyword, cs_type, user_id, product_type, css_at, cse_at /
     *                           idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function getList($params = null)
    {
        $params = $params === null ? array() : $params;
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;

        $query = array(
            'page' => isset($params['page']) ? $params['page'] : 1,
            'limit' => isset($params['limit']) ? $params['limit'] : 24
        );
        if (isset($params['keyword'])) {
            $query['keyword'] = $params['keyword'];
        }
        if (isset($params['cs_type'])) {
            $query['cs_type'] = $params['cs_type'];
        }
        if (isset($params['user_id'])) {
            $query['user_id'] = $params['user_id'];
        }
        if (isset($params['product_type'])) {
            $query['product_type'] = $params['product_type'];
        }
        if (isset($params['css_at'])) {
            $query['css_at'] = $params['css_at'];
        }
        if (isset($params['cse_at'])) {
            $query['cse_at'] = $params['cse_at'];
        }

        $queryString = http_build_query($query);
        return $this->bootpay->get("invoices?{$queryString}", $this->invoiceHeaders($idempotencyKey));
    }

    /**
     * 청구서 생성
     * POST /v1/invoices — 서버 스코프는 user:invoice_create 뿐이라 인스턴스 role 과 무관하게
     * BOOTPAY-ROLE: user 로 보낸다 (manager/supervisor 로 나가면 401 API_ROLE_NOT_SUPPORT).
     * @param array $invoice 청구서 정보
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function create($invoice, $idempotencyKey = null)
    {
        return $this->bootpay->post('invoices', $invoice, $this->invoiceHeaders($idempotencyKey));
    }

    /**
     * 청구서 알림 발송
     * POST /v1/invoices/{invoice_id}/notify
     * sendTypes 미전달시 서버가 빈 배열로 처리한다.
     * ⚠️ 실제 고객에게 알림이 발송되므로 테스트 호출 주의.
     * @param string $invoiceId 청구서 ID
     * @param array|null $sendTypes 발송 타입 배열 (예: [1, 2] - SMS, Email 등)
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function notify($invoiceId, $sendTypes = null, $idempotencyKey = null)
    {
        $payload = $sendTypes === null ? new \stdClass() : array('send_types' => $sendTypes);
        return $this->bootpay->post(
            "invoices/{$invoiceId}/notify",
            $payload,
            $this->invoiceHeaders($idempotencyKey)
        );
    }

    /**
     * 청구서 상세 조회
     * GET /v1/invoices/{invoice_id}
     * @param string $invoiceId 청구서 ID
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function detail($invoiceId, $idempotencyKey = null)
    {
        return $this->bootpay->get("invoices/{$invoiceId}", $this->invoiceHeaders($idempotencyKey));
    }

    /**
     * 청구서 API 요청 헤더
     * Idempotency-Key 는 미지정시 매 호출마다 생성된다.
     */
    private function invoiceHeaders($idempotencyKey = null)
    {
        return array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: user'
        );
    }
}
