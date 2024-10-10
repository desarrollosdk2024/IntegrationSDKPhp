<?php

namespace IntegrationPos\Util;

class Field
{
    public $Id;
    public $Name;
    public $Length;
    public $Format;
    public $Alias;
}

class FieldsHelper
{
      private static $responses =  [
    '0' => 'TRANS. APROBADA',
    '1' => 'CONTACTE / BANCO',
    '3' => 'COMERCIO INVALID',
    '4' => 'TARJ. OBSERVADA',
    '*default' => 'TRANS. RECHAZADA',
    '7' => 'TARJ. OBSERVADA',
    '12' => 'TRANS. INVALIDA',
    '13' => 'MONTO INVALIDO',
    '14' => 'TARJETA INVALIDA',
    '17' => 'BANCO INVALIDA',
    '19' => 'BANCO NO RESPOND',
    '20' => 'RTA INVALIDA',
    '30' => 'ERROR DE FORMATO',
    '33' => 'TARJ. EXPIRADA',
    '36' => 'TARJ.RESTRINGIDA',
    '38' => 'EXCEDE INT. PIN',
    '41' => 'TARJETA PERDIDA',
    '42' => 'CUENTA NO ENCON.',
    '43' => 'TARJETA PERDIDA',
    '45' => 'DEPOSITE LOTE',
    '50' => 'SIN RESPUESTA',
    '51' => 'FONDOS INSUFICI.',
    '08-10-11-15-16-32-34-37-39-40-52-53-56-57-59-60' => 'TRANS. RECHAZADA',
    '54' => 'TARJETA VENCIDA',
    '55' => 'PIN INVALIDO',
    '58' => 'CUENTA NO ENCON.',
    '61' => 'EXCEDE LIMITE',
    '62' => 'TARJ. RESTRINGID',
    '65' => 'EXCEDE LIMITE',
    '67' => 'TARJETA OBSERVAD',
    '68' => 'SIN RESPUESTA',
    '75' => 'EXCEDE INT. PIN',
    '76' => 'ERROR COD. PROD.',
    '77' => 'ERROR DEPOSITO',
    '78' => 'TRAMA NO ENCONT.',
    '80' => 'LINEA DESCONECT.',
    '81' => 'LINEA OCUPADA',
    '82' => 'NO CONTESTA',
    '83' => 'NO HAY TONO',
    '84' => 'ERROR DE COMUN.',
    '85' => 'LOTE NO ENCONTR.',
    '87' => 'OPER. CANCELADA',
    '88' => 'ERROR CONEXION',
    '89' => 'TCP ERROR',
    '90' => 'REVERSA PENDIENT',
    '91' => 'BANCO NO RESPOND',
    '92' => 'BANCO NO RESPOND',
    '94' => 'TRAMA DUPLICADA',
    '95' => 'TRANS. DE LOTES',
    '96' => 'SIS. CON ERROR',
    'AP' => 'TRANS. APROBADA',
    'BL' => 'BATERIA BAJA',
    'CN' => 'TRANS. CANCELADA',
    'DE' => 'DATOS ERRONEOS',
    'HO-NC' => 'INTENTE DE NUEVO',
    'IP' => 'LLAMAR A ATC IP',
    'IR' => 'LLAMAR A ATC IR',
    'IS' => 'LLAMAR A ATC IS',
    'IT' => 'LLAMAR A ATC IT',
    'LC' => 'INTENTE DE NUEVO',
    'LN' => 'NO HAY TONO',
    'LO' => 'LINEA OCUPADA',
    'MA' => 'DESLICE TARJETA',
    'RE' => 'ERROR EN LA LECTURA',
    'SB' => 'TARJETA BLOQUEAD',
    'SC' => 'DESLICE TAJ.AMEX',
    'SE' => 'ERROR DEL SISTEM',
    'TA' => 'TRANS. ACEPTADA',
    'TO' => 'INTENTE DE NUEVO',
    'VS' => 'VERIFIQUE FIRMA',
    'UC' => 'TJ. NO SOPORTADA',
    'PR' => 'TRANS. PROCESADA',
    '**' => 'BANCO INVALIDO'
];
    private static $fields = [
        ['Id' => 1, 'Name' => 'Código de autorización', 'Length' => 6, 'Format' => 'ASCII', 'Alias' => 'authCode'],
        ['Id' => 30, 'Name' => 'BIN de la tarjeta', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'cardBIN'],
        ['Id' => 31, 'Name' => 'Número de tarjeta', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'cardNumber'],
        ['Id' => 40, 'Name' => 'Monto compra', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'purchaseAmount'],
        ['Id' => 41, 'Name' => 'IVA', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'IVA'],
        ['Id' => 42, 'Name' => 'Número de caja', 'Length' => 10, 'Format' => 'ASCII', 'Alias' => 'cashierNumber'],
        ['Id' => 43, 'Name' => 'Número de recibo', 'Length' => 6, 'Format' => 'ASCII', 'Alias' => 'receiptNumber'],
        ['Id' => 44, 'Name' => 'RRN', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'RRN'],
        ['Id' => 45, 'Name' => 'Terminal ID', 'Length' => 8, 'Format' => 'ASCII', 'Alias' => 'terminalID'],
        ['Id' => 46, 'Name' => 'Fecha transacción', 'Length' => 4, 'Format' => 'ASCII', 'Alias' => 'transactionDate'],
        ['Id' => 47, 'Name' => 'Hora transacción', 'Length' => 4, 'Format' => 'ASCII', 'Alias' => 'transactionTime'],
        ['Id' => 48, 'Name' => 'Código de respuesta', 'Length' => 2, 'Format' => 'ASCII', 'Alias' => 'responseCode'],
        ['Id' => 49, 'Name' => 'Franquicia', 'Length' => 10, 'Format' => 'ASCII', 'Alias' => 'franchise'],
        ['Id' => 50, 'Name' => 'Tipo de cuenta', 'Length' => 2, 'Format' => 'ASCII', 'Alias' => 'accountType'],
        ['Id' => 51, 'Name' => 'Número de cuotas', 'Length' => 2, 'Format' => 'ASCII', 'Alias' => 'installmentNumber'],
        ['Id' => 53, 'Name' => 'Número de transacción', 'Length' => 10, 'Format' => 'ASCII', 'Alias' => 'transactionNumber'],
        ['Id' => 54, 'Name' => 'Últimos 4 dígitos', 'Length' => 4, 'Format' => 'ASCII', 'Alias' => 'last4Digits'],
        ['Id' => 55, 'Name' => 'Tipo de documento', 'Length' => 1, 'Format' => 'ASCII', 'Alias' => 'documentType'],
        ['Id' => 56, 'Name' => 'Número de documento', 'Length' => 11, 'Format' => 'ASCII', 'Alias' => 'documentNumber'],
        ['Id' => 57, 'Name' => 'Número telefónico', 'Length' => 8, 'Format' => 'ASCII', 'Alias' => 'phoneNumber'],
        ['Id' => 58, 'Name' => 'Código del banco', 'Length' => 2, 'Format' => 'ASCII', 'Alias' => 'bankCode'],
        ['Id' => 59, 'Name' => 'Número de cuenta', 'Length' => 13, 'Format' => 'ASCII', 'Alias' => 'accountNumber'],
        ['Id' => 60, 'Name' => 'Número de cheque', 'Length' => 10, 'Format' => 'ASCII', 'Alias' => 'chequeNumber'],
        ['Id' => 61, 'Name' => 'Mensaje de error', 'Length' => 69, 'Format' => 'ASCII', 'Alias' => 'errorMessage'],
        ['Id' => 62, 'Name' => 'Holder name', 'Length' => 26, 'Format' => 'ASCII', 'Alias' => 'cardHolderName'],
        ['Id' => 63, 'Name' => 'Criptograma', 'Length' => 16, 'Format' => 'ASCII', 'Alias' => 'cryptogram'],
        ['Id' => 64, 'Name' => 'TVR', 'Length' => 10, 'Format' => 'ASCII', 'Alias' => 'TVR'],
        ['Id' => 66, 'Name' => 'TSI', 'Length' => 4, 'Format' => 'ASCII', 'Alias' => 'TSI'],
        ['Id' => 67, 'Name' => 'AID', 'Length' => 32, 'Format' => 'ASCII', 'Alias' => 'AID'],
        ['Id' => 68, 'Name' => 'Label', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'label'],
        ['Id' => 69, 'Name' => 'Fecha vencimiento cheque 4', 'Length' => 16, 'Format' => 'ASCII', 'Alias' => 'chequeExpirationDate4'],
        ['Id' => 70, 'Name' => 'Valor cheque 5', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'chequeValue5'],
        ['Id' => 71, 'Name' => 'Fecha vencimiento cheque 5', 'Length' => 4, 'Format' => 'ASCII', 'Alias' => 'chequeExpirationDate5'],
        ['Id' => 73, 'Name' => 'Valor cheque 6', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'chequeValue6'],
        ['Id' => 74, 'Name' => 'Fecha vencimiento cheque 6', 'Length' => 4, 'Format' => 'ASCII', 'Alias' => 'chequeExpirationDate6'],
        ['Id' => 75, 'Name' => 'BIN Tarjeta', 'Length' => 6, 'Format' => 'ASCII', 'Alias' => 'cardBINTarjeta'],
        ['Id' => 76, 'Name' => 'Fecha de vencimiento tarjeta', 'Length' => 4, 'Format' => 'ASCII', 'Alias' => 'cardExpirationDate'],
        ['Id' => 77, 'Name' => 'Código comercio', 'Length' => 23, 'Format' => 'ASCII', 'Alias' => 'merchantCode'],
        ['Id' => 78, 'Name' => 'Dirección establecimiento', 'Length' => 23, 'Format' => 'ASCII', 'Alias' => 'establishmentAddress'],
        ['Id' => 79, 'Name' => 'Label', 'Length' => 2, 'Format' => 'ASCII', 'Alias' => 'label'],
        ['Id' => 80, 'Name' => 'Valor base devolución', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'refundBaseAmount'],
        ['Id' => 81, 'Name' => 'Propina', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'tip'],
        ['Id' => 82, 'Name' => 'N/A', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'N/A'],
        ['Id' => 83, 'Name' => 'Id cajero', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'cashierId'],
        ['Id' => 84, 'Name' => 'Valor tasa administrativa', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'administrativeFeeAmount'],
        ['Id' => 85, 'Name' => 'IVA tasa administrativa', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'administrativeFeeIVA'],
        ['Id' => 86, 'Name' => 'Base devolución IVA tasa administrativa', 'Length' => 12, 'Format' => 'ASCII', 'Alias' => 'refundBaseAdministrativeFeeIVA'],
        ['Id' => 87, 'Name' => 'Solicitud nueva pantalla', 'Length' => 2, 'Format' => 'HEXA', 'Alias' => 'newScreenRequest'],
        ['Id' => 88, 'Name' => 'Tipo de cuenta selección', 'Length' => 1, 'Format' => 'ASCII', 'Alias' => 'accountTypeSelection'],
        ['Id' => 89, 'Name' => 'N/A', 'Length' => 0, 'Format' => 'ASCII', 'Alias' => 'N/A'],
        ['Id' => 90, 'Name' => 'N/A', 'Length' => 0, 'Format' => 'ASCII', 'Alias' => 'N/A'],
        ['Id' => 91, 'Name' => 'N/A', 'Length' => 0, 'Format' => 'ASCII', 'Alias' => 'N/A'],
        ['Id' => 92, 'Name' => 'Id autorización', 'Length' => 10, 'Format' => 'ASCII', 'Alias' => 'authorizationId'],
        ['Id' => 93, 'Name' => 'N/A', 'Length' => 0, 'Format' => 'ASCII', 'Alias' => 'N/A'],
        ['Id' => 94, 'Name' => 'Id compra/venta', 'Length' => 10, 'Format' => 'ASCII', 'Alias' => 'purchaseSaleId'],
        ['Id' => 95, 'Name' => 'N/A', 'Length' => 0, 'Format' => 'ASCII', 'Alias' => 'N/A'],
        ['Id' => 96, 'Name' => 'Identificador dispositivo', 'Length' => 8, 'Format' => 'ASCII', 'Alias' => 'deviceId'],
        ['Id' => 97, 'Name' => 'Fecha y hora dispositivo', 'Length' => 14, 'Format' => 'ASCII', 'Alias' => 'deviceDateTime'],
        ['Id' => 98, 'Name' => 'Id de contrato', 'Length' => 15, 'Format' => 'ASCII', 'Alias' => 'contractId'],
        ['Id' => 99, 'Name' => 'Datos de transacción', 'Length' => 40, 'Format' => 'ASCII', 'Alias' => 'transactionData'],
    ];

