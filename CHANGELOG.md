### 2.9.3

Ruby SDK parity — 알림톡 발송에 건별 결과 웹훅 주소 `webhook_url` 추가 (`05ea556`). **기존 메서드·파라미터 제거 없음.**

`alimtalkSend->send()` · `alimtalkSend->sendBulk()` 가 `webhook_url` 을 받는다.
주면 그 건(벌크는 그 요청으로 나간 모든 수신자 건)의 발송 성공·실패·문자(LMS) 대체발송·예약취소 웹훅이
**이 주소로만** 가고, 프로젝트 웹훅 설정(`alimtalkWebhook->update()`)은 쓰이지 않는다.

- `https` 만 허용하며 2,000자를 넘으면 `3028` 로 거부된다. 벌크는 형식이 틀리면 **요청 전체**가 거부된다.
- 서명은 프로젝트 시크릿으로 한다. 웹훅 설정 없이 시크릿만 필요하면 `alimtalkWebhook->rotateSecret()` 으로 발급받을 수 있다.
- ⚠️ 같은 `ref_id` 로 이미 접수·성공한 건을 다시 요청하면 기존 접수가 그대로 돌아오고 **새 주소는 무시된다**(멱등).
- 다른 선택 파라미터와 같이 미지정(`null`)이면 키 자체를 보내지 않는다 — 프로젝트 웹훅 설정을 따른다.

```php
$response = $bootpay->alimtalkSend->send(array(
    'template_code' => 'template_code_here',
    'to'            => '01012345678',
    'ref_id'        => 'order-0001',
    'webhook_url'   => 'https://example.com/hooks/alimtalk'
));

// 벌크는 요청 단위로 하나 — 이 요청으로 나간 모든 수신자 건의 결과가 같은 주소로 온다
$response = $bootpay->alimtalkSend->sendBulk(array(
    'template_code' => 'template_code_here',
    'recipients'    => array(array('to' => '01012345678', 'ref_id' => 'bulk-0001')),
    'webhook_url'   => 'https://example.com/hooks/alimtalk'
));
```

### 2.9.2

Ruby SDK parity — 알림톡 API 를 메시지 API 호스트로 (`3f959fc`). **기존 메서드·파라미터 제거 없음.**

알림톡 API 는 이제 커머스 API(`api.bootapi.com/v1`)가 아니라 메시지 API(`message.bootapi.com`, 경로에 `/v1` 없음)가 받는다.
경로·파라미터·응답은 그대로이고 호스트만 다르므로, 알림톡 모듈은 그대로 두고 공통 계층(`BootpayCommerceApi`)에서
요청 경로가 `alimtalk` 으로 시작하면 메시지 API 호스트로 보낸다. 옛 주소(`/v1/alimtalk/*`)는 410 으로 응답한다.

| mode | 알림톡 | 그 외 |
| --- | --- | --- |
| `development` | `https://dev-m.bootapi.com` | `https://dev-api.bootapi.com/v1` |
| `stage` | `https://stage-m.bootapi.com` | `https://stage-api.bootapi.com/v1` |
| `production` | `https://message.bootapi.com` | `https://api.bootapi.com/v1` |

- JSON(`request`)·원문(`requestRaw`)·multipart(`requestMultipart`) 세 경로 모두 적용된다.
- `setMessageApiUrl($url)` 추가 — 현재 mode 의 알림톡 호스트를 덮어쓴다 (Ruby `set_message_api_url`).

### 2.9.1

Ruby SDK parity — 별건 현금영수증의 `pg` 를 선택 파라미터로 (`c716a1f`). **기존 메서드·파라미터 제거 없음.**

Ruby 의 `request_cash_receipt(pg:, ...)` 가 `request_cash_receipt(pg: nil, ...)` 로 바뀌었다.
PG 를 지정하지 않으면 가맹점에 설정된 **기본 PG** 로 현금영수증이 발행된다.

PHP 는 파라미터를 연관배열로 받으므로 호출부에서 `pg` 를 빼는 것 자체는 원래도 가능했지만,
그렇게 하면 요청 바디에서 키가 통째로 사라져 Ruby 가 보내는 wire 와 달라진다.
Ruby 쪽 `request` 는 payload 를 `.compact` 하지 않아 `"pg": null` 이 그대로 실려 나가기 때문이다.
그래서 `requestCashReceipt()` 는 `pg` 키가 없을 때 `null` 을 채워 **항상 payload 에 싣는다** (`array_key_exists` 기준이라
호출부가 명시한 `null` 도 그대로 유지된다).

