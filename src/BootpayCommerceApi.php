<?php

namespace Bootpay\ServerPhp;

use Bootpay\ServerPhp\Commerce\UserModule;
use Bootpay\ServerPhp\Commerce\UserGroupModule;
use Bootpay\ServerPhp\Commerce\ProductModule;
use Bootpay\ServerPhp\Commerce\InvoiceModule;
use Bootpay\ServerPhp\Commerce\OrderModule;
use Bootpay\ServerPhp\Commerce\OrderCancelModule;
use Bootpay\ServerPhp\Commerce\OrderSubscriptionModule;
use Bootpay\ServerPhp\Commerce\OrderSubscriptionBillModule;
use Bootpay\ServerPhp\Commerce\OrderSubscriptionAdjustmentModule;
use Bootpay\ServerPhp\Commerce\OrderSubscriptionRequestModule;
use Bootpay\ServerPhp\Commerce\StoreModule;
use Bootpay\ServerPhp\Commerce\CategoryModule;
use Bootpay\ServerPhp\Commerce\CouponModule;
use Bootpay\ServerPhp\Commerce\PointModule;
use Bootpay\ServerPhp\Commerce\CartModule;
use Bootpay\ServerPhp\Commerce\MallSettingModule;
use Bootpay\ServerPhp\Commerce\WebhookModule;
use Bootpay\ServerPhp\Commerce\NoticeModule;
use Bootpay\ServerPhp\Commerce\DepositModule;
use Bootpay\ServerPhp\Commerce\PayoutModule;
use Bootpay\ServerPhp\Commerce\FaqModule;
use Bootpay\ServerPhp\Commerce\InquiryModule;
use Bootpay\ServerPhp\Commerce\ProductQnaModule;
use Bootpay\ServerPhp\Commerce\ProductReviewModule;
use Bootpay\ServerPhp\Commerce\AlimtalkSenderModule;
use Bootpay\ServerPhp\Commerce\AlimtalkTemplateModule;
use Bootpay\ServerPhp\Commerce\AlimtalkSendModule;
use Bootpay\ServerPhp\Commerce\AlimtalkMessageModule;
use Bootpay\ServerPhp\Commerce\AlimtalkOptoutModule;
use Bootpay\ServerPhp\Commerce\AlimtalkOfficialModule;
use Bootpay\ServerPhp\Commerce\AlimtalkWebhookModule;

class BootpayCommerceApi
{
    private $token = '';
    private $clientKey = '';
    private $secretKey = '';
    private $mode = 'production';
    private $role = 'user';

    private static $API_URL = array(
        'development' => 'https://dev-api.bootapi.com/v1',
        'stage' => 'https://stage-api.bootapi.com/v1',
        'production' => 'https://api.bootapi.com/v1'
    );

    /**
     * 알림톡 API 전용 base URL — 메시지 API 가 직접 받는다(경로에 /v1 없음).
     * 26-09-18: resolveBaseUrl() 이 요청 경로로 이 표와 $API_URL 중 하나를 고른다.
     */
    private static $MESSAGE_API_URL = array(
        'development' => 'https://dev-m.bootapi.com',
        'stage' => 'https://stage-m.bootapi.com',
        'production' => 'https://message.bootapi.com'
    );

    private static $postMethods = array('POST', 'PUT');
    private static $apiVersion = '2.5.0';
    private static $sdkVersion = '2.11.0';

    public $user;
    public $userGroup;
    public $product;
    public $invoice;
    public $order;
    public $orderCancel;
    public $orderSubscription;
    public $orderSubscriptionBill;
    public $orderSubscriptionAdjustment;
    public $orderSubscriptionRequest;
    public $store;
    public $category;
    public $coupon;
    public $point;
    public $cart;
    public $mallSetting;
    public $webhook;
    public $notice;
    public $deposit;
    public $payout;
    public $faq;
    public $inquiry;
    public $productQna;
    public $productReview;
    public $alimtalkSender;
    public $alimtalkTemplate;
    public $alimtalkSend;
    public $alimtalkMessage;
    public $alimtalkOptout;
    public $alimtalkOfficial;
    public $alimtalkWebhook;