    public static function GetFields($IdList)
    {
     
        $fields = [];
        foreach (self::$fields as $field) {
            if (in_array($field['Id'], $IdList)) {

                $f = new Field();
                $f->Id = $field['Id'];
                $f->Name = $field['Name'];
                $f->Length = $field['Length'];
                $f->Format = $field['Format'];
                $f->Alias = $field['Alias'];
                $fields[$f->Id] = $f;
            }
        }
        return $fields;
    }
    
    public static function GetPorCodigoRespuesta($codigo) {
        // Recorremos las claves (códigos) del array de respuestas
        foreach (self::$responses as $key => $message) {
            // Dividimos la clave por el guion ('-'), obteniendo una lista de códigos
            $codes = explode('-', $key);
            // Si el código buscado se encuentra en la lista, retornamos el mensaje correspondiente
            if (in_array($codigo, $codes)) {
                return $message;
            }
        }
        
        // Si no se encuentra el código, devolvemos el mensaje por defecto (si existe) o un mensaje de error
        return isset(self::$responses['*default']) ? self::$responses['*default'] : 'Código no encontrado';
    }

    public static function GetFieldsLoad()
    {
        $fields = [];
        foreach (self::$fields as $field) {
            $f = new Field();
            $f->Id = $field['Id'];
            $f->Name = $field['Name'];
            $f->Length = $field['Length'];
            $f->Format = $field['Format'];
            $f->Alias = $field['Alias'];
            $fields[$f->Id] = $f;
        }
        return $fields;
    }
    public static function GetField($id)
    {
        $fields = self::GetFieldsLoad();
        return isset($fields[$id]) ? $fields[$id] : null;
    }
}

?>