```php
// pg 생략 — 가맹점 기본 PG 로 발행
BootpayApi::requestCashReceipt(array(
    'price'             => 1000,
    'tax_free'          => 0,
    'order_name'        => '테스트 상품',
    'order_id'          => 'order_' . time(),
    'cash_receipt_type' => '소득공제',
    'identity_no'       => '01012345678'
));
```

검증은 offline wire 스위트(`tests/pg/PgHttpWireTest.php`)에 붙였다 — echo 서버로 실제 curl 바디를 받아
`pg` 생략시 `"pg": null`, 명시시 지정한 값이 나가는지 본다.

### 2.9.0

Ruby SDK parity — 알림톡 v1 API 35종 (`8f1ee1e`). **기존 메서드·파라미터 제거 없음.**

`/v1/alimtalk` 계열을 7개 모듈로 나눠 붙였다. Ruby 의 concern 파일 구성을 그대로 따랐다.

| 모듈 | 메서드 | 엔드포인트 |
| --- | --- | --- |
| `alimtalkSender` | `categories` `otp` `create` `getList` `detail` `release` `variableExamples` | `/v1/alimtalk/categories` · `/v1/alimtalk/senders*` |
| `alimtalkTemplate` | `getList` `create` `detail` `update` `delete` `register` `inspect` `export` `image` `highlightImage` | `/v1/alimtalk/templates*` |
| `alimtalkSend` | `send` `sendBulk` `cancel` | `/v1/alimtalk/send*` |
| `alimtalkMessage` | `getList` `stats` `detail` | `/v1/alimtalk/messages*` |
| `alimtalkOptout` | `getList` `create` `check` `release` | `/v1/alimtalk/optouts*` |
| `alimtalkOfficial` | `getList` `recommend` `detail` | `/v1/alimtalk/official*` |
| `alimtalkWebhook` | `detail` `update` `test` `rotateSecret` `deliveries` | `/v1/alimtalk/webhook*` |

> ⚠️ **발송 계열은 샌드박스가 없다.** `alimtalkSend->send`/`sendBulk` 는 실제로 카카오톡이 나가고 과금되며,
> `alimtalkSender->otp` 는 관리자폰으로 실제 문자를, `alimtalkSender->create` · `alimtalkTemplate->register`/`inspect` 는
> 카카오에 실제 등록·검수 요청을 보낸다.

#### 알림톡 계열의 요청 규약 2가지

- **`BOOTPAY-ROLE: user` 고정** — 서버 스코프가 전부 `user:alimtalk_*` 다. 인스턴스 role 이 `manager` 로 바뀌어 있어도 알림톡 호출은 `user` 로 나간다.
- **`Idempotency-Key` 를 붙이지 않는다** — 알림톡 API 는 이 헤더를 읽지 않는다. invoice/product 처럼 무조건 붙이면
  서버가 주지 않는 멱등 보장을 SDK 가 주는 것처럼 보인다. 멱등은 발송 payload 의 `ref_id` 로만 성립한다.

#### `fallback` · `enabled` · `register` 의 false 는 잘리지 않는다

`fallback` 은 **미지정(생략)과 `false` 의 의미가 다르다** — 생략하면 프로젝트 기본값을 따르고 `false` 는 명시적으로 끈다.
`isset()` 기반으로 파라미터를 걸렀다면 `false` 가 통째로 사라져 "껐는데 폴백이 나가는" 조용한 실패가 된다.
그래서 알림톡 모듈의 파라미터 필터는 `array_key_exists()` + `!== null` 로 본다 (Ruby 의 `.compact` 와 같은 기준).
같은 이유로 쿼리스트링의 bool 은 `http_build_query` 기본 직렬화(`1`/`''`)가 아니라 `true`/`false` 문자열로 보낸다 (`sync=false`, `include_content=false`).

#### 공통 계층 — `requestRaw()` / `getRaw()` 추가

