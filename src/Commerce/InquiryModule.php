<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

/**
 * 1:1 문의 게시판 — /v1/inquiries 계열
 *
 * 회원 모드는 user_id(Bootpay 회원 _id 또는 외부 회원 ID) · login_id · user_jwt 중 하나로 회원을 정하고
 * 그 회원의 문의만 다룬다. supervisor 를 주면 몰 전체 문의가 보이고, 이때 user_id 는 작성 회원 필터(선택)다.
 * ⚠️ 답변 등록·수정(answer)은 supervisor 전용이다.
 */
class InquiryModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 1:1 문의 목록 조회
     * GET /v1/inquiries
     * answered 는 true 면 답변 완료만, false 면 미답변만, 미지정이면 전체다.
     * @param array|null $params user_id / login_id / user_jwt / answered / page(기본 1) / limit(기본 20) / supervisor
     *                           (user_jwt 는 Bootpay-User-JWT, idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function getList($params = null)
    {
        $params = $params === null ? array() : $params;

        $query = $this->pick($params, array('user_id', 'login_id', 'answered'));
        $query['page'] = isset($params['page']) ? $params['page'] : 1;
        $query['limit'] = isset($params['limit']) ? $params['limit'] : 20;

        return $this->bootpay->get('inquiries' . $this->buildQueryString($query), $this->headers($params));
    }

    /**
     * 1:1 문의 단건 조회
     * GET /v1/inquiries/{inquiry_id}
     * 회원 모드는 작성 회원 본인 문의만 보인다(다른 회원 문의는 404). supervisor 를 주면 몰의 모든 문의를 본다.
     * @param array $params inquiry_id(필수) / user_id / login_id / user_jwt / supervisor
     * @return object
     * @throws \Exception
     */
    public function detail($params)
    {
        $inquiryId = $this->requireValue($params, 'inquiry_id');
        $query = $this->pick($params, array('user_id', 'login_id'));

        return $this->bootpay->get(
            "inquiries/{$inquiryId}" . $this->buildQueryString($query),
            $this->headers($params)
        );
    }

    /**
     * 1:1 문의 작성 (회원 전용)
     * POST /v1/inquiries
     * ⚠️ product_id 는 이 몰의 상품, order_id 는 작성 회원의 주문이어야 한다.
     *    option 은 상품 옵션 문구이며 product_id 가 함께 있어야 한다.
     * @param array $params content(필수) / user_id / login_id / user_jwt / title / product_id / option / order_id
     * @return object
     * @throws \Exception
     */
    public function create($params)
    {
        $this->requireValue($params, 'content');
        $payload = $this->payload(
            $params,
            array('user_id', 'login_id', 'title', 'content', 'product_id', 'option', 'order_id')
        );

        return $this->bootpay->post('inquiries', $payload, $this->headers($params, 'user'));
    }

    /**
     * 1:1 문의 수정 (작성 회원 본인, 답변 전만)
     * PUT /v1/inquiries/{inquiry_id}
     * ⚠️ 답변이 달린 문의는 409 INQUIRY_ALREADY_ANSWERED 다. title 에 '' 를 보내면 제목을 지운다.
     * @param array $params inquiry_id(필수) / user_id / login_id / user_jwt / title / content
     * @return object
     * @throws \Exception
     */
    public function update($params)
    {
        $inquiryId = $this->requireValue($params, 'inquiry_id');
        $payload = $this->payload($params, array('user_id', 'login_id', 'title', 'content'));

        return $this->bootpay->put("inquiries/{$inquiryId}", $payload, $this->headers($params, 'user'));
    }

    /**
     * 1:1 문의 삭제 (작성 회원 본인 또는 supervisor)
     * DELETE /v1/inquiries/{inquiry_id}
     * 달린 답변도 함께 삭제된다.
     * @param array $params inquiry_id(필수) / user_id / login_id / user_jwt / supervisor
     * @return object
     * @throws \Exception
     */
    public function delete($params)
    {
        $inquiryId = $this->requireValue($params, 'inquiry_id');
        $query = $this->pick($params, array('user_id', 'login_id'));

        return $this->bootpay->delete(
            "inquiries/{$inquiryId}" . $this->buildQueryString($query),
            $this->headers($params)
        );
    }

    /**
     * 1:1 문의 답변 등록·수정 (supervisor 전용)
     * PUT /v1/inquiries/{inquiry_id}/answer
     * 답변이 없으면 만들고, 있으면 내용을 바꾼다. 응답은 답변이 반영된 문의 객체다.
     * @param array $params inquiry_id(필수) / content(필수)
     * @return object
     * @throws \Exception
     */
    public function answer($params)
    {
        $inquiryId = $this->requireValue($params, 'inquiry_id');
        $this->requireValue($params, 'content');

        return $this->bootpay->put(
            "inquiries/{$inquiryId}/answer",
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
