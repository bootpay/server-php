<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

class StoreModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 가맹점 기본 정보 조회
     * GET /v1/store
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function getStore($idempotencyKey = null)
    {
        return $this->bootpay->get('store', $this->storeHeaders($idempotencyKey));
    }

    /**
     * 가맹점 기본 정보 조회 (getStore 별칭)
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function info($idempotencyKey = null)
    {
        return $this->getStore($idempotencyKey);
    }

    /**
     * 가맹점 상세 정보 조회
     * GET /v1/store/detail
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function getStoreDetail($idempotencyKey = null)
    {
        return $this->bootpay->get('store/detail', $this->storeHeaders($idempotencyKey));
    }

    /**
     * 가맹점 상세 정보 조회 (getStoreDetail 별칭)
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function detail($idempotencyKey = null)
    {
        return $this->getStoreDetail($idempotencyKey);
    }

    /**
     * 가맹점 정보 조회 요청 헤더
     * Idempotency-Key 는 미지정시 매 호출마다 생성된다.
     */
    private function storeHeaders($idempotencyKey = null)
    {
        return array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: user'
        );
    }
}