`GET /v1/alimtalk/templates/export` 는 `format=csv` 일 때 CSV 본문을 돌려준다.
공용 `request()` 는 응답을 무조건 `json_decode` 하므로 **성공한 요청인데도 `null` 이 돌아와** "통신 실패" 로 오인된다.
그래서 파싱하지 않는 원문 경로를 따로 뒀다. `export()` 는 `format` 기본값을 `json` 으로 두고(서버 기본은 csv),
`csv` 를 주면 `{ body: '<원문 문자열>', content_type: '...', status: 200 }` 을 돌려준다.

#### 공통 계층 — multipart 파일 필드명 지정 지원

알림톡 템플릿 이미지는 파일 필드명이 상품의 `images[n]` 이 아니라 **`image`** 다.
`requestMultipart()` 의 `$files` 배열이 **문자열 키면 그 이름을 필드명으로 그대로** 쓰고, 정수 키는 종전대로 `images[0]`, `images[1]` … 로 인덱싱한다.
기존 상품 이미지 업로드 호출은 정수 키라 동작이 그대로다.

#### 문서 · 테스트

- README 에 `8. 알림톡` 절 추가 (발신프로필 · 공식 카탈로그 · 자체 템플릿 · 발송 · 발송내역 · 수신거부 · 웹훅).
- 라이브 스크립트 `tests/commerce/Alimtalk.php` 추가 — 조회 계열만 실행하고 부작용이 있는 호출은 주석으로 남겼다.
- `tests/commerce/AlimtalkWireFormatTest.php` 신설 (offline 스위트 등록) — 35종 전부의 URL · 헤더 · 바디 규약,
  `Idempotency-Key` 미부착, `false` 보존, `keyword`→`q` 매핑, csv 원문 분기, `image` 필드명, PSR-4 단독 autoload 검증.
- `CommerceHttpWireTest` 에 실제 curl wire 검증 4건 추가 (multipart `image` 필드명, csv 원문 응답, json 기본값, `fallback: false` 실전송).

### 2.8.0

#### `product.list` 의 조회 필터를 서버 실제 계약에 맞춤

서버(`v1/products_controller#index`)가 읽는 것은 **page · limit · keyword · category_id · ex_uid · sort** 뿐인데,
``product->getList()`` 은 정작 그중 `category_id` · `ex_uid` · `sort` 를 **보내지 않고**, 서버가 읽지 않는
`type` · `period_type` · `s_at` · `e_at` · `category_code` 만 보내고 있었다.
필터가 걸린 줄 알았는데 전체 목록이 돌아오는, `member_type` → `membership_type` 과 같은 조용한 실패였다.

- `조회 파라미터 배열` 에 **`category_id` / `ex_uid` / `sort`** 추가 — 서버가 읽는 값이라 이제 실제로 필터가 걸린다.
- 서버가 읽지 않는 `type` / `period_type` / `s_at` / `e_at` / `category_code` 는 **전송은 그대로 유지**하되(기존 호출 보호) 무시된다는 경고를 문서에 달았다.
  `type` 은 서버의 상품 타입 필터가 문자열(`subscription`/`discount`/`normal`)이라 이 숫자 필드와 값 체계 자체가 다르다.
- ⚠️ `keyword` 는 **26-08-26 서버 변경부터** 실제로 적용된다 (그 이전 배포본에서는 무시된다).
  같은 라운드에서 `GET /v1/products` 의 `sort` 가 항상 무시되던 서버 버그도 함께 고쳤다 — SDK 쪽 변경은 없다.


Ruby SDK parity — SDK 에 누락돼 있던 조회·수정 파라미터 보강 (`d4c8989`). 서버는 이미 읽고 있었는데 SDK 가 보내지 않아 쓸 수 없던 것들이다. **제거된 메서드·파라미터 없음.**

#### 주문 목록 — `order->getList`

- `status` / `payment_status` / `order_subscription_ids` 가 **배열뿐 아니라 단일 값·콤마 문자열**도 받는다 (배열만 콤마로 잇던 것을 일반화).
- 값이 비어 있으면(`[]`) 아예 전송하지 않는다. 이전에는 `status=` 처럼 빈 값이 실려 나갔다 (서버는 무시했지만 노이즈였다).
- `order_subscription_ids` · `subscription_billing_type` 은 2.7.0 에서 이미 지원한다 — 구독 계약별 · 결제유형별 필터.

#### 정기구독 목록 — `orderSubscription->getList(['order_number' => ...])`

