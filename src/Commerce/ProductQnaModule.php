<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

/**
 * 상품문의 게시판 — /v1/product-qnas 계열
 *
 * 회원(user_id · login_id · user_jwt) 과 비회원(guest_name + guest_password) 둘 다 글을 쓴다.
 * ⚠️ 고객 모드 목록은 product_id 가 필수이고, 다른 사람의 비밀글은 is_viewable: false 로 본문 없이 온다.
 *    supervisor + view: 'all' 이면 몰 전체(숨김 포함)가 보이고, 이때 product_id 는 선택 필터다.
 *    답변 등록·수정(answer)은 supervisor 전용이다.
 */
class ProductQnaModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 상품문의 목록 조회
     * GET /v1/product-qnas
     * 회원을 함께 주면 그 회원이 쓴 비밀글의 본문까지 보인다.
     * @param array|null $params product_id / view / page(기본 1) / limit(기본 20) / user_id / login_id / user_jwt
     *                           / supervisor (idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function getList($params = null)
    {
        $params = $params === null ? array() : $params;

        $query = $this->pick($params, array('product_id', 'view'));
        $query['page'] = isset($params['page']) ? $params['page'] : 1;
        $query['limit'] = isset($params['limit']) ? $params['limit'] : 20;
        $query = array_merge($query, $this->pick($params, array('user_id', 'login_id')));

        return $this->bootpay->get('product-qnas' . $this->buildQueryString($query), $this->headers($params));
    }

    /**
     * 상품문의 단건 조회
     * GET /v1/product-qnas/{product_qna_id}
     * ⚠️ 다른 사람의 비밀글은 403 PRODUCT_QNA_SECRET_FORBIDDEN 이다.
     *    supervisor 를 주면 비밀글·숨김 글도 본다.
     * @param array $params product_qna_id(필수) / user_id / login_id / user_jwt / supervisor
     * @return object
     * @throws \Exception
     */
    public function detail($params)
    {
        $productQnaId = $this->requireValue($params, 'product_qna_id');
        $query = $this->pick($params, array('user_id', 'login_id'));

        return $this->bootpay->get(
            "product-qnas/{$productQnaId}" . $this->buildQueryString($query),
            $this->headers($params)
        );
    }

    /**
     * 상품문의 작성
     * POST /v1/product-qnas
     * 회원(user_id · login_id · user_jwt) 또는 비회원(guest_name + guest_password, 몰이 비회원 작성을 허용할 때)이 쓴다.
     * ⚠️ 회원 문의면 guest_* 는 무시된다.
     * @param array $params product_id(필수) / content(필수) / title / product_option_id / option_text / is_secret
     *                      / guest_name / guest_password / user_id / login_id / user_jwt
     * @return object
     * @throws \Exception
     */
    public function create($params)
    {
        $this->requireValue($params, 'product_id');
        $this->requireValue($params, 'content');
        $payload = $this->payload($params, array(
            'product_id',
            'title',
            'content',
            'product_option_id',
            'option_text',
            'is_secret',
            'guest_name',
            'guest_password',
            'user_id',
            'login_id'
        ));

        return $this->bootpay->post('product-qnas', $payload, $this->headers($params, 'user'));
    }

    /**
     * 상품문의 수정 (작성 회원 본인 또는 비회원 비밀번호, 답변 전만)
     * PUT /v1/product-qnas/{product_qna_id}
     * ⚠️ 답변이 달린 문의는 409 PRODUCT_QNA_ALREADY_ANSWERED,
     *    비회원 비밀번호가 틀리면 403 PRODUCT_QNA_GUEST_PASSWORD_INVALID 다.
     * @param array $params product_qna_id(필수) / title / content / is_secret / guest_password
     *                      / user_id / login_id / user_jwt
     * @return object
     * @throws \Exception
     */
    public function update($params)
    {
        $productQnaId = $this->requireValue($params, 'product_qna_id');
        $payload = $this->payload(
            $params,
            array('title', 'content', 'is_secret', 'guest_password', 'user_id', 'login_id')
        );

        return $this->bootpay->put("product-qnas/{$productQnaId}", $payload, $this->headers($params, 'user'));
    }

    /**
     * 상품문의 삭제 (작성 회원 본인 · 비회원 비밀번호 또는 supervisor)
     * DELETE /v1/product-qnas/{product_qna_id}
     * ⚠️ guest_password 는 URL 쿼리가 아니라 본문으로 보낸다 (액세스 로그 노출 방지 — 서버는 둘 다 받는다).
     * @param array $params product_qna_id(필수) / guest_password / user_id / login_id / user_jwt / supervisor
     * @return object
     * @throws \Exception
     */
    public function delete($params)
    {
        $productQnaId = $this->requireValue($params, 'product_qna_id');
        $payload = $this->payload($params, array('guest_password', 'user_id', 'login_id'));

        return $this->bootpay->delete(
            "product-qnas/{$productQnaId}",
            $this->headers($params),
            $payload
        );
    }

    /**
     * 상품문의 답변 등록·수정 (supervisor 전용)
     * PUT /v1/product-qnas/{product_qna_id}/answer
     * 답변이 없으면 만들고, 있으면 내용을 바꾼다. 응답은 답변이 반영된 상품문의 객체다.
     * @param array $params product_qna_id(필수) / content(필수)
     * @return object
     * @throws \Exception
     */
    public function answer($params)
    {
        $productQnaId = $this->requireValue($params, 'product_qna_id');
        $this->requireValue($params, 'content');

        return $this->bootpay->put(
            "product-qnas/{$productQnaId}/answer",
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
