<?php

namespace IntegrationPos\Util;

use IntegrationPos\Util\Extensions;
use IntegrationPos\Util\Step;
use stdClass;

class NextSteps
{

    /**
     * Creates a new Step object with the specified parameters.
     *
     * @param mixed $id The ID of the step.
     * @param string $description The description of the step.
     * @param mixed $message The message to be sent.
     * @param array $fields Additional fields for the step (optional).
     * @param callable|null $callback A callback function to be executed after the step (optional).
     * @return Step The newly created Step object.
     */
    private static function createSendStep($id, $description, $message, $fields = [], $callback = null): Step
    {
        return new Step("send", $id, $description, $message, $fields, $callback);
    }

    /**
     * Creates a new Step object for receiving.
     *
     * @param mixed $id The ID of the step.
     * @param string $description The description of the step.
     * @param string $message The message to be received.
     * @param array $fields Additional fields for the step (optional).
     * @param callable|null $callback A callback function to be executed after the step (optional).
     * @return Step The newly created Step object.
     */
    private static function createReceiveStep($id, $description, $message = "", $fields = [], $callback = null): Step
    {
        return new Step("receive", $id, $description, $message, $fields, $callback);
    }

    /**
     * Returns an array of Step objects representing the steps involved in a chip transaction.
     *
     * @return array An associative array where each key represents a step and its value is a Step object.
     */
    public static function getChipSteps(): array
    {
        return [
            "step1" => self::createSendStep("1000000", "Transacción de solicitud de conexión", "02001736303030303030303030313030303030300323"),
            "step2" => self::createReceiveStep("06", "ACK"),
            "step3" => self::createReceiveStep("10001", "Última transacción", "", [43, 48]),
            "step4" => self::createSendStep("06", "ACK", "06"),
            "step5" => self::createSendStep("1000001", "Transacción No reverso", "02002436303030303030303030313030303030311C3438000258580303", [48]),
            "step6" => self::createReceiveStep("06", "ACK"),
            "step7" => self::createReceiveStep("10040", "Solicitud nueva pantalla", "", [48, 87]),
            "step8" => self::createSendStep("06", "ACK", "06"),
            "step9" => self::createReceiveStep("10002", "Solicitud de datos", "", [48]),
            "step10" => self::createSendStep("06", "ACK", "06"),
            "step11" => self::createSendStep(
                "1000002",
                "Transacción de envío de datos",
                "007736303030303030303030313030303030321C",
                [40, 42, 48, 53, 88],
                function ($imports) {
                    $tempSteps = self::getChipSteps()["step11"];
                    $list = FieldsHelper::getFields($tempSteps->fields);
                    $values = [
                        40 => $imports,
                        42 => str_pad("123", 10, ' '),
                        48 => "  ",
                        53 => str_pad("123456", 10, ' '),
                        88 => "1"
                    ];
                    return Extensions::packMessage($tempSteps->message, $list, $values);
                }
            ),
            "step12" => self::createReceiveStep("06", "ACK"),
            "step13" => self::createReceiveStep("10040", "Solicitud nueva pantalla PIN", "", [48, 87]),
            "step14" => self::createSendStep("06", "ACK", "06"),
            "step17" => self::createReceiveStep(
                "10003",
                "Respuesta del HOST de la venta realizada",
                "",
                [1, 40, 43, 44, 45, 46, 47, 48, 50, 51, 54, 61, 75],
                function ($strReply) {
                    return self::extractValues($strReply);
                }
            ),
            "step18" => self::createSendStep("06", "ACK", "06"),
        ];
    }

