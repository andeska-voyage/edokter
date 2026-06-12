<?php
/**
 * iCare API Handler
 * Replikasi persis dari Java: ApiICareBPJS.java + ICareRiwayatPerawatan.java
 * 
 * Signature: HMAC-SHA256(cons_id + "&" + timestamp, secret_key) → Base64
 * Timestamp: Unix epoch seconds (UTC)
 * Decrypt:   AES-256-CBC(response, key=sha256(cons_id+secret_key+timestamp))
 *            + LZString.decompressFromEncodedURIComponent()
 */

header('Content-Type: application/json');

require_once dirname(__DIR__) . '/conf/conf.php';

// Parameter
$param = isset($_POST['param']) ? trim($_POST['param']) : '';
$kodedokter = isset($_POST['kodedokter']) ? trim($_POST['kodedokter']) : '';

if (empty($param) || empty($kodedokter)) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap']);
    exit;
}

// Config
$cons_id    = ICARE_CONS_ID;
$secret_key = ICARE_SECRET_KEY;
$user_key   = ICARE_USER_KEY;
$base_url   = ICARE_API_URL;

// =============================================
// TIMESTAMP: epoch seconds (sama dengan Java: System.currentTimeMillis()/1000)
// =============================================
$timestamp = strval(time());

// =============================================
// SIGNATURE: HMAC-SHA256(cons_id + "&" + timestamp, secret_key) → Base64
// Persis dari Java ApiICareBPJS.getHmac():
//   salt = Consid + "&" + utc
//   SecretKeySpec(key.getBytes("UTF-8"), "HmacSHA256")
//   mac.doFinal(data.getBytes("UTF-8"))
//   Base64.encode(hmacData)
// =============================================
$data_sign = $cons_id . "&" . $timestamp;
$signature = base64_encode(hash_hmac('sha256', $data_sign, $secret_key, true));

// Request body
$request_body = json_encode([
    'param'      => $param,
    'kodedokter' => intval($kodedokter)
]);

// POST ke API
$url = rtrim($base_url, '/') . '/validate';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-cons-id: ' . $cons_id,
        'x-timestamp: ' . $timestamp,
        'x-signature: ' . $signature,
        'user_key: ' . $user_key
    ],
    CURLOPT_POSTFIELDS     => $request_body
]);

$response = curl_exec($ch);
$curl_error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Debug log
error_log("[iCare] URL: $url");
error_log("[iCare] Body: $request_body");
error_log("[iCare] ConsID: $cons_id | Timestamp: $timestamp");
error_log("[iCare] Signature: $signature");
error_log("[iCare] HTTP $http_code | Response: " . substr($response, 0, 500));

if ($curl_error) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi ke server BPJS gagal: ' . $curl_error]);
    exit;
}

$result = json_decode($response, true);
if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Response tidak valid dari BPJS (HTTP ' . $http_code . ')']);
    exit;
}

