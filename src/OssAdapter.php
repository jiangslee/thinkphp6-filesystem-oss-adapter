<?php

namespace Enoliu\Flysystem\Oss;


use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use OSS\Core\OssException;
use OSS\OssClient;
use Throwable;

class OssAdapter extends AbstractAdapter
{
    /**
     * @var string
     */
    protected $accessKeyId;

    /**
     * @var string
     */
    protected $accessKeySecret;

    /**
     * @var string
     */
    protected $endPoint;

    /**
     * @var string
     */
    protected $bucket;

    /**
     * @var bool
     */
    private $isCName;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var bool
     */
    protected $useSSL;

    /**
     * @var OssClient
     */
    protected $ossClient;

    /**
     * OssAdapter constructor.
     *
     * @param array $config
     *
     * @throws Throwable
     */
    public function __construct(array $config)
    {
        try {
            $this->accessKeyId = $config['accessId'];
            $this->accessKeySecret = $config['accessSecret'];;
            $this->endPoint = $config['endPoint'];
            $this->bucket = $config['bucket'];
            $this->isCName = $config['isCName'] ?? false;
            $this->token = $config['token'] ?? null;
            $this->useSSL = $config['useSSL'] ?? false;
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config  Config object
     *
     * @return array|false false on failure file meta data on success
     * @throws OssException
     */
    public function write($path, $contents, Config $config)
    {
        return $this->getOssClient()->putObject($this->bucket, $path, $contents, $this->getOssOptions($config));
    }

    /**
     * Write a new file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config  Config object
     *
     * @return array|false false on failure file meta data on success
     * @throws OssException
     */
    public function writeStream($path, $resource, Config $config)
    {
        if ( ! is_resource($resource)) {
            return false;
        }
        $i = 0;
        $bufferSize = 1000000; // 1M
        while ( ! feof($resource)) {
            if (false === $buffer = fread($resource, $block = $bufferSize)) {
                return false;
            }
            $position = $i * $bufferSize;
            $size = $this->getOssClient()->appendObject(
                $this->bucket,
                $path,
                $buffer,
                $position,
                $this->getOssOptions($config)
            );
            $i++;
        }
        fclose($resource);
        return true;
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config  Config object
     *
     * @return array|false false on failure file meta data on success
     * @throws OssException
     */
    public function update($path, $contents, Config $config)
    {
        return $this->getOssClient()->putObject($this->bucket, $path, $contents, $this->getOssOptions($config));
    }

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config  Config object
     *
     * @return array|false false on failure file meta data on success
     * @throws OssException
     */
    public function updateStream($path, $resource, Config $config)
    {
        $result = $this->write($path, stream_get_contents($resource), $config);
        if (is_resource($resource)) {
            fclose($resource);
        }
        return $result;
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     * @throws OssException
     */
    public function rename($path, $newpath)
    {
        $this->getOssClient()->copyObject($this->bucket, $path, $this->bucket, $newpath);
        $this->getOssClient()->deleteObject($this->bucket, $path);
        return true;
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     * @throws OssException
     */
    public function copy($path, $newpath)
    {
        $this->getOssClient()->copyObject($this->bucket, $path, $this->bucket, $newpath);
        return true;
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     * @throws OssException
     */
    public function delete($path)
    {
        $this->getOssClient()->deleteObject($this->bucket, $path);
        return true;
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     * @throws OssException
     */
    public function deleteDir($dirname)
    {
        $lists = $this->listContents($dirname, true);
        if ( ! $lists) {
            return false;
        }
        $objectList = [];
        foreach ($lists as $value) {
            $objectList[] = $value['path'];
        }
        $this->getOssClient()->deleteObjects($this->bucket, $objectList);
        return true;
    }

    /**
     * Create a directory.
     *
     * @param string $dirname  directory name
     * @param Config $config
     *
     * @return array|false
     * @throws OssException
     */
    public function createDir($dirname, Config $config)
    {
        $this->getOssClient()->createObjectDir($this->bucket, $dirname);
        return true;
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false file meta data
     *
     * Aliyun OSS ACL value: 'default', 'private', 'public-read', 'public-read-write'
     * @throws OssException
     */
    public function setVisibility($path, $visibility)
    {
        $this->getOssClient()->putObjectAcl(
            $this->bucket,
            $path,
            ($visibility == 'public') ? 'public-read' : 'private'
        );
        return true;
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return array|bool|null
     * @throws OssException
     */
    public function has($path)
    {
        return $this->getOssClient()->doesObjectExist($this->bucket, $path);
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     * @throws OssException
     */
    public function read($path)
    {
        return [
            'contents' => $this->getOssClient()->getObject($this->bucket, $path)
        ];
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {
        $resource = 'http://' . $this->bucket . '.' . $this->endpoint . '/' . $path;
        return [
            'stream' => $resource = fopen($resource, 'r')
        ];
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     *
     * @return array
     * @throws OssException
     */
    public function listContents($directory = '', $recursive = false): array
    {
        $directory = rtrim($directory, '\\/');

        $result = [];
        $nextMarker = '';
        while (true) {
            // max-keys 用于限定此次返回object的最大数，如果不设定，默认为100，max-keys取值不能大于1000。
            // prefix   限定返回的object key必须以prefix作为前缀。注意使用prefix查询时，返回的key中仍会包含prefix。
            // delimiter是一个用于对Object名字进行分组的字符。所有名字包含指定的前缀且第一次出现delimiter字符之间的object作为一组元素
            // marker   用户设定结果从marker之后按字母排序的第一个开始返回。
            $options = [
                'max-keys'  => 1000,
                'prefix'    => $directory . '/',
                'delimiter' => '/',
                'marker'    => $nextMarker,
            ];
            $res = $this->getOssClient()->listObjects($this->bucket, $options);

            // 得到nextMarker，从上一次$res读到的最后一个文件的下一个文件开始继续获取文件列表
            $nextMarker = $res->getNextMarker();
            $prefixList = $res->getPrefixList(); // 目录列表
            $objectList = $res->getObjectList(); // 文件列表
            if ($prefixList) {
                foreach ($prefixList as $value) {
                    $result[] = [
                        'type' => 'dir',
                        'path' => $value->getPrefix()
                    ];
                    if ($recursive) {
                        $result = array_merge($result, $this->listContents($value->getPrefix(), $recursive));
                    }
                }
            }
            if ($objectList) {
                foreach ($objectList as $value) {
                    if (($value->getSize() === 0) && ($value->getKey() === $directory . '/')) {
                        continue;
                    }
                    $result[] = [
                        'type'      => 'file',
                        'path'      => $value->getKey(),
                        'timestamp' => strtotime($value->getLastModified()),
                        'size'      => $value->getSize()
                    ];
                }
            }
            if ($nextMarker === '') {
                break;
            }
        }

        return $result;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     * @throws OssException
     */
    public function getMetadata($path)
    {
        return $this->getOssClient()->getObjectMeta($this->bucket, $path);
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     *
     * @return array|false
     * @throws OssException
     */
    public function getSize($path)
    {
        return [
            'size' => $this->getMetadata($path)['content-length']
        ];
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     * @throws OssException
     */
    public function getMimetype($path)
    {
        return [
            'mimetype' => $this->getMetadata($path)['content-type']
        ];
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     *
     * @return array|false
     * @throws OssException
     */
    public function getTimestamp($path)
    {
        return [
            'timestamp' => $this->getMetadata($path)['last-modified']
        ];
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     * @throws OssException
     */
    public function getVisibility($path)
    {
        $response = $this->getOssClient()->getObjectAcl($this->bucket, $path);
        return [
            'visibility' => $response,
        ];
    }

    /**
     * @param $path
     *
     * @return string
     * @author liuxiaolong
     */
    public function getUrl($path): string
    {
        return $this->normalizeHost() . ltrim($path, '/');
    }

    /**
     * Get OSS Options
     *
     * @param Config $config
     *
     * @return array
     */
    private function getOssOptions(Config $config): array
    {
        $options = [];
        if ($config->has("headers")) {
            $options['headers'] = $config->get("headers");
        }

        if ($config->has("Content-Type")) {
            $options["Content-Type"] = $config->get("Content-Type");
        }

        if ($config->has("Content-Md5")) {
            $options["Content-Md5"] = $config->get("Content-Md5");
            $options["checkmd5"] = false;
        }
        return $options;
    }

    /**
     * @return OssClient
     * @throws OssException
     * @author liuxiaolong
     */
    protected function getOssClient(): OssClient
    {
        return $this->ossClient ?: new OssClient(
            $this->accessKeyId,
            $this->accessKeySecret,
            $this->endPoint,
            $this->isCName,
            $this->token
        );
    }

    /**
     * @return string
     * @author liuxiaolong
     */
    protected function normalizeHost(): string
    {
        $domain = $this->bucket.'.'.$this->endPoint;

        if ($this->isCName) {
            $domain = $this->endPoint;
        }

        return $this->useSSL ? 'https://' : 'http://' . rtrim($domain, '/').'/';
    }
}
