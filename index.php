<?php

use HRDBase\Api\HRDApi;

include_once 'vendor/autoload.php';

$apiInstance = HRDApi::getInstance([
    'apiHash' => '__hash__',
    'apiLogin' => '__login__',
    'apiPass' => '__haslo_api__',
]);


// Example Fix utf8 keys
$partnerPricingServiceInfo = $apiInstance->partnerPricingServiceInfo('d_pl');
$info = [];
foreach ($partnerPricingServiceInfo['prices'] as $key => $value) {
    $info[] = [
        'name' => utf8_decode($key),
        'value' => $value,
    ];
}
echo '<pre>';
var_dump($info);