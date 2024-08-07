<?php

namespace IntegrationPos;

date_default_timezone_set('America/La_Paz');

use React\Http\HttpServer;
use React\Socket\SocketServer;
use React\EventLoop\Factory;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use Exception;

use IntegrationPos\Util\NextSteps;
use IntegrationPos\Util\Extensions;
use IntegrationPos\Middleware\TimeoutMiddleware;
use IntegrationPos\Middleware\TokenAuthMiddleware;
use IntegrationPos\RequestHandler as Handler;
use React\Promise\PromiseInterface;
use React\Promise\Deferred;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;

class IntegrationPos
{
    private static $instance;
    private $PORT;
    private $HOST;
    public $app;
    private $server;
    private $timeout = 120;
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

    public function response($code, $response): PromiseInterface
    {
        $deferred = new Deferred();

        $deferred->resolve(new Response($code, ['Content-Type' => 'application/json'], json_encode($response)));

        return $deferred->promise();
    }
    private function serverHttp()
    {
        $timeoutMiddleware = new TimeoutMiddleware($this->timeout, $this->loop);
        $authMiddleware = new TokenAuthMiddleware($this->validToken);
        $handler = new Handler($this->logger);
        $this->server = new HttpServer(
            $this->loop,
            $timeoutMiddleware,
            function (ServerRequestInterface $request) use ($authMiddleware, $handler) {
                return $authMiddleware($request, function (ServerRequestInterface $request) use ($handler) {

                    try {
                        $uri = $request->getUri();
                        $path = $uri->getPath();
                        $method = $request->getMethod();
                        $body = json_decode((string) $request->getBody(), true);

                        if (!isset($body['Device'])) {
                            return $this->response(StatusCode::STATUS_BAD_GATEWAY, ['Status' => 'error', 'Message' => 'Device required']);
                        }
                        if (
                            !in_array($path, ['/api/chip', '/api/ctl', '/api/qr', '/api/lotclosing', '/api/initialization', '/api/annulment'])
                            || $method !== RequestMethod::METHOD_POST
                        ) {
                            return $this->response(403, ['Status' => 'error', 'Message' => 'Action not permitted']);
                        }

                        switch ($path) {
                            case '/api/chip':

                                return $handler->handleChipRequest(
                                    $body,
                                    function ($data) {
                                        return $this->handleChip($data);
                                    },
                                    [$this, 'response']
                                );

                            case '/api/ctl':

                                return $handler->handleCtlRequest($body, function ($data) {
                                    return $this->handleCtl($data);
                                }, [$this, 'response']);

                            case '/api/qr':

                                return $handler->handleQrRequest($body, function ($data) {
                                    return $this->handleQr($data);
                                }, [$this, 'response']);

                            case '/api/lotclosing':

                                return $handler->handleLotClosureRequest($body, function ($data) {
                                    return $this->handleLotClosure($data);
                                }, [$this, 'response']);

                            case '/api/initialization':

                                return $handler->handleInitializationRequest($body, function ($data) {
                                    return $this->handleInitialization($data);
                                }, [$this, 'response']);

                            case '/api/annulment':

                                return $handler->handleAnnulmentRequest($body, function ($data) {
                                    return $this->handleAnnulment($data);
                                }, function ($status, $data) {
                                    return $this->response($status, $data);
                                });

                            default:
                                return $this->response(StatusCode::STATUS_NOT_FOUND, ['Status' => 'error', 'Message' => 'Not Found']);
                        }
                    } catch (Exception $th) {
                        return $this->response(StatusCode::STATUS_INTERNAL_SERVER_ERROR, ['Status' => 'error', 'Message' => $th->getMessage()]);
                    }
                });
            }
        );
    }
    private function isBlockedAddress($ip)
    {
        $localIp = gethostbyname(gethostname());
        $localAddresses = ['127.0.0.1', '::1', $localIp];
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
    private function transactionMiddleware($config, callable $next): PromiseInterface
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
        $env->Logger = $config->Logger;
        try {
            $next($client, $env, $responseDelegate, $deferred);
        } catch (Exception $e) {
            Extensions::logger($e->getMessage());
            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $e->getMessage()]));
        }

        return $deferred->promise();
    }

    public function handleChip($config): PromiseInterface
    {
        return $this->transactionMiddleware($config, function ($client, $env, $responseDelegate, $deferred) use (&$config) {

            $steps = NextSteps::getChipSteps();

            $this->executeStep($steps, 'step1', $client, $env);
            $env->StepNext = 'step2';
            $importe = Extensions::validateAmount($config->Importe);
            $this->handleData($client, function ($strReply) use (&$env, &$steps, &$client, &$responseDelegate, &$importe, &$deferred) {
                $step = $env->StepNext;
                $stepParams = $steps[$step] ?? null;
                if ($env->Logger) {
                    Extensions::logger(json_encode(['step' => $env->StepNext, 'process' => "Receiving POS: " . ($stepParams->name ?? '')]));
                }
                switch ($env->StepNext) {
                    case 'step2':
                        $env->StepNext = 'step3';
                        break;
                    case 'step3':
                        $this->executeStep($steps, 'step4', $client, $env);
                        $this->executeStep($steps, 'step5', $client, $env);
                        $env->StepNext = 'step6';
                        break;
                    case 'step6':
                        $env->StepNext = 'step7';
                        break;
                    case 'step7':
                        $unpackMessage = Extensions::unpackMessage($strReply);

                        if ($unpackMessage['87']['value'] === '1201') {
                            $this->executeStep($steps, 'step8', $client, $env);
                            $env->StepNext = 'step9';
                        } else {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['002']]));
                        }
                        break;
                    case 'step9':
                        $unpackMessage = Extensions::unpackMessage($strReply);
                        if (isset($unpackMessage['48'])) {
                            $this->executeStep($steps, 'step10', $client, $env);
                            $tempStep = $steps['step11'];
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
                            $this->executeStep($steps, 'step14', $client, $env);
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
                            $this->executeStep($steps, 'step18', $client, $env);
                            $deferred->resolve($responseDelegate((object) ['Status' => 'success', 'Message' => 'Transaccion procesada con exito', 'Data' => $resps]));
                        }
                        break;
                    default:
                        Extensions::logger("Unknown step: {$env->StepNext}");
                        break;
                }
            });
        });
    }

    public function handleQr($config): PromiseInterface
    {
        return $this->transactionMiddleware($config, function ($client, $env, $responseDelegate, $deferred) use (&$config) {

            $steps = NextSteps::getQrSteps(); // Obtener los pasos para el proceso QR

            // Inicialización y envío del primer paso
            $this->executeStep($steps, 'step1', $client, $env);
            $env->StepNext = 'step2';
            $importe = Extensions::validateAmount($config->Importe);
            // Manejo de datos recibidos
            $this->handleData($client, function ($strReply) use (&$env, &$steps, &$client, &$responseDelegate, &$deferred, $importe) {
                $step = $env->StepNext;
                $stepParams = $steps[$step] ?? null;

                if ($env->Logger) {
                    Extensions::logger(json_encode(['step' => $env->StepNext, 'process' => "Receiving POS: " . ($stepParams->name ?? ''), 'message' => $strReply]));
                }

                switch ($env->StepNext) {
                    case 'step2': // Recepción de ACK
                        $env->StepNext = 'step3';
                        break;

                    case 'step3': // Solicitud de datos
                        $unpackMessage = Extensions::unpackMessage($strReply);
                        if (isset($unpackMessage['48'])) {
                            $this->executeStep($steps, 'step4', $client, $env);
                            $tempStep = $steps['step5'];
                            $this->sendMessageBoxToPos($tempStep->func->__invoke($importe), $client, $env);
                            $env->StepNext = 'step6';
                        } else {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['000']]));
                        }
                        break;

                    case 'step6': // Recepción de ACK
                        $env->StepNext = 'step7';
                        break;

                    case 'step7': // Solicitud de nueva pantalla
                        $unpackMessage = Extensions::unpackMessage($strReply);
                        if ($unpackMessage['48']['value'] === '  ') {
                            $env->numberReference = $unpackMessage['43']['value'];
                            $this->executeStep($steps, 'step8', $client, $env);
                            $env->StepNext = 'step9';
                        }
                        break;

                    case 'step9': // Recepción de ACK
                        $env->StepNext = 'step10';
                        break;

                    case 'step10': // Solicitud de nueva pantalla
                        $unpackMessage = Extensions::unpackMessage($strReply);
                        if ($unpackMessage['48']['value'] === '  ') {
                            $this->executeStep($steps, 'step11', $client, $env);
                            $env->StepNext = 'step12';
                        } else {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['002']]));
                        }
                        break;

                    case 'step12': // Respuesta del host
                        if (Extensions::isNAck($strReply)) {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['001']]));
                        } else {
                            $resp = $stepParams->func->__invoke($strReply);
                            $resp[] = ['name' => 'numberReference', 'value' => $env->numberReference];
                            $deferred->resolve($responseDelegate((object) ['Status' => 'success', 'Message' => $resp]));
                            $this->executeStep($steps, 'step13', $client, $env);
                        }
                        break;

                    default:
                        Extensions::logger("Unknown step: {$env->StepNext}");
                        break;
                }
            });
        });
    }

    public function handleCtl($config): PromiseInterface
    {
        return $this->transactionMiddleware($config, function ($client, $env, $responseDelegate, $deferred) use (&$config) {

            $steps = NextSteps::getCtlSteps(); // Obtener los pasos para el proceso de CTL
            $this->executeStep($steps, 'step1', $client, $env);
            $env->StepNext = 'step2';
            $importe = Extensions::validateAmount($config->Importe);
            // Manejo de datos recibidos
            $this->handleData($client, function ($strReply) use (&$env, &$steps, &$client, &$responseDelegate, &$deferred, $importe) {
                $step = $env->StepNext;
                $stepParams = $steps[$step] ?? null;

                if ($env->Logger) {
                    Extensions::logger(json_encode(['step' => $env->StepNext, 'process' => "Receiving POS: " . ($stepParams->name ?? '')]));
                }

                switch ($env->StepNext) {
                    case 'step2':
                        $env->StepNext = 'step3';
                        break;
                    case 'step3':
                        $this->executeStep($steps, 'step4', $client, $env);
                        $this->executeStep($steps, 'step5', $client, $env);
                        $env->StepNext = 'step6';
                        break;
                    case 'step6':
                        $env->StepNext = 'step7';
                        break;
                    case 'step7':
                        $unpackMessage = Extensions::unpackMessage($strReply);
                        if (isset($unpackMessage['48'])) {
                            $this->executeStep($steps, 'step8', $client, $env);
                            $tempStep = $steps['step9'];
                            $this->sendMessageBoxToPos($tempStep->func->__invoke($importe), $client, $env);
                            $env->StepNext = 'step10';
                        } else {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['000']]));
                        }
                        break;
                    case 'step10':
                        $env->StepNext = 'step11';
                        break;
                    case 'step11':
                        $unpackMessage = Extensions::unpackMessage($strReply);
                        if ($unpackMessage['87']['value'] === '1201') {
                            $this->executeStep($steps, 'step12', $client, $env);
                            $this->executeStep($steps, 'step13', $client, $env);
                            $env->StepNext = 'step14';
                        }
                        break;
                    case 'step14':
                        $env->StepNext = 'step15';
                        break;
                    case 'step15':
                        $unpackMessage = Extensions::unpackMessage($strReply);
                        if (isset($unpackMessage['87'])) {
                            $this->executeStep($steps, 'step16', $client, $env);
                            $env->StepNext = 'step19';
                        } else {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['003']]));
                        }
                        break;
                    case 'step19':
                        if (Extensions::isNAck($strReply)) {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['001']]));
                        } else {
                            $resps = $stepParams->func->__invoke($strReply);
                            $deferred->resolve($responseDelegate((object) ['Status' => 'success', 'Message' => $resps]));
                            $this->executeStep($steps, 'step20', $client, $env);
                        }
                        break;
                    default:
                        Extensions::logger("Unknown step: {$env->StepNext}");
                        break;
                }
            });
        });
    }

    public function handleAnnulment($config): PromiseInterface
    {
        return $this->transactionMiddleware($config, function ($client, $env, $responseDelegate, $deferred) use (&$config) {
            $steps = NextSteps::getAnnulmentSteps();

            $this->executeStep($steps, 'step1', $client, $env);
            $env->StepNext = 'step2';

            $reference = Extensions::validateReference($config->Reference);
            // Manejo de datos recibidos
            $this->handleData($client, function ($strReply) use (&$env, &$steps, &$client, &$responseDelegate, &$deferred, &$reference) {
                $step = $env->StepNext;
                $stepParams = $steps[$step] ?? null;

                if ($env->Logger) {
                    Extensions::logger(json_encode(['step' => $env->StepNext, 'process' => "Receiving POS: " . ($stepParams->name ?? ''), 'message' => $strReply]));
                }

                switch ($env->StepNext) {
                    case 'step2':
                        $env->StepNext = 'step3';
                        break;
                    case 'step3':
                        $this->executeStep($steps, 'step4', $client, $env);
                        $tempStep = $steps['step5'];
                        $this->sendMessageBoxToPos($tempStep->func->__invoke($reference), $client, $env);
                        $env->StepNext = 'step6';
                        break;
                    case 'step6':
                        $env->StepNext = 'step7';
                        break;
                    case 'step7':
                        $unpackMessage = Extensions::unpackMessage($strReply);
                        if (isset($unpackMessage['48'])) {
                            if ($unpackMessage['48']['value'] !== '  ') {
                                $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['004']]));
                                $this->executeStep($steps, 'finish', $client, $env);
                                $env->StepNext = 'finish';
                            } else {
                                $this->executeStep($steps, 'step8', $client, $env);
                                $this->executeStep($steps, 'step9', $client, $env);
                                $env->StepNext = 'step10';
                            }
                        } else {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['004']]));
                        }
                        break;
                    case 'step10':
                        $env->StepNext = 'step11';
                        if (Extensions::isNAck($strReply)) {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['005']]));
                            $this->executeStep($steps, 'finish', $client, $env);
                            $env->StepNext = 'finish';
                        }
                        break;
                    case 'step11':
                        if (Extensions::isNAck($strReply)) {
                            if (Extensions::isNAck($strReply)) {
                                $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['005']]));
                                $this->executeStep($steps, 'finish', $client, $env);
                                $env->StepNext = 'finish';
                            }
                        } else {
                            $resp = $stepParams->func->__invoke($strReply);
                            $deferred->resolve($responseDelegate((object) ['Status' => 'success', 'Message' => $resp]));
                            $this->executeStep($steps, 'step12', $client, $env);
                        }
                        break;
                    case 'finish':
                    default:
                        $client->end();
                }
            });
        });
    }

    public function handleLotClosure($config): PromiseInterface
    {
        return $this->transactionMiddleware($config, function ($client, $env, $responseDelegate, $deferred) {
            $steps = NextSteps::getLotClosureSteps();

            $this->executeStep($steps, 'step1', $client, $env);
            $env->StepNext = 'step2';
            $env->Lotes = [];
            // Manejo de datos recibidos
            $this->handleData($client, function ($strReply) use (&$env, &$steps, &$client, &$responseDelegate, &$deferred) {
                $step = $env->StepNext;
                $stepParams = $steps[$step] ?? null;
                $unpackMessage = Extensions::unpackMessage($strReply);

                if ($env->Logger) {
                    Extensions::logger(json_encode(['step' => $env->StepNext, 'process' => "Receiving POS: " . ($stepParams->name ?? ''), 'message' => $strReply]));
                }

                switch ($env->StepNext) {
                    case 'step2':
                        $env->StepNext = 'step3';
                        if (Extensions::isNAck($strReply)) {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['006']]));
                            $this->executeStep($steps, 'finish', $client, $env);
                            $env->StepNext = 'finish';
                        }
                        break;
                    case 'step3':
                        if ($unpackMessage['48']['value'] === 'XX') {
                            $env->StepNext = 'finish';
                            $deferred->resolve($responseDelegate((object) ['Status' => 'success', 'Message' => $env->Lotes]));
                        } else {
                            $env->StepNext = 'step5';
                        }
                        $this->executeStep($steps, 'step4', $client, $env);
                        break;

                    case 'step5':
                        if (empty($unpackMessage['1']['value'])) {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'success', 'Message' => $env->Lotes]));
                        } else {
                            $datas = $unpackMessage['data'];
                            $env->Lotes[] = [
                                'receiptNumber' => $datas[43]['value'],
                                'purchaseAmount' => (float) $datas[40]['value'],
                                'responseCode' => $datas[48]['value']
                            ];
                        }
                        $this->executeStep($steps, 'step6', $client, $env);
                        $env->StepNext = 'step5';
                        break;
                    case 'finish':
                    default:
                        $client->end();
                }
            });
        });
    }
    public function handleInitialization($config): PromiseInterface
    {
        return $this->transactionMiddleware($config, function ($client, $env, $responseDelegate, $deferred) {
            $steps = NextSteps::getInitializeSteps();

            $this->executeStep($steps, 'step1', $client, $env);
            $env->StepNext = 'step2';
            $env->Lotes = [];

            // Manejo de datos recibidos
            $this->handleData($client, function ($strReply) use (&$env, &$steps, &$client, &$responseDelegate, &$deferred) {
                $step = $env->StepNext;
                $stepParams = $steps[$step] ?? null;
                $unpackMessage = Extensions::unpackMessage($strReply);

                if ($env->Logger) {
                    Extensions::logger(json_encode(['step' => $env->StepNext, 'process' => "Receiving POS: " . ($stepParams->name ?? ''), 'message' => $strReply]));
                }
                switch ($env->StepNext) {
                    case 'step2':
                        if (Extensions::isNAck($strReply)) {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['007']]));
                            $this->executeStep($steps, 'finish', $client, $env);
                            $env->StepNext = 'finish';
                            break;
                        };

                        if ($strReply === '0615') {
                            $env->StepNext = 'finish';
                            $deferred->resolve($responseDelegate((object) ['Status' => 'success', 'Message' => 'Inicializacion rechazada']));
                        } else {
                            $env->StepNext = 'step3';
                        }
                        break;

                    case 'step3':
                        if (Extensions::isNAck($strReply)) {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'error', 'Message' => 'Inicializacion rechazada']));
                        } else {
                            $deferred->resolve($responseDelegate((object) ['Status' => 'success', 'Message' => 'Inicializacion exitosa']));
                        }
                        $this->executeStep($steps, 'step4', $client, $env);
                        $env->StepNext = 'finish';
                        break;
                    case 'finish':
                    default:
                        $client->end();
                }
            });
        });
    }


    private function disconnect($client)
    {
        if ($client !== null) {
            $client->close();
            Extensions::logger("Closed Connection\n");
        }
    }
}