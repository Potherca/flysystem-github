# Flysystem Adapter for Github

[![Latest Version](https://img.shields.io/github/release/potherca/flysystem-github.svg?style=flat-square)](https://github.com/potherca/flysystem-github/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/potherca/flysystem-github/master.svg?style=flat-square)](https://travis-ci.org/potherca/flysystem-github)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/potherca/flysystem-github.svg?style=flat-square)](https://scrutinizer-ci.com/g/potherca/flysystem-github/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/potherca/flysystem-github.svg?style=flat-square)](https://scrutinizer-ci.com/g/potherca/flysystem-github)
[![Total Downloads](https://img.shields.io/packagist/dt/potherca/flysystem-github.svg?style=flat-square)](https://packagist.org/packages/potherca/flysystem-github)


## Install

Via Composer

``` bash
$ composer require potherca/flysystem-github
```

## Usage

The Github adapter can be used *without* credentials to do read-only actions on
public repositories. To save changes or read from private repositories, 
credentials are required.

To avoid the Github API limit or to save traffic caching can be utilized.

### Basic Usage

```php
use Github\Client;
use League\Flysystem\Filesystem;
use Potherca\Flysystem\Github\GithubAdapter;

$project = 'thephpleague/flysystem';

$client = new Client();
$adapter = new GithubAdapter($client, $project);

$filesystem = new Filesystem($adapter);
```

### Authentication

```php
use Github\Client;
use League\Flysystem\Filesystem;
use Potherca\Flysystem\Github\GithubAdapter;

$project = 'thephpleague/flysystem';

$client = new Client();
$client->authenticate($token, null, Client::AUTH_HTTP_TOKEN);
// or $client->authenticate($username, $password, Client::AUTH_HTTP_PASSWORD);
$adapter = new GithubAdapter($client, $project);

$filesystem = new Filesystem($adapter);
```

For full details, see the [php-github-api documentation concerning authentication](https://github.com/KnpLabs/php-github-api/blob/master/doc/security.md).

### Cache Usage

```php
use Github\Client;
use Github\HttpClient\CachedHttpClient as CachedClient;
use Github\HttpClient\Cache\FilesystemCache as Cache;
use League\Flysystem\Filesystem;
use Potherca\Flysystem\Github\GithubAdapter;

$project = 'thephpleague/flysystem';

$cache = new Cache('/tmp/github-api-cache')
$cacheClient = new CachedClient();
$cacheClient->setCache($cache);

$client = new Client($cacheClient);
$adapter = new GithubAdapter($client, $project);

$filesystem = new Filesystem($adapter);
```

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email potherca@gmail.com instead of using the issue tracker.

## Credits

- [Potherca](https://github.com/potherca)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