주문번호로 구독을 역조회한다. 서버(`#index`)가 `params[:order_number]` 를 읽는데 SDK 가 보내지 않고 있었다.
⚠️ 날짜 키는 `search_date_from`/`search_date_to` (또는 `s_at`/`e_at`) 다 — `order->getList` 의 `css_at`/`cse_at` 와 다르다.

#### 구독 계약 변경 — `orderSubscription->update(['memo' => ...])`

변경이력(`SUBSCRIPTION_ACTION_UPDATE`)에 남길 사유다. 파라미터 배열은 패스스루라 시그니처는 그대로다.

#### 상품 목록 — `product->products(['ex_uid' => ...])`

외부 UID 로 상품을 찾는다. 컨트롤러가 `params[:ex_uid]` 를 읽는데 SDK 에 인자가 없었다.

#### 상품 상세 — `product->detail($productId, $userJwt = null, $idempotencyKey = null)`

매뉴얼이 `GET /v1/products/:id` 에 `user_jwt` 를 안내하는데 `detail` 만 헤더를 보내지 않아 회원 컨텍스트 조회가 안 됐다. 이제 `productDetail` 과 동작이 같다 (`Idempotency-Key` 자동 부착, `user_jwt` 지정시 `Bootpay-User-JWT` 헤더). 인자는 모두 선택이라 기존 `detail($productId)` 호출은 그대로 동작한다.

#### 회원 목록 — `user->getList(['membership_type' => ...])`

서버(`v1/users_controller#index`)가 읽는 회원등급 필터 키는 `membership_type` 인데 SDK 는 `member_type` 을 보내고 있어 **필터가 에러 없이 무시되고 전체 목록이 돌아왔다.** 이제 `membership_type` 으로 보낸다. 기존 호출 호환을 위해 `member_type` 도 계속 받아 같은 키로 매핑한다 (둘 다 있으면 `membership_type` 우선).

#### 테스트

- `CommerceWireFormatTest` 에 구독 필터 · 스칼라 목록값 · 빈 목록값 생략 · `order_number` · `memo` · `ex_uid` · `detail` 의 `user_jwt` · `membership_type` 별칭 회귀 8건 추가.

### 2.7.0

Ruby SDK parity — 구독 기준금액 변경 · 회차 범위 조정항목 (`9832af9`). **공개 API 변화 없음** (두 메서드 모두 배열 파라미터 패스스루라 시그니처는 그대로다).

#### 구독 기준금액 변경 — `orderSubscription->update(['price' => ...])`

`price` 는 회차별 결제 금액의 **기준금액**이다. 바꾸면 결제예정(READY) 회차의 청구액이 즉시 다시 계산되고, 이후 회차도 이 금액으로 만들어진다. 이미 결제된 회차는 그대로다. 0 이하는 받지 않는다. 특정 회차만 가감하려면 `orderSubscriptionAdjustment->create` 를 쓴다. (관리자 화면의 금액 변경과 같은 구현을 탄다)

#### 회차 범위로 조정항목 추가 — `orderSubscriptionAdjustment->create`

`duration_from` / `duration_to` / `is_unlimited` 를 받는다. 회차 지정 방법 3가지 (아래로 갈수록 넓다):

- `duration: 5` → 5회차 한 건만
- `duration_from: 3, duration_to: 7` → 3~7회차 각각 한 건씩 (총 5건)
- `duration_from: 3, is_unlimited: true` → 3회차부터 계약 끝까지 (레코드는 1건, `duration_to` 는 무시)

상한은 계약 총회차이며, 총회차가 무제한인 계약은 60회차까지다. 이미 결제가 끝난 회차는 거절된다. 범위 중 한 회차라도 최종 금액이 음수면 전부 거절된다 (부분 반영 없음).

#### 테스트

- `CommerceWireFormatTest` 에 회차 범위 · 무제한 범위 · `is_unlimited=false` 보존 · 기준금액 전송 회귀 4건 추가.

### 2.6.1

패키징·안전성 개선. **공개 API 변화 없음** (2.6.0 대비 메서드 추가·삭제·시그니처 변경 0건).

#### PSR-4 정합 — `OrderSubscriptionRequestIngModule` 파일 분리

`OrderSubscriptionRequestIngModule` 이 `src/Commerce/OrderSubscriptionModule.php` 안에 함께 정의돼 있어, 이 클래스를 **직접 참조하면 autoload 가 실패**할 수 있었다 (`OrderSubscriptionModule` 이 먼저 로드된 경우에만 우연히 동작). `src/Commerce/OrderSubscriptionRequestIngModule.php` 로 분리했다.

