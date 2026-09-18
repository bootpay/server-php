<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

/**
 * 가맹점 자체 알림톡 템플릿 CRUD · 등록 · 검수 — /alimtalk/templates 계열
 *
 * 흐름: (초안 생성 → 확인 → 대행사 등록) → 검수 요청 → 승인(APR) → 발송 가능
 *   `create(array('register' => false, ...))` 로 초안만 만들고, 내용을 확인한 뒤
 *   `register($templateId)` 로 올리는 것을 권장한다.
 *
 * ⚠️ `register` 를 명시적으로 false 로 주지 않으면 **생성 즉시 대행사·카카오에 실제 등록**된다.
 * ⚠️ 본문 변수는 `#{변수명}` 형식이고 템플릿 전체에서 최대 40개다.
 */
class AlimtalkTemplateModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 내 자체 템플릿 목록 조회
     * GET /alimtalk/templates
     *
     * - ins: 검수상태 필터 — 1 REG(등록) / 2 REQ(검수요청) / 3 APR(승인) / 4 KRR(등록거절) / 5 REJ(승인반려).
     *        숫자 · 숫자문자열 · 벤더 문자열('APR' 등)을 모두 받는다. 해석 못 하는 값은 필터 없음으로 떨어진다.
     * - keyword: 코드 · 이름 · 본문 · 분류 부분일치
     * - sort: latest(기본) / oldest / code
     * ⚠️ 페이지네이션이 없다 — 필터에 걸린 템플릿을 한 번에 모두 돌려준다.
     * @param array|null $params ins / sort / keyword
     * @return object
     */
    public function getList($params = null)
    {
        $queryString = $this->buildQueryString($this->pick($params, array('ins', 'sort', 'keyword')));

        return $this->bootpay->get('alimtalk/templates' . $queryString, $this->userHeaders());
    }

    /**
     * 자체 템플릿 생성
     * POST /alimtalk/templates
     * ⚠️ register 를 false 로 주지 않으면 대행사·카카오에 **실제 등록**된다(되돌리려면 삭제해야 한다).
     *
     * - emphasize_type: NONE / TEXT(강조표기형) / IMAGE(이미지형) / ITEM_LIST(아이템리스트형)
     *   - TEXT 는 emphasize_title · emphasize_subtitle 둘 다 필수(각 50자 · 40자)
     *   - IMAGE 는 이미지 필수 — image() 로 올린 URL 을 storage_image_url 로 넘긴다
     *   - ITEM_LIST 는 template_item.list(2~10개) 필수 + template_header · item_highlight · 이미지 중 하나 이상
     * - msg_type: BA(기본형) / EX(부가정보형, template_extra 필수) / AD(채널추가형) / MI(복합형)
     *   - AD · MI 는 채널추가(AC) 버튼이 필수다
     * - examples: 변수 예문(표시용). 주면 **모든 변수에 예문이 있어야** 한다(없으면 3017).
     *
     * 여기 명시되지 않은 값도 서버 파라미터로 그대로 전달된다 (Ruby SDK 의 **attrs 패스스루와 동일).
     * @param array $params ksp_id(필수) / register / name / content / buttons / msg_type / emphasize_* /
     *                      template_extra / template_header / item_highlight / template_item /
     *                      image_url / storage_image_url / security_flag / category / tags / examples / template_code
     * @return object
     * @throws \Exception
     */
    public function create($params)
    {
        if (!is_array($params) || !isset($params['ksp_id']) || $params['ksp_id'] === '') {
            throw new \Exception('ksp_id is required');
        }

        return $this->bootpay->post('alimtalk/templates', $this->compact($params), $this->userHeaders());
    }

    /**
     * 자체 템플릿 상세 조회
     * GET /alimtalk/templates/{template_id}
     * template_id 는 문서 id 이고, ObjectId 형식이 아니면 **템플릿 코드**로 해석한다.
     * ⚠️ sync 는 서버 기본값이 **true** 라 조회만 해도 벤더 상태 동기화가 일어난다.
     *    초안(등록 전)을 조회할 때는 sync 를 false 로 주는 것을 권장한다.
     * @param string $templateId 템플릿 문서 id 또는 템플릿 코드
     * @param bool|null $sync 벤더 동기화 여부 (서버 기본 true)
     * @return object
     */
    public function detail($templateId, $sync = null)
    {
        $queryString = $this->buildQueryString(array('sync' => $sync));

        return $this->bootpay->get("alimtalk/templates/{$templateId}" . $queryString, $this->userHeaders());
    }

    /**
     * 자체 템플릿 수정
     * PUT /alimtalk/templates/{template_id}
     * ⚠️ **부분 수정이 아니다.** 보내지 않은 필드는 null 로 덮어써지므로 항상 전체 필드를 보낸다.
     * ⚠️ 등록된 템플릿을 수정하면 벤더에도 수정 요청이 나간다.
     *    수정 가능 상태는 초안 / REG(등록) / REJ(승인반려) / KRR(등록거절) 뿐이다 — APR · REQ 는 거부된다.
     * storage_image_url 을 빈 값으로 보내면 **이미지 삭제**로 처리되어 벤더에도 전달된다.
     * @param string $templateId 템플릿 문서 id 또는 템플릿 코드
     * @param array $params 수정할 값 (여기 명시되지 않은 값도 그대로 전달된다)
     * @return object
     */
    public function update($templateId, $params = null)
    {
        return $this->bootpay->put(
            "alimtalk/templates/{$templateId}",
            $this->compact($params),
            $this->userHeaders()
        );
    }

    /**
     * 자체 템플릿 삭제
     * DELETE /alimtalk/templates/{template_id}
     * 초안(등록 전)은 대행사 거부와 무관하게 로컬에서 삭제된다.
     * ⚠️ 등록분은 **대행사 삭제가 성공해야** 삭제된다 — 승인(APR) 템플릿은 카카오가 거부하므로
     *    500(3013)이 오고 템플릿은 남는다. 같은 코드가 대행사에 선점된 채 로컬만 사라지는 것을 막기 위함이다.
     * @param string $templateId 템플릿 문서 id 또는 템플릿 코드
     * @return object
     */
    public function delete($templateId)
    {
        return $this->bootpay->delete("alimtalk/templates/{$templateId}", $this->userHeaders());
    }

    /**
     * 초안을 대행사에 등록
     * POST /alimtalk/templates/{template_id}/register
     * ⚠️ 대행사·카카오에 실제 등록된다. 등록 전(초안) 상태에서만 호출할 수 있다.
     * @param string $templateId 템플릿 문서 id 또는 템플릿 코드
     * @return object
     */
    public function register($templateId)
    {
        return $this->bootpay->post(
            "alimtalk/templates/{$templateId}/register",
            new \stdClass(),
            $this->userHeaders()
        );
    }

    /**
     * 검수 요청
     * POST /alimtalk/templates/{template_id}/inspect
     * ⚠️ **카카오에 검수를 요청하며 취소할 수 없다.**
     * 대행사 등록이 끝난 대기(R) + REG(등록) 상태에서만 호출할 수 있다 — 초안은 먼저 register() 를 부른다.
     * 반려(REJ/KRR)된 건은 재요청이 아니라 **수정 후 재요청**이다. 반려 사유는 응답의 comments 에 담긴다.
     * @param string $templateId 템플릿 문서 id 또는 템플릿 코드
     * @return object
     */
    public function inspect($templateId)
    {
        return $this->bootpay->post(
            "alimtalk/templates/{$templateId}/inspect",
            new \stdClass(),
            $this->userHeaders()
        );
    }

    /**
     * 템플릿 목록 내보내기
     * GET /alimtalk/templates/export
     *
     * - scope: private(기본, 내 채널 자체 템플릿) / official(공식 카탈로그) / all
     * ⚠️ SDK 기본 format 을 **json 으로 둔다** — 서버 기본은 csv 지만, csv 본문은 JSON 이 아니라서
     *    공용 request() 의 json_decode 를 통과하지 못하고 null 이 된다.
     *    format 을 csv 로 주면 파싱하지 않는 원문 경로(getRaw)로 요청하고
     *    { body: '<csv 원문>', content_type: '...', status: 200 } 을 돌려준다.
     * 1회 5,000건을 넘으면 3031 로 거부되므로 채널·상태 필터로 좁힌다.
     * @param array|null $params format(기본 json) / scope / ksp_id / status / include_content
     * @return object
     */
    public function export($params = null)
    {
        $params = is_array($params) ? $params : array();
        if (!isset($params['format'])) {
            $params['format'] = 'json';
        }
        $query = $this->pick($params, array('format', 'scope', 'ksp_id', 'status', 'include_content'));
        $queryString = $this->buildQueryString($query);

        if (strtolower((string)$query['format']) === 'csv') {
            return $this->bootpay->getRaw('alimtalk/templates/export' . $queryString, $this->userHeaders());
        }

        return $this->bootpay->get('alimtalk/templates/export' . $queryString, $this->userHeaders());
    }

    /**
     * 이미지형 템플릿의 원본 이미지 업로드
     * POST /alimtalk/templates/image
     * 돌려받은 image_url 을 템플릿 생성/수정의 storage_image_url 로 넘긴다.
     * 규격을 업로드 **전에** 서버가 검사한다 — jpg/png · 500KB 이하 · 가로 500px 이상 · 2:1.
     * replace_url 을 주면 업로드 성공 후에 기존 파일을 지운다.
     * @param string $imagePath 업로드할 이미지 파일 경로
     * @param string|null $replaceUrl 교체 대상 기존 이미지 URL (선택)
     * @return object
     * @throws \Exception
     */
    public function image($imagePath, $replaceUrl = null)
    {
        return $this->uploadImage('alimtalk/templates/image', $imagePath, $replaceUrl);
    }

    /**
     * 아이템리스트형 하이라이트 썸네일 업로드
     * POST /alimtalk/templates/highlight_image
     * ⚠️ 본문 이미지와 **규격이 다르다** — jpg/png · 500KB 이하 · 가로 **108px** 이상 · **1:1**.
     *    본문 이미지 엔드포인트로 올리면 거부된다.
     * 돌려받은 image_url 은 item_highlight.storage_image_url 로 넘긴다.
     * ⚠️ 썸네일을 붙이면 하이라이트 글자 한도가 줄어든다(타이틀 30→21, 설명 19→13).
     * @param string $imagePath 업로드할 이미지 파일 경로
     * @param string|null $replaceUrl 교체 대상 기존 이미지 URL (선택)
     * @return object
     * @throws \Exception
     */
    public function highlightImage($imagePath, $replaceUrl = null)
    {
        return $this->uploadImage('alimtalk/templates/highlight_image', $imagePath, $replaceUrl);
    }

    /**
     * multipart/form-data 로 이미지 1건을 올린다.
     * 파일 필드명은 상품 이미지의 images[n] 이 아니라 image 다 — 문자열 키로 넘겨 필드명을 고정한다.
     */
    private function uploadImage($uri, $imagePath, $replaceUrl)
    {
        if (!is_string($imagePath) || !file_exists($imagePath)) {
            // 조용히 빠지면 서버가 "이미지 없음" 으로 거절해 원인을 찾기 어렵다.
            throw new \Exception('image file not found: ' . (is_string($imagePath) ? $imagePath : gettype($imagePath)));
        }

        $data = array();
        if ($replaceUrl !== null && $replaceUrl !== '') {
            $data['replace_url'] = $replaceUrl;
        }

        return $this->bootpay->requestMultipart(
            'POST',
            $uri,
            $data,
            array('image' => $imagePath),
            $this->userHeaders()
        );
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
     * ⚠️ isset() 이 아니라 array_key_exists() 로 본다 — register / include_content 의 false 를 살려야 한다.
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
