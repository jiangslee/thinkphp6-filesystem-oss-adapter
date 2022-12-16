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