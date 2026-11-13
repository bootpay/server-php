# Bootpay PHP SDK 테스트 가이드

```bash
composer instal
```


## 폴더 구조

```
tests/
├── config.php          # 공통 설정 (API 키, 환경 설정, 테스트 데이터)
├── pg/                 # PG API 테스트
│   ├── GetAccessToken.php
│   ├── LookupReceiptPayment.php
│   ├── CancelReceipt.php
│   ├── Certificate.php
│   └── ...
└── commerce/           # Commerce API 테스트
    └── ...
```

## 환경 설정

`tests/config.php`에서 환경을 설정합니다:

```php
// development 또는 production
const CURRENT_ENV = 'production';
```

## 테스트 실행 방법

### 1. Composer 설치 확인

프로젝트 루트에서 의존성을 설치합니다:

```bash
cd backend-php  # 리포 루트
composer install
```

### 2. PG API 테스트

`tests/pg/` 폴더로 이동 후 개별 파일 실행:

```bash
cd tests/pg

# 토큰 발급 테스트
php GetAccessToken.php

# 결제 조회 테스트
php LookupReceiptPayment.php

# 결제 취소 테스트
php CancelReceipt.php

# 본인인증 결과 조회 테스트
php Certificate.php

# 빌링키 테스트
php GetBillingKey.php
php LookupBillingKey.php
php LookupSubscribeBillingKey.php
php DestroyBillingKey.php

# 정기결제 테스트
php RequestSubscribeCardPayment.php
php RequestSubscribePayment.php
php SubscribePaymentReserve.php
php CancelSubscribeReserve.php

# 계좌 자동이체 빌링키
php RequestSubscribeAutomaticTransferBillingKey.php
php publishAutomaticTransferBillingKey.php

# 현금영수증 테스트
php RequestCashReceipt.php
php CancelCashReceipt.php
php CashPublishOnReceipt.php
php CashCancelOnReceipt.php

# 에스크로 테스트
php ShippingStart.php

# 사용자 토큰 테스트
php RequestUserToken.php

# 서버 승인 테스트
php ConfirmPayment.php
```

### 3. Commerce API 테스트

`tests/commerce/` 폴더로 이동 후 실행:

```bash
cd tests/commerce

# 개별 테스트 파일 실행
php TestFile.php
```

## PG API 테스트 목록

| 파일명 | 설명 |
|--------|------|
| `GetAccessToken.php` | 토큰 발급 |
| `LookupReceiptPayment.php` | 결제 조회 |
| `CancelReceipt.php` | 결제 취소 |
| `Certificate.php` | 본인인증 결과 조회 |
| `ConfirmPayment.php` | 서버 승인 |
| `GetBillingKey.php` | 빌링키 발급 |
| `LookupBillingKey.php` | 빌링키 조회 |
| `LookupSubscribeBillingKey.php` | 정기결제 빌링키 조회 |
| `DestroyBillingKey.php` | 빌링키 삭제 |
| `RequestSubscribeCardPayment.php` | 카드 정기결제 실행 |
| `RequestSubscribePayment.php` | 정기결제 실행 |
| `SubscribePaymentReserve.php` | 예약 결제 등록 |
| `CancelSubscribeReserve.php` | 예약 결제 취소 |
| `RequestSubscribeAutomaticTransferBillingKey.php` | 계좌 자동이체 빌링키 요청 |
| `publishAutomaticTransferBillingKey.php` | 계좌 자동이체 빌링키 발급 |
| `RequestCashReceipt.php` | 현금영수증 발급 |
| `CancelCashReceipt.php` | 현금영수증 취소 |
| `CashPublishOnReceipt.php` | 결제건 현금영수증 발급 |
| `CashCancelOnReceipt.php` | 결제건 현금영수증 취소 |
| `ShippingStart.php` | 에스크로 배송 시작 |
| `RequestUserToken.php` | 사용자 토큰 발급 |
| `AuthenticateRequestRest.php` | 본인인증 요청 |
| `AuthenticateConfirmRest.php` | 본인인증 확인 |
| `AuthenticateRealarmRest.php` | 본인인증 재알림 |

