<?php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Desabilita rate limiting em testes — o cache array persiste entre testes
        // no mesmo processo PHP, causando 429 depois do 6º request ao grupo auth.
        $this->withoutMiddleware(ThrottleRequests::class);
    }
}