- 네임스페이스·클래스명은 그대로다 (`Bootpay\ServerPhp\Commerce\OrderSubscriptionRequestIngModule`).
- 기존 사용법 `$commerce->orderSubscription->requestIng->...` 은 변화 없다.

#### 알 수 없는 mode 에 경고 추가

2.6.0 에서 알 수 없는 mode 를 `production` 으로 폴백하도록 바꿨는데, 결제 SDK 에서 `developmnet` 같은 **오타가 조용히 production 으로 나가면 실거래가 발생한다**. 폴백은 유지하되 `E_USER_WARNING` 을 함께 낸다.

- 정상 mode(`development`/`stage`/`production`)에는 경고가 없다.
- 경고는 실행을 막지 않으므로 기존 동작이 깨지지 않는다.

### 2.6.0

#### Commerce scope(BOOTPAY-ROLE) 정합성 (동작 변경)

서버(commerce-api)가 `scope_invalid!` 로 supervisor / manager scope 를 요구하는 10개 엔드포인트가 `BOOTPAY-ROLE: user` 로 나가고 있었다. 요청 단위로 올바른 scope 를 붙인다. Java SDK 3.3.0 · Ruby SDK 와 같은 규약이다.

- `orderSubscription` — `supervisorApprove` / `supervisorReject` / `supervisorTerminate` / `supervisorPause` / `supervisorResume` → **supervisor**
- `category` — `create` / `update` / `destroy` → **supervisor**
- `userGroup` — `userCreate` / `userDelete` → **manager**

부수 효과로 이 10개 호출에 `Idempotency-Key` 가 자동 부착된다 (다른 supervisor 메서드·Ruby SDK 와 동일). 요청 경로·바디는 변경 없다.
⚠️ 그동안 이 API 들은 올바른 키로도 scope 오류로 거절됐다. 우회하려고 role 을 직접 조작하던 코드가 있다면 제거해도 된다.

- 파라미터 배열에 `idempotency_key` (optional) 를 추가했다. 지정하면 그 값이 `Idempotency-Key` 헤더로 나가고 바디에는 실리지 않는다. `category->destroy($categoryId, $idempotencyKey = null)` / `userGroup->userCreate($userGroupId, $userId, $idempotencyKey = null)` / `userGroup->userDelete(...)` 는 선택 인자로 받는다.

#### 알 수 없는 mode 는 production 폴백 (버그 수정)

- `BootpayApi` · `BootpayCommerceApi` 가 `$API_URL[$mode]` 를 그대로 인덱싱해서, mode 오타 하나에 Warning 이 뜨고 base URL 이 빈 문자열로 붕괴 → 요청 URL 이 `/{path}` 가 되어 curl errno 3 (`URL rejected: No host part in the URL`) 이 났다. production 은 `display_errors=Off` 라 Warning 이 보이지 않아 **mode 와 무관해 보이는 curl 에러만 남았다.**
- 이제 알 수 없는 mode 는 `production` 으로 폴백한다 (Go · Java SDK 와 같은 규칙).

#### 테스트

- `tests/commerce/CommerceScopeTest.php` 신설 (offline 스위트에 등록) — 10개 엔드포인트의 scope·Idempotency-Key 와 mode 폴백 회귀.
- `CommerceHttpWireTest` 의 `BOOTPAY-SDK-VERSION` 단정에서 버전 리터럴을 걷어내고 `composer.json` 의 패키지 버전을 따라가는지로 바꿨다 (버전 올릴 때마다 깨지던 테스트).

### 2.5.0

NodeJS SDK 2.9.0 parity.

* PG: `BootpayApi::lookupSequentialBillingKey($widgetKey, $billingKey, $userId)` 추가 — `GET subscribe/sequential_billing_key/{billing_key}?widget_key=&user_id=` (우선순위/순차 결제 빌링키 조회).
* Commerce: 신규 모듈 2종 추가.
  * `MallSettingModule` (`mallSetting`) — `getMallSetting`/`detail`, `updateMallSetting`/`update` (supervisor 전용, update 는 flatten 바디에 null 값 미전송).
  * `WebhookModule` (`webhook`) — `sendTest([...])` (`POST webhook/test`, `header_content_type` 파라미터).
