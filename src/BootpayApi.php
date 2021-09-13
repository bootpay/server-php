<?php namespace Bootpay\BackendPhp;

/**
 * Created by IntelliJ IDEA.
 * User: ehowlsla
 * Date: 2017. 8. 3.
 * Time: PM 5:37
 */



class BootpayApi
{
    use Singleton;

    public const BankCode = [        
        "한국은행" => "001",
        "기업은행" => "003",
        "국민은행" => "004",
        "외환은행" => "005",
        "수협은행" => "007",
        "농협은행" => "011",
        "우리은행" => "020",
        "SC은행" => "023",
        "대구은행" => "031",
        "부산은행" => "032",
        "광주은행" => "034",
        "경남은행" => "039",
        "우체국" => "071",
        "KEB하나은행" => "081",
        "신한은행" => "088",
        "케이뱅크" => "089",
        "카카오뱅크" => "090"
    ];

    public $mode = '';
    private $defaultParams = [];
    private $accessToken = null;

    private $baseUrl = [
        'development' => 'https://dev-api.bootpay.co.kr',
        'stage' => 'https://stage-api.bootpay.co.kr',
        'production' => 'https://api.bootpay.co.kr'
    ];

    public static function setConfig($applicationId, $privateKey, $mode = 'production')
    {
        static::instance();
        static::$instances->defaultParams = [
            'application_id' => $applicationId,
            'private_key' => $privateKey
        ];
        static::$instances->mode = $mode;
        return static::$instances;
    }

    # 1. 토큰 발급 
    public static function requestAccessToken()
    {
        $result = static::$instances->tokenInstance(static::$instances->defaultParams);
        if ($result->status === 200) {
            static::$instances->setAccessToken($result->data->token);
        }
        return $result;
    }

    # 2. 결제 검증 
    public static function verify($receiptId)
    {
        return static::$instances->verifyInstance($receiptId);
    }

    # 3. 결제 취소 (전액 취소 / 부분 취소)
    #
    # price - (선택사항) 부분취소 요청시 금액을 지정, 미지정시 전액 취소 (부분취소가 가능한 PG사, 결제수단에 한해 적용됨)
    # cancel_id - (선택사항) 부분취소 요청시 중복 요청을 방지하기 위한 고유값
    # refund - (선택사항) 가상계좌 환불요청시, 전제조건으로 PG사와 CMS 특약이 체결되어 있을 경우에만 환불요청 가능, 기본적으로 가상계좌는 결제취소가 안됨 
    # [
    #    account: '6756010101234', # 환불받을 계좌번호 
    #    accountholder: '홍길동', # 환불받을 계좌주
    #    bankcode: BootpayApi::BankCode['국민은행'] # 은행 코드 
    # ]
    #
    public static function cancel($receiptId, $price = null, $name = 'API 관리자', $reason = 'API에 의한 요청', $cancelId = null, $taxFree = null, $refund = null)
    {
        $data = [
            'receipt_id' => $receiptId,
            'price' => $price,
            'name' => $name,
            'reason' => $reason,
            'cancel_id' => $cancelId,
            'tax_free' => $taxFree,
            'refund' => $refund
        ];

        return static::$instances->cancelInstance($data);
    }


