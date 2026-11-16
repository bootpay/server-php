<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

class CouponModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 사용자 보유 쿠폰 목록
     * @param array|null $params 조회 파라미터 (status, page, limit)
     * @return object
     */
    public function getList($params = null)
    {
        $queryString = $this->buildQueryString($params);
        return $this->bootpay->get('coupon' . $queryString, array('BOOTPAY-ROLE: ' . $this->bootpay->userOrSupervisorRole()));
    }

    /**
     * 다운로드 가능한 쿠폰 목록
     * @return object
     */
    public function available()
    {
        return $this->bootpay->get('coupon/available', array('BOOTPAY-ROLE: ' . $this->bootpay->userOrSupervisorRole()));
    }

    /**
     * 쿠폰 다운로드 (issue_from_template)
     * @param array $params 다운로드 파라미터
     * @return object
     */
    public function download($params)
    {
        return $this->bootpay->post('coupon/download', $params, array('BOOTPAY-ROLE: user'));
    }

    private function buildQueryString($params)
    {
        if ($params === null || empty($params)) {
            return '';
        }

        $query = array();
        if (isset($params['status'])) {
            $query['status'] = $params['status'];
        }
        if (isset($params['page'])) {
            $query['page'] = $params['page'];
        }
        if (isset($params['limit'])) {
            $query['limit'] = $params['limit'];
        }

        if (empty($query)) {
            return '';
        }

        return '?' . http_build_query($query);
    }
}
