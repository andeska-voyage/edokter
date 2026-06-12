<?php
/**
 * API Handler: Analisis Radiologi dengan Google Gemini Vision
 * Endpoint: api_analyze_radiologi.php
 * VERSION: v2.0 - Download via HTTP (Fixed for remote server)
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

// Ambil parameter
$image_url = isset($_POST['image_url']) ? trim($_POST['image_url']) : '';

if(empty($image_url)){
    echo json_encode([
        'success' => false,
        'error' => 'Parameter image_url required'
    ]);
    exit();
}

try {
    // Validasi URL
    if(!filter_var($image_url, FILTER_VALIDATE_URL)){
        throw new Exception('Invalid image URL format');
    }
    
    // Download image dari HTTP (karena file ada di server lain)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $image_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    $image_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Validasi response
    if($curl_error){
        throw new Exception('cURL Error saat download gambar: ' . $curl_error);
    }
    
    if($http_code !== 200){
        throw new Exception('Gagal download gambar. HTTP Code: ' . $http_code);
    }
    
    if(empty($image_data)){
        throw new Exception('Gambar kosong atau tidak dapat didownload');
    }
    
    // Validasi ukuran file
    $file_size = strlen($image_data);
    if($file_size > MAX_IMAGE_SIZE){
        $size_mb = round($file_size / 1048576, 2);
        throw new Exception("Ukuran file terlalu besar ({$size_mb}MB). Maksimal 4MB");
    }
    
    // Validasi tipe file dari content-type header
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    
    // Extract mime type dari content-type (buang charset dll)
    $mime_type = strtolower(trim(explode(';', $content_type)[0]));
    
    // Fallback: detect dari data jika content-type tidak valid
    if(!in_array($mime_type, $allowed_types)){
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_buffer($finfo, $image_data);
        finfo_close($finfo);
    }
    
    if(!in_array($mime_type, $allowed_types)){
        throw new Exception('Tipe file tidak didukung: ' . $mime_type . '. Hanya JPG/PNG yang diizinkan.');
    }
    
    // Convert ke base64
    $base64_image = base64_encode($image_data);
    
    // Tentukan mime type untuk API
    $mime_for_api = ($mime_type === 'image/png') ? 'image/png' : 'image/jpeg';
    
    // Prepare API request untuk Google Gemini
    $api_url = GEMINI_API_URL;
    
    $payload = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => GEMINI_PROMPT_RADIOLOGI
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => $mime_for_api,
                            'data' => $base64_image
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'topK' => 32,
            'topP' => 1,
            'maxOutputTokens' => 2048
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
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
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if($curl_error){
        throw new Exception('cURL Error saat call Gemini API: ' . $curl_error);
    }
    
    if($http_code !== 200){
        $error_detail = '';
        $result = json_decode($response, true);
        
        if(isset($result['error']['message'])){
            $error_detail = $result['error']['message'];
        }
        
        throw new Exception('Gemini API Error (HTTP ' . $http_code . '): ' . ($error_detail ?: $response));
    }
    
    $result = json_decode($response, true);
    
    if(!isset($result['candidates'][0]['content']['parts'][0]['text'])){
        throw new Exception('Invalid API response format. Response: ' . substr($response, 0, 200));
    }
    
    $ai_text = $result['candidates'][0]['content']['parts'][0]['text'];
    
    // Format output sebagai HTML
    $html_result = nl2br(htmlspecialchars($ai_text));
    
    // Return success
    echo json_encode([
        'success' => true,
        'result_html' => $html_result,
        'raw_text' => $ai_text,
        'timestamp' => date('Y-m-d H:i:s'),
        'model' => GEMINI_MODEL,
        'image_size_kb' => round($file_size / 1024, 2)
    ]);
    
} catch(Exception $e) {
    // Log error
    error_log('Gemini API Error: ' . $e->getMessage());
    
    // Return error
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>