    # 4. 빌링키 발급 
    # user_info # 구매자 모델 설명 
    # [ 
    #      id: '', # 개발사에서 관리하는 회원 고유 id
    #      username: '', # 구매자 이름
    #      email: '', # 구매자 email
    #      phone: '', # 01012341234
    #      gender: 0 또는 1, # 0:여자, 1:남자
    #      area: '서울', # 서울|인천|대구|광주|부산|울산|경기|강원|충청북도|충북|충청남도|충남|전라북도|전북|전라남도|전남|경상북도|경북|경상남도|경남|제주|세종|대전 중 택 1
    #      birth: '' # 생일 901004
    # ],
    # extra - 빌링키 발급 옵션 
    # [
    #      subscribe_tst_payment: 0, # 100원 결제 후 결제가 되면 billing key를 발행, 결제가 실패하면 에러
    #      raw_data: 0 //PG 오류 코드 및 메세지까지 리턴
    #  ]  
    public static function getSubscribeBillingKey($pg, $orderId, $itemName, $cardNo, $cardPw, $expireYear, $expireMonth, $identifyNumber, $userInfo = null, $extra = null)
    {
        $data = [
            'pg' => $pg, # PG사의 Alias ex) danal, kcp, inicis 등
            'order_id' => $orderId, # 개발사에서 관리하는 고유 주문 번호
            'item_name' => $itemName, # 상품명
            'card_no' => $cardNo, # 카드 일련번호
            'card_pw' => $cardPw, # 카드 비밀번호 앞 2자리
            'expire_year' => $expireYear, # 카드 유효기간 년
            'expire_month' => $expireMonth, # 카드 유효기간 월
            'identify_number' => $identifyNumber, # 주민등록번호 또는 사업자번호
            'user_info' => $userInfo,  # 구매자 정보 
            'extra' => $extra  # 기타 옵션 
        ];

        return static::$instances->getSubscribeBillingKeyInstance($data);
    }

    # 4-1. 발급된 빌링키로 결제 승인 요청 
    # optional 선택사항 모델 설명 
    # {
    #     items: [], # item 모델 배열 
    #     userInfo: nil, # user_info 모델 
    #     extra: nil, # extra 모델 
    #     tax_free: 0, # 비과세 금액, 면세 상품일 경우 해당만큼의 금액을 설정
    #     quota: 0,  # int 형태, 5만원 이상 결제건에 적용하는 할부개월수. 0-일시불, 1은 지정시 에러 발생함, 2-2개월, 3-3개월... 12까지 지정가능
    #     interest: 0 또는 1, # 웰컴페이먼츠 전용, 무이자여부를 보내는 파라미터가 있다
    #     feedback_url: '', # webhook 통지시 받으실 url 주소 (localhost 사용 불가)
    #     feedback_content_type: ''  # webhook 통지시 받으실 데이터 타입 (json 또는 urlencoded, 기본값 urlencoded)
    # }
    # user_info # 구매자 모델 설명 
    # { 
    #             id: '', # 개발사에서 관리하는 회원 고유 id
    #             username: '', # 구매자 이름
    #             email: '', # 구매자 email
    #             phone: '', # 01012341234
    #             gender: 0 또는 1, # 0:여자, 1:남자
    #             area: '서울', # 서울|인천|대구|광주|부산|울산|경기|강원|충청북도|충북|충청남도|충남|전라북도|전북|전라남도|전남|경상북도|경북|경상남도|경남|제주|세종|대전 중 택 1
    #             birth: '' # 생일 901004
    # },
    # item 모델 설명
    # {
    #   item_name: '', # 상품명
    #   qty: 1, # 수량
    #   unique: '', # 상품 고유키
    #   price: 1000, # 상품단가
    #   cat1: '', # 카테고리 상
    #   cat2: '', # 카테고리 중
    #   cat3: '' # 카테고리 하
    # }
    # extra - 빌링키 발급 옵션 
    # {
    #   subscribe_tst_payment: 0, # 100원 결제 후 결제가 되면 billing key를 발행, 결제가 실패하면 에러
    #   raw_data: 0 //PG 오류 코드 및 메세지까지 리턴
    # }  
    public static function subscribeCardBilling($billingKey, $orderId, $itemName, $price, $optional=null)
    {
        $data = [
            'billing_key' => $billingKey, # 발급받은 빌링키
            'item_name' => $itemName, # 결제할 상품명
            'price' => $price, # 결제할 상품금액
            'order_id' => $orderId, # 개발사에서 지정하는 고유주문번호
            'items' => $optional['items'], # 구매할 상품정보, 통계용
            'user_info' => $optional['userInfo'], # 구매자 정보, 특정 PG사의 경우 구매자 휴대폰 번호를 필수로 받는다
            'extra' => $optional['extra'], # 기타 옵션 
            'tax_free' => $optional['taxFree'], # 면세 상품일 경우 해당만큼의 금액을 설정
            'quota' => $optional['quota'], # int 형태, 5만원 이상 결제건에 적용하는 할부개월수. 0-일시불, 1은 지정시 에러 발생함, 2-2개월, 3-3개월... 12까지 지정가능
            'interest' => $optional['interest'], # 웰컴페이먼츠 전용, 무이자여부를 보내는 파라미터가 있다
            'feedback_url' => $optional['feedbackUrl'], # webhook 통지시 받으실 url 주소 (localhost 사용 불가)
            'feedback_content_type' => $optional['feedbackContentType'] # webhook 통지시 받으실 데이터 타입 (json 또는 urlencoded, 기본값 urlencoded)
        ];

        return static::$instances->subscribeCardBillingInstance($data);
    }

