<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

class UserGroupModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 사용자 그룹 생성
     * @param array $userGroup 그룹 정보
     * @return object
     */
    public function create($userGroup)
    {
        return $this->bootpay->post('user-groups', $userGroup, array('BOOTPAY-ROLE: user'));
    }

    /**
     * 사용자 그룹 목록 조회
     * @param array|null $params 조회 파라미터
     * @return object
     */
    public function getList($params = null)
    {
        $queryString = $this->buildQueryString($params);
        return $this->bootpay->get('user-groups' . $queryString, array('BOOTPAY-ROLE: user'));
    }

    /**
     * 사용자 그룹 상세 조회
     * @param string $userGroupId 그룹 ID
     * @return object
     */
    public function detail($userGroupId)
    {
        return $this->bootpay->get("user-groups/{$userGroupId}", array('BOOTPAY-ROLE: user'));
    }

    /**
     * 사용자 그룹 수정
     * @param array $userGroup 그룹 정보
     * @return object
     * @throws \Exception
     */
    public function update($userGroup)
    {
        if (!isset($userGroup['user_group_id']) || empty($userGroup['user_group_id'])) {
            throw new \Exception('user_group_id is required');
        }
        $userGroupId = $userGroup['user_group_id'];
        return $this->bootpay->put("user-groups/{$userGroupId}", $userGroup, array('BOOTPAY-ROLE: user'));
    }

    /**
     * 그룹에 사용자 추가
     * POST /v1/user-groups/{user_group_id}/user
     * ⚠️ 서버가 manager scope 를 요구한다 (scope_invalid!).
     * @param string $userGroupId 그룹 ID
     * @param string $userId 사용자 ID
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function userCreate($userGroupId, $userId, $idempotencyKey = null)
    {
        return $this->bootpay->post(
            "user-groups/{$userGroupId}/user",
            array('user_id' => $userId),
            $this->managerHeaders($idempotencyKey)
        );
    }

    /**
     * 그룹에서 사용자 제거
     * DELETE /v1/user-groups/{user_group_id}/user/{user_id}
     * ⚠️ 서버가 manager scope 를 요구한다 (scope_invalid!).
     * @param string $userGroupId 그룹 ID
     * @param string $userId 사용자 ID
     * @param string|null $idempotencyKey 미지정시 자동 생성
     * @return object
     */
    public function userDelete($userGroupId, $userId, $idempotencyKey = null)
    {
        return $this->bootpay->delete(
            "user-groups/{$userGroupId}/user/{$userId}",
            $this->managerHeaders($idempotencyKey)
        );
    }

    /**
     * 그룹 구매한도 설정 (manager 전용)
     * PUT /v1/user-groups/{user_group_id}/limit
     * ⚠️ update 로는 한도가 절대 반영되지 않는다 — 서버 user_groups_controller#update 가
     *    use_limit / limit_message / limit_month_purchase / limit_week_purchase 를 명시적으로 제거하기 때문이다.
     *    한도는 이 전용 라우트로만 바뀐다.
     * @param array $params 제한 설정 파라미터 (user_group_id 필수 / use_limit, limit_message,
     *                      limit_month_purchase, limit_week_purchase / idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     * @throws \Exception
     */
    public function limit($params)
    {
        if (!isset($params['user_group_id']) || empty($params['user_group_id'])) {
            throw new \Exception('user_group_id is required');
        }
        $userGroupId = $params['user_group_id'];
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['user_group_id'], $params['idempotency_key']);
        return $this->bootpay->put(
            "user-groups/{$userGroupId}/limit",
            $this->compact($params),
            $this->managerHeaders($idempotencyKey)
        );
    }

    /**
     * 그룹 구독 합산청구(정산주기) 설정 변경 (manager 전용)
     * PUT /v1/user-groups/{user_group_id}/aggregate-transaction
     * update 에도 같은 이름의 인자가 있지만 서버는 이 전용 라우트에서만 처리한다.
     * @param array $params 집계 파라미터 (user_group_id 필수 / idempotency_key 는 Idempotency-Key 헤더로 전송된다)
     * @return object
     * @throws \Exception
     */
    public function aggregateTransaction($params)
    {
        if (!isset($params['user_group_id']) || empty($params['user_group_id'])) {
            throw new \Exception('user_group_id is required');
        }
        $userGroupId = $params['user_group_id'];
        $idempotencyKey = isset($params['idempotency_key']) ? $params['idempotency_key'] : null;
        unset($params['user_group_id'], $params['idempotency_key']);
        return $this->bootpay->put(
            "user-groups/{$userGroupId}/aggregate-transaction",
            $this->compact($params),
            $this->managerHeaders($idempotencyKey)
        );
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
     * 그룹 한도/합산청구 설정 요청 헤더 — 서버가 manager scope 를 요구한다.
     * Idempotency-Key 는 미지정시 매 호출마다 생성된다.
     */
    private function managerHeaders($idempotencyKey = null)
    {
        return array(
            'Idempotency-Key: ' . ($idempotencyKey ?: BootpayCommerceApi::generateIdempotencyKey()),
            'BOOTPAY-ROLE: manager'
        );
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
        if (isset($params['corporate_type'])) {
            $query['corporate_type'] = $params['corporate_type'];
        }

        if (empty($query)) {
            return '';
        }

        return '?' . http_build_query($query);
    }
}