// Cek metaData
$meta = isset($result['metaData']) ? $result['metaData'] : null;
if (!$meta || $meta['code'] != '200') {
    $msg = $meta ? $meta['message'] : 'Tidak ada metadata';
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

// =============================================
// DECRYPT — Replikasi dari Java ApiICareBPJS.Decrypt():
//   mykey = ApiBPJSEnc.generateKey(Consid + Key + utc)
//   data  = ApiBPJSEnc.decrypt(data, mykey.getKey(), mykey.getIv())
//   data  = ApiBPJSLZString.decompressFromEncodedURIComponent(data)
//
// ApiBPJSEnc.generateKey pattern:
//   key_plain = cons_id + secret_key + timestamp
//   key_hash  = sha256(key_plain) → 64 hex chars
//   AES key   = substr(key_hash, 0, 32)  → 32 chars = 256 bit
//   AES IV    = substr(key_hash, 0, 16)  → 16 chars = 128 bit
// =============================================
$encrypted_response = $result['response'];
$key_plain = $cons_id . $secret_key . $timestamp;
$key_hash = hash('sha256', $key_plain);
$aes_key = substr($key_hash, 0, 32);
$aes_iv = substr($key_hash, 0, 16);

error_log("[iCare] KeyPlain: $key_plain");
error_log("[iCare] KeyHash: $key_hash");

// =============================================
// Coba beberapa variasi decrypt (AES-256-CBC)
// Pattern ApiBPJSEnc.generateKey() di Java:
//   MessageDigest sha256 = MessageDigest.getInstance("SHA-256");
//   byte[] keyBytes = sha256.digest(keyPlain.getBytes("UTF-8"));
//   key = first 16 bytes → Base64 encoded
//   iv  = first 16 bytes of key → raw
// Tapi implementasi bervariasi. Coba semua:
// =============================================
$decrypted = false;

// Variasi 1: key = hex substr 0-32, iv = hex substr 0-16
if ($decrypted === false) {
    $k = substr($key_hash, 0, 32);
    $v = substr($key_hash, 0, 16);
    $decrypted = @openssl_decrypt($encrypted_response, 'AES-256-CBC', $k, 0, $v);
    if ($decrypted !== false) error_log("[iCare] Decrypt OK variasi 1");
}

// Variasi 2: key = hex2bin(full 64 chars) = 32 bytes, iv = hex2bin(first 32 chars) = 16 bytes  
if ($decrypted === false) {
    $k = hex2bin($key_hash);
    $v = hex2bin(substr($key_hash, 0, 32));
    $decrypted = @openssl_decrypt($encrypted_response, 'AES-256-CBC', $k, 0, $v);
    if ($decrypted !== false) error_log("[iCare] Decrypt OK variasi 2");
}

// Variasi 3: key = hex2bin(full), iv = first 16 bytes of hex2bin(full)
if ($decrypted === false) {
    $k = hex2bin($key_hash);
    $v = substr(hex2bin($key_hash), 0, 16);
    $decrypted = @openssl_decrypt($encrypted_response, 'AES-256-CBC', $k, 0, $v);
    if ($decrypted !== false) error_log("[iCare] Decrypt OK variasi 3");
}

// Variasi 4: AES-128-CBC — key = hex2bin(first 32 chars) = 16 bytes, iv = hex2bin(first 32 chars) = 16 bytes
if ($decrypted === false) {
    $k = hex2bin(substr($key_hash, 0, 32));
    $v = hex2bin(substr($key_hash, 0, 32));
    $decrypted = @openssl_decrypt($encrypted_response, 'AES-128-CBC', $k, 0, $v);
    if ($decrypted !== false) error_log("[iCare] Decrypt OK variasi 4 (AES-128)");
}

// Variasi 5: AES-128-CBC — key = first 16 bytes raw, iv = first 16 bytes raw
if ($decrypted === false) {
    $raw = hash('sha256', $key_plain, true); // 32 raw bytes
    $k = substr($raw, 0, 16);
    $v = substr($raw, 0, 16);
    $decrypted = @openssl_decrypt($encrypted_response, 'AES-128-CBC', $k, 0, $v);
    if ($decrypted !== false) error_log("[iCare] Decrypt OK variasi 5 (AES-128 raw)");
}

// Variasi 6: AES-256-CBC — key = 32 raw bytes, iv = first 16 raw bytes
if ($decrypted === false) {
    $raw = hash('sha256', $key_plain, true); // 32 raw bytes
    $k = $raw;              // 32 bytes
    $v = substr($raw, 0, 16); // 16 bytes
    $decrypted = @openssl_decrypt($encrypted_response, 'AES-256-CBC', $k, 0, $v);
    if ($decrypted !== false) error_log("[iCare] Decrypt OK variasi 6 (AES-256 full raw)");
}

// Variasi 7: OPENSSL_RAW_DATA flag + base64_decode manual
if ($decrypted === false) {
    $raw = hash('sha256', $key_plain, true);
    $k = $raw;
    $v = substr($raw, 0, 16);
    $decrypted = @openssl_decrypt(base64_decode($encrypted_response), 'AES-256-CBC', $k, OPENSSL_RAW_DATA, $v);
    if ($decrypted !== false) error_log("[iCare] Decrypt OK variasi 7 (raw data flag)");
}

// Variasi 8: AES-128-CBC raw bytes + OPENSSL_RAW_DATA
if ($decrypted === false) {
    $raw = hash('sha256', $key_plain, true);
    $k = substr($raw, 0, 16);
    $v = substr($raw, 0, 16);
    $decrypted = @openssl_decrypt(base64_decode($encrypted_response), 'AES-128-CBC', $k, OPENSSL_RAW_DATA, $v);
    if ($decrypted !== false) error_log("[iCare] Decrypt OK variasi 8 (AES-128 raw data)");
}

error_log("[iCare] Decrypted: " . ($decrypted !== false ? substr($decrypted, 0, 300) : 'ALL FAILED'));

if ($decrypted === false) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal dekripsi response BPJS']);
    exit;
}

// =============================================
// LZString decompress — ApiBPJSLZString.decompressFromEncodedURIComponent()
// =============================================
$decompressed = lzstring_decompressFromEncodedURIComponent($decrypted);

error_log("[iCare] Decompressed: " . ($decompressed ? substr($decompressed, 0, 300) : 'NULL/EMPTY, using raw decrypted'));

// Jika decompress gagal, coba pakai raw decrypted (mungkin tidak dicompress)
if (empty($decompressed)) {
    $decompressed = $decrypted;
}

// Trim quotes
$decompressed = trim($decompressed, " \t\n\r\0\x0B\"'");

// Cek apakah URL langsung
if (filter_var($decompressed, FILTER_VALIDATE_URL)) {
    echo json_encode(['status' => 'success', 'url' => $decompressed]);
    exit;
}

// Coba parse JSON
$data = json_decode($decompressed, true);
if ($data && isset($data['url'])) {
    echo json_encode(['status' => 'success', 'url' => $data['url']]);
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'Format response tidak dikenali',
    'debug' => substr($decompressed, 0, 300)
]);
exit;