    # 4-2. 발급된 빌링키로 결제 예약 요청
    # optional 선택사항 모델 설명 
    # {
    #     items: [], # item 모델 배열 
    #     userInfo: nil, # user_info 모델 
    #     extra: nil, # extra 모델 
    #     tax_free: 0, # 비과세 금액, 면세 상품일 경우 해당만큼의 금액을 설정
    #     quota: 0,  # int 형태, 5만원 이상 결제건에 적용하는 할부개월수. 0-일시불, 1은 지정시 에러 발생함, 2-2개월, 3-3개월... 12까지 지정가능
    #     interest: 0 또는 1, # 웰컴페이먼츠 전용, 무이자여부를 보내는 파라미터가 있다
    #     feedback_url: '', # webhook 통지시 받으실 url 주소 (localhost 사용 불가)
    #     feedback_content_type: ''  # webhook 통지시 받으실 데이터 타입 (json 또는 urlencoded, 기본값 urlencoded)
    # }
    # user_info # 구매자 모델 설명 
    # { 
    #             id: '', # 개발사에서 관리하는 회원 고유 id
    #             username: '', # 구매자 이름
    #             email: '', # 구매자 email
    #             phone: '', # 01012341234
    #             gender: 0 또는 1, # 0:여자, 1:남자
    #             area: '서울', # 서울|인천|대구|광주|부산|울산|경기|강원|충청북도|충북|충청남도|충남|전라북도|전북|전라남도|전남|경상북도|경북|경상남도|경남|제주|세종|대전 중 택 1
    #             birth: '' # 생일 901004
    # },
    # item 모델 설명
    # {
    #   item_name: '', # 상품명
    #   qty: 1, # 수량
    #   unique: '', # 상품 고유키
    #   price: 1000, # 상품단가
    #   cat1: '', # 카테고리 상
    #   cat2: '', # 카테고리 중
    #   cat3: '' # 카테고리 하
    # }
    # extra - 빌링키 발급 옵션 
    # {
    #   subscribe_tst_payment: 0, # 100원 결제 후 결제가 되면 billing key를 발행, 결제가 실패하면 에러
    #   raw_data: 0 //PG 오류 코드 및 메세지까지 리턴
    # }  
    public static function subscribeCardBillingReserve($billingKey, $itemName, $price, $orderId, $executeAt = null, $optional=null)
    {
        $data = [
            'billing_key' => $billingKey, # 발급받은 빌링키
            'item_name' => $itemName, # 결제할 상품명
            'price' => $price, # 결제할 상품금액
            'order_id' => $orderId, # 개발사에서 지정하는 고유주문번호
            'execute_at' => $executeAt, # 결제 수행(예약) 시간, 기본값으로 10초 뒤 결제 
            'scheduler_type' => 'oneshot',            
            'items' => $optional['items'], # 구매할 상품정보, 통계용
            'user_info' => $optional['userInfo'], # 구매자 정보, 특정 PG사의 경우 구매자 휴대폰 번호를 필수로 받는다
            'extra' => $extra['extra'], # # 기타 옵션 
            'tax_free' => $extra['taxFree'], # 면세 상품일 경우 해당만큼의 금액을 설정
            'quota' => $extra['quota'], # int 형태, 5만원 이상 결제건에 적용하는 할부개월수. 0-일시불, 1은 지정시 에러 발생함, 2-2개월, 3-3개월... 12까지 지정가능
            'interest' => $extra['interest'], # 웰컴페이먼츠 전용, 무이자여부를 보내는 파라미터가 있다
            'feedback_url' => $extra['feedbackUrl'], # webhook 통지시 받으실 url 주소 (localhost 사용 불가)
            'feedback_content_type' => $extra['feedbackContentType'], # webhook 통지시 받으실 데이터 타입 (json 또는 urlencoded, 기본값 urlencoded)
        ];

        return static::$instances->subscribeCardBillingReserveInstance($data);
    }

