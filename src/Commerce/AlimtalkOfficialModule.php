<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

/**
 * 부트페이 공식 알림톡 템플릿 카탈로그 — GET/POST /alimtalk/official 계열
 *
 * 부트페이가 미리 카카오 승인을 받아 둔 템플릿이라, 그룹키가 등록된 채널이면 **검수 없이 즉시 발송**된다.
 * `alimtalkSender->create()` 로 채널을 등록하면 그룹 등록이 함께 끝나므로 따로 채택할 것이 없다.
 *
 * 전부 조회 계열이라 부작용이 없다(자체 DB 만 본다).
 * 26-08-27: 채택(adopt) 엔드포인트는 서버에서도 비활성화되어 SDK 에 두지 않는다.
 */
class AlimtalkOfficialModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 공식 템플릿 검색
     * GET /alimtalk/official
     * keyword 는 본문·이름·분류를 부분일치(대소문자 무시)로 훑는다.
     * msg_type 은 BA(기본형) · EX(부가정보형)만 존재한다 — 그룹 템플릿이라 AD/MI 는 쓸 수 없다.
     * ksp_id 를 주면 그 채널의 변수 예문 사전으로 variable_examples 를 채워 준다(표시용).
     * per 은 서버 기본 20, 최대 100 으로 clamp 된다.
     *
     * 응답: { list: [...], count, page, per, categories: [...] }
     * @param array|null $params keyword(=q) / category / msg_type / page / per / ksp_id
     * @return object
     */
    public function getList($params = null)
    {
        $query = $this->pick($params, array('q', 'keyword', 'category', 'msg_type', 'page', 'per', 'ksp_id'));

        // 서버는 q 를 먼저 보고 없으면 keyword 를 본다 — 정본 키인 q 로 보낸다.
        if (!isset($query['q']) && isset($query['keyword'])) {
            $query['q'] = $query['keyword'];
        }
        unset($query['keyword']);

        return $this->bootpay->get('alimtalk/official' . $this->buildQueryString($query), $this->userHeaders());
    }

    /**
     * 보내려는 문구로 공식 템플릿 추천받기
     * POST /alimtalk/official/recommend
     * 유사도 score(0~1) 내림차순으로 돌려준다. limit 은 서버 기본 5.
     * @param array $params text(필수) / category / limit / ksp_id
     * @return object
     * @throws \Exception
     */
    public function recommend($params)
    {
        $payload = $this->pick($params, array('text', 'category', 'limit', 'ksp_id'));
        if (!isset($payload['text']) || $payload['text'] === '') {
            throw new \Exception('text is required');
        }

        return $this->bootpay->post('alimtalk/official/recommend', $payload, $this->userHeaders());
    }

    /**
     * 공식 템플릿 상세 조회
     * GET /alimtalk/official/{code}
     * code 는 서버 채번 코드(슬래시를 포함하지 않는다). 없거나 미노출이면 404(3015).
     * @param string $code 공식 템플릿 코드
     * @param string|null $kspId 변수 예문을 채워 볼 채널 id (선택)
     * @return object
     */
    public function detail($code, $kspId = null)
    {
        $queryString = $this->buildQueryString(array('ksp_id' => $kspId));

        return $this->bootpay->get("alimtalk/official/{$code}" . $queryString, $this->userHeaders());
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
