<?php

use Symfony\Component\HttpFoundation\Request;

require __DIR__.'/vendor/autoload.php';
if (PHP_VERSION_ID < 70000) {
    include_once __DIR__.'./var/bootstrap.php.cache';
}
if (file_exists('./src/config.php') === false)  {
    header('Location:' . './setup/');
    exit();
}
    
//$kernel = new AppKernel('dev', true);
$kernel = new AppKernel('prod', false);
if (PHP_VERSION_ID < 70000) {
    $kernel->loadClassCache();
}
//$kernel = new AppCache($kernel);

// When using the HttpCache, you need to call the method in your front controller instead of relying on the configuration parameter
//Request::enableHttpMethodParameterOverride();
$request = Request::createFromGlobals();

// Behind a reverse proxy (Apache/Nginx + TLS termination) on the same host:
// trust the immediate proxy so Symfony reads X-Forwarded-Proto/Host/Port and
// generates absolute URLs with the correct https scheme. Without this, links
// and XHR endpoints are emitted as http:// and get blocked as mixed content.
if (($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $request->server->get('REMOTE_ADDR')) !== null) {
    Request::setTrustedProxies(
        explode(',', $trustedProxies),
        Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
    );
    $request = Request::createFromGlobals();
}

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
