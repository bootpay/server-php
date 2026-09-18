# Bootpay Server Side Package for PHP [![Packagist Version](https://img.shields.io/packagist/v/bootpay/server-php)](https://packagist.org/packages/bootpay/server-php)

## Bootpay PHP Server Side Library

부트페이 공식 PHP 라이브러리 입니다 (서버사이드 용)

PHP 언어로 작성된 어플리케이션, 프레임워크 등에서 사용가능합니다.

* PG 결제창 연동은 클라이언트 라이브러리에서 수행됩니다. (Javascript, Android, iOS, React Native, Flutter 등)
* 결제 검증 및 취소, 빌링키 발급, 본인인증 등의 수행은 서버사이드에서 진행됩니다. (Java, PHP, Python, Ruby, Node.js, Go, ASP.NET 등)

## 목차

### PG API
- [사용하기](#사용하기)
  - [1. 토큰 발급](#1-토큰-발급)
  - [2. 결제 단건 조회](#2-결제-단건-조회)
  - [3. 결제 취소 (전액 취소 / 부분 취소)](#3-결제-취소-전액-취소--부분-취소)
  - [4. 자동/빌링/정기 결제](#4-자동빌링정기-결제)
    - [4-1. 카드 빌링키 발급](#4-1-카드-빌링키-발급)
    - [4-2. 계좌 빌링키 발급](#4-2-계좌-빌링키-발급)
    - [4-3. 결제 요청하기](#4-3-결제-요청하기)
    - [4-4. 결제 예약하기](#4-4-결제-예약하기)
    - [4-5. 예약 조회하기](#4-5-예약-조회하기)
    - [4-6. 예약 취소하기](#4-6-예약-취소하기)
    - [4-7. 빌링키 삭제하기](#4-7-빌링키-삭제하기)
    - [4-8. 빌링키 조회하기](#4-8-빌링키-조회하기)
  - [5. 회원 토큰 발급요청](#5-회원-토큰-발급요청)
  - [6. 서버 승인 요청](#6-서버-승인-요청)
  - [7. 본인 인증 결과 조회](#7-본인-인증-결과-조회)
  - [8. 에스크로 이용시 PG사로 배송정보 보내기](#8-에스크로-이용시-pg사로-배송정보-보내기)
  - [9. 현금영수증](#9-현금영수증)
    - [9-1. 현금영수증 발행하기](#9-1-현금영수증-발행하기)
    - [9-2. 현금영수증 발행 취소](#9-2-현금영수증-발행-취소)
    - [9-3. 별건 현금영수증 발행](#9-3-별건-현금영수증-발행)
    - [9-4. 별건 현금영수증 발행 취소](#9-4-별건-현금영수증-발행-취소)

### Commerce API
- [Commerce API 사용하기](#commerce-api-사용하기)
  - [1. Commerce 토큰 발급](#1-commerce-토큰-발급)
  - [2. 사용자 관리](#2-사용자-관리)
  - [3. 사용자 그룹 관리](#3-사용자-그룹-관리)
  - [4. 상품 관리](#4-상품-관리)
  - [5. 주문 관리](#5-주문-관리)
  - [6. 청구서 관리](#6-청구서-관리)
  - [7. 정기구독 관리](#7-정기구독-관리)
  - [8. 알림톡](#8-알림톡)
    - [8-1. 발신프로필(카카오채널)](#8-1-발신프로필카카오채널)
    - [8-2. 공식 템플릿 카탈로그](#8-2-공식-템플릿-카탈로그)
    - [8-3. 자체 템플릿](#8-3-자체-템플릿)
    - [8-4. 발송](#8-4-발송)
    - [8-5. 발송내역·집계](#8-5-발송내역집계)
    - [8-6. 수신거부](#8-6-수신거부)
    - [8-7. 웹훅](#8-7-웹훅)

### 기타
- [Example 프로젝트](#example-프로젝트)
- [Documentation](#documentation)
- [기술문의](#기술문의)
- [License](#license)

---

## 설치하기

### Composer로 설치

```bash
composer require bootpay/server-php
```

### 요구사항

- PHP >= 5.3.0
- ext-json

---

# PG API

#
## 환경변수 설정

예제와 테스트는 각 SDK 루트의 `.env` 파일을 우선 읽습니다. 먼저 `.env.example`을 복사한 뒤 필요한 키만 변경하세요. `.env`는 gitignore 처리되어 커밋되지 않습니다.

```bash
cp .env.example .env
# BOOTPAY_ENV=production 또는 development
```

주요 변수:

```env
BOOTPAY_ENV=production
BOOTPAY_PG_CLIENT_KEY_PROD=...
BOOTPAY_PG_SECRET_KEY_PROD=...
BOOTPAY_PG_CLIENT_KEY_DEV=...
BOOTPAY_PG_SECRET_KEY_DEV=...
BOOTPAY_COMMERCE_CLIENT_KEY_PROD=...
BOOTPAY_COMMERCE_SECRET_KEY_PROD=...
BOOTPAY_COMMERCE_CLIENT_KEY_DEV=...
BOOTPAY_COMMERCE_SECRET_KEY_DEV=...
```

변수가 없으면 SDK 테스트용 기본값(NodeJS 기준 ck/sk)으로 fallback 합니다.

# 사용하기

```php
<?php
require_once 'vendor/autoload.php';

use Bootpay\ServerPhp\BootpayApi;

BootpayApi::setClientKeyConfiguration(
    getenv('BOOTPAY_PG_CLIENT_KEY_PROD'),
    getenv('BOOTPAY_PG_SECRET_KEY_PROD')
);
// Legacy application_id/private_key 방식은 setConfiguration()으로 계속 지원됩니다.

$response = BootpayApi::getAccessToken();
var_dump($response);
```

> 함수 단위의 샘플 코드는 [tests/pg](https://github.com/bootpay/backend-php/tree/main/tests/pg) 폴더를 참조하세요.

## 1. 토큰 발급

부트페이와 서버간 통신을 하기 위해서는 부트페이 서버로부터 토큰을 발급받아야 합니다.
발급된 토큰은 30분간 유효하며, 최초 발급일로부터 30분이 지날 경우 토큰 발급 함수를 재호출 해주셔야 합니다.

```php
<?php
require_once 'vendor/autoload.php';

use Bootpay\ServerPhp\BootpayApi;

BootpayApi::setClientKeyConfiguration(
    getenv('BOOTPAY_PG_CLIENT_KEY_PROD'),
    getenv('BOOTPAY_PG_SECRET_KEY_PROD')
);

$response = BootpayApi::getAccessToken();
var_dump($response);
```

## 2. 결제 단건 조회

결제창 및 정기결제에서 승인/취소된 결제건에 대하여 올바른 결제건인지 서버간 통신으로 결제검증을 합니다.

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::receiptPayment('receipt_id_here');
    var_dump($response);
}
```

## 3. 결제 취소 (전액 취소 / 부분 취소)

price를 지정하지 않으면 전액취소 됩니다.

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::cancelPayment(array(
        'receipt_id' => 'receipt_id_here',
        'cancel_price' => 1000,           // 부분취소 금액 (없으면 전액취소)
        'cancel_tax_free' => 0,
        'cancel_username' => '관리자',
        'cancel_message' => '테스트 결제 취소'
    ));
    var_dump($response);
}
```

## 4. 자동/빌링/정기 결제

### 4-1. 카드 빌링키 발급

REST API 방식으로 고객으로부터 카드 정보를 전달하여, PG사에게 빌링키를 발급받을 수 있습니다.

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::getSubscribeBillingKey(
        'nicepay',                    // PG사
        'subscription_' . time(),     // subscription_id
        '30일 정기권 결제',            // order_name
        '5570********1074',           // card_no
        '12',                         // card_pw (앞 2자리)
        '25',                         // card_expire_year
        '12',                         // card_expire_month
        '901012'                      // card_identity_no (생년월일 또는 사업자번호)
    );
    var_dump($response);
}
```

### 4-2. 계좌 빌링키 발급

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::requestSubscribeAutomaticTransferBillingKey(array(
        'pg' => 'nicepay',
        'subscription_id' => 'subscription_' . time(),
        'order_name' => '자동이체 등록',
        'auth_type' => 'ARS',
        'username' => '홍길동',
        'bank_name' => '국민은행',
        'bank_account' => '12345678901234',
        'identity_no' => '901012'
    ));
    var_dump($response);
}
```

### 4-3. 결제 요청하기

발급된 빌링키로 원하는 시점에 결제 승인 요청을 합니다.

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::requestSubscribeCardPayment(array(
        'billing_key' => 'billing_key_here',
        'order_name' => '정기결제 테스트',
        'price' => 1000,
        'order_id' => 'order_' . time()
    ));
    var_dump($response);
}
```

### 4-4. 결제 예약하기

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::subscribePaymentReserve(array(
        'billing_key' => 'billing_key_here',
        'order_name' => '예약결제 테스트',
        'price' => 1000,
        'order_id' => 'order_' . time(),
        'reserve_execute_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
    ));
    var_dump($response);
}
```

### 4-5. 예약 조회하기

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::subscribePaymentReserveLookup('reserve_id_here');
    var_dump($response);
}
```

### 4-6. 예약 취소하기

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::cancelSubscribeReserve('reserve_id_here');
    var_dump($response);
}
```

### 4-7. 빌링키 삭제하기

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::destroyBillingKey('billing_key_here');
    var_dump($response);
}
```

### 4-8. 빌링키 조회하기

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::lookupSubscribeBillingKey('receipt_id_here');
    var_dump($response);
}
```

## 5. 회원 토큰 발급요청

부트페이에서 제공하는 간편결제창, 생체인증 기반의 결제 사용을 위해 사용자 토큰을 발급합니다.

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::requestUserToken(array(
        'user_id' => 'user_123',
        'phone' => '01012345678'
    ));
    var_dump($response);
}
```

## 6. 서버 승인 요청

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::confirmPayment('receipt_id_here');
    var_dump($response);
}
```

## 7. 본인 인증 결과 조회

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::certificate('receipt_id_here');
    var_dump($response);
}
```

## 8. 에스크로 이용시 PG사로 배송정보 보내기

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::shippingStart(array(
        'receipt_id' => 'receipt_id_here',
        'tracking_number' => '1234567890',
        'delivery_corp' => 'CJ대한통운',
        'user' => array(
            'username' => '홍길동',
            'phone' => '01012345678'
        )
    ));
    var_dump($response);
}
```

## 9. 현금영수증

### 9-1. 현금영수증 발행하기

기존 결제건에 대해 현금영수증을 발행합니다.

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::cashReceiptPublishOnReceipt(array(
        'receipt_id' => 'receipt_id_here',
        'identity_no' => '01012345678',
        'cash_receipt_type' => '소득공제'
    ));
    var_dump($response);
}
```

### 9-2. 현금영수증 발행 취소

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::cashReceiptCancelOnReceipt('receipt_id_here');
    var_dump($response);
}
```

### 9-3. 별건 현금영수증 발행

결제 건과 별개로 현금영수증을 발행합니다.

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::requestCashReceipt(array(
        'pg' => 'nicepay',
        'price' => 1000,
        'order_name' => '테스트 상품',
        'order_id' => 'order_' . time(),
        'cash_receipt_type' => '소득공제',
        'identity_no' => '01012345678'
    ));
    var_dump($response);
}
```

`pg` 는 **선택 파라미터**입니다. 생략하면 가맹점에 설정된 기본 PG 로 발행됩니다.

```php
$response = BootpayApi::requestCashReceipt(array(
    'price' => 1000,
    'order_name' => '테스트 상품',
    'order_id' => 'order_' . time(),
    'cash_receipt_type' => '소득공제',
    'identity_no' => '01012345678'
));
```

### 9-4. 별건 현금영수증 발행 취소

```php
$token = BootpayApi::getAccessToken();
if (!$token->error_code) {
    $response = BootpayApi::cancelCashReceipt('receipt_id_here');
    var_dump($response);
}
```

---

# Commerce API

## Commerce API 사용하기

```php
<?php
require_once 'vendor/autoload.php';

use Bootpay\ServerPhp\BootpayCommerceApi;

$bootpay = new BootpayCommerceApi(
    'your_client_key',
    'your_secret_key',
    'production'  // 또는 'development'
);

try {
    // 토큰 발급
    $bootpay->getAccessToken();

    // API 호출 예시
    $response = $bootpay->user->getList(array('page' => 1, 'limit' => 10));
    print_r($response);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

> 함수 단위의 샘플 코드는 [tests/commerce](https://github.com/bootpay/backend-php/tree/main/tests/commerce) 폴더를 참조하세요.

## 1. Commerce 토큰 발급

Commerce API 사용을 위한 토큰을 발급받습니다.

```php
$bootpay = new BootpayCommerceApi(
    'your_client_key',
    'your_secret_key',
    'production'
);

$bootpay->getAccessToken();
```

## 2. 사용자 관리

### 사용자 토큰 발급
```php
$response = $bootpay->user->token('user_id_here');
```

### 사용자 가입
```php
$response = $bootpay->user->join(array(
    'login_id' => 'test_user',
    'login_pw' => 'password123',
    'username' => '홍길동',
    'email' => 'test@example.com',
    'phone' => '01012345678'
));
```

### 사용자 목록 조회
회원등급 필터의 서버 키는 `membership_type` 입니다. 기존 호환을 위해 `member_type` 으로 넘겨도 `membership_type` 으로 전송됩니다.

```php
$response = $bootpay->user->getList(array(
    'page' => 1,
    'limit' => 10,
    'membership_type' => 1
));
```

### 사용자 상세 조회
```php
$response = $bootpay->user->detail('user_id_here');
```

### 사용자 정보 수정
```php
$response = $bootpay->user->update(array(
    'user_id' => 'user_id_here',
    'username' => '변경된 이름'
));
```

### 사용자 삭제
```php
$response = $bootpay->user->delete('user_id_here');
```

## 3. 사용자 그룹 관리

### 그룹 생성
```php
$response = $bootpay->userGroup->create(array(
    'company_name' => '테스트 회사',
    'business_number' => '1234567890',
    'ceo_name' => '홍길동',
    'corporate_type' => 2
));
```

### 그룹 목록 조회
```php
$response = $bootpay->userGroup->getList(array(
    'page' => 1,
    'limit' => 10
));
```

### 그룹 상세 조회
```php
$response = $bootpay->userGroup->detail('user_group_id_here');
```

### 그룹 수정
```php
$response = $bootpay->userGroup->update(array(
    'user_group_id' => 'user_group_id_here',
    'company_name' => '변경된 회사명'
));
```

## 4. 상품 관리

### 상품 목록 조회
```php
$response = $bootpay->product->getList(array(
    'page' => 1,
    'limit' => 10
));

// Mall API — category_id / ex_uid / sort 필터 지원 (ex_uid 는 외부 UID 로 상품 조회)
$response = $bootpay->product->products(array(
    'page' => 1,
    'limit' => 10,
    'ex_uid' => 'EX-1'
));
```

### 상품 생성
```php
$response = $bootpay->product->create(array(
    'name' => '테스트 상품',
    'price' => 10000,
    'type' => 1
));
```

### 상품 상세 조회
`user_jwt` 를 함께 넘기면 회원 컨텍스트로 조회합니다 (선택).

```php
$response = $bootpay->product->detail('product_id_here');
$response = $bootpay->product->detail('product_id_here', 'user_jwt_here');
```

### 상품 수정
```php
$response = $bootpay->product->update(array(
    'product_id' => 'product_id_here',
    'name' => '변경된 상품명'
));
```

### 상품 삭제
```php
$response = $bootpay->product->delete('product_id_here');
```

## 5. 주문 관리

### 주문 목록 조회
`status` / `payment_status` / `order_subscription_ids` 는 배열·단일 값·콤마 문자열을 모두 받습니다 (빈 값은 전송하지 않습니다).
날짜 필터 키는 `search_date_from` / `search_date_to` 입니다.

```php
$response = $bootpay->order->getList(array(
    'page' => 1,
    'limit' => 10,
    'status' => array(1, 2),
    'order_subscription_ids' => array('order_subscription_id_here'),
    'subscription_billing_type' => 1
));
```

### 주문 상세 조회
```php
$response = $bootpay->order->detail('order_id_here');
```

### 주문 취소 요청
```php
$response = $bootpay->orderCancel->request(array(
    'order_id' => 'order_id_here',
    'cancel_reason' => '고객 요청'
));
```

## 6. 청구서 관리

### 청구서 목록 조회
```php
$response = $bootpay->invoice->getList(array(
    'page' => 1,
    'limit' => 10
));
```

### 청구서 생성
```php
$response = $bootpay->invoice->create(array(
    'user_id' => 'user_id_here',
    'price' => 10000,
    'order_name' => '청구서 테스트'
));
```

### 청구서 알림 발송
```php
$response = $bootpay->invoice->notify('invoice_id_here', array(
    'send_types' => array(1)  // 1: SMS
));
```

## 7. 정기구독 관리

### 정기구독 목록 조회
`order_number` 로 주문번호 역조회가 가능합니다. 날짜 필터 키는 `search_date_from` / `search_date_to` (또는 `s_at` / `e_at`) 입니다.

```php
$response = $bootpay->orderSubscription->getList(array(
    'page' => 1,
    'limit' => 10,
    'order_number' => 'order_number_here'
));
```

### 정기구독 상세 조회
```php
$response = $bootpay->orderSubscription->detail('order_subscription_id_here');
```

### 정기구독 일시정지
```php
$response = $bootpay->orderSubscription->requestIng->pause(array(
    'order_subscription_id' => 'order_subscription_id_here'
));
```

### 정기구독 재개
```php
$response = $bootpay->orderSubscription->requestIng->resume(array(
    'order_subscription_id' => 'order_subscription_id_here'
));
```

### 해지 수수료 계산
```php
$response = $bootpay->orderSubscription->requestIng->calculateTerminationFee(array(
    'order_subscription_id' => 'order_subscription_id_here'
));
```

### 정기구독 해지
```php
$response = $bootpay->orderSubscription->requestIng->termination(array(
    'order_subscription_id' => 'order_subscription_id_here'
));
```

### 구독 가격(기준금액) 변경
`price` 는 회차별 결제 금액의 기준금액입니다. 변경하면 결제예정(READY) 회차의 청구액이 즉시 다시 계산되고, 이후 회차도 이 금액으로 생성됩니다. 이미 결제된 회차는 그대로입니다. 0 이하는 받지 않습니다.

```php
$response = $bootpay->orderSubscription->update(array(
    'order_subscription_id' => 'order_subscription_id_here',
    'price' => 19900,
    'memo' => '고객 요청 금액 변경' // 변경이력에 남길 사유 (선택)
));
```

### 회차별 가감산 조정
특정 회차만 금액을 가감하려면 조정항목을 사용합니다. 회차 지정 방법은 3가지입니다.

```php
// 5회차 한 건만
$response = $bootpay->orderSubscriptionAdjustment->create('order_subscription_id_here', array(
    'duration' => 5,
    'price' => -1000,
    'name' => '5회차 할인'
));

// 3~7회차 각각 한 건씩 (총 5건)
$response = $bootpay->orderSubscriptionAdjustment->create('order_subscription_id_here', array(
    'duration_from' => 3,
    'duration_to' => 7,
    'price' => -1000,
    'name' => '3~7회차 할인'
));

// 3회차부터 계약 끝까지 (레코드 1건, duration_to 는 무시)
$response = $bootpay->orderSubscriptionAdjustment->create('order_subscription_id_here', array(
    'duration_from' => 3,
    'is_unlimited' => true,
    'price' => -1000,
    'name' => '3회차 이후 상시 할인'
));
```

> 상한은 계약 총회차이며, 총회차가 무제한인 계약은 60회차까지입니다. 이미 결제가 끝난 회차는 거절되며, 범위 중 한 회차라도 최종 금액이 음수면 전부 거절됩니다 (부분 반영 없음).

## 8. 알림톡

카카오 알림톡 발송·템플릿·수신거부·웹훅을 다루는 `/alimtalk` 계열 API입니다.

> 알림톡 API 는 커머스 API(`api.bootapi.com/v1`)가 아니라 **메시지 API 호스트**가 받습니다 (경로에 `/v1` 없음).
> SDK 가 경로로 호스트를 자동 선택하므로 호출 코드는 바꿀 필요가 없습니다.
>
> | mode | 알림톡 호스트 |
> | --- | --- |
> | `development` | `https://dev-m.bootapi.com` |
> | `stage` | `https://stage-m.bootapi.com` |
> | `production` | `https://message.bootapi.com` |
>
> 주소를 바꿔야 하면 `$commerce->setMessageApiUrl('https://...')` 로 현재 mode 의 알림톡 호스트를 덮어씁니다.

> ⚠️ **발송 계열은 샌드박스가 없습니다.** `alimtalkSend->send()` · `sendBulk()` 는 실제로 카카오톡이 나가고 과금되며,
> `alimtalkSender->otp()` 는 채널 관리자폰으로 실제 문자를, `alimtalkSender->create()` · `alimtalkTemplate->register()` ·
> `alimtalkTemplate->inspect()` 는 카카오에 실제 등록·검수 요청을 보냅니다.

> 알림톡 계열은 서버 스코프가 전부 `user:alimtalk_*` 라 `BOOTPAY-ROLE: user` 로 고정 전송됩니다.
> 또한 **`Idempotency-Key` 헤더를 붙이지 않습니다** — 알림톡 API 는 이 헤더를 읽지 않으며, 멱등은 발송 payload 의 `ref_id` 로만 성립합니다.

### 8-1. 발신프로필(카카오채널)

카테고리 조회 → OTP 발송 → 발신프로필 등록 → 목록/상세 → 연동 해지 순으로 사용합니다.
등록이 끝나면 서버가 그룹키 등록까지 수행하므로 공식 템플릿은 별도 채택 없이 바로 발송됩니다.

```php
// 등록에 필요한 category_code 후보 조회
$response = $bootpay->alimtalkSender->categories();

// ⚠️ 채널 관리자폰으로 실제 문자가 발송됩니다
$response = $bootpay->alimtalkSender->otp(array(
    'yellow_id' => '@bootpay',
    'phone' => '01012345678'
));

// ⚠️ 카카오에 발신프로필이 실제 등록됩니다 (같은 yellow_id 재등록 시 기존 프로필 재사용)
$response = $bootpay->alimtalkSender->create(array(
    'otp' => '123456',
    'yellow_id' => '@bootpay',
    'phone' => '01012345678',
    'category_code' => '001001'
));

$response = $bootpay->alimtalkSender->getList();
$response = $bootpay->alimtalkSender->detail('ksp_id_here');        // 자체 DB 만 조회
$response = $bootpay->alimtalkSender->detail('ksp_id_here', true);  // 벤더에서 상태 재조회 (느립니다)

// 템플릿 미리보기용 변수 예문 (표시용이며 발송값이 아닙니다. 보낸 키만 덮어씁니다)
$response = $bootpay->alimtalkSender->variableExamples('ksp_id_here', array(
    'user_name' => '홍길동',
    'company_name' => '부트페이몰'
));

// 이 프로젝트와의 연동만 끊습니다 (채널 모델·템플릿은 보존)
$response = $bootpay->alimtalkSender->release('ksp_id_here');
```

### 8-2. 공식 템플릿 카탈로그

부트페이가 미리 카카오 승인을 받아 둔 템플릿이라, 그룹키가 등록된 채널이면 **검수 없이 즉시 발송**됩니다. 전부 조회 계열이라 부작용이 없습니다.

```php
// keyword 는 본문·이름·분류 부분일치. msg_type 은 BA(기본형)·EX(부가정보형)만 존재합니다.
$response = $bootpay->alimtalkOfficial->getList(array(
    'keyword' => '주문',
    'category' => '주문',
    'msg_type' => 'BA',
    'page' => 1,
    'per' => 20,     // 서버 기본 20, 최대 100
    'ksp_id' => 'ksp_id_here'
));

// 보내려는 문구로 유사 템플릿 추천 (score 내림차순)
$response = $bootpay->alimtalkOfficial->recommend(array(
    'text' => '주문이 완료되었습니다',
    'limit' => 5
));

$response = $bootpay->alimtalkOfficial->detail('official_code_here');
```

### 8-3. 자체 템플릿

(초안 생성 → 확인 → 대행사 등록) → 검수 요청 → 승인(APR) → 발송 가능 순서입니다.

> ⚠️ `register` 를 명시적으로 `false` 로 주지 않으면 **생성 즉시 대행사·카카오에 실제 등록**됩니다.
> 본문 변수는 `#{변수명}` 형식이며 템플릿 전체에서 최대 40개입니다.

```php
// ins: 1 REG(등록) / 2 REQ(검수요청) / 3 APR(승인) / 4 KRR(등록거절) / 5 REJ(승인반려) — 'APR' 같은 문자열도 받습니다
// ⚠️ 페이지네이션이 없습니다 (필터에 걸린 템플릿을 한 번에 모두 반환)
$response = $bootpay->alimtalkTemplate->getList(array(
    'ins' => 'APR',
    'sort' => 'latest',  // latest(기본) / oldest / code
    'keyword' => '주문'
));

// 초안만 생성 (권장)
$response = $bootpay->alimtalkTemplate->create(array(
    'ksp_id' => 'ksp_id_here',
    'name' => '주문완료 안내',
    'content' => "#{user_name}님, 주문이 완료되었습니다.",
    'register' => false,
    'msg_type' => 'BA',           // BA / EX(template_extra 필수) / AD / MI (AD·MI 는 채널추가 버튼 필수)
    'emphasize_type' => 'NONE',   // NONE / TEXT / IMAGE / ITEM_LIST
    'examples' => array('user_name' => '홍길동')  // 주면 모든 변수에 예문이 있어야 합니다
));

// ⚠️ sync 는 서버 기본값이 true 라 조회만 해도 벤더 동기화가 일어납니다. 초안은 false 권장.
$response = $bootpay->alimtalkTemplate->detail('template_id_here', false);

// ⚠️ 부분 수정이 아닙니다 — 보내지 않은 필드는 null 로 덮어써집니다. 항상 전체 필드를 보내세요.
$response = $bootpay->alimtalkTemplate->update('template_id_here', array(
    'name' => '주문완료 안내',
    'content' => "#{user_name}님, 주문이 완료되었습니다."
));

$response = $bootpay->alimtalkTemplate->register('template_id_here'); // ⚠️ 대행사·카카오 실제 등록
$response = $bootpay->alimtalkTemplate->inspect('template_id_here');  // ⚠️ 카카오 검수 요청 (취소 불가)
$response = $bootpay->alimtalkTemplate->delete('template_id_here');

// 이미지 업로드 — 돌려받은 image_url 을 create/update 의 storage_image_url 로 넘깁니다
$response = $bootpay->alimtalkTemplate->image('/path/to/body.png');            // jpg/png · 500KB 이하 · 가로 500px 이상 · 2:1
$response = $bootpay->alimtalkTemplate->highlightImage('/path/to/thumb.png');  // jpg/png · 500KB 이하 · 가로 108px 이상 · 1:1
```

내보내기는 `format` 기본값이 `json` 입니다. `csv` 로 주면 응답을 파싱하지 않고 원문 그대로 돌려줍니다.

```php
$response = $bootpay->alimtalkTemplate->export(array(
    'scope' => 'private',   // private(기본) / official / all
    'ksp_id' => 'ksp_id_here',
    'status' => 'APR',
    'include_content' => true
));

// csv 는 { body: '<원문 문자열>', content_type: '...', status: 200 } 로 돌아옵니다
$csv = $bootpay->alimtalkTemplate->export(array('format' => 'csv', 'scope' => 'private'));
echo $csv->body;
```

> 1회 5,000건을 넘으면 거부(3031)되므로 채널·상태 필터로 좁혀 주세요.

### 8-4. 발송

> ⚠️ **실제로 카카오톡이 발송되고 과금됩니다.**

```php
$response = $bootpay->alimtalkSend->send(array(
    'template_code' => 'template_code_here',
    'to' => '01012345678',
    'variables' => array('user_name' => '홍길동', 'company_name' => '부트페이몰'),
    'ref_id' => 'order-0001',                     // 멱등 키 — 같은 값으로 재요청하면 기존 receipt 를 반환
    'fallback' => false,                          // 알림톡 실패 시 문자(LMS) 대체발송
    'reserved_at' => '2026-09-01T10:00:00+09:00', // 생략하면 즉시 발송
    'sender_key' => 'sender_key_here'             // 연동 채널이 둘 이상일 때만 필수
));
```

> `fallback` 은 **미지정(생략)과 `false` 의 의미가 다릅니다.** 생략하면 프로젝트 기본값을 따르고, `false` 는 명시적으로 끕니다.
> 켜면 발신번호가 등록돼 있어야 하며 없으면 거부(3030)됩니다.

```php
// 벌크 발송 (1요청 = N수신자) — ⚠️ 수신자 수만큼 실제 발송·과금됩니다
$response = $bootpay->alimtalkSend->sendBulk(array(
    'template_code' => 'template_code_here',
    'recipients' => array(
        array('to' => '01012345678', 'ref_id' => 'bulk-0001', 'variables' => array('user_name' => '홍길동')),
        array('to' => '01087654321', 'ref_id' => 'bulk-0002', 'variables' => array('user_name' => '김철수'))
    )
));

// 예약 발송 취소 — 접수(READY) 상태의 예약 건만 가능합니다
$response = $bootpay->alimtalkSend->cancel('receipt_id_here');
```

> 벌크는 쿼터를 넘으면 요청 시점에 **전체 거부**(3022)됩니다. 수신거부 번호는 `skipped` 로 빠지며 과금되지 않습니다.

### 8-5. 발송내역·집계

**유료** 알림톡만 조회됩니다(무료 커머스 알림톡은 포함되지 않습니다).

```php
$response = $bootpay->alimtalkMessage->getList(array(
    'template_code' => 'template_code_here',
    'status' => 'success',   // requested / success / failed / canceled
    'ref_id' => 'order-0001',
    'to' => '01012345678',
    's_at' => '2026-08-01',
    'e_at' => '2026-08-27',
    'page' => 1,
    'limit' => 20            // 서버 기본 20, 최대 100
));

$response = $bootpay->alimtalkMessage->stats(array('s_at' => '2026-08-01', 'e_at' => '2026-08-27'));
$response = $bootpay->alimtalkMessage->detail('receipt_id_here');
```

> 기간 기본값은 최근 30일이고 최대 조회 폭은 92일입니다. 초과분은 거부하지 않고 시작일을 당겨 잘라내므로, 실제 적용 구간은 응답의 `period` 로 확인하세요.
> 집계의 `billing.unit_price_source` 가 `default` 면 **잠정 단가**입니다(확정 청구액이 아닙니다).

### 8-6. 수신거부

발송 판정과 같은 기준(부트페이 전역 + 내 프로젝트)으로 다룹니다.

```php
// phone 은 숫자만 남겨 부분일치로 찾습니다 (50건 단위 페이징)
$response = $bootpay->alimtalkOptout->getList(array('phone' => '1234', 'page' => 1));

$response = $bootpay->alimtalkOptout->create(array('phone' => '01012345678', 'reason' => 'CRM 동기화'));

// 발송 전 사전 확인 — 벌크에서 skipped 로 낭비될 건을 미리 뺄 수 있습니다 (1회 최대 1,000건)
$response = $bootpay->alimtalkOptout->check(array('phones' => array('01012345678', '01087654321')));
$response = $bootpay->alimtalkOptout->check(array('phone' => '01012345678'));

$response = $bootpay->alimtalkOptout->release('01012345678');
```

> ⚠️ 전역(global) 수신거부는 **조회는 되지만 해제할 수 없습니다**(`releasable: false`).
> 해제를 호출해도 전역 차단은 남고 응답의 `global_blocked: true` 로 알려 줍니다.

### 8-7. 웹훅

> ⚠️ **주문·구독 통합 웹훅(`webhook->sendTest()`)과 완전히 별개입니다.** 알림톡 이벤트를 기존 주문 웹훅 URL 로 태우면 수신 서버가 모르는 payload 를 받아 기존 연동이 깨집니다.

```php
$response = $bootpay->alimtalkWebhook->detail();   // 미설정이면 { configured: false }

// url 은 https 만 허용합니다. 최초 저장 시 서명 시크릿이 자동 발급됩니다.
$response = $bootpay->alimtalkWebhook->update(array(
    'url' => 'https://example.com/hooks/alimtalk',
    'events' => array(301, 302, 303, 304, 310, 311),
    'enabled' => true
));

$response = $bootpay->alimtalkWebhook->test();          // ⚠️ 설정된 URL 로 실제 요청이 나갑니다
$response = $bootpay->alimtalkWebhook->rotateSecret();  // ⚠️ 이 응답에서만 secret 원문을 돌려줍니다
$response = $bootpay->alimtalkWebhook->deliveries(array('page' => 1, 'limit' => 20));
```

이벤트 코드: `300` 발송 접수(기본 미구독) / `301` 전달 성공 / `302` 전달 실패 / `303` 예약 취소 / `304` 문자(LMS) 대체발송 전환 / `310` 검수 승인 / `311` 검수 반려 / `320` 수신거부 등록(기본 미구독). `events` 를 비우면 기본 구독셋(`301`·`302`·`303`·`304`·`310`·`311`)이 적용됩니다.

서명 검증은 다음 헤더로 합니다. 타임스탬프가 5분 이상 지난 요청은 거부하세요(replay 방지).

```
X-Bootpay-Signature: sha256=HMAC_SHA256(secret, "{X-Bootpay-Timestamp}.{raw_body}")
```


## Role 설정

Commerce API에서는 역할(Role)에 따라 접근 권한이 달라집니다.

```php
// 매니저 역할로 설정
$bootpay->asManager();

// 사용자 역할로 설정
$bootpay->asUser();

// 파트너 역할로 설정
$bootpay->asPartner();

// 역할 초기화
$bootpay->clearRole();
```

---

## Example 프로젝트

[적용한 샘플 프로젝트](https://github.com/bootpay/backend-php-example)를 참조해주세요

## Documentation

- [부트페이 결제 개발문서](https://developers.bootpay.ai)
- [부트페이 커머스 개발문서](https://commerce.bootpay.ai)

## 기술문의

[부트페이 홈페이지](https://www.bootpay.ai)에서 문의해주세요!

## License

[MIT License](https://opensource.org/licenses/MIT).