    /**
     * Retrieves the steps for processing a Contactless transaction.
     *
     * @return array
     */
    public static function getCtlSteps(): array
    {
        return [
            "step1" => new Step("send", "1006000", "Solicitud de conexión de una venta por contactless", "02001736303030303030303030313030363030300325"),
            "step2" => new Step("receive", "06", "ACK", "", []),
            "step3" => new Step("receive", "10001", "Última transacción", "", [43, 48]),
            "step4" => new Step("send", "06", "ACK", "06", []),
            "step5" => new Step("send", "1000001", "Transacción No reverso", "02002436303030303030303030313030303030311C3438000258580303", [48]),
            "step6" => new Step("receive", "06", "ACK", "", []),
            "step7" => new Step("receive", "10002", "Solicitud de datos", "", [48]),
            "step8" => new Step("send", "06", "ACK", "06", []),
            "step9" => new Step(
                "send",
                "1000002",
                "Transacción de envío de datos",
                "007736303030303030303030313030303030321C",
                [40, 42, 48, 53, 88],
                function ($imports) {
                    $tempSteps = self::getCtlSteps()["step9"];
                    $list = FieldsHelper::getFields($tempSteps->fields);
                    $values = [
                        40 => $imports,
                        42 => str_pad("123", 10, ' '),
                        48 => "  ",
                        53 => str_pad("123456", 10, ' '),
                        88 => "1"
                    ];
                    return Extensions::packMessage($tempSteps->message, $list, $values);
                }
            ),
            "step10" => new Step("receive", "06", "ACK", "", []),
            "step11" => new Step("receive", "10040", "Solicitud nueva pantalla", "", [48, 87]),
            "step12" => new Step("send", "06", "ACK", "06", []),
            "step13" => new Step("send", "1006001", "Tipo de lectura de la tarjeta", "02001736303030303030303030313030363030310324", [48]),
            "step14" => new Step("receive", "06", "ACK", "", []),
            "step15" => new Step("receive", "10040", "Solicitud nueva pantalla PIN", "", [48, 87]),
            "step16" => new Step("send", "06", "ACK", "06", []),
            "step17" => new Step(
                "receive",
                "10060",
                "Respuesta del HOST de la venta realizada por contactless",
                "",
                [1, 40, 43, 44, 45, 46, 47, 48, 50, 51, 54, 61, 75],
                function ($strReply) {
                    return self::extractValues($strReply);
                }
            ),
            "step20" => new Step("send", "06", "ACK", "06", []),
        ];
    }

    /**
     * Retrieves the steps for processing a QR transaction.
     *
     * @return array
     */
    public static function getQrSteps(): array
    {
        return [
            "step1" => new Step("send", "1008000", "Transacción Solicitud de conexión QR", "0200173630303030303030303031303038303030032B", []),
            "step2" => new Step("receive", "06", "ACK", "", []),
            "step3" => new Step("receive", "10001", "Solicitud de datos", "31303030202032", [48]),
            "step4" => new Step("send", "06", "ACK", "06", []),
            "step5" => new Step(
                "send",
                "1000002",
                "Transacción de envío de datos",
                "008036303030303030303030313030303030321C",
                [40, 42, 48, 53, 88],
                function ($imports) {
                    $tempSteps = self::getQrSteps()["step5"];

                    $list = FieldsHelper::getFields($tempSteps->fields);
                    $values = [
                        40 => $imports,
                        42 => str_pad("123", 10, ' '),
                        48 => "  ",
                        53 => str_pad("123456", 10, ' '),
                        88 => "1"
                    ];
                    return Extensions::packMessage($tempSteps->message, $list, $values);
                }
            ),
            "step6" => new Step("receive", "06", "ACK", "", []),
            "step7" => new Step("receive", "10040", "Número de referencias pendiente", "", [43, 48]),
            "step8" => new Step("send", "06", "ACK", "06", []),
            "step9" => new Step("receive", "06", "ACK", "", []),
            "step10" => new Step("receive", "10002", "Solicitud de nueva pantalla", "", [48]),
            "step11" => new Step("send", "06", "ACK", "06", []),
            "step12" => new Step(
                "receive",
                "10060",
                "Respuesta del HOST de la venta realizada por contactless",
                "",
                [1, 40, 43, 45, 46, 47, 48, 50, 51, 54, 61],
                function ($strReply) {
                    return self::extractValues($strReply);
                }
            ),
            "step13" => new Step("send", "06", "ACK", "06", []),
            "finish" => new Step("finish", null, "FINISH - ACK", "06"),
        ];
    }
    /**
     * Returns an array of steps for the annulment process.
     *
     * @return array An array of Step objects representing the annulment process steps.
     */
    public static function getAnnulmentSteps(): array
    {
        return [
            "step1" => new Step("send", "1005000", "Solicitud de anulación", "02001736303030303030303030313030353030300326"),
            "step2" => new Step("receive", "06", "ACK", "", []),
            "step3" => new Step("receive", "10050", "Solicitud de la referencia", "", [48]),
            "step4" => new Step("send", "06", "ACK", "06", []),
            "step5" => new Step(
                "send",
                "1005001",
                "Referencia de la transacción",
                "003536303030303030303030313030353030311C",
                [43, 48],
                function ($numberReference) {
                    $tempSteps = self::getAnnulmentSteps()["step5"];

                    $list = FieldsHelper::getFields($tempSteps->fields);
                    $values = [
                        43 => $numberReference,
                        48 => "  "
                    ];
                    return Extensions::packMessage($tempSteps->message, $list, $values);
                }
            ),
            "step6" => new Step("receive", "06", "ACK", "", []),
            "step7" => new Step("receive", "10050", "Respuesta del HOST de la anulación", "", [1, 43, 44, 45, 46, 47, 48, 50, 51, 54, 61, 75]),
            "step8" => new Step("send", "06", "ACK", "06", []),
        ];
    }