// =============================================
// LZString PHP Implementation
// Port dari: lz-string JavaScript library
// Khusus method: decompressFromEncodedURIComponent
// =============================================
function lzstring_decompressFromEncodedURIComponent($input) {
    if ($input === null || $input === '') return '';
    
    $input = str_replace(' ', '+', $input);
    
    $keyStrUriSafe = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-\$";
    $baseReverseDic = [];
    for ($i = 0; $i < strlen($keyStrUriSafe); $i++) {
        $baseReverseDic[$keyStrUriSafe[$i]] = $i;
    }
    
    return lzstring_decompress(strlen($input), 32, function($index) use ($input, $baseReverseDic) {
        return isset($baseReverseDic[$input[$index]]) ? $baseReverseDic[$input[$index]] : 0;
    });
}

function lzstring_decompress($length, $resetValue, $getNextValue) {
    $dictionary = [];
    $enlargeIn = 4;
    $dictSize = 4;
    $numBits = 3;
    $entry = '';
    $result = [];
    $w = '';
    $c = '';
    
    $data_val = $getNextValue(0);
    $data_position = $resetValue;
    $data_index = 1;
    
    for ($i = 0; $i < 3; $i++) {
        $dictionary[$i] = $i;
    }
    
    $bits = 0;
    $maxpower = pow(2, 2);
    $power = 1;
    
    while ($power != $maxpower) {
        $resb = $data_val & $data_position;
        $data_position >>= 1;
        if ($data_position == 0) {
            $data_position = $resetValue;
            $data_val = $getNextValue($data_index++);
        }
        $bits |= ($resb > 0 ? 1 : 0) * $power;
        $power <<= 1;
    }
    
    switch ($bits) {
        case 0:
            $bits = 0;
            $maxpower = pow(2, 8);
            $power = 1;
            while ($power != $maxpower) {
                $resb = $data_val & $data_position;
                $data_position >>= 1;
                if ($data_position == 0) {
                    $data_position = $resetValue;
                    $data_val = $getNextValue($data_index++);
                }
                $bits |= ($resb > 0 ? 1 : 0) * $power;
                $power <<= 1;
            }
            $c = chr($bits);
            break;
        case 1:
            $bits = 0;
            $maxpower = pow(2, 16);
            $power = 1;
            while ($power != $maxpower) {
                $resb = $data_val & $data_position;
                $data_position >>= 1;
                if ($data_position == 0) {
                    $data_position = $resetValue;
                    $data_val = $getNextValue($data_index++);
                }
                $bits |= ($resb > 0 ? 1 : 0) * $power;
                $power <<= 1;
            }
            $c = chr($bits);
            break;
        case 2:
            return '';
    }
    
    $dictionary[3] = $c;
    $w = $c;
    $result[] = $c;
    
    while (true) {
        if ($data_index > $length) return '';
        
        $bits = 0;
        $maxpower = pow(2, $numBits);
        $power = 1;
        while ($power != $maxpower) {
            $resb = $data_val & $data_position;
            $data_position >>= 1;
            if ($data_position == 0) {
                $data_position = $resetValue;
                if ($data_index < $length) {
                    $data_val = $getNextValue($data_index++);
                }
            }
            $bits |= ($resb > 0 ? 1 : 0) * $power;
            $power <<= 1;
        }
        
        $cc = $bits;
        switch ($cc) {
            case 0:
                $bits = 0;
                $maxpower = pow(2, 8);
                $power = 1;
                while ($power != $maxpower) {
                    $resb = $data_val & $data_position;
                    $data_position >>= 1;
                    if ($data_position == 0) {
                        $data_position = $resetValue;
                        if ($data_index < $length) {
                            $data_val = $getNextValue($data_index++);
                        }
                    }
                    $bits |= ($resb > 0 ? 1 : 0) * $power;
                    $power <<= 1;
                }
                $dictionary[$dictSize++] = chr($bits);
                $cc = $dictSize - 1;
                $enlargeIn--;
                break;
            case 1:
                $bits = 0;
                $maxpower = pow(2, 16);
                $power = 1;
                while ($power != $maxpower) {
                    $resb = $data_val & $data_position;
                    $data_position >>= 1;
                    if ($data_position == 0) {
                        $data_position = $resetValue;
                        if ($data_index < $length) {
                            $data_val = $getNextValue($data_index++);
                        }
                    }
                    $bits |= ($resb > 0 ? 1 : 0) * $power;
                    $power <<= 1;
                }
                $dictionary[$dictSize++] = chr($bits);
                $cc = $dictSize - 1;
                $enlargeIn--;
                break;
            case 2:
                return implode('', $result);
        }
        
        if ($enlargeIn == 0) {
            $enlargeIn = pow(2, $numBits);
            $numBits++;
        }
        
        if (isset($dictionary[$cc])) {
            $entry = $dictionary[$cc];
        } else {
            if ($cc === $dictSize) {
                $entry = $w . $w[0];
            } else {
                return null;
            }
        }
        
        $result[] = $entry;
        $dictionary[$dictSize++] = $w . $entry[0];
        $enlargeIn--;
        
        if ($enlargeIn == 0) {
            $enlargeIn = pow(2, $numBits);
            $numBits++;
        }
        
        $w = $entry;
    }
}