* Commerce: 수시결제(온디맨드) charge_key 결제/해지 추가 (supervisor 전용).
  * `orderSubscription->supervisorCharge($params)` — `POST order_subscriptions/charge`. charge_key 는 body 로만 전송 (URL/query 금지).
  * `orderSubscription->supervisorChargeRevoke($params)` — `DELETE order_subscriptions/charge` (body 전송). 해지 후 해당 키로 재결제 불가.
  * 두 endpoint 모두 `Idempotency-Key` 헤더 자동 생성 (`idempotency_key` 파라미터로 직접 지정 가능).
* Commerce: 쇼핑몰(V1 Mall API) 회원 endpoint 추가 — 복수형 `users/...` 경로 (v1 에 단수 `user/...` 라우트 없음).
  * `user->userLogin`/`userSession`/`userLogout`/`userJoin`/`userJoinCheck`/`uidExist`.
  * 세션이 필요한 호출은 회원 JWT 를 `Bootpay-User-JWT` 헤더로 전달 (값이 있을 때만 부착).
* Commerce: 상품 조회 Mall API 추가.
  * `product->products($params)` — `page`/`limit` 기본값 1/20, `category_id`/`sort` 파라미터 및 `user_jwt` 지원.
  * `product->productDetail($productId, $userJwt, $idempotencyKey)`.
* Commerce: 구독 변경 요청 endpoint 추가 — `orderSubscription->requestIng->purchase()` (중도인수) / `transfer()` (이전/승계).
* Commerce: multipart 전송 정비 — `product->create()` 는 이미지가 없으면 JSON, 있으면 multipart(`images[0]`, `images[1]` … 인덱싱)로 전송. multipart 요청에도 요청별 헤더(Idempotency-Key, BOOTPAY-ROLE)가 병합되며 Content-Type 은 지정하지 않아 boundary 가 유실되지 않는다.
  * multipart 의 boolean 필드는 `'true'`/`'false'` 문자열로 직렬화 (curl 기본 직렬화는 `false`→`""` 로 서버에서 nil 로 읽히는 문제가 있었다 — NodeJS `String(bool)` 동등).
* Commerce: 인자·응답 규약 정정.
  * `invoice->getList` 응답 data 는 `{ list, count }` — phpdoc 정정, `limit` 기본값 24, `cs_type`/`user_id`/`product_type`/`css_at`/`cse_at` 파라미터 추가.
  * `invoice->notify` 의 `$sendTypes` 를 선택 인자로 변경 (미전달시 서버가 빈 배열로 처리).
  * `orderCancel->approve`/`reject`/`withdraw` 인자명을 `order_cancellation_request_id` 로 통일 (구 이름 `order_cancel_request_history_id` 도 계속 지원). `withdraw` 는 문자열/배열 모두 허용.
  * `orderSubscriptionAdjustment->delete` 는 대상 ID 를 query 가 아니라 body 로 전송.
  * `orderSubscriptionAdjustment->update` 에 `adjustments` 배열 지원 (서버는 `duration` 회차 단위로 교체), `duration` 기본값 1.
  * `userGroup->limit` 에 `limit_month_purchase`/`limit_week_purchase` 지원 명시 (서버 정식 인자명; `update` 로는 한도가 반영되지 않는다).
  * `order->getList` 에 `search_date_from`/`search_date_to` 추가 (`css_at`/`cse_at` 는 서버 별칭으로 계속 지원).
  * `orderSubscription->getList` 에 `search_date_from`/`search_date_to`/`status` 추가.
  * `orderSubscriptionRequest->getList` 에 `order_subscription_id`/`user_id`/`user_group_id` 추가, `page`/`limit` 기본값 1/20.
  * `orderSubscriptionBill->getList` 에 `page`/`limit` 기본값 1/20 적용 (NodeJS HEAD 동작 미러링).
* Commerce: 서버가 요구하는 `BOOTPAY-ROLE` scope 를 endpoint 별로 명시 — 상품 쓰기/그룹 한도는 `manager`, 구독 계약변경·조정항목·요청 승인·charge·mallSetting 은 `supervisor`, 나머지는 `user`.
  * `orderSubscriptionRequest->getList`/`detail` 은 `project_id` 가 있으면 `supervisor`, 없으면 `user`.
  * 요청별로 지정된 `BOOTPAY-ROLE` 헤더를 공통 계층이 기본 role 과 중복 전송하지 않도록 수정 (`mergeHeaders`).
  * `store->getStore`/`getStoreDetail` (`info`/`detail` 별칭 유지) 에 `Idempotency-Key` 헤더 부착 (인자로 직접 지정 가능).
