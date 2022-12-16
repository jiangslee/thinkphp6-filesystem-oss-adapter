<?php


namespace Enoliu\Flysystem\Oss\Traits;


use Exception;
use OSS\Core\OssException;

trait UploadTrait
{

    /**
     * multipart upload
     *
     * @param string     $path
     * @param string     $file
     * @param array|null $options  key-value array
     *
     * @return null
     * @throws OssException
     * @author liuxiaolong
     */
    public function multipartUpload(string $path, string $file, array $options = null)
    {
        return $this->getOssClient()->multiuploadFile($this->bucket, $path, $file, $options);
    }

    /**
     * @param array $config
     *
     * @return array
     * @throws Exception
     * @author liuxiaolong
     */
    public function directUpload(array $config = []): array
    {
        // 合并配置
        $config = array_merge(
            [
                'dir'      => '',
                'expire'   => 30,
                'callback' => '',
                'maxSize'  => 1048576000
            ],
            $config
        );

        // 回调地址
        $base64_callback_body = base64_encode(
            json_encode(
                [
                    'callbackUrl'      => $config['callback'],
                    'callbackBody'     => 'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}',
                    'callbackBodyType' => 'application/x-www-form-urlencoded'
                ]
            )
        );
        // policy过期时间
        $expire = time() + $config['expire'];

        $base64_policy = base64_encode(
            json_encode(
                [
                    'expiration' => gmt_iso8601($expire),
                    'conditions' => [
                        [
                            0 => 'content-length-range',
                            1 => 0,
                            2 => $config['maxSize']
                        ],
                        [
                            0 => 'starts-with',
                            1 => '$key',
                            2 => $config['dir']
                        ]
                    ]
                ]
            )
        );

        return [
            'accessid'  => $this->accessKeyId,
            'host'      => $this->bucket . '.' . $this->endPoint,
            'policy'    => $base64_policy,
            'signature' => base64_encode(hash_hmac('sha1', $base64_policy, $this->accessKeySecret, true)),
            'expire'    => $expire,
            'callback'  => $base64_callback_body,
            'dir'       => $config['dir']
        ];
    }


    /**
     * oss 直传配置.
     *
     * @param null $callBackUrl
     *
     * @return false|string
     *
     * @throws \Exception
     */
    public function signatureConfig(string $prefix = '', $callBackUrl = null, array $customData = [], int $expire = 30, int $contentLengthRangeValue = 1048576000, array $systemData = []): array
    {
        $systemFields = [
            'bucket' => '${bucket}',
            'etag' => '${etag}',
            'filename' => '${object}',
            'size' => '${size}',
            'mimeType' => '${mimeType}',
            'height' => '${imageInfo.height}',
            'width' => '${imageInfo.width}',
            'format' => '${imageInfo.format}',
        ];

        // 系统参数
        $system = [];
        if (empty($systemData)) {
            $system = $systemFields;
        } else {
            foreach ($systemData as $key => $value) {
                if (!in_array($value, $systemFields)) {
                    throw new \InvalidArgumentException("Invalid oss system filed: ${value}");
                }
                $system[$key] = $value;
            }
        }

        // 自定义参数
        $callbackVar = [];
        $data = [];
        if (!empty($customData)) {
            foreach ($customData as $key => $value) {
                $callbackVar['x:'.$key] = (string) $value;
                $data[$key] = '${x:'.$key.'}';
            }
        }

        $callbackParam = [
            'callbackUrl' => $callBackUrl,
            'callbackBody' => urldecode(http_build_query(array_merge($system, $data))),
            'callbackBodyType' => 'application/x-www-form-urlencoded',
        ];
        $callbackString = json_encode($callbackParam);
        $base64CallbackBody = base64_encode($callbackString);

        $now = time();
        $end = $now + $expire;
        $expiration = gmt_iso8601($end);

        // 最大文件大小.用户可以自己设置
        $condition = [
            0 => 'content-length-range',
            1 => 0,
            2 => $contentLengthRangeValue,
        ];
        $conditions[] = $condition;

        $start = [
            0 => 'starts-with',
            1 => '$key',
            2 => $prefix,
        ];
        $conditions[] = $start;

        // 添加bucket及callback条件，对该参数做上传验证
        $conditions[] = ['bucket' => $this->bucket];
        /**
         * 坑：回传参数前端需要单独append到formData https://help.aliyun.com/document_detail/31989.html
            let formData = new FormData();
            formData.append("OSSAccessKeyId", res.accessid);
            formData.append("policy", res.policy);
            formData.append("signature", res.signature);
            formData.append("key", res.dir + key);
            formData.append("callback", res.callback);
            //
            Object.entries(res['callback-var']).forEach(item=>formData.append(item[0], item[1]));
            formData.append("success_action_status", 200);
            formData.append("file", file);
         */
        if(!empty($customData)) $conditions[] = ['callback' => $base64CallbackBody];

        $arr = [
            'expiration' => $expiration,
            'conditions' => $conditions,
        ];
        $policy = json_encode($arr);
        $base64Policy = base64_encode($policy);
        $stringToSign = $base64Policy;
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));

        $response = [];
        $response['accessid'] = $this->accessKeyId;
        $response['host'] = $this->bucket . '.' . $this->endPoint;
        $response['policy'] = $base64Policy;
        $response['signature'] = $signature;
        $response['expire'] = $end;
        $response['callback'] = $base64CallbackBody;
        $response['callback-var'] = $callbackVar;
        $response['dir'] = $prefix;  // 这个参数是设置用户上传文件时指定的前缀。

        return $response;
    }

    /**
     * 验签.
     */
    public function verify(): array
    {
        // oss 前面header、公钥 header
        $authorizationBase64 = '';
        $pubKeyUrlBase64 = '';

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorizationBase64 = $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (isset($_SERVER['HTTP_X_OSS_PUB_KEY_URL'])) {
            $pubKeyUrlBase64 = $_SERVER['HTTP_X_OSS_PUB_KEY_URL'];
        }

        // 验证失败
        if ('' == $authorizationBase64 || '' == $pubKeyUrlBase64) {
            return [false, ['CallbackFailed' => 'authorization or pubKeyUrl is null']];
        }

        // 获取OSS的签名
        $authorization = base64_decode($authorizationBase64);
        // 获取公钥
        $pubKeyUrl = base64_decode($pubKeyUrlBase64);
        // 请求验证
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $pubKeyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $pubKey = curl_exec($ch);

        if ('' == $pubKey) {
            return [false, ['CallbackFailed' => 'curl is fail']];
        }

        // 获取回调 body
        $body = file_get_contents('php://input');
        // 拼接待签名字符串
        $path = $_SERVER['REQUEST_URI'];
        $pos = strpos($path, '?');
        if (false === $pos) {
            $authStr = urldecode($path)."\n".$body;
        } else {
            $authStr = urldecode(substr($path, 0, $pos)).substr($path, $pos, strlen($path) - $pos)."\n".$body;
        }
        // 验证签名
        $ok = openssl_verify($authStr, $authorization, $pubKey, OPENSSL_ALGO_MD5);

        if (1 !== $ok) {
            return [false, ['CallbackFailed' => 'verify is fail, Illegal data']];
        }

        parse_str($body, $data);

        return [true, $data];
    }
}