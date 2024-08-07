<?php 
namespace IntegrationPos\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\Promise;
use React\Promise\Timer\TimeoutException;
use React\Promise\Timer;

class TimeoutMiddleware
{
    private $timeout;
    private $loop;

    public function __construct($timeout, $loop)
    {
        $this->timeout = $timeout;
        $this->loop = $loop;
    }

    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        return Timer\timeout(new Promise(function ($resolve, $reject) use ($request, $next) {
            $resolve($next($request));
        }), $this->timeout, $this->loop)->catch(function (TimeoutException $e) {
            return new Response(
                408,
                ['Content-Type' => 'application/json'],
                json_encode(['Status' => 'error', 'Message' => 'Request Timeout'])
            );
        });
    }
}