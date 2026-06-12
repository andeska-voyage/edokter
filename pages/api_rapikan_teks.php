<?php
/**
 * API Handler: Rapikan Teks Medis dengan Google Gemini
 * Endpoint: api_rapikan_teks.php
 * VERSION: v1.0 - Resume Medis Rawat Inap
 */

session_start();
require_once('../conf/conf.php');
require_once('config_ai.php');

// Set header JSON
header('Content-Type: application/json');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized: Session expired'
    ]);
    exit();
}

// Validasi request method
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
    exit();
}

// Ambil parameter dari JSON body
$input = json_decode(file_get_contents('php://input'), true);

$field = isset($input['field']) ? trim($input['field']) : '';
$content = isset($input['content']) ? trim($input['content']) : '';
$diagnosa = isset($input['diagnosa']) ? trim($input['diagnosa']) : '';
$prosedur = isset($input['prosedur']) ? trim($input['prosedur']) : '';

// Validasi parameter
if(empty($field)){
    echo json_encode([
        'success' => false,
        'error' => 'Parameter field required'
    ]);
    exit();
}

if(empty($content)){
    echo json_encode([
        'success' => false,
        'error' => 'Konten kosong'
    ]);
    exit();
}

// Get prompt berdasarkan field (dengan konteks diagnosa & prosedur)
$promptData = getPromptRapikan($field, $diagnosa, $prosedur);

if(!$promptData){
    echo json_encode([
        'success' => false,
        'error' => 'Field tidak dikenali: ' . $field
    ]);
    exit();
}

try {
    // Build full prompt
    $fullPrompt = $promptData['prompt'] . "\n\nTEKS ASLI:\n" . $content . "\n\nTEKS YANG DIRAPIKAN:";
    
    // Prepare API request untuk Google Gemini
    $api_url = GEMINI_API_URL;
    
    $payload = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $fullPrompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 2048
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_NONE'
            ]
        ]
    ];
    
    // Execute API call ke Google Gemini
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . GEMINI_API_KEY
        ],
        CURLOPT_TIMEOUT => GEMINI_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if($curl_error){
        throw new Exception('cURL Error: ' . $curl_error);
    }
    
    if($http_code !== 200){
        $error_detail = '';
        $result = json_decode($response, true);
        
        if(isset($result['error']['message'])){
            $error_detail = $result['error']['message'];
        }
        
        throw new Exception('Gemini API Error (HTTP ' . $http_code . '): ' . ($error_detail ?: substr($response, 0, 200)));
    }
    
    $result = json_decode($response, true);
    
    if(!isset($result['candidates'][0]['content']['parts'][0]['text'])){
        throw new Exception('Invalid API response format');
    }
    
    $ai_text = trim($result['candidates'][0]['content']['parts'][0]['text']);
    
    // Return success
    echo json_encode([
        'success' => true,
        'field' => $field,
        'field_label' => $promptData['label'],
        'original' => $content,
        'result' => $ai_text,
        'timestamp' => date('Y-m-d H:i:s'),
        'model' => GEMINI_MODEL
    ]);
    
} catch(Exception $e) {
    // Log error
    error_log('Gemini Rapikan API Error: ' . $e->getMessage());
    
    // Return error
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
