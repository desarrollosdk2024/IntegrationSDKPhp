<?php

namespace IntegrationPos;

use IntegrationPos\Util\Extensions;
use React\Promise\PromiseInterface;
use Fig\Http\Message\StatusCodeInterface as StatusCode;

class RequestHandler
{
    private $logger;

    /**
     * Constructs a new instance of the class.
     *
     * @param mixed $logger The logger instance to be used by the class.
     */
    public function __construct($logger)
    {
        $this->logger = $logger;
    }


    /**
     * Handles a chip request.
     *
     * @param array $body The request body containing the necessary data.
     * @param callable $handleProcess The callback function to handle the annulment process.
     * @param callable $response The callback function to handle the response.
     * @return PromiseInterface A promise representing the result of the annulment process.
     */
    public function handleChipRequest(array $body, callable $handleProcess, callable $response): PromiseInterface
    {
        if (empty($body['Importe'])) {
            return $response(400, ['Status' => 'error', 'Message' => 'Amount required']);
        }
        return $handleProcess((object) [
            'Logger' => $this->logger,
            'Device' => $body['Device'],
            'Importe' => $body['Importe'],
            'Response' => function ($result) use ($response) {
                Extensions::logger("Message: " . $result->Message);
                return $response(StatusCode::STATUS_OK, $result);
            },
        ]);
    }


    /**
     * Handles a CTL request.
     *
     * @param array $body The request body containing the necessary data.
     * @param callable $handleProcess The callback function to handle the annulment process.
     * @param callable $response The callback function to handle the response.
     * @return PromiseInterface A promise representing the result of the annulment process.
     */
    public function handleCtlRequest(array $body, callable $handleProcess, callable $response): PromiseInterface
    {
        if (empty($body['Importe'])) {
            return $response(400, ['Status' => 'error', 'Message' => 'Amount required']);
        }
        return $handleProcess((object) [
            'Logger' => $this->logger,
            'Device' => $body['Device'],
            'Reference' => $body['Reference'] ?? '',
            'Response' => function ($result) use ($response) {
                Extensions::logger("Message: " . $result->Message);
                return $response(StatusCode::STATUS_OK, $result);
            },
        ]);
    }

    /**
     * Handles a QR request.
     *
     * @param array $body The request body containing the necessary data.
     * @param callable $handleProcess The callback function to handle the annulment process.
     * @param callable $response The callback function to handle the response.
     * @return PromiseInterface A promise representing the result of the annulment process.
     */
    public function handleQrRequest(array $body, callable $handleProcess, callable $response): PromiseInterface
    {
        if (empty($body['Importe'])) {
            return $response(400, ['Status' => 'error', 'Message' => 'Amount required']);
        }
        return $handleProcess((object) [
            'Logger' => $this->logger,
            'Device' => $body['Device'],
            'Data' => $body['Data'] ?? '',
            'Response' => function ($result) use ($response) {
                Extensions::logger("Message: " . $result->Message);
                return $response(StatusCode::STATUS_OK, $result);
            },
        ]);
    }

    /**
     * Handles a lot closure request.
     *
     * @param array $body The request body containing the necessary data.
     * @param callable $handleProcess The callback function to handle the annulment process.
     * @param callable $response The callback function to handle the response.
     * @return PromiseInterface A promise representing the result of the annulment process.
     */
    public function handleLotClosureRequest(array $body, callable $handleProcess, callable $response): PromiseInterface
    {
        return $handleProcess((object) [
            'Logger' => $this->logger,
            'Device' => $body['Device'],
            'Response' => function ($result) use ($response) {
                Extensions::logger("Message: " . $result->Message);
                return $response(StatusCode::STATUS_OK, $result);
            },
        ]);
    }


    /**
     * Handles an initialization request.
     *
     * @param array $body The request body containing the necessary data.
     * @param callable $handleProcess The callback function to handle the annulment process.
     * @param callable $response The callback function to handle the response.
     * @return PromiseInterface A promise representing the result of the annulment process.
     */
    public function handleInitializationRequest(array $body, callable $handleProcess, callable $response): PromiseInterface
    {
        return $handleProcess((object) [
            'Logger' => $this->logger,
            'Device' => $body['Device'],
            'Response' => function ($result) use ($response) {
                Extensions::logger("Message: " . $result->Message);
                return $response(StatusCode::STATUS_OK, $result);
            },
        ]);
    }

    /**
     * Handles an annulment request.
     *
     * @param array $body The request body containing the necessary data.
     * @param callable $handleProcess The callback function to handle the annulment process.
     * @param callable $response The callback function to handle the response.
     * @return PromiseInterface A promise representing the result of the annulment process.
     */
    public function handleAnnulmentRequest(array $body, callable $handleProcess, callable $response): PromiseInterface
    {
        if (empty($body['Reference'])) {
            return $response(400, ['Status' => 'error', 'Message' => 'Reference required']);
        }
        return $handleProcess((object) [
            'Logger' => $this->logger,
            'Device' => $body['Device'],
            'Reference' => $body['Reference'],
            'Response' => function ($result) use ($response) {
                Extensions::logger("Message: " . $result->Message);
                return $response(StatusCode::STATUS_OK, $result);
            },
        ]);
    }

}