    # 4-2-1. 발급된 빌링키로 결제 예약 - 취소 요청 
    public static function subscribeCardBillingReserveCancel($reserveId)
    {
        return static::$instances->subscribeCardBillingReserveCancelInstance($reserveId);
    }

    # 4-3. 빌링키 삭제 
    public static function destroySubscribeBillingKey($billingKey)
    {
        return static::$instances->destroySubscribeBillingKeyInstance($billingKey);
    }

    # 5. (부트페이 단독 - 간편결제창, 생체인증 기반의 사용자를 위한) 사용자 토큰 발급 
    public static function getUserToken($userId, $email = null, $name = null, $gender = null, $birth = null, $phone = null)
    {
        $data = [
            'user_id' => $userId, # 개발사에서 관리하는 회원 고유 id
            'email' => $email, # 구매자 email
            'name' => $name, # 구매자 이름
            'gender' => $gender, # 0:여자, 1:남자
            'birth' => $birth, # 생일 901004
            'phone' => $phone # 01012341234
        ];

        return static::$instances->getUserTokenInstance($data);
    }

    # 6. 결제링크 생성 
    # optional 선택사항 모델 설명 
    # {
    #     pg: 'kcp', # PG사의 Alias ex) danal, kcp, inicis 등
    #     method: 'card', # 결제수단 Alias ex) card, phone, vbank, bank, easy 중 1개, 미적용시 통합결제창 
    #     methods: ['card', 'phone'], # 결제수단 array, 통합결제창 사용시 활성화된 결제수단 중 사용할 method array 지정  
    #     params: '', # 전달할 string, 결제 후 다시 되돌려 줌, json string 형태로 활용해도 무방 
    #     extra: nil, # extra 모델 
    #     tax_free: 0, # 비과세 금액, 면세 상품일 경우 해당만큼의 금액을 설정
    #     user_info: nil, # 구매자 모델 
    #     items: [], # item 모델 array 
    #     extra: nil, 
    # }
    # user_info # 구매자 모델 설명 
    # { 
    #   id: '', # 개발사에서 관리하는 회원 고유 id
    #   username: '', # 구매자 이름
    #   email: '', # 구매자 email
    #   phone: '', # 01012341234
    #   gender: 0 또는 1, # 0:여자, 1:남자
    #   area: '서울', # 서울|인천|대구|광주|부산|울산|경기|강원|충청북도|충북|충청남도|충남|전라북도|전북|전라남도|전남|경상북도|경북|경상남도|경남|제주|세종|대전 중 택 1
    #   birth: '' # 생일 901004
    # },
    # item 모델 설명
    # {
    #   item_name: '', # 상품명
    #   qty: 1, # 수량
    #   unique: '', # 상품 고유키
    #   price: 1000, # 상품단가
    #   cat1: '', # 카테고리 상
    #   cat2: '', # 카테고리 중
    #   cat3: '' # 카테고리 하
    # }
    # extra 모델 설명 
    # { 
    #     escrow: false, # 에스크로 연동 시 true, 기본값 false
    #     quota: [0,2,3], #List<int> 형태,  결제금액이 5만원 이상시 할부개월 허용범위를 설정할 수 있음, ex) "0,2,3" 지정시 - [0(일시불), 2개월, 3개월] 허용, 미설정시 PG사별 기본값 적용, 1 지정시 에러가 발생할 수 있음
    #     disp_cash_result: nil, # 현금영수증 노출할지 말지 (가상계좌 이용시)
    #     offer_period: '월 자동결제', # 통합결제창, PG 정기결제창에서 표시되는 '월 자동결제'에 해당하는 문구를 지정한 값으로 변경, 지원하는 PG사만 적용 가능
    #     theme: nil, # 통합결제창 테마, [ red, purple(기본), custom ] 중 택 1
    #     custom_background: nil, # 통합결제창 배경색,  ex) "#00a086" theme가 custom 일 때 background 색상 지정 가능
    #     custom_font_color: nil # 통합결제창 글자색,  ex) "#ffffff" theme가 custom 일 때 font color 색상 지정 가능
    # }
    public static function requestPayment($orderId, $name, $price, $optional=null)
    {
        $data = [
            'order_id' => $orderId, # 개발사에서 지정하는 고유주문번호
            'name' => $name, # 상품명 
            'price' => $price, # 개발사에서 지정하는 고유주문번호
            'pg' => $optional['pg'], # PG사의 Alias ex) danal, kcp, inicis 등
            'method' => $optional['method'], # 'card', # 결제수단 Alias ex) card, phone, vbank, bank, easy 중 1개, 미적용시 통합결제창 
            'methods' => $optional['methods'], # ['card', 'phone'], # 결제수단 array, 통합결제창 사용시 활성화된 결제수단 중 사용할 method array 지정  
            'params' => $optional['params'],
            'tax_free' => $optional['taxFree'],
            'user_info' => $optional['userInfo'],
            'extra' => $optional['extra'],
            'items' => $optional['items']
        ];

        return static::$instances->requestPaymentInstance($data);
    }

