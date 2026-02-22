<?php
/**
 * Encryption Helper Functions
 * For secure storage of SF credentials
 */

/**
 * Encrypt sensitive data
 * 
 * @param string $data Data to encrypt
 * @return array ['encrypted' => string, 'iv' => string]
 * @throws Exception if encryption fails
 */
function encryptData($data) {
    $key = getEncryptionKey();
    $iv = openssl_random_pseudo_bytes(16);
    
    $encrypted = openssl_encrypt(
        $data,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
    
    if ($encrypted === false) {
        throw new Exception('Encryption failed');
    }
    
    // HMAC für Integritätsprüfung
    $hmac = hash_hmac('sha256', $encrypted . $iv, $key, true);
    
    return [
        'encrypted' => base64_encode($encrypted),
        'iv'        => base64_encode($iv),
        'hmac'      => base64_encode($hmac)
    ];
}

/**
 * Decrypt sensitive data
 * 
 * @param string $encrypted Base64 encoded encrypted data
 * @param string $iv Base64 encoded IV
 * @return string Decrypted data
 * @throws Exception if decryption fails
 */
function decryptData($encrypted, $iv, $hmac = null) {
    if (empty($encrypted) || empty($iv)) {
        return null;
    }
    
    $key = getEncryptionKey();
    
    $encryptedRaw = base64_decode($encrypted);
    $ivRaw        = base64_decode($iv);

    // HMAC-Prüfung wenn vorhanden (abwärtskompatibel für alte Einträge ohne HMAC)
    if ($hmac !== null) {
        $expectedHmac = hash_hmac('sha256', $encryptedRaw . $ivRaw, $key, true);
        if (!hash_equals($expectedHmac, base64_decode($hmac))) {
            throw new Exception('HMAC verification failed – data may have been tampered with');
        }
    }
    
    $decrypted = openssl_decrypt(
        $encryptedRaw,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $ivRaw
    );
    
    if ($decrypted === false) {
        throw new Exception('Decryption failed');
    }
    
    return $decrypted;
}

/**
 * Get encryption key from environment
 * 
 * @return string Encryption key
 * @throws Exception if key not found
 */
function getEncryptionKey() {
    // Try environment variable first
    $key = getenv('ENCRYPTION_KEY');
    
    // Fallback to config file if exists
    if (!$key && file_exists(__DIR__ . '/../config/.env')) {
        $env = parse_ini_file(__DIR__ . '/../config/.env');
        $key = $env['ENCRYPTION_KEY'] ?? null;
    }
    
    if (!$key) {
        throw new Exception('ENCRYPTION_KEY not configured. Set it in environment or config/.env file');
    }
    
    return $key;
}

/**
 * Generate a random encryption key
 * For initial setup
 * 
 * @return string Base64 encoded random key
 */
function generateEncryptionKey() {
    return base64_encode(openssl_random_pseudo_bytes(32));
}
