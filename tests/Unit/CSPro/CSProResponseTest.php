<?php

namespace Tests\Unit\CSPro;

use AppBundle\CSPro\CSProResponse;
use PHPUnit\Framework\TestCase;

class CSProResponseTest extends TestCase
{
    public function testDefaultStatusIs200(): void
    {
        $response = new CSProResponse();
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testContentTypeIsJson(): void
    {
        $response = new CSProResponse();
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testSetErrorSetsStatusAndContent(): void
    {
        $response = new CSProResponse();
        $response->setError(404, 'not_found', 'Resource not found');

        $this->assertSame(404, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        $this->assertSame('error', $json['type']);
        $this->assertSame(404, $json['status']);
        $this->assertSame('not_found', $json['code']);
        $this->assertSame('Resource not found', $json['message']);
    }

    public function testSetErrorDefaultCode(): void
    {
        $response = new CSProResponse();
        $response->setError(400);

        $json = json_decode($response->getContent(), true);
        $this->assertSame('bad_request', $json['code']);
    }

    public function testSetErrorDefaultMessage(): void
    {
        $response = new CSProResponse();
        $response->setError(500);

        $json = json_decode($response->getContent(), true);
        $this->assertSame('', $json['message']);
    }

    public function testIsInvalidReturnsFalseForKnownCodes(): void
    {
        $knownCodes = [200, 400, 404, 500];
        foreach ($knownCodes as $code) {
            $response = new CSProResponse('', $code);
            $this->assertFalse($response->isInvalid(), "Code $code should be valid");
        }
    }

    public function testIsInvalidReturnsTrueForUnknownCode(): void
    {
        $response = new CSProResponse();
        // Bypass Symfony's strict validation to set a code not in $statusCodes
        $ref = new \ReflectionProperty($response, 'statusCode');
        $ref->setAccessible(true);
        $ref->setValue($response, 418);
        $this->assertTrue($response->isInvalid());
    }

    public function testCreateFactory(): void
    {
        $response = CSProResponse::create('{"ok":true}', 201);
        $this->assertInstanceOf(CSProResponse::class, $response);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('{"ok":true}', $response->getContent());
    }

    public function testStatusCodesMapping(): void
    {
        $expected = [
            200 => 'success',
            404 => 'not_found',
            500 => 'internal_server_error',
            401 => 'unauthorized',
            403 => 'forbidden',
        ];

        foreach ($expected as $code => $label) {
            $this->assertSame($label, CSProResponse::$statusCodes[$code]);
        }
    }
}
