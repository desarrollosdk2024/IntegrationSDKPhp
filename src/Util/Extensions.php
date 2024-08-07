<?php
namespace Integration\Util;

use Integration\Util\FieldsHelper;
use InvalidArgumentException;
class Extensions
{
 
    public static function getValueOrDefault($dictionary, $key)
    {
        return array_key_exists($key, $dictionary) ? $dictionary[$key] : null;
    }

    public static function byteArrayToString($byteArray)
    {
        return implode('', array_map(function ($byte) {
            return sprintf('%02X', $byte);
        }, $byteArray));
    }

    public static function removeSpaces($input)
    {
        return preg_replace('/\s+/', '', $input);
    }

    public static function logger($input)
    {
    
        echo date('Y-m-d H:i:s') . ' | ' . $input . PHP_EOL;
    }

    /**
     * Checks if the given input is equal to '06'.
     *
     * @param mixed $input The input to check.
     * @return bool Returns true if the input is equal to '06', false otherwise.
     */
    public static function isAck($input)
    {
        return $input === '06';
    }

    /**
     * Checks if the given input is equal to '15'.
     *
     * @param mixed $input The input to check.
     * @return bool Returns true if the input is equal to '15', false otherwise.
     */
    public static function isNAck($input)
    {
        return $input === '15';
    }

    public static function asciiToHex($input)
    {
        $hex = '';
        foreach (str_split($input) as $char) {
            $hex .= sprintf('%02X', ord($char));
        }
        return $hex;
    }

    public static function hexToAscii($input)
    {
        $ascii = '';
        for ($i = 0; $i < strlen($input); $i += 2) {
            $hexPair = substr($input, $i, 2);
            if ($hexPair === '00') {
                break;
            }
            $ascii .= chr(hexdec($hexPair));
        }
        return $ascii;
    }

    public static function hexToAscii01($input)
    {
        $ascii = '';
        for ($i = 0; $i < strlen($input); $i += 2) {
            $ascii .= chr(hexdec(substr($input, $i, 2)));
        }
        return $ascii;
    }

    public static function toHexSigned2Complement($num)
    {
        if ($num < 0) {
            $num = 0xFFFF + $num + 1;
        }
        return strtoupper(sprintf('%04X', $num));
    }

    public static function xor($hexStr)
    {
        $bytes = self::HexStringToByteArrays($hexStr);
        $x = 0;
        foreach ($bytes as $byte) {
            $x ^= $byte;
        }
        return sprintf('%02X', $x);
    }
    public static function HexStringToByteArrays($hex)
    {
        return self::BaseHexStringToByteArray($hex);
    }
      public static function hexStringToByteArray($hex)
    {
        $bytes = self::BaseHexStringToByteArray($hex);
        return implode(array_map("chr", $bytes));
    }
    /**
     * Converts a hexadecimal string to an array of bytes.
     *
     * @param string $hex The hexadecimal string to convert.
     * @return array An array of bytes representing the hexadecimal string.
     */
     public static function BaseHexStringToByteArray($hex)
    {
        $bytes = [];
        for ($i = 0; $i < strlen($hex); $i += 2) {
            $bytes[] = hexdec(substr($hex, $i, 2));
        }
        return $bytes;
    }

    /**
     * Validates an amount by removing commas and periods from the input and padding it with leading zeros.
     *
     * @param string $input The input amount to validate.
     * @return string The validated amount padded with leading zeros.
     */
    public static function validateAmount($input)
    {
        $amount = str_replace([',', '.'], '', $input);
        return str_pad(intval($amount), 12, '0', STR_PAD_LEFT);
    }

    /**
     * Validates a reference by padding it with leading zeros.
     *
     * @param string $input The input reference to validate.
     * @return string The validated reference padded with leading zeros.
     */
    public static function validateReference($input)
    {
        return str_pad(substr($input, -6), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Converts a string to a float with two decimal places.
     *
     * @param string $input The input string to convert.
     * @return string The converted float with two decimal places.
     */
    public static function toFloatDecimals($input)
    {
        $cleanString = ltrim($input, '0');
        if ($cleanString === '') {
            $cleanString = '0';
        }
        $floatNumber = floatval($cleanString) / 100;
        return number_format($floatNumber, 2, '.', '');
    }

    /**
     * Formats a hex string into a field object.
     *
     * @param string $hexStr The hex string to format.
     * @return array The formatted field object with 'id', 'name', 'value', and 'description'.
     * @throws InvalidArgumentException If the hex string is too short.
     */
    public static function formatField($hexStr)
    {
        $startIndex = 8;
        if (strlen($hexStr) < $startIndex) {
            throw new InvalidArgumentException('Hex string must have at least 8 characters.');
        }

        $fieldId = intval(self::hexToAscii(substr($hexStr, 0, 4)));
        $field = FieldsHelper::getField($fieldId);
        $lengthHex = $field->Length * 2;

        if (strlen($hexStr) < ($startIndex + $lengthHex)) {
            $hexStr = str_pad($hexStr, $startIndex + $lengthHex, '0');
        }

        $value = $field->Format === 'ASCII'
            ? substr($hexStr, 8, $lengthHex) ? self::hexToAscii(substr($hexStr, 8, $lengthHex)) : ''
            : substr($hexStr, 8, $lengthHex);

        return [
            'id' => (string)$fieldId,
            'name' => $field->Alias,
            'value' => $value,
            'description' => $field->Name
        ];
    }

    /**
     * Unpacks the message by processing the input message string.
     *
     * @param string $message The message string to be unpacked.
     * @throws InvalidArgumentException If the message is less than 26 characters long.
     * @return array The unpacked data array.
     */
    public static function unpackMessage($message)
    {
        if (strlen($message) < 26) {
            throw new InvalidArgumentException('Message must be at least 26 characters long.');
        }

        $message = substr($message, 26);
        $fieldArray = explode('1C', strtoupper($message));
        $firstElement = self::hexToAscii($fieldArray[0]);
        $firstElement = self::removeSpaces($firstElement);

        $dataArray = [];
        for ($i = 1; $i < count($fieldArray); $i++) {
            $format = self::formatField($fieldArray[$i]);
            $id = $format['id'];
            $dataArray[$id] = $format;
        }

        return $dataArray;
    }

    /**
     * Packs the given message using the provided list and values.
     *
     * @param string $message The message to be packed.
     * @param array $list The list of fields to be included in the packing process.
     * @param array $values The values to be used in the packing process. The keys should match the Ids of the fields in the list.
     * @return string The packed message.
     * @throws InvalidArgumentException If the values array does not contain a value for a field in the list.
     */
    public static function packMessage($message, $list, $values)
    {
        $delimiter = '1C';
        $sendDefault = '02';

        $result = '';
        foreach ($list as $row) {
            if (isset($values[$row->Id])) {
                $code = str_pad((string)$row->Id, 2, '0', STR_PAD_LEFT);
                $code = self::asciiToHex($code);
                $lengths = self::toHexSigned2Complement($row->Length);
                $result .= $code . $lengths . self::asciiToHex($values[$row->Id]) . $delimiter;
            }
        }
        $resultString = $message . substr($result, 0, -strlen($delimiter));
        $xor = self::xor($resultString);
        return $sendDefault . $resultString . $xor;
    }
}