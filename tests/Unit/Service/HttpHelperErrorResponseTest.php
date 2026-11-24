<?php

namespace Tests\Unit\Service;

// HttpHelperErrorResponse shares a file with HttpHelper (not PSR-4 autoloadable)
require_once __DIR__ . '/../../../src/AppBundle/Service/HttpHelper.php';

use AppBundle\Service\HttpHelperErrorResponse;
use PHPUnit\Framework\TestCase;

class HttpHelperErrorResponseTest extends TestCase
{
    public function testGetStatusCodeReturns500(): void
    {
        $response = new HttpHelperErrorResponse('Something went wrong');
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testGetHeadersReturnsEmptyArray(): void
    {
        $response = new HttpHelperErrorResponse('error');
        $this->assertSame([], $response->getHeaders());
    }

    public function testGetHeaderReturnsEmptyString(): void
    {
        $response = new HttpHelperErrorResponse('error');
        $this->assertSame('', $response->getHeader('Content-Type'));
    }

    public function testGetBodyReturnsMessage(): void
    {
        $msg = 'Connection timed out';
        $response = new HttpHelperErrorResponse($msg);
        $this->assertSame($msg, $response->getBody());
    }
}