## 주의사항

1. **실제 결제 테스트**: `CancelReceipt.php`, `LookupReceiptPayment.php` 등은 실제 receipt_id가 필요합니다.
2. **환경 선택**: `config.php`의 `CURRENT_ENV`로 development/production 환경을 선택합니다.
3. **순서 의존성**: 일부 테스트는 다른 테스트 결과(receipt_id, billing_key 등)가 필요합니다.
4. **테스트 데이터**: `config.php`의 `TEST_DATA` 배열에서 테스트에 사용할 ID 값들을 관리합니다.

## PG 인증 방식 토글 (BOOTPAY_AUTH_MODE)

PG 테스트는 기본적으로 신규 `client_key/secret_key` 방식으로 동작한다. 매 실행 시 환경변수로 레거시 `application_id/private_key` 방식으로 전환할 수 있다.

### 토글 contract

| `BOOTPAY_AUTH_MODE` | 동작 |
|---|---|
| `new` (기본, 미설정 시 동일) | `BootpayApi::setClientKeyConfiguration(clientKey, secretKey, env)` 호출. Basic Auth 헤더 자동 부착. |
| `legacy` | `BootpayApi::setConfiguration(applicationId, privateKey, env)` 호출. `getAccessToken()` 호출 후 `Bearer` 헤더 사용. |

키 값은 모두 `.env` (또는 환경변수) 로 주입한다 — `.env.example` 참고.

### 사용법

```bash
# (1) 기본 — env var 생략 (= new)
php tests/pg/ReceiptPayment.php

# (2) 한 번만 legacy 로 전환
BOOTPAY_AUTH_MODE=legacy php tests/pg/ReceiptPayment.php

# (3) PHPUnit 도 동일하게 환경변수로 토글
BOOTPAY_AUTH_MODE=legacy ./vendor/bin/phpunit
BOOTPAY_AUTH_MODE=legacy ./vendor/bin/phpunit --filter PgPaymentTest

# (4) 셸 세션 동안 legacy 고정
export BOOTPAY_AUTH_MODE=legacy
php tests/pg/ReceiptPayment.php
./vendor/bin/phpunit
unset BOOTPAY_AUTH_MODE

# (5) 영구 전환 — .env 의 BOOTPAY_AUTH_MODE 값을 legacy 로 바꾸면 셸 export 없이도 동작
```

### 진입 헬퍼 — 어디서 토글이 흡수되는가

| 테스트 종류 | 위치 | 헬퍼 |
|---|---|---|
| Standalone 스크립트 (`tests/pg/*.php`) | `tests/config.php` | `setupActiveBootpayApi()` |
| PHPUnit 통합 테스트 (`tests/Pg/*Test.php`) | `tests/TestConfig.php` | `TestConfig::setupPgApi()` / `setupPgApiWithToken()` |

PHPUnit `setupPgApiWithToken()` 은 legacy 모드에서만 실제 토큰 발급을 수행한다. 어느 모드든 테스트 코드는 동일하다:

```php
// Standalone
require_once __DIR__ . '/../config.php';
setupActiveBootpayApi();

// PHPUnit
self::setupPgApiWithToken();
```

### 실행 시 인증 모드 표시

진입 헬퍼가 호출될 때마다 stdout 에 한 줄로 어떤 모드가 활성화됐는지 표시된다 (PHPUnit 는 `--debug` 또는 stdout 직접 출력 시 확인):

```
[BOOTPAY_AUTH_MODE=new] PG: client_key/secret_key (Basic Auth) | env=production
[BOOTPAY_AUTH_MODE=legacy] PG: application_id/private_key (Bearer) | env=production
```

### 토글의 영향을 받지 않는 파일

다음 테스트는 두 모드를 한 함수 안에서 모두 검증하므로 환경변수에 무관하게 동일한 동작을 한다:

- `tests/pg/LegacyCompatibilityTest.php`
