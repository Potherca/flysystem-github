# Flysystem Adapter for Github

[![Latest Version][Latest Version Badge]][Release Page]
[![Software License][Software License Badge]][License file]
[![Build Status][Build Status Badge]][Travis Page]
[![Coverage Status][Coverage Status Badge]][Coveralls Page]
[![Quality Score][Quality Score Badge]][Scrutinizer Page]
[![Total Downloads][Total Downloads Badge]][Packagist Page]

## Install

Via Composer

``` bash
$ composer require potherca/flysystem-github
```

## Usage

The Github adapter can be used *without* credentials to do read-only actions on
public repositories. To avoid reaching the Github API limit, to save changes, or 
to read from private repositories, credentials are required.

Caching can be utilized to save traffic or to postpone reaching the Github API 
limit.

### Basic Usage

```php
use Github\Client;
use League\Flysystem\Filesystem;
use Potherca\Flysystem\Github\Api;
use Potherca\Flysystem\Github\GithubAdapter;
use Potherca\Flysystem\Github\Settings;

$project = 'thephpleague/flysystem';

$settings = new Settings($project);

$api = new Api(new Client(), $settings);
$adapter = new GithubAdapter($api);
$filesystem = new Filesystem($adapter);
```

### Authentication

```php
use Github\Client;
use League\Flysystem\Filesystem;
use Potherca\Flysystem\Github\Api;
use Potherca\Flysystem\Github\GithubAdapter;
use Potherca\Flysystem\Github\Settings;

$project = 'thephpleague/flysystem';
$credentials = [Settings::AUTHENTICATE_USING_TOKEN, '83347e315b8bb4790a48ed6953a5ad9e825b4e10'];
// or $authentications = [Settings::AUTHENTICATE_USING_PASSWORD, $username, $password];
    
$settings = new Settings($project, $credentials);

$api = new Api(new Client(), $settings);
$adapter = new GithubAdapter($api);
$filesystem = new Filesystem($adapter);
```

### Cache Usage

```php
use Github\Client;
use Github\HttpClient\CachedHttpClient as CachedClient;
use Github\HttpClient\Cache\FilesystemCache as Cache;
use League\Flysystem\Filesystem;
use Potherca\Flysystem\Github\Api;
use Potherca\Flysystem\Github\GithubAdapter;
use Potherca\Flysystem\Github\Settings;

$project = 'thephpleague/flysystem';

$settings = new Settings($project);

$cache = new Cache('/tmp/github-api-cache')
$cacheClient = new CachedClient();
$cacheClient->setCache($cache);

$api = new Api($cacheClient, $settings);
$adapter = new GithubAdapter($api);
$filesystem = new Filesystem($adapter);

```

## Testing

The unit-tests can be run with the following command:

``` bash
$ composer test
```

To run integration tests, which use the Github API, a [Github API token](https://help.github.com/articles/creating-an-access-token-for-command-line-use/) might be needed (to stop the tests hitting the API Limit).
An API key can be added by setting it in the environment as `GITHUB_API_KEY` or by creating an `.env` file in the integration tests directory and setting it there.
See `tests/integration-tests/.env.example` for an example.

To run the integration test, run the following command (this will also run the unit-tests):

``` bash
$ composer test-all
```
 
## Security

If you discover any security related issues, please email potherca@gmail.com instead of using the issue tracker.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Change Log

Please see [CHANGELOG](CHANGELOG.md) for details.

## Credits

- [Potherca](https://github.com/potherca)

## License

The MIT License (MIT). Please see [License File] for more information.

[Release Page]: https://github.com/potherca/flysystem-github/releases
[License File]: LICENSE.md
[Travis Page]: https://travis-ci.org/Potherca/flysystem-github
[Coveralls Page]: https://coveralls.io/github/potherca/flysystem-github
[Scrutinizer Page]: https://scrutinizer-ci.com/g/potherca/flysystem-github
[Packagist Page]: https://packagist.org/packages/potherca/flysystem-github

[Latest Version Badge]: https://img.shields.io/github/release/potherca/flysystem-github.svg
[Software License Badge]: https://img.shields.io/badge/license-MIT-brightgreen.svg
[Build Status Badge]: https://img.shields.io/travis/Potherca/flysystem-github.svg
[Coverage Status Badge]: https://coveralls.io/repos/potherca/flysystem-github/badge.svg
[Quality Score Badge]: https://img.shields.io/scrutinizer/g/potherca/flysystem-github.svg
[Total Downloads Badge]: https://img.shields.io/packagist/dt/potherca/flysystem-github.svg