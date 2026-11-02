<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

class ProductModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 상품 목록 조회
     * GET /v1/products
     *
     * ⚠️ 서버(v1/products_controller#index)가 읽는 것은
     *    page / limit / keyword / category_id / ex_uid / sort **뿐**이다.
     *    type / period_type / s_at / e_at / category_code 는 보내도 에러 없이 무시되고
     *    전체 목록이 돌아온다 (하위호환을 위해 전송 자체는 유지한다).
     *    keyword 는 26-08-26 서버 변경부터 적용된다 — 그 이전 배포본에서는 무시된다.
     * @param array|null $params 조회 파라미터
     * @return object
     */
    public function getList($params = null)
    {
        $queryString = $this->buildQueryString($params);
        return $this->bootpay->get('products' . $queryString);
    }

    /**
     * 상품 목록 조회 (V1 Mall API)
     * GET /v1/products
     * page/limit 은 미지정시 각각 1 / 20 이 적용되고, 나머지 값은 지정된 것만 전송한다.
     * ⚠️ 서버(v1/products_controller#index)가 읽는 것은 page/limit/keyword/category_id/ex_uid/sort 뿐이다.
     *    type/period_type/s_at/e_at/category_code 는 보내도 조용히 무시된다.
     *    keyword 는 26-08-26 서버 변경부터 적용된다 — 그 이전 배포본에서는 무시된다.
     * @param array|null $params 조회 파라미터 (page/limit/category_id/ex_uid/sort — user_jwt 는 Bootpay-User-JWT 헤더,
     *                           idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     */
    public function products($params = null)
    {
        $params = $params === null ? array() : $params;
        $userJwt = isset($params['user_jwt']) ? $params['user_jwt'] : null;
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['user_jwt'], $params['idempotency_key']);

        $query = array(
            'page' => isset($params['page']) ? $params['page'] : 1,
            'limit' => isset($params['limit']) ? $params['limit'] : 20
        );
        if (isset($params['category_id'])) {
            $query['category_id'] = $params['category_id'];
        }
        if (isset($params['ex_uid'])) {
            $query['ex_uid'] = $params['ex_uid'];
        }
        if (isset($params['sort'])) {
            $query['sort'] = $params['sort'];
        }
        if (isset($params['keyword'])) {
            $query['keyword'] = $params['keyword'];
        }
        if (isset($params['type'])) {
            $query['type'] = $params['type'];
        }
        if (isset($params['period_type'])) {
            $query['period_type'] = $params['period_type'];
        }
        if (isset($params['s_at'])) {
            $query['s_at'] = $params['s_at'];
        }
        if (isset($params['e_at'])) {
            $query['e_at'] = $params['e_at'];
        }
        if (isset($params['category_code'])) {
            $query['category_code'] = $params['category_code'];
        }

        $queryString = http_build_query($query);
        return $this->bootpay->get("products?{$queryString}", $this->mallHeaders($userJwt, $idempotencyKey));
    }

    /**
     * 상품 생성 (BOOTPAY-ROLE: user)
     * POST /v1/products
     * $imagePaths 가 있으면 multipart/form-data (images[0], images[1] ... 인덱싱), 없으면 JSON 으로 보낸다.
     * @param array $product 상품 정보 (여기 명시되지 않은 값도 서버 _product_params 로 그대로 전달된다)
     * @param array|null $imagePaths 이미지 파일 경로 배열
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function create($product, $imagePaths = null, $idempotencyKey = null)
    {
        $payload = $this->compact($product);
        $headers = $this->userHeaders($idempotencyKey);

        if ($imagePaths === null || count($imagePaths) === 0) {
            return $this->bootpay->post('products', $payload, $headers);
        }

        return $this->bootpay->requestMultipart('POST', 'products', $payload, $imagePaths, $headers);
    }

    /**
     * 상품 상세 조회
     * GET /v1/products/{product_id}
     * user_jwt 를 넘기면 Bootpay-User-JWT 헤더가 붙어 회원 컨텍스트로 조회한다 (productDetail 과 동작이 같다).
     * @param string $productId 상품 ID
     * @param string|null $userJwt 회원 JWT (선택)
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function detail($productId, $userJwt = null, $idempotencyKey = null)
    {
        return $this->bootpay->get("products/{$productId}", $this->mallHeaders($userJwt, $idempotencyKey));
    }

    /**
     * 상품 상세 조회 (V1 Mall API)
     * GET /v1/products/{product_id}
     * @param string $productId 상품 ID
     * @param string|null $userJwt 회원 JWT (선택)
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function productDetail($productId, $userJwt = null, $idempotencyKey = null)
    {
        return $this->bootpay->get("products/{$productId}", $this->mallHeaders($userJwt, $idempotencyKey));
    }

    /**
     * 상품 수정 (BOOTPAY-ROLE: user)
     * PUT /v1/products/{product_id}
     * 바뀐 값만 보내면 된다. ⚠️ category_id 는 키 존재 여부로 '해제 의사'를 판별하므로 주의.
     * @param array $product 상품 정보 (product_id 필수)
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     * @throws \Exception
     */
    public function update($product, $idempotencyKey = null)
    {
        if (!isset($product['product_id']) || empty($product['product_id'])) {
            throw new \Exception('product_id is required');
        }
        $productId = $product['product_id'];
        return $this->bootpay->put(
            "products/{$productId}",
            $this->compact($product),
            $this->userHeaders($idempotencyKey)
        );
    }

    /**
     * 상품 판매/노출 상태 변경 (BOOTPAY-ROLE: user)
     * PUT /v1/products/{product_id}/status
     * ⚠️ 재고(stock)는 여기가 아니라 update 로 바꾼다.
     * @param array $params 상태 변경 파라미터 (product_id 필수 / idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     * @throws \Exception
     */
    public function status($params)
    {
        if (!isset($params['product_id']) || empty($params['product_id'])) {
            throw new \Exception('product_id is required');
        }
        $productId = $params['product_id'];
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['product_id'], $params['idempotency_key']);
        return $this->bootpay->put(
            "products/{$productId}/status",
            $this->compact($params),
            $this->userHeaders($idempotencyKey)
        );
    }

    /**
     * 상품 삭제 (BOOTPAY-ROLE: user)
     * DELETE /v1/products/{product_id}
     * @param string $productId 상품 ID
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function delete($productId, $idempotencyKey = null)
    {
        return $this->bootpay->delete("products/{$productId}", $this->userHeaders($idempotencyKey));
    }

    /**
     * 상품 쓰기(등록/수정/삭제/상태변경) 요청 헤더
     * 26-11-02 BOOTPAY-ROLE manager → user. 서버 스코프는 user:product_create/update/delete/status 뿐이라
     * manager 로 보내면 manager:product_* 를 찾다 401 API_ROLE_NOT_SUPPORT(-50) 로 막혔다.
     */
    private function userHeaders($idempotencyKey = null)
    {
        return array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: user'
        );
    }

    /**
     * V1 Mall API 요청 헤더
     * Idempotency-Key 는 미지정시 매 호출마다 생성되고, Bootpay-User-JWT 는 값이 있을 때만 붙는다.
     */
    private function mallHeaders($userJwt = null, $idempotencyKey = null)
    {
        $headers = array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey())
        );
        if ($userJwt !== null && $userJwt !== '') {
            $headers[] = 'Bootpay-User-JWT: ' . $userJwt;
        }
        return $headers;
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

    private function buildQueryString($params)
    {
        if ($params === null || empty($params)) {
            return '';
        }

        $query = array();
        if (isset($params['page'])) {
            $query['page'] = $params['page'];
        }
        if (isset($params['limit'])) {
            $query['limit'] = $params['limit'];
        }
        if (isset($params['keyword'])) {
            $query['keyword'] = $params['keyword'];
        }
        if (isset($params['category_id'])) {
            $query['category_id'] = $params['category_id'];
        }
        if (isset($params['ex_uid'])) {
            $query['ex_uid'] = $params['ex_uid'];
        }
        if (isset($params['sort'])) {
            $query['sort'] = $params['sort'];
        }
        // 아래 4개는 서버가 읽지 않는다 — 기존 호출을 깨지 않으려고 전송만 유지한다
        if (isset($params['type'])) {
            $query['type'] = $params['type'];
        }
        if (isset($params['period_type'])) {
            $query['period_type'] = $params['period_type'];
        }
        if (isset($params['s_at'])) {
            $query['s_at'] = $params['s_at'];
        }
        if (isset($params['e_at'])) {
            $query['e_at'] = $params['e_at'];
        }
        if (isset($params['category_code'])) {
            $query['category_code'] = $params['category_code'];
        }

        if (empty($query)) {
            return '';
        }

        return '?' . http_build_query($query);
    }
}