    /**
     * Returns an array of steps for the lot closure process.
     *
     * @return array An array of Step objects representing the lot closure process steps.
     */
    public static function getLotClosureSteps(): array
    {
        return [
            "step1" => new Step("send", "1005000", "Solicitud de cierre", "02001736303030303030303030313030313030300322", []),
            "step2" => new Step("receive", "06", "ACK/NACK", "", []),
            "step3" => new Step("receive", "10050", "Cantidad de transacciones", "", [48, 90]),
            "step4" => new Step("send", "06", "ACK/NACK", "06", []),
            "step5" => new Step(
                "receive",
                "10011",
                "Transacción 1, 2, ...N",
                "",
                [1, 40, 43, 44, 45, 46, 47, 48, 50, 51, 54, 75],
                function ($strReply) {
                    $response = Extensions::unpackMessage($strReply);
                    $extractedValues = array_filter($response['data'], function ($item) {
                        return isset($item['name']) && isset($item['value']);
                    });
                    return array_map(function ($item) {
                        return [
                            'name' => $item['name'],
                            'value' => $item['value']
                        ];
                    }, $extractedValues);
                }
            ),
            "step6" => new Step("send", "06", "ACK/NACK", "06", []),
            "step7" => new Step(
                "receive",
                "10012",
                "Respuesta del HOST del cierre",
                "",
                [1, 48],
                function ($strReply) {
                    $response = Extensions::unpackMessage($strReply);
                    $extractedValues = array_filter($response['data'], function ($item) {
                        return isset($item['name']) && isset($item['value']);
                    });
                    return array_map(function ($item) {
                        return [
                            'name' => $item['name'],
                            'value' => $item['value']
                        ];
                    }, $extractedValues);
                }
            ),
            "step8" => new Step("send", "06", "ACK/NACK", "06", []),
            "stepNack" => new Step("send", "15", "ACK/NACK", "15", []),
            "finish" => new Step("finish", null, "FINISH - ACK", "06"),
        ];
    }
    /**
     * Retrieves the steps for initializing a process.
     *
     * @return array An array of Step objects representing the initialization steps.
     */
    public static function getInitializeSteps(): array
    {
        return [
            "step1" => new Step("send", "1005000", "Solicitud de inicialización", "02001736303030303030303030313030323030300321", []),
            "step2" => new Step("receive", "06", "ACK/NACK", "", []),
            "step3" => new Step("receive", "10050", "Respuesta del HOST de la inicialización", "", [48]),
            "step4" => new Step("send", "06", "ACK/NACK", "06", []),
            "finish" => new Step("finish", null, "FINISH - ACK", "06"),
        ];
    }
    /**
     * Extracts values from a string reply and returns them as a stdClass object.
     *
     * @param string $strReply The string reply to extract values from.
     * @return stdClass The stdClass object containing the extracted values.
     */
    function extractValues(string $strReply): stdClass
    {
        $unpackedMessage = Extensions::unpackMessage($strReply);
        return array_reduce($unpackedMessage, function ($result, $item) {
            if (isset($item['name']) && isset($item['value'])) {
                $result->{$item['name']} = $item['value'];
            }
            return $result;
        }, new stdClass());
    }
}