<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

class CategoryModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 카테고리 트리 조회
     * @return object
     */
    public function getList()
    {
        return $this->bootpay->get('categories', array('BOOTPAY-ROLE: user'));
    }

    /**
     * 카테고리 단건 조회
     * @param string $categoryId 카테고리 ID
     * @return object
     */
    public function detail($categoryId)
    {
        return $this->bootpay->get("categories/{$categoryId}", array('BOOTPAY-ROLE: user'));
    }

    /**
     * 카테고리 생성
     * POST /v1/categories
     * ⚠️ 서버가 supervisor scope 를 요구한다 (scope_invalid!).
     * @param array $params 카테고리 생성 파라미터 (idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function create($params)
    {
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['idempotency_key']);
        return $this->bootpay->post('categories', $params, $this->supervisorHeaders($idempotencyKey));
    }

    /**
     * 카테고리 수정
     * PUT /v1/categories/{category_id}
     * ⚠️ 서버가 supervisor scope 를 요구한다 (scope_invalid!).
     * @param array $params 카테고리 수정 파라미터 (category_id 필수 / idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     * @throws \Exception
     */
    public function update($params)
    {
        if (!isset($params['category_id']) || empty($params['category_id'])) {
            throw new \Exception('category_id is required');
        }
        $categoryId = $params['category_id'];
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        $body = $params;
        unset($body['category_id'], $body['idempotency_key']);
        return $this->bootpay->put("categories/{$categoryId}", $body, $this->supervisorHeaders($idempotencyKey));
    }

    /**
     * 카테고리 삭제
     * DELETE /v1/categories/{category_id}
     * ⚠️ 서버가 supervisor scope 를 요구한다 (scope_invalid!).
     * @param string $categoryId 카테고리 ID
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function destroy($categoryId, $idempotencyKey = null)
    {
        return $this->bootpay->delete("categories/{$categoryId}", $this->supervisorHeaders($idempotencyKey));
    }

    /**
     * 카테고리 쓰기(등록/수정/삭제) 요청 헤더 — 서버가 supervisor scope 를 요구한다.
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