    public function __construct($clientKey = null, $secretKey = null, $mode = 'production')
    {
        if ($clientKey !== null || $secretKey !== null) {
            $this->setConfiguration($clientKey, $secretKey, $mode);
        }
        $this->initModules();
    }

    private function initModules()
    {
        $this->user = new UserModule($this);
        $this->userGroup = new UserGroupModule($this);
        $this->product = new ProductModule($this);
        $this->invoice = new InvoiceModule($this);
        $this->order = new OrderModule($this);
        $this->orderCancel = new OrderCancelModule($this);
        $this->orderSubscription = new OrderSubscriptionModule($this);
        $this->orderSubscriptionBill = new OrderSubscriptionBillModule($this);
        $this->orderSubscriptionAdjustment = new OrderSubscriptionAdjustmentModule($this);
        $this->orderSubscriptionRequest = new OrderSubscriptionRequestModule($this);
        $this->store = new StoreModule($this);
        $this->category = new CategoryModule($this);
        $this->coupon = new CouponModule($this);
        $this->point = new PointModule($this);
        $this->cart = new CartModule($this);
        $this->mallSetting = new MallSettingModule($this);
        $this->webhook = new WebhookModule($this);
        $this->notice = new NoticeModule($this);
        $this->deposit = new DepositModule($this);
        $this->payout = new PayoutModule($this);
        $this->faq = new FaqModule($this);
        $this->inquiry = new InquiryModule($this);
        $this->productQna = new ProductQnaModule($this);
        $this->productReview = new ProductReviewModule($this);
        $this->alimtalkSender = new AlimtalkSenderModule($this);
        $this->alimtalkTemplate = new AlimtalkTemplateModule($this);
        $this->alimtalkSend = new AlimtalkSendModule($this);
        $this->alimtalkMessage = new AlimtalkMessageModule($this);
        $this->alimtalkOptout = new AlimtalkOptoutModule($this);
        $this->alimtalkOfficial = new AlimtalkOfficialModule($this);
        $this->alimtalkWebhook = new AlimtalkWebhookModule($this);
    }

    public function setConfiguration($clientKey, $secretKey, $mode = 'production')
    {
        $this->requireCommerceCredentials($clientKey, $secretKey);
        $this->clientKey = $clientKey;
        $this->secretKey = $secretKey;
        $this->mode = $mode;
        return $this;
    }

    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function hasToken()
    {
        return !empty($this->token);
    }

