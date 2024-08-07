/* public function handleChip($config): PromiseInterface
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
*/