    # 7. 서버 승인 요청 
    public static function submit($receiptId)
    {
        return static::$instances->submitInstance($receiptId);
    }

    # 8. 본인 인증 결과 검증 
    public static function certificate($receiptId)
    {
        return static::$instances->certificateInstance($receiptId);
    }

    # deprecated 
    public static function remoteForm($data)
    {
        return static::$instances->remoteFormInstance($data);
    }

    # deprecated 
    public static function sendSMS($data)
    {
        return static::$instances->sendSMSInstance($data);
    }

    # deprecated 
    public static function sendLMS($data)
    {
        return static::$instances->sendLMSInstance($data);
    }


    # deprecated 
    public static function startDelivery($data)
    {
        return static::$instances->startDeliveryInstance($data);
    }


    private function getRestUrl()
    {
        return $this->baseUrl[$this->mode];
    }

    public function setAccessToken($token)
    {
        return $this->accessToken = $token;
    }


    public function cancelInstance($data)
    {
        return self::post(
            implode('/', [$this->getRestUrl(), 'cancel']),
            $data,
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

    public function verifyInstance($receiptId)
    {
        return self::get(
            implode('/', [$this->getRestUrl(), 'receipt', $receiptId]),
            [],
            [
                "Authorization: {$this->accessToken}",
                'Content-Type: application/json; charset=UTF-8',
                'Accept: application/json',
            ]
        );
    }

    public function subscribeCardBillingInstance($data)
    {
        return self::post(
            implode('/', [$this->getRestUrl(), 'subscribe', 'billing.json']),
            $data,
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

    public function subscribeCardBillingReserveInstance($data)
    {
        return self::post(
            implode('/', [$this->getRestUrl(), 'subscribe', 'billing', 'reserve.json']),
            $data,
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

    public function subscribeCardBillingReserveCancelInstance($reserveId)
    {
        return self::delete(
            implode('/', [$this->getRestUrl(), 'subscribe', 'billing', 'reserve', $reserveId]),
            [],
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

    public function destroySubscribeBillingKeyInstance($billingKey)
    {
        return self::delete(
            implode('/', [$this->getRestUrl(), 'subscribe', 'billing', "{$billingKey}.json"]),
            [],
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

    public function remoteFormInstance($data)
    {
        $data["application_id"] = $this->defaultParams["application_id"];
        return self::post(
            implode('/', [$this->getRestUrl(), 'app', 'rest', 'remote_form.json']),
            $data,
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

    public function sendSMSInstance($data)
    {
        return self::post(
            implode('/', [$this->getRestUrl(), 'push', 'sms.json']),
            $data,
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

    public function sendLMSInstance($data)
    {
        return self::post(
            implode('/', [$this->getRestUrl(), 'push', 'lms.json']),
            $data,
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

    public function getSubscribeBillingKeyInstance($data)
    {
        return self::post(
            implode('/', [$this->getRestUrl(), 'request', 'card_rebill.json']),
            $data,
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

    public function submitInstance($receiptId)
    {
        return self::post(
            implode('/', [$this->getRestUrl(), 'submit.json']),
            [
                'receipt_id' => $receiptId
            ],
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

    public function startDeliveryInstance($data)
    {
        return self::put(
            implode('/', [$this->getRestUrl(), 'delivery', 'start', "{$data['receipt_id']}.json"]),
            [
                'delivery_no' => $data['delivery_no'],
                'delivery_corp' => $data['delivery_corp']
            ],
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

    public function certificateInstance($receiptId)
    {
        return self::get(
            implode('/', [$this->getRestUrl(), 'certificate', $receiptId]),
            [],
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

    public function requestPaymentInstance($data)
    {
        return self::post(
            implode('/', [$this->getRestUrl(), 'request', 'payment.json']),
            $data,
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

    public function tokenInstance($data)
    {
        return self::post(implode('/', [$this->getRestUrl(), 'request', 'token']), $data);
    }

    public function getUserTokenInstance($data)
    {
        return self::post(
            implode('/', [$this->getRestUrl(), 'request', 'user', 'token.json']),
            $data,
            [
                "Authorization: {$this->accessToken}"
            ]
        );
    }

//  공통 부분
    public static function get($url, $data, $headers = [])
    {
        $ch = self::getCurlHandler($url, $data, false, $headers);
        return self::execute($ch);
    }

    public static function post($url, $data, $headers = [])
    {
        $ch = self::getCurlHandler($url, $data, true, $headers);
        return self::execute($ch);
    }

    public static function put($url, $data, $headers = [])
    {
        $ch = self::getCurlHandler($url, $data, true, $headers, 'PUT');
        return self::execute($ch);
    }

    public static function delete($url, $data, $headers = [])
    {
        $ch = self::getCurlHandler($url, $data, true, $headers, 'DELETE');
        return self::execute($ch);
    }

    private static function execute($ch)
    {
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $errstr = curl_error($ch);
        if ($errno) throw new Exception('error: ' . $errno . ', msg: ' . $errstr);

        $json = json_decode(trim($response));
        curl_close($ch);
        return $json;
    }

    private static function getCurlHandler($url, $data = array(), $isPost = true, $headers = [], $customRequest = null)
    {
        $headers = array_merge(['Content-Type: application/json'], $headers);
        $ch = curl_init();
//        curl_setopt($cHandler, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, $isPost);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($isPost) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        if ($customRequest != null) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $customRequest);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        return $ch;
    }

    public function __construct()
    {
    }
}
