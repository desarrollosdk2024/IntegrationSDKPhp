<?php

namespace IntegrationPos\Util;


class Logger
{
    private static $instance = null;
    private static $logFile = 'log.log'; // Ruta predeterminada del log

    // Constructor privado para evitar instanciación directa
    private function __construct()
    {
        // Evita inicialización directa
    }

    /**
     * Configurar la ruta del log y obtener la instancia única
     *
     * @param string $logPath Ruta del archivo de log
     */
    public static function configure($logPath)
    {
        self::$logFile = $logPath;
        if (self::$instance === null) {
            self::$instance = new self();
        }
    }

    /**
     * Registrar un mensaje de información
     *
     * @param string $message Mensaje a registrar
     */
    public static function info($message)
    {
        self::log('INFO', $message);
    }

    /**
     * Registrar un mensaje de error
     *
     * @param string $message Mensaje a registrar
     */
    public static function error($message)
    {
        self::log('ERROR', $message);
    }

    /**
     * Método genérico para registrar logs
     *
     * @param string $level Nivel del log (INFO, ERROR, etc.)
     * @param string $message Mensaje a registrar
     */
    private static function log($level, $message)
    {
       
        $logDirectory = dirname(self::$logFile); // Obtiene el directorio del archivo de log
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0777, true); // Crea el directorio de forma recursiva
        }

        if (!file_exists(self::$logFile)) {
            touch(self::$logFile); // Crea el archivo vacío si no existe
        }

        $logEntry = sprintf("[%s] %s | %s\n", date('Y-m-d H:i:s'), $level, $message);
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND);
    }

}