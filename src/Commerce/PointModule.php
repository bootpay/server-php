<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

class PointModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 적립금 잔액 조회
     * @return object
     */
    public function balance()
    {
        return $this->bootpay->get('point/balance', array('BOOTPAY-ROLE: user'));
    }

    /**
     * 적립금 내역 조회
     * @param array|null $params 조회 파라미터 (page, limit, transaction_type)
     * @return object
     */
    public function transactions($params = null)
    {
        $queryString = $this->buildQueryString($params);
        return $this->bootpay->get('point/transactions' . $queryString, array('BOOTPAY-ROLE: user'));
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
        if (isset($params['transaction_type'])) {
            $query['transaction_type'] = $params['transaction_type'];
        }

        if (empty($query)) {
            return '';
        }

        return '?' . http_build_query($query);
    }
}
