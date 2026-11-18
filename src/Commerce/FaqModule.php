<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

/**
 * FAQ 게시판 — /v1/faqs 계열
 *
 * 조회는 고객(user)·운영자(supervisor) 두 모드가 있고, 쓰기(등록/수정/삭제)는 supervisor 전용이다.
 * ⚠️ 고객 모드는 몰 FAQ 사용여부가 꺼져 있으면 BOARD_FEATURE_DISABLED 로 거절된다.
 *    비공개 FAQ 는 supervisor 모드(+ view: 'all')에서만 보인다.
 */
class FaqModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * FAQ 목록 조회
     * GET /v1/faqs
     * supervisor 를 주면 운영자 모드(BOOTPAY-ROLE: supervisor)로 조회한다 — view: 'all' 로 비공개 FAQ 까지 본다.
     * @param array|null $params page(기본 1) / limit(기본 20) / keyword / view / supervisor
     *                           (idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function getList($params = null)
    {
        $params = $params === null ? array() : $params;

        $query = array(
            'page' => isset($params['page']) ? $params['page'] : 1,
            'limit' => isset($params['limit']) ? $params['limit'] : 20
        );
        $query = array_merge($query, $this->pick($params, array('keyword', 'view')));

        return $this->bootpay->get('faqs' . $this->buildQueryString($query), $this->headers($params));
    }

    /**
     * FAQ 단건 조회
     * GET /v1/faqs/{faq_id}
     * ⚠️ 비공개 FAQ 는 supervisor 를 줬을 때만 보인다 (아니면 404 POST_NOT_FOUND).
     * @param array|string $params faq_id(필수) / supervisor / idempotency_key — 문자열이면 faq_id 로 본다
     * @return object
     * @throws \Exception
     */
    public function detail($params)
    {
        $params = $this->normalize($params, 'faq_id');
        $faqId = $this->requireValue($params, 'faq_id');

        return $this->bootpay->get("faqs/{$faqId}", $this->headers($params));
    }

    /**
     * FAQ 등록 (supervisor 전용)
     * POST /v1/faqs
     * @param array $params title(필수) / content(필수) / images / is_display
     *                      (idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     * @throws \Exception
     */
    public function create($params)
    {
        $this->requireValue($params, 'title');
        $this->requireValue($params, 'content');
        $payload = $this->payload($params, array('title', 'content', 'images', 'is_display'));

        return $this->bootpay->post('faqs', $payload, $this->headers($params, 'supervisor'));
    }

    /**
     * FAQ 수정 (supervisor 전용)
     * PUT /v1/faqs/{faq_id}
     * 보낸 필드만 바뀐다. ⚠️ images 는 보내면 목록 전체를 교체한다 (빈 배열이면 모두 삭제).
     * @param array $params faq_id(필수) / title / content / images / is_display
     * @return object
     * @throws \Exception
     */
    public function update($params)
    {
        $faqId = $this->requireValue($params, 'faq_id');
        $payload = $this->payload($params, array('title', 'content', 'images', 'is_display'));

        return $this->bootpay->put("faqs/{$faqId}", $payload, $this->headers($params, 'supervisor'));
    }

    /**
     * FAQ 삭제 (supervisor 전용)
     * DELETE /v1/faqs/{faq_id}
     * @param array|string $params faq_id(필수) / idempotency_key — 문자열이면 faq_id 로 본다
     * @return object
     * @throws \Exception
     */
    public function delete($params)
    {
        $params = $this->normalize($params, 'faq_id');
        $faqId = $this->requireValue($params, 'faq_id');

        return $this->bootpay->delete("faqs/{$faqId}", $this->headers($params, 'supervisor'));
    }

    /**
     * 게시판 요청 헤더.
     * $role 을 주면 그 역할로 고정하고, 없으면 supervisor 파라미터로 user / supervisor 를 가른다.
     * Idempotency-Key 는 미지정시 매 호출마다 생성된다.
     */
    private function headers($params, $role = null)
    {
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        if ($role === null) {
            $role = (isset($params['supervisor']) && $params['supervisor']) ? 'supervisor' : 'user';
        }

        return array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: ' . $role
        );
    }

    /**
     * 문자열로 넘어온 ID 를 파라미터 배열로 바꾼다.
     */
    private function normalize($params, $idKey)
    {
        return is_array($params) ? $params : array($idKey => $params);
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
