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
}