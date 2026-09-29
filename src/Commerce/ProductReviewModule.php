<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

/**
 * 상품평 게시판 — /v1/reviews 계열
 *
 * 회원 모드는 user_id · login_id · user_jwt 로 정한 회원이 쓴 상품평만 다룬다.
 * supervisor 를 주면 몰 전체 상품평이 보이고, 이때 user_id 는 작성 회원 필터(선택)다.
 * ⚠️ 상품 상세의 공개 상품평 목록은 이 모듈이 아니라 GET /v1/products/{id}/reviews 를 쓴다.
 *    판매자 답글(reply)은 supervisor 전용이다.
 */
class ProductReviewModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 상품평 목록 조회
     * GET /v1/reviews
     * @param array|null $params page(기본 1) / limit(기본 20) / product_id / view / user_id / login_id / user_jwt
     *                           / supervisor (idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function getList($params = null)
    {
        $params = $params === null ? array() : $params;

        $query = array(
            'page' => isset($params['page']) ? $params['page'] : 1,
            'limit' => isset($params['limit']) ? $params['limit'] : 20
        );
        $query = array_merge($query, $this->pick($params, array('product_id', 'view', 'user_id', 'login_id')));

        return $this->bootpay->get('reviews' . $this->buildQueryString($query), $this->headers($params));
    }

    /**
     * 상품평 단건 조회
     * GET /v1/reviews/{product_review_id}
     * 공개 상품평은 누구나 보고, 숨김 상품평은 작성 회원 본인 또는 supervisor 만 본다.
     * @param array $params product_review_id(필수) / user_id / login_id / user_jwt / supervisor
     * @return object
     * @throws \Exception
     */
    public function detail($params)
    {
        $productReviewId = $this->requireValue($params, 'product_review_id');
        $query = $this->pick($params, array('user_id', 'login_id'));

        return $this->bootpay->get(
            "reviews/{$productReviewId}" . $this->buildQueryString($query),
            $this->headers($params)
        );
    }

    /**
     * 상품평 작성 (회원 전용)
     * POST /v1/reviews
     * ⚠️ order_id 는 회원의 구매확정 주문, product_id(·product_option_id)는 그 주문에 담긴 상품이어야 한다.
     *    images 는 사진 URL 최대 5개(문자열 또는 { url: ... })이며 파일 업로드는 지원하지 않는다.
     * @param array $params order_id(필수) / product_id(필수) / rating(필수) / content(필수)
     *                      / product_option_id / images / user_id / login_id / user_jwt
     * @return object
     * @throws \Exception
     */
    public function create($params)
    {
        $this->requireValue($params, 'order_id');
        $this->requireValue($params, 'product_id');
        $this->requireValue($params, 'rating');
        $this->requireValue($params, 'content');
        $payload = $this->payload($params, array(
            'user_id',
            'login_id',
            'order_id',
            'product_id',
            'product_option_id',
            'rating',
            'content',
            'images'
        ));

        return $this->bootpay->post('reviews', $payload, $this->headers($params, 'user'));
    }

    /**
     * 상품평 수정 (작성 회원 본인, 작성 후 7일 이내)
     * PUT /v1/reviews/{product_review_id}
     * ⚠️ images 는 보내면 통째로 교체한다 (빈 배열이면 모두 삭제). 7일이 지나면 REVIEW_EDIT_TIME_EXPIRED 다.
     * @param array $params product_review_id(필수) / rating / content / images / user_id / login_id / user_jwt
     * @return object
     * @throws \Exception
     */
    public function update($params)
    {
        $productReviewId = $this->requireValue($params, 'product_review_id');
        $payload = $this->payload($params, array('user_id', 'login_id', 'rating', 'content', 'images'));

        return $this->bootpay->put("reviews/{$productReviewId}", $payload, $this->headers($params, 'user'));
    }

    /**
     * 상품평 삭제 (작성 회원 본인 또는 supervisor)
     * DELETE /v1/reviews/{product_review_id}
     * 지급된 적립금 회수·상품 통계 차감이 함께 일어난다. reason 은 운영자 모드의 삭제 사유다.
     * @param array $params product_review_id(필수) / reason / user_id / login_id / user_jwt / supervisor
     * @return object
     * @throws \Exception
     */
    public function delete($params)
    {
        $productReviewId = $this->requireValue($params, 'product_review_id');
        $query = $this->pick($params, array('user_id', 'login_id', 'reason'));

        return $this->bootpay->delete(
            "reviews/{$productReviewId}" . $this->buildQueryString($query),
            $this->headers($params)
        );
    }

    /**
     * 상품평 판매자 답글 등록·수정 (supervisor 전용)
     * PUT /v1/reviews/{product_review_id}/reply
     * 답글이 없으면 만들고, 있으면 내용을 바꾼다(10자 이상). 응답은 답글이 반영된 상품평 객체다.
     * @param array $params product_review_id(필수) / content(필수)
     * @return object
     * @throws \Exception
     */
    public function reply($params)
    {
        $productReviewId = $this->requireValue($params, 'product_review_id');
        $this->requireValue($params, 'content');

        return $this->bootpay->put(
            "reviews/{$productReviewId}/reply",
            array('content' => $params['content']),
            $this->headers($params, 'supervisor')
        );
    }

    /**
     * 게시판 요청 헤더.
     * $role 을 주면 그 역할로 고정하고, 없으면 supervisor 파라미터로 user / supervisor 를 가른다.
     * Bootpay-User-JWT 는 값이 있을 때만 붙고, Idempotency-Key 는 미지정시 매 호출마다 생성된다.
     */
    private function headers($params, $role = null)
    {
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        if ($role === null) {
            $role = (isset($params['supervisor']) && $params['supervisor']) ? 'supervisor' : 'user';
        }

        $headers = array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: ' . $role
        );

        $userJwt = isset($params['user_jwt']) ? $params['user_jwt'] : null;
        if ($userJwt !== null && $userJwt !== '') {
            $headers[] = 'Bootpay-User-JWT: ' . $userJwt;
        }

        return $headers;
    }

    /**
     * 필수값을 꺼낸다. 없으면 예외.
     * @throws \Exception
     */
    private function requireValue($params, $key)
    {
        if (!is_array($params) || !isset($params[$key]) || $params[$key] === '') {
            throw new \Exception("{$key} is required");
        }
        return $params[$key];
    }

    /**
     * 화이트리스트 키만 뽑는다. null 은 제외한다 (Ruby SDK 의 .compact 와 동일 동작).
     * false / 0 / '' / 빈 배열은 의미 있는 값이므로 그대로 싣는다.
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
     * 요청 바디를 만든다 — 화이트리스트 키만 싣고, 비면 {} 로 보낸다.
     */
    private function payload($params, $keys)
    {
        $picked = $this->pick($params, $keys);
        // 빈 배열은 JSON 인코딩 시 [] 가 되므로 {} 로 전송되도록 객체로 변환
        return empty($picked) ? new \stdClass() : $picked;
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