    public function setRole($role)
    {
        $this->role = $role;
        return $this;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function withRole($role)
    {
        $this->role = $role;
        return $this;
    }

    public function asUser()
    {
        return $this->withRole('user');
    }

    public function asManager()
    {
        return $this->withRole('manager');
    }

    public function asPartner()
    {
        return $this->withRole('partner');
    }

    public function asVendor()
    {
        return $this->withRole('vendor');
    }

    public function asSupervisor()
    {
        return $this->withRole('supervisor');
    }

    public function clearRole()
    {
        $this->role = 'user';
        return $this;
    }

    /**
     * 알림톡(메시지 API) base URL 을 현재 mode 에 대해 변경한다.
     * @param string $url
     * @return $this
     */
    public function setMessageApiUrl($url)
    {
        $mode = (is_string($this->mode) && isset(self::$MESSAGE_API_URL[$this->mode])) ? $this->mode : 'production';
        self::$MESSAGE_API_URL[$mode] = $url;
        return $this;
    }

    private function entrypoints($url)
    {
        return implode('/', array(self::resolveBaseUrl($this->mode, $url), $url));
    }

    /**
     * 요청 경로에 맞는 base URL 을 돌려준다.
     *
     * 26-09-18: 알림톡 API 는 커머스 API(api.bootapi.com/v1)가 아니라 메시지 API(message.bootapi.com, /v1 없음)가 받는다.
     * 경로·파라미터·응답은 그대로이고 호스트만 다르다 — 그래서 알림톡 모듈은 고치지 않고 여기서 주소만 가른다.
     * 옛 주소(/v1/alimtalk/*)는 410 으로 응답한다.
     */
    private static function resolveBaseUrl($mode, $url)
    {
        if (strpos(ltrim((string)$url, '/'), 'alimtalk') === 0) {
            $messageMode = (is_string($mode) && isset(self::$MESSAGE_API_URL[$mode])) ? $mode : 'production';
            // 알 수 없는 mode 경고는 resolveApiUrl 과 같은 규칙으로 남긴다
            if ($messageMode !== $mode) {
                self::resolveApiUrl($mode);
            }
            return self::$MESSAGE_API_URL[$messageMode];
        }
        return self::resolveApiUrl($mode);
    }

    /**
     * mode 에 해당하는 base URL 을 돌려준다.
     *
     * 26-08-24: 알 수 없는 mode 는 production 으로 폴백한다 (go · java SDK 와 같은 규칙).
     * 이전에는 $API_URL[$mode] 를 그대로 인덱싱해서 오타 하나에 Warning 이 뜨고 URL 이
     * "/{path}" 로 붕괴, curl errno 3 (URL rejected: No host part in the URL) 이 났다.
     */
    private static function resolveApiUrl($mode)
    {
        if (is_string($mode) && isset(self::$API_URL[$mode])) {
            return self::$API_URL[$mode];
        }
        // 26-08-24: 알 수 없는 mode 는 production 으로 폴백하되 **조용히 넘어가지 않는다**.
        // 폴백 자체는 go·java SDK 와 같은 규칙이지만, 결제 SDK 에서 'developmnet' 같은
        // 오타가 조용히 production 으로 나가면 실거래가 발생한다. 경고를 남겨 로그에서 잡히게 한다.
        trigger_error(
            sprintf(
                'Bootpay: 알 수 없는 mode "%s" — production 으로 폴백합니다. '
                . 'development / stage / production 중 하나를 지정하세요.',
                is_string($mode) ? $mode : gettype($mode)
            ),
            E_USER_WARNING
        );
        return self::$API_URL['production'];
    }

    private function getBasicAuthHeader()
    {
        $this->requireCommerceCredentials($this->clientKey, $this->secretKey);
        $credentials = $this->clientKey . ':' . $this->secretKey;
        return 'Basic ' . base64_encode($credentials);
    }

    private function requireCommerceCredentials($clientKey, $secretKey)
    {
        $hasClientKey = !empty($clientKey);
        $hasSecretKey = !empty($secretKey);
        if (!$hasClientKey || !$hasSecretKey) {
            throw new \InvalidArgumentException('client_key/secret_key를 함께 입력해주세요.');
        }
    }

    private function createHeaders($headers = null, $useBasicAuth = false)
    {
        if (!isset($headers)) {
            $headers = array();
        }

        $defaultHeaders = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Accept-Charset: utf-8',
            'BOOTPAY-SDK-VERSION: ' . self::$sdkVersion,
            'BOOTPAY-API-VERSION: ' . self::$apiVersion,
            'BOOTPAY-SDK-TYPE: 303',
            'BOOTPAY-ROLE: ' . $this->role
        );

        $basicAuth = $this->getBasicAuthHeader();
        if (!empty($basicAuth)) {
            $defaultHeaders[] = 'Authorization: ' . $basicAuth;
        }

        return $this->mergeHeaders($defaultHeaders, $headers);
    }

    /**
     * 요청별 헤더가 기본 헤더와 같은 이름이면 기본 헤더를 제거하고 요청별 값을 유지한다.
     * (supervisor 전용 endpoint 의 BOOTPAY-ROLE 이 기본 role 과 중복 전송되는 것을 방지)
     */
    private function mergeHeaders($defaultHeaders, $headers)
    {
        if (empty($headers)) {
            return $defaultHeaders;
        }

        $overridden = array();
        foreach ($headers as $header) {
            $pos = strpos($header, ':');
            if ($pos === false) {
                continue;
            }
            $overridden[strtolower(trim(substr($header, 0, $pos)))] = true;
        }

        $merged = array();
        foreach ($defaultHeaders as $header) {
            $pos = strpos($header, ':');
            $name = $pos === false ? '' : strtolower(trim(substr($header, 0, $pos)));
            if ($name === '' || !isset($overridden[$name])) {
                $merged[] = $header;
            }
        }

        return array_merge($merged, $headers);
    }

