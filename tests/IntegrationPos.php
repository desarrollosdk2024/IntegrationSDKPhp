<?php

namespace Integration;

use React\Socket\TcpServer;
use React\EventLoop\Factory as LoopFactory;
use Exception;
use React\Socket\ConnectionInterface;
use Integration\Extensions;
class IntegrationPos
{
    private static $instance;
    private $PORT;
    private $HOST;
    private $server;
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

    private function __construct() {}

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function initialize($host, $port, $devicesConfig)
    {
        $this->PORT = $port;
        $this->HOST = $host;
        $this->devicesConfig = $devicesConfig;
        return $this;
    }

    public function start()
    {
         $loop = LoopFactory::create();

        $this->server = new TcpServer("{$this->HOST}:{$this->PORT}", $loop);
        echo "Started server on port TCP-IP: {$this->PORT}\n";

        $this->server->on('connection', function (ConnectionInterface $client) {
            echo "Client connected.\n";
            $ip = $client->getRemoteAddress();
            echo "** New connection ** IP: {$ip}\n";

            $this->connectedDevices[$ip] = [
                'TcpClient' => $client,
                'Env' => (object) ['Logger' => false, 'StepNext' => 'step0', 'Name' => '------']
            ];
                echo "Current connections: " . count($this->connectedDevices) . "\n";
        });
      
        $loop->run();
        return $this;
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
            echo "Closed Connection\n";
        }
    }

    public function executeStep($tempSteps, $step, $socket, $env)
    {
        if (isset($tempSteps[$step])) {
            $stepParams = $tempSteps[$step];
            $env->Name = $stepParams['Name'];
            $env->StepNext = $step;
            $this->sendMessageBoxToPos($stepParams['Message'], $socket, $env);
        } else {
            echo "Step not found.\n";
        }
    }

    private function sendMessageBoxToPos($msg, $socket, $env)
    {
        $buffer = hex2bin($msg);
        $socket->write($buffer);

        if ($env->Logger) {
            echo json_encode(['step' => $env->StepNext, 'process' => "Sent to POS: " . $env->Name, 'message' => $msg]) . "\n";
        }
    }

    public function handleChip($config)
    {
        $deviceName = $config->Device ?? "---------";
        $responseDelegate = $config->Response;
    
        $connectedDevicesResult = $this->validConnectDevices($deviceName);
        if ($connectedDevicesResult->Status === 'error') {
            $responseDelegate((object) ['Status' => 'error', 'Message' => 'Device not configured']);
            return;
        }

        $client = $connectedDevicesResult->Device['TcpClient'];
        $env = $connectedDevicesResult->Device['Env'];
        $chipSteps = NextSteps::getSteps('chip');

        $steps = "";
        try {
            $env->Logger = $config->Logger;
            $this->executeStep($chipSteps, 'step1', $client, $env);
            $env->StepNext = 'step2';
            $importe = Extensions::validateAmount($config->Importe);
            $this->handleData($client, function ($strReply) use (&$steps, &$env, &$chipSteps, &$client, &$responseDelegate, &$importe) {
                $steps = $env->StepNext;
                $stepParams = $chipSteps[$steps] ?? null;

                if ($env->Logger) {
                    echo json_encode(['step' => $env->StepNext, 'process' => "Receiving POS: " . ($stepParams->name ?? ''), 'message' => $strReply]) . "\n";
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
                            $responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['002']]);
                        }
                        break;
                    case 'step9':
                        $unpackMessage = Extensions::unpackMessage($strReply);
                        if (isset($unpackMessage['48'])) {
                            $this->executeStep($chipSteps, 'step10', $client, $env);
                            $tempStep = $chipSteps['step11'];
                            $env->Name = $tempStep['Name'];
                            $env->StepNext = 'step11';
                            $this->sendMessageBoxToPos($tempStep['Func']($importe), $client, $env);
                            $env->StepNext = 'step12';
                        } else {
                            $responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['000']]);
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
                            $responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['002']]);
                        }
                        break;
                    case 'step17':
                        if (Extensions::isNAck($strReply)) {
                            $responseDelegate((object) ['Status' => 'error', 'Message' => $this->messageError['001']]);
                        } else {
                            $resps = $stepParams['Func']($strReply);
                            $this->executeStep($chipSteps, 'step18', $client, $env);
                            $responseDelegate((object) ['Status' => 'success', 'Message' => 'Transaccion procesada con exito', 'Data' => $resps]);
                        }
                        break;
                    default:
                        echo "Unknown step: {$steps}\n";
                        break;
                }
            });

            return;
        } catch (Exception $ex) {
            $responseDelegate((object) ['Status' => 'error', 'Message' => "Operation failed: {$ex->getMessage()}"]);
        } finally {
            $this->disconnect($client);
        }
    }
}