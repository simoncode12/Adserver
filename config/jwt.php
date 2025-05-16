<?php
// config/jwt.php - Konfigurasi JWT

define('JWT_SECRET', 'W99pySFYcvmBwHDLM3jmFIRUzXNznUJAMBqI7kstXPBbKOtVHO'); // Ganti dengan secret key yang kuat dan simpan di environment variable
define('JWT_EXPIRATION', 86400); // Token berlaku 24 jam

class JWTHandler {
    private $secret;
    private $expiration;
    
    public function __construct() {
        $this->secret = JWT_SECRET;
        $this->expiration = JWT_EXPIRATION;
    }
    
    public function generateToken($user_id, $username, $role) {
        $issued_at = time();
        $expiration = $issued_at + $this->expiration;
        
        $payload = [
            'iat' => $issued_at,
            'exp' => $expiration,
            'iss' => $_SERVER['SERVER_NAME'],
            'data' => [
                'id' => $user_id,
                'username' => $username,
                'role' => $role
            ]
        ];
        
        return $this->encode($payload);
    }
    
    public function validateToken($token) {
        if (empty($token)) {
            return false;
        }
        
        $decoded = $this->decode($token);
        
        if (!$decoded || empty($decoded['data'])) {
            return false;
        }
        
        // Verifikasi expiration
        if (isset($decoded['exp']) && $decoded['exp'] < time()) {
            return false;
        }
        
        return $decoded['data'];
    }
    
    private function encode($payload) {
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', "$header.$payload", $this->secret, true));
        
        return "$header.$payload.$signature";
    }
    
    private function decode($token) {
        list($header, $payload, $signature) = explode('.', $token);
        
        $decodedHeader = json_decode($this->base64UrlDecode($header), true);
        $decodedPayload = json_decode($this->base64UrlDecode($payload), true);
        
        // Verifikasi signature
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );
        
        if ($signature !== $expectedSignature) {
            return false;
        }
        
        return $decodedPayload;
    }
    
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