* 공통: `BootpayCommerceApi::delete()` 에 optional `$data` 인자 추가 (DELETE body 지원, 기존 호출 불변). `generateIdempotencyKey()` (uuid v4) 추가.
* 테스트: 네트워크 없는 wire-format 검증 인프라 추가.
  * mock 계층 — `tests/RecordingCommerceApi.php` + `tests/commerce/CommerceWireFormatTest.php` (모듈이 공통 계층에 넘기는 URL·헤더·바디 검증).
  * 실전송 계층 — `tests/wire-echo-server.php` + `tests/WireEchoTestCase.php` + `tests/commerce/CommerceHttpWireTest.php` + `tests/pg/PgHttpWireTest.php` (로컬 echo 서버로 curl 이 실제 전송하는 wire 검증: multipart boundary 유지, `images[n]` 인덱싱, DELETE body, 헤더 병합 결과).
  * 하위호환 — `tests/commerce/BackwardCompatibilityTest.php` (v2.4.1 공개 API 표면 동결 계약: 메서드 존재·public·필수 파라미터 수 미증가). `tests/echo-suite-bootstrap.php` 로 기존 라이브 테스트 스위트를 echo 서버로 회귀 실행 가능.
  * 신규 endpoint 라이브 스크립트 추가 (development 전용).
  * phpunit 안전 구조 — `phpunit.xml` 에 `defaultTestSuite="offline"` 도입: 무인자 `vendor/bin/phpunit` 은 오프라인 스위트만 실행 (외부 요청 0건). 라이브 테스트(`PG`/`Commerce` 스위트)는 `TestConfig::requireLiveEnvironment()` 가드로 `BOOTPAY_ENV=development` 가 아니면 skip (production 실호출 방지).

### 2.4.1
* PHP 8.5 호환성: `curl_close()` 호출 제거 (PHP 8.0+ 부터 no-op, PHP 8.5 부터 deprecated). 동작 변화 없음.
  * `src/BootpayApi.php`, `src/BootpayCommerceApi.php` (json + multipart).

### 2.4.0
* Commerce: 5개 모듈 신규 추가 (NodeJS SDK parity).
  * `Category`, `Coupon`, `Point`, `Cart`, `OrderSubscriptionRequest` 모듈을 `src/Commerce/` 에 추가.
  * `BootpayCommerceApi` facade 에 5개 모듈 wire-up.
  * `list` 가 PHP 예약어 충돌이 있는 위치에서는 `getList()` 네이밍 사용.
* Commerce: user-group URL parity 수정 — `/add_user` → `/user`, `/remove_user` → `/user/{userId}`.
* chore: `vendor/` 디렉토리 gitignore 처리 및 트래킹 해제 (composer build artifact).

### 2.3.0
* 인증: client_key/secret_key Basic Auth 지원 (PG + Commerce 공통).
  * 기존 application_id/private_key Bearer 방식 하위 호환 유지.
  * `BootpayApi::setClientKeyConfiguration($clientKey, $secretKey, $mode)` 추가 — application_id/private_key 와 같이 쓸 경우 ck/sk 가 우선.
  * ck/sk 모드는 매 요청 자동 Basic Auth 헤더 부착 — `getAccessToken()` 은 합성 응답 (`{access_token: '', expire_in: 0}`) 을 반환하며 `request/token` 호출이 발생하지 않음.
* Commerce: `BootpayCommerceApi` 의 모든 호출이 ck/sk Basic Auth 사용.
* `getUserWallets`, `requestWalletPayment` `@deprecated` 처리 (`E_USER_DEPRECATED`) — 다음 메이저 버전에서 제거 예정.
* 테스트 인프라: `.env` / `BOOTPAY_AUTH_MODE=new|legacy` / `BOOTPAY_ENV` 토글로 ck/sk · legacy 양쪽 검증. `tests/pg/LegacyCompatibilityTest.php` 단위 테스트 추가.

### 2.2.0
Commerce API 추가

### 2.0.0
v1 -> v2 update