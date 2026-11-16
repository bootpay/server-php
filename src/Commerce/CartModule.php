<?php

namespace Bootpay\ServerPhp\Commerce;

use Bootpay\ServerPhp\BootpayCommerceApi;

class CartModule
{
    private $bootpay;

    public function __construct(BootpayCommerceApi $bootpay)
    {
        $this->bootpay = $bootpay;
    }

    /**
     * 주문 미리보기 (배송비/할인 권위적 계산)
     * POST /v1/cart/order-preview
     *
     * member_mode='guest' (기본): cart_items 필수
     * member_mode='member': 서버 장바구니 사용 (user 토큰 필요)
     *
     * @param array|null $params 주문 미리보기 파라미터
     * @return object
     */
    public function orderPreview($params = null)
    {
        if ($params === null) {
            $params = array();
        }
        return $this->bootpay->post('cart/order-preview', $params, array('BOOTPAY-ROLE: user'));
    }
}
