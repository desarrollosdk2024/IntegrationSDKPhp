<?php

namespace Integration;

date_default_timezone_set('America/La_Paz');

use React\Http\HttpServer;
use React\Socket\SocketServer;
use React\EventLoop\Factory;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use Exception;

use Integration\NextSteps;
use Integration\TimeoutMiddleware;
use Integration\TokenAuthMiddleware;

use React\Promise\PromiseInterface;
use React\Promise\Deferred;

class IntegrationPosv1
{
    private static $instance;
    private $PORT;
    private $HOST;
    public $app;
    private $server;
    private $timeout = 30;
    private $loop;
    private $logger = true;
    private $validToken = '';
    public $devicesConfig = [];
    private $connectedDevices = [];
    private $messageError = [
        "000" => "Error: No hay tarjeta",
        "001" => "Usuario canceló el ingreso de PIN",
        "002" => "Error en el ingreso del PIN",
        "003" => "Transacción declinada por tarjeta",
        "004" => "Transacción no existe",
        "005" => "Anulación no confirmada",
        "006" => "Cierre de lote cancelada",
        "007" => "Invalid unpacked data format",
        "008" => "Tiene Cierre de lote pendiente"
    ];
    /*
        private function __construct() {}
    */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function extractIpFromAddress($address)
    {
        $pattern = '/^(tcp|tls):\/\/([\w\.:]+)/';
        if (preg_match($pattern, $address, $matches)) {
            $ipPort = $matches[2];
            $ip = explode(':', $ipPort)[0];
            return $ip;
        }
        return null;
    }

    public function setToken($Token)
    {
        $this->validToken = $Token;
        return $this;
    }
    public function initialize($host, $port, $devicesConfig)
    {
        $this->loop = Factory::create();

        $this->PORT = $port;
        $this->HOST = $host;
        $this->devicesConfig = $devicesConfig;
        return $this;
    }

    public function response($code, $response)
    {
        return new Response(
            $code,
            ['Content-Type' => 'application/json'],
            json_encode($response)
        );
    }

    private function serverHttp()
    {
        $timeoutMiddleware = new TimeoutMiddleware($this->timeout, $this->loop);
        $authMiddleware = new TokenAuthMiddleware($this->validToken);

        $this->server = new HttpServer(
            $this->loop,
          //  $timeoutMiddleware,
            function (ServerRequestInterface $request) {// use ($authMiddleware)
               // return $authMiddleware($request, function (ServerRequestInterface $request) {
                    try {
                        $uri = $request->getUri();
                        $path = $uri->getPath();
                        $method = $request->getMethod();
                        $body = json_decode((string) $request->getBody(), true);

                        if (!isset($body['Device'])) {
                            return $this->response(400, ['Status' => 'error', 'Message' => 'Device required']);
                        }

                        if (!in_array($path, ['/api/chip', '/api/ctl', '/api/qr', '/api/lotclosing', '/api/initialization', '/api/annulment']) || $method !== 'POST') {
                            return $this->response(403, ['Status' => 'error', 'Message' => 'Action not permitted']);
                        }

                        switch ($path) {
                            case '/api/chip':
                                if (!isset($body['Importe']) || empty($body['Importe'])) {
                                    return $this->response(400, ['Status' => 'error', 'Message' => 'Amount required']);
                                }
                                return $this->handleChip((object) [
                                    'Logger' => $this->logger,
                                    'Device' => $body['Device'] ?? '',
                                    'Importe' => $body['Importe'] ?? '',
                                    'Response' => function ($result) {
                                        Extensions::logger("Message: " . $result->Message);
                                        return $this->response(200, $result);
                                    },
                                ]);
                            default:
                                return $this->response(404, ['Status' => 'error', 'Message' => 'Not Found']);
                        }
                    } catch (Exception $th) {
                        return $this->response(500, ['Status' => 'error', 'Message' => $th->getMessage()]);
                    }
               // });
            }
        );
    }
    private function isBlockedAddress($ip)
    {
       $hostname = gethostname();
       $localIp = gethostbyname($hostname);
       $localAddresses = ['127.0.0.1', '::1',$localIp];
       var_dump( $localAddresses);
        return in_array($ip, $localAddresses);
    }
    public function start()
    {
        $this->serverHttp();
        try {
            $tcpServer = new SocketServer("{$this->HOST}:{$this->PORT}", [], $this->loop);
            $tcpServer->on('connection', function ($client) {
                Extensions::logger("Client connected.");
                $ip = $this->extractIpFromAddress($client->getRemoteAddress());
                if (!$this->isBlockedAddress($ip)) {
                   Extensions::logger("** New connection ** IP: {$ip}");
                    $this->connectedDevices[$ip] = [
                        'TcpClient' => $client,
                        'Env' => (object) ['Logger' => false, 'StepNext' => 'step0', 'Name' => '------']
                    ];
                    Extensions::logger("Current connections: " . count($this->connectedDevices)); 
                }
                $client->on('close', function () {
                    Extensions::logger("TCP connection closed.");
                });
            });

            $this->server->listen($tcpServer);
            Extensions::logger("Started server on port TCP-IP: {$this->PORT}");
            $this->loop->run();
        } catch (Exception $e) {
            Extensions::logger("Error starting server: " . $e->getMessage());
        }
    }

