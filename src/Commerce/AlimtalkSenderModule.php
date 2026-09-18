<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

/**
 * 알림톡 발신프로필(카카오채널) 생명주기 — GET /alimtalk/categories · /alimtalk/senders 계열
 *
 * 카테고리 조회 → OTP 발송 → 발신프로필 등록 → 목록/상세 → 연동 해지 순으로 쓴다.
 * 등록이 끝나면 서버가 그룹키 등록까지 자동으로 하므로, 공식 템플릿은 별도 채택 없이 바로 발송된다.
 *
 * ⚠️ 실제 부작용: `otp()` 는 채널 관리자 휴대폰으로 **문자를 실제 발송**하고,
 *    `create()` 는 카카오에 발신프로필을 **실제 등록**한다. 샌드박스가 없다.
 *
 * ★Idempotency-Key 를 싣지 않는다★ 알림톡 API 는 이 헤더를 읽지 않는다(멱등은 발송의 ref_id 로만 성립).
 *   invoice/product 처럼 무조건 붙이면 서버가 주지 않는 보장을 주는 것처럼 보인다.
 * ★BOOTPAY-ROLE 은 항상 user★ 알림톡 스코프 키가 전부 `user:alimtalk_*` 다.
 */
class AlimtalkSenderModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 카카오 카테고리 목록 조회
     * GET /alimtalk/categories
     * 발신프로필 등록 시 필요한 category_code 후보다. 벤더 응답을 그대로 프록시한다.
     * @return object
     */
    public function categories()
    {
        return $this->bootpay->get('alimtalk/categories', $this->userHeaders());
    }

    /**
     * 채널 관리자폰으로 OTP 발송
     * POST /alimtalk/senders/otp
     * ⚠️ 실제로 문자가 나간다. 여기서 받은 인증번호를 create() 의 otp 로 넘긴다.
     * @param array $params yellow_id(필수) / phone(필수)
     * @return object
     * @throws \Exception
     */
    public function otp($params)
    {
        $payload = $this->pick($params, array('yellow_id', 'phone'));
        $this->requireKeys($payload, array('yellow_id', 'phone'));

        return $this->bootpay->post('alimtalk/senders/otp', $payload, $this->userHeaders());
    }

    /**
     * 발신프로필 등록
     * POST /alimtalk/senders
     * ⚠️ 카카오에 발신프로필이 실제 등록된다. 같은 yellow_id 를 다시 등록하면 기존 프로필을 재사용한다(dedup).
     * 등록 성공 시 그룹키 등록까지 서버가 수행하므로 공식 카탈로그 전체를 바로 발송할 수 있다.
     * @param array $params otp(필수) / yellow_id(필수) / phone(필수) / category_code(필수)
     * @return object
     * @throws \Exception
     */
    public function create($params)
    {
        $payload = $this->pick($params, array('otp', 'yellow_id', 'phone', 'category_code'));
        $this->requireKeys($payload, array('otp', 'yellow_id', 'phone', 'category_code'));

        return $this->bootpay->post('alimtalk/senders', $payload, $this->userHeaders());
    }

    /**
     * 연동한 채널 목록 조회
     * GET /alimtalk/senders
     * 자체 DB 만 조회하며 벤더를 호출하지 않는다. 응답은 { list: [...], count: N }.
     * @return object
     */
    public function getList()
    {
        return $this->bootpay->get('alimtalk/senders', $this->userHeaders());
    }

    /**
     * 채널 상세 조회
     * GET /alimtalk/senders/{ksp_id}
     * sync 를 true 로 주면 벤더에서 채널 상태를 다시 읽어 반영한다(느리다). 미지정이면 자체 DB 만 본다.
     * ⚠️ 미연동/미존재 채널은 404, 다른 프로젝트의 채널은 403 으로 오며 둘 다 error_code 는 3024 다.
     * @param string $kspId 채널 문서 id
     * @param bool|null $sync 벤더 재조회 여부
     * @return object
     */
    public function detail($kspId, $sync = null)
    {
        $queryString = $this->buildQueryString(array('sync' => $sync));
        return $this->bootpay->get("alimtalk/senders/{$kspId}" . $queryString, $this->userHeaders());
    }

    /**
     * 채널 연동 해지
     * DELETE /alimtalk/senders/{ksp_id}
     * 이 프로젝트와의 연동만 끊는다 — 채널 모델과 템플릿은 보존된다. 성공 시 본문은 null 이다.
     * @param string $kspId 채널 문서 id
     * @return object
     */
    public function release($kspId)
    {
        return $this->bootpay->delete("alimtalk/senders/{$kspId}", $this->userHeaders());
    }

    /**
     * 채널 변수 예문 사전 갱신
     * PUT /alimtalk/senders/{ksp_id}/variable_examples
     * 템플릿 미리보기에서 #{user_name} 대신 '홍길동' 처럼 읽히게 하는 **표시용** 값이다.
     * ⚠️ 발송값이 아니다 — 벤더로 전송되지 않으므로 검수 상태와 무관하다. 보낸 키만 덮어쓴다(부분 갱신).
     * @param string $kspId 채널 문서 id
     * @param array $examples 예: array('user_name' => '홍길동') — 키에 '.' 이나 선행 '$' 는 쓸 수 없다
     * @return object
     */
    public function variableExamples($kspId, $examples)
    {
        return $this->bootpay->put(
            "alimtalk/senders/{$kspId}/variable_examples",
            array('examples' => $examples === null ? new \stdClass() : $examples),
            $this->userHeaders()
        );
    }

    /**
     * 알림톡 전용 요청 헤더.
     * 서버 스코프가 전부 user:alimtalk_* 이므로 BOOTPAY-ROLE 을 user 로 고정한다.
     * ⚠️ Idempotency-Key 는 붙이지 않는다 — 알림톡 API 는 이 헤더를 읽지 않는다.
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
     * 서버가 필수로 요구하는 값이 빠졌으면 요청 전에 끊는다.
     */
    private function requireKeys($payload, $keys)
    {
        foreach ($keys as $key) {
            if (!isset($payload[$key]) || $payload[$key] === '') {
                throw new \Exception($key . ' is required');
            }
        }
    }

    /**
     * 쿼리스트링을 만든다. null 은 빼고, bool 은 'true'/'false' 로 보낸다.
     * (http_build_query 는 bool 을 1/'' 로 직렬화해 false 가 서버에서 사라진다)
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
