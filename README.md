## API v2 - HRD.pl 

- [Oficjalna Dokumentacja](https://api.hrd.pl/)

### Installation

```sh
$ composer require hrdbase/api
```

### Usage

``` php
use HRDBase\Api\HRDApi;

include_once 'vendor/autoload.php';

$apiInstance = HRDApi::getInstance([
    'apiHash' => '__hash__',
    'apiLogin' => '__login__',
    'apiPass' => '__haslo_api__',
]);
```