    private function validConnectDevices($device)
    {

        if (!isset($this->devicesConfig[$device])) {
            return (object) ['Status' => 'error', 'Message' => 'Device not configured'];
        }
        $ipAddress = $this->devicesConfig[$device];
        if (!isset($this->connectedDevices[$ipAddress])) {
            return (object) ['Status' => 'error', 'Message' => 'Device not connected'];
        }
        return (object) ['Status' => 'success', 'Device' => $this->connectedDevices[$ipAddress], 'Message' => 'connected successfully'];
    }

    public function handleData($client, $dataHandler)
    {
        $client->on('data', function ($data) use ($dataHandler) {
            $hexString = bin2hex($data);
            $dataHandler($hexString);
        });
    }

    private function disconnect($client)
    {
        if ($client !== null) {
            $client->close();
            Extensions::logger("Closed Connection\n");
        }
    }

    public function executeStep($tempSteps, $step, $socket, $env)
    {

        if (isset($tempSteps[$step])) {
            $stepParams = $tempSteps[$step];
            $env->Name = $stepParams->name;
            $env->StepNext = $step;
            $this->sendMessageBoxToPos($stepParams->message, $socket, $env);
        } else {
            Extensions::logger("Step not found.");
        }
    }

    private function sendMessageBoxToPos($msg, $socket, $env)
    {
        $buffer = Extensions::HexStringToByteArray($msg);
        $socket->write($buffer);
        if ($env->Logger) {
            Extensions::logger(json_encode(['step' => $env->StepNext, 'process' => "Sent to POS: " . $env->Name, 'message' => $msg]));
        }
    }

    public function handleChip($config): PromiseInterface
    {
        $deferred = new Deferred();

        $deviceName = $config->Device ?? "---------";
        $responseDelegate = $config->Response;

        $connectedDevicesResult = $this->validConnectDevices($deviceName);

        if ($connectedDevicesResult->Status === 'error') {
            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => 'Device not configured']));
            return $deferred->promise();
        }
        $client = $connectedDevicesResult->Device['TcpClient'];
        $env = $connectedDevicesResult->Device['Env'];
        $chipSteps = NextSteps::getSteps('chip');
        $env->Logger = $config->Logger;

        try {

            $this->executeStep($chipSteps, 'step1', $client, $env);

            $env->StepNext = 'step2';

            $importe = Extensions::validateAmount($config->Importe);

            $this->handleData($client, function ($strReply) use (&$env, &$chipSteps, &$client, &$responseDelegate, &$importe, &$deferred) {

                $step = $env->StepNext;
                $stepParams = $chipSteps[$step] ?? null;

                if ($env->Logger) {
                    Extensions::logger(json_encode(['step' => $env->StepNext, 'process' => "Receiving POS: " . ($stepParams->name ?? '')]));
                }

                switch ($env->StepNext) {
                    case 'step2':
                        $env->StepNext = 'step3';
                        break;
                    case 'step3':
                        $this->executeStep($chipSteps, 'step4', $client, $env);
                        $this->executeStep($chipSteps, 'step5', $client, $env);
                        $env->StepNext = 'step6';
                        break;
                    case 'step6':
                        $env->StepNext = 'step7';
                        break;
                    case 'step7':
                        $unpackMessage = Extensions::unpackMessage($strReply);

                        if ($unpackMessage['87']['value'] === '1201') {
                            $this->executeStep($chipSteps, 'step8', $client, $env);
                            $env->StepNext = 'step9';
                        } else {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['002']]));
                        }
                        break;
                    case 'step9':
                        $unpackMessage = Extensions::unpackMessage($strReply);
                        if (isset($unpackMessage['48'])) {
                            $this->executeStep($chipSteps, 'step10', $client, $env);
                            $tempStep = $chipSteps['step11'];
                            $env->Name = $tempStep->name;
                            $env->StepNext = 'step11';
                            $this->sendMessageBoxToPos($tempStep->func->__invoke($importe), $client, $env);
                            $env->StepNext = 'step12';
                        } else {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['000']]));
                        }
                        break;
                    case 'step12':
                        $env->StepNext = 'step13';
                        break;
                    case 'step13':
                        $unpackMessage = Extensions::unpackMessage($strReply);
                        if ($unpackMessage['87']['value'] === '1202') {
                            $this->executeStep($chipSteps, 'step14', $client, $env);
                            $env->StepNext = 'step17';
                        } else {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['002']]));
                        }
                        break;
                    case 'step17':
                        if (Extensions::isNAck($strReply)) {
                            $responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['001']]);
                        } else {
                            $resps = $stepParams->func->__invoke($strReply);
                            $this->executeStep($chipSteps, 'step18', $client, $env);
                            $deferred->resolve($responseDelegate((object) ['Status' => 'success', 'Message' => 'Transaccion procesada con exito', 'Data' => $resps]));
                        }
                        break;
                    default:
                        Extensions::logger("Unknown step: {$env->StepNext}");
                        break;
                }
            });

        } catch (Exception $e) {
            Extensions::logger($e->getMessage());
            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $e->getMessage()]));
        }
       // $this->disconnect($client);
        return $deferred->promise();
    }
}