    /**
     * Idempotency-Key 헤더용 uuid v4 생성
     */
    public static function generateIdempotencyKey()
    {
        if (function_exists('random_bytes')) {
            $bytes = random_bytes(16);
        } else {
            $bytes = openssl_random_pseudo_bytes(16);
        }
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public function request($method, $url, $data = null, $headers = null, $useBasicAuth = false)
    {
        if (!isset($headers)) {
            $headers = array();
        }

        $isPost = in_array($method, self::$postMethods);
        $fullUrl = $this->entrypoints($url);

        $channel = curl_init($fullUrl);
        curl_setopt($channel, CURLOPT_URL, $fullUrl);
        curl_setopt($channel, CURLOPT_HTTPHEADER, $this->createHeaders($headers, $useBasicAuth));

        if ($isPost) {
            curl_setopt($channel, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($channel, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        if (in_array($method, array('DELETE', 'PUT'))) {
            curl_setopt($channel, CURLOPT_CUSTOMREQUEST, $method);
            if ($data !== null) {
                curl_setopt($channel, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        curl_setopt($channel, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($channel);
        $errno = curl_errno($channel);
        $errMsg = curl_error($channel);

        if ($errno) {
            throw new \Exception('error: ' . $errno . ', msg: ' . $errMsg);
        }

        $json = json_decode(trim($response));

        return $json;
    }

    /**
     * JSON 이 아닌 본문(CSV 등)을 파싱하지 않고 그대로 받는다.
     *
     * ⚠️ 공용 request() 는 응답을 무조건 json_decode 하므로, CSV 를 돌려주는 엔드포인트
     *    (알림톡 템플릿 내보내기 format=csv)에서는 성공한 요청인데도 null 이 돌아와
     *    "통신 실패" 로 오인된다. 그래서 원문 경로를 따로 둔다.
     * @param string $method
     * @param string $url
     * @param array|null $headers
     * @return object { body: '<원문 문자열>', content_type: '...', status: 200 }
     */
    public function requestRaw($method, $url, $headers = null)
    {
        $fullUrl = $this->entrypoints($url);

        $rawHeaders = array(
            'Accept: */*',
            'Accept-Charset: utf-8',
            'BOOTPAY-SDK-VERSION: ' . self::$sdkVersion,
            'BOOTPAY-API-VERSION: ' . self::$apiVersion,
            'BOOTPAY-SDK-TYPE: 303',
            'BOOTPAY-ROLE: ' . $this->role
        );

        $basicAuth = $this->getBasicAuthHeader();
        if (!empty($basicAuth)) {
            $rawHeaders[] = 'Authorization: ' . $basicAuth;
        }

        if (!empty($headers)) {
            $rawHeaders = $this->mergeHeaders($rawHeaders, $headers);
        }

        $channel = curl_init($fullUrl);
        curl_setopt($channel, CURLOPT_URL, $fullUrl);
        curl_setopt($channel, CURLOPT_HTTPHEADER, $rawHeaders);
        if ($method !== 'GET') {
            curl_setopt($channel, CURLOPT_CUSTOMREQUEST, $method);
        }
        curl_setopt($channel, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($channel);
        $errno = curl_errno($channel);
        $errMsg = curl_error($channel);
        $status = curl_getinfo($channel, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($channel, CURLINFO_CONTENT_TYPE);

        if ($errno) {
            throw new \Exception('error: ' . $errno . ', msg: ' . $errMsg);
        }

        return (object)array(
            'body' => $response === false ? '' : $response,
            'content_type' => $contentType === null ? '' : $contentType,
            'status' => (int)$status
        );
    }

    public function requestMultipart($method, $url, $data = null, $files = null, $headers = null)
    {
        $fullUrl = $this->entrypoints($url);

        $channel = curl_init($fullUrl);
        curl_setopt($channel, CURLOPT_URL, $fullUrl);

        $multipartHeaders = array(
            'Accept: application/json',
            'Accept-Charset: utf-8',
            'BOOTPAY-SDK-VERSION: ' . self::$sdkVersion,
            'BOOTPAY-API-VERSION: ' . self::$apiVersion,
            'BOOTPAY-SDK-TYPE: 303',
            'BOOTPAY-ROLE: ' . $this->role
        );

        $basicAuth = $this->getBasicAuthHeader();
        if (!empty($basicAuth)) {
            $multipartHeaders[] = 'Authorization: ' . $basicAuth;
        }

        // ⚠️ Content-Type 은 지정하지 않는다 — curl 이 붙이는 multipart boundary 가 유실되면
        // 서버가 본문을 null 로 읽는다. 요청별 헤더(Idempotency-Key, BOOTPAY-ROLE 등)만 병합한다.
        if (!empty($headers)) {
            $multipartHeaders = $this->mergeHeaders($multipartHeaders, $headers);
        }

        curl_setopt($channel, CURLOPT_HTTPHEADER, $multipartHeaders);
        curl_setopt($channel, CURLOPT_POST, true);

        $postData = array();

        if ($data !== null) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $postData[$key] = json_encode($value);
                } elseif (is_bool($value)) {
                    // curl 은 bool 을 true→"1", false→"" 로 보내 false 가 서버에서 nil 로 읽힌다.
                    // NodeJS SDK 의 String(bool) 과 동일하게 'true'/'false' 문자열로 전송한다.
                    $postData[$key] = $value ? 'true' : 'false';
                } else {
                    $postData[$key] = $value;
                }
            }
        }

        if ($files !== null && is_array($files)) {
            foreach ($files as $index => $filePath) {
                if (file_exists($filePath)) {
                    // 정수 키는 상품 이미지처럼 images[0], images[1] ... 로 인덱싱하고,
                    // 문자열 키는 그 이름을 필드명으로 그대로 쓴다 (알림톡 템플릿 이미지의 image 필드).
                    $field = is_string($index) ? $index : "images[$index]";
                    $postData[$field] = new \CURLFile($filePath);
                }
            }
        }

        curl_setopt($channel, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($channel, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($channel);
        $errno = curl_errno($channel);
        $errMsg = curl_error($channel);

        if ($errno) {
            throw new \Exception('error: ' . $errno . ', msg: ' . $errMsg);
        }

        $json = json_decode(trim($response));

        return $json;
    }

    public function get($url, $headers = null)
    {
        return $this->request('GET', $url, null, $headers);
    }

    public function post($url, $data = null, $headers = null)
    {
        return $this->request('POST', $url, $data, $headers);
    }

    /**
     * 원문(파싱하지 않은) GET 요청 — CSV 등 JSON 이 아닌 응답용
     * @param string $url
     * @param array|null $headers
     * @return object
     */
    public function getRaw($url, $headers = null)
    {
        return $this->requestRaw('GET', $url, $headers);
    }

    public function postWithBasicAuth($url, $data = null, $headers = null)
    {
        return $this->request('POST', $url, $data, $headers, true);
    }

    public function put($url, $data = null, $headers = null)
    {
        return $this->request('PUT', $url, $data, $headers);
    }

    /**
     * DELETE 요청
     * @param string $url
     * @param array|null $headers
     * @param array|object|null $data DELETE body 가 필요한 endpoint 용 (예: order_subscriptions/charge)
     */
    public function delete($url, $headers = null, $data = null)
    {
        return $this->request('DELETE', $url, $data, $headers);
    }

    /**
     * 액세스 토큰 발급
     * client_key/secret_key로 인증
     */
    public function getAccessToken()
    {
        $response = $this->postWithBasicAuth('request/token', array(
            'client_key' => $this->clientKey,
            'secret_key' => $this->secretKey
        ));

        if (isset($response->access_token)) {
            $this->token = $response->access_token;
        }

        return $response;
    }

    /**
     * 토큰을 발급받아 설정합니다. (메서드 체이닝 지원)
     */
    public function withToken()
    {
        $this->getAccessToken();
        return $this;
    }
}
