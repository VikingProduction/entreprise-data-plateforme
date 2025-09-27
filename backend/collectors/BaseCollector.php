<?php
/**
 * Collecteur de base avec fonctionnalités communes
 */

abstract class BaseCollector 
{
    protected $db;
    protected $logFile;

    public function __construct() 
    {
        $this->db = Database::getInstance();
        $this->logFile = __DIR__ . '/../../storage/logs/collector_' . date('Y-m-d') . '.log';

        // Créer le dossier de logs si nécessaire
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Effectuer une requête HTTP
     */
    protected function makeRequest(string $method, string $url, array $headers = [], string $body = null): array 
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Entreprise-Data-Platform/1.0',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            $this->log("Erreur cURL: {$error}", 'ERROR');
        }

        return [
            'status' => $httpCode,
            'body' => $response,
            'error' => $error
        ];
    }

    /**
     * Logger les événements
     */
    protected function log(string $message, string $level = 'INFO'): void 
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Aussi afficher en console si en CLI
        if (php_sapi_name() === 'cli') {
            echo $logEntry;
        }
    }

    /**
     * Valider un SIREN
     */
    protected function isValidSiren(string $siren): bool 
    {
        if (strlen($siren) !== 9 || !ctype_digit($siren)) {
            return false;
        }

        // Algorithme de Luhn pour SIREN
        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $digit = (int)$siren[$i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = $digit - 9;
                }
            }
            $sum += $digit;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit === (int)$siren[8];
    }

    /**
     * Nettoyer et valider des données
     */
    protected function sanitizeData(array $data): array 
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
                $data[$key] = $data[$key] === '' ? null : $data[$key];
            }
        }
        return $data;
    }
}
