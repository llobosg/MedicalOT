<?php
// includes/BrevoMailer.php
class BrevoMailer {
    private $apiKey;
    private $fromEmail = 'contacto@medicalot.com'; // Actualizar con dominio verificado
    private $fromName = 'MedicalOT';
    
    public function __construct() {
        $this->apiKey = getenv('BREVO_API_KEY');
        if (!$this->apiKey) {
            error_log('❌ BREVO_API_KEY no configurada');
            // En desarrollo, permitir fallback sin error fatal
            if (getenv('APP_ENV') !== 'production') {
                $this->apiKey = 'dummy-key-for-dev';
            }
        }
    }

    public function send(string $toEmail, string $subject, string $htmlBody, array $cc = []): bool {
        // Validar email para prevenir header injection
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Email inválido: $toEmail");
            return false;
        }

        $data = [
            'sender' => ['name' => $this->fromName, 'email' => $this->fromEmail],
            'to' => [['email' => $toEmail]],
            'subject' => htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'),
            'htmlContent' => $htmlBody
        ];
        
        if (!empty($cc)) {
            $data['cc'] = array_map(fn($e) => ['email' => $e], $cc);
        }

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 201) {
            error_log("❌ Brevo Error $httpCode: $response | $error");
            return false;
        }

        error_log("✅ Email enviado a $toEmail - Asunto: $subject");
        return true;
    }
}
?>