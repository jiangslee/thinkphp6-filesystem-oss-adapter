<h1 align="center"> flysystem-oss </h1>

<p align="center"> Flysystem adapter for the AliYun OSS storage..</p>


## Installing

```shell
$ composer require enoliu/flysystem-oss -vvv
```

## Usage
```php
use Enoliu\Flysystem\Oss\OssAdapter;   
use Enoliu\Flysystem\Oss\Plugins\FileUrl;  

$config = [
'accessId'     => 'LTAI77*****wHf',
'accessSecret' => 'MfSs*****DTcOzpP',
'bucket'       => 'l*****2',
'endPoint'     => 'oss-cn-beijing.aliyuncs.com',
// 'timeout'        => 3600,
// 'connectTimeout' => 10,
// 'isCName'        => false,
// 'token'          => '',
// 'useSSL'         => false
];


$flysystem = new League\Flysystem\Filesystem(new OssAdapter($config));
```
## 常用方法

```php
bool $flysystem->write('file.md', 'contents');

bool $flysystem->write('file.md', 'http://httpbin.org/robots.txt', ['options' => ['xxxxx' => 'application/redirect302']]);

bool $flysystem->writeStream('file.md', fopen('path/to/your/local/file.jpg', 'r'));

bool $flysystem->update('file.md', 'new contents');

bool $flysystem->updateStream('file.md', fopen('path/to/your/local/file.jpg', 'r'));

bool $flysystem->rename('foo.md', 'bar.md');

bool $flysystem->copy('foo.md', 'foo2.md');

bool $flysystem->delete('file.md');

bool $flysystem->has('file.md');

string|false $flysystem->read('file.md');

array $flysystem->listContents();

array $flysystem->getMetadata('file.md');

int $flysystem->getSize('file.md');

string $flysystem->getAdapter()->getUrl('file.md');

string $flysystem->getMimetype('file.md');

int $flysystem->getTimestamp('file.md');
```

## 插件扩展

```php
use Enoliu\Flysystem\Oss\Plugins\FileUrl; 

// 获取 oss 资源访问链接
$flysystem->addPlugin(new FileUrl());

string $flysystem->getUrl('file.md');

```
## 高级用法
```php
// 获取前端直传签名配置
$config = [
    'dir'      => 'upload/tmp',
    'expire'   => 60 * 60,
    'callback' => 'http://www.baidu.com',
    'maxSize'  => 10 * 1024 * 1024
];
array $flysystem->getAdapter()->directUpload($config);
```


## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/enoliu/flysystem-oss/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/enoliu/flysystem-oss/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT