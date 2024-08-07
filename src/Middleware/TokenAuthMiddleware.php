<?php
namespace IntegrationPos\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use React\Promise\Deferred;
use Exception;

class TokenAuthMiddleware
{
    private $validToken;

    public function __construct(string $validToken)
    {
        $this->validToken = $validToken;
    }

    public function __invoke(ServerRequestInterface $request, callable $handler): PromiseInterface
    {
        $headers = $request->getHeaders();
        $authorizationHeader = $this->getAuthorizationHeader($headers);

        $deferred = new Deferred();
        if ($this->validateToken($authorizationHeader)) {
            // Create a deferred to handle the promise
            $handler($request)->then(
                function (Response $response) use ($deferred) {
                    $deferred->resolve($response);
                },
                function (Exception $e) use ($deferred) {
                    $deferred->reject($e);
                }
            );
            return $deferred->promise();
        } else {
            $deferred->resolve(new Response(403, ['Content-Type' => 'application/json'], json_encode([
                'Status' => 'error',
                'Message' => 'Unauthorized'
            ])));
            return $deferred->promise();
        }
    }
    private function getAuthorizationHeader(array $headers): string
    {
        $authorizationHeader = '';
        foreach ($headers as $key => $values) {
            if (strtolower($key) === 'authorization') {
                $authorizationHeader = $values[0] ?? '';
                break;
            }
        }
        return trim($authorizationHeader);
    }
     private function validateToken(string $authorizationHeader): bool
    {
        return $authorizationHeader === $this->validToken;
    }
}