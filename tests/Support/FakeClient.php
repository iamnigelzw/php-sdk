<?php

declare(strict_types=1);

namespace ContiPay\PhpSdk\Tests\Support;

use ContiPay\PhpSdk\Core\Client;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;

/**
 * Core client that answers from queued responses instead of the network,
 * and records every request it sends.
 */
class FakeClient extends Client
{
    /**
     * @var array<int, array{request: RequestInterface}>
     */
    public array $history = [];

    protected MockHandler $mock;

    /**
     * @param array $responses Responses or exceptions to return, in order
     */
    public function __construct(string $token = 'test-token', string $secret = 'test-secret', array $responses = [])
    {
        parent::__construct($token, $secret);
        $this->mock = new MockHandler($responses);
    }

    public function lastRequest(): RequestInterface
    {
        return end($this->history)['request'];
    }

    protected function http(): HttpClient
    {
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        return new HttpClient([
            'base_uri' => $this->getBaseURL(),
            'handler' => $stack,
        ]);
    }
}
