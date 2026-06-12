<?php
/**
 * api_orthanc.php
 * PHP Class untuk Orthanc API Integration
 * 
 * Based on: ApiOrthanc.java
 * 
 * Features:
 * - Query studies by PatientID + StudyDate
 * - Get series list
 * - Get instance previews
 * - Web viewer link generation
 */

class ApiOrthanc {
    
    private $url;
    private $port;
    private $username;
    private $password;
    private $auth_header;
    
    /**
     * Get default Orthanc instance from configuration
     * 
     * @return ApiOrthanc Configured instance
     */
    public static function fromConfig() {
        // Check if constants are defined in conf.php
        if (defined('ORTHANC_URL') && defined('ORTHANC_PORT') && 
            defined('ORTHANC_USER') && defined('ORTHANC_PASS')) {
            return new self(
                ORTHANC_URL,
                ORTHANC_PORT,
                ORTHANC_USER,
                ORTHANC_PASS
            );
        }
        
        // Fallback to default values (if conf.php not updated)
        return new self(
            'http://192.168.88.52',
            '8042',
            'pku2024',
            'pkupky2024'
        );
    }
    
    /**
     * Constructor
     * 
     * @param string $url Orthanc URL (e.g., http://192.168.88.52)
     * @param string $port Orthanc Port (e.g., 8042)
     * @param string $username Orthanc username
     * @param string $password Orthanc password
     */
    public function __construct($url, $port, $username, $password) {
        $this->url = $url;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        
        // Generate Basic Auth header
        $auth = base64_encode($username . ':' . $password);
        $this->auth_header = 'Authorization: Basic ' . $auth;
    }
    
    /**
     * Get Base URL
     * 
     * @return string Base URL with port
     */
    private function getBaseUrl() {
        return $this->url . ':' . $this->port;
    }
    
    /**
     * Execute HTTP Request
     * 
     * @param string $method HTTP method (GET/POST)
     * @param string $endpoint API endpoint
     * @param mixed $data POST data (optional)
     * @return mixed Response data or false on error
     */
    private function executeRequest($method, $endpoint, $data = null) {
        $url = $this->getBaseUrl() . $endpoint;
        
        $ch = curl_init($url);
        
        // Set headers
        $headers = [
            $this->auth_header,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 3 seconds timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // 2 seconds connect timeout
        
        // Set method and data
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        // Check for errors
        if ($error) {
            error_log("Orthanc API Error: " . $error);
            return false;
        }
        
        if ($http_code !== 200) {
            error_log("Orthanc API HTTP Error: " . $http_code);
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Query Studies (AmbilSeries equivalent)
     * 
     * @param string $patient_id Patient ID (No. RM)
     * @param string $study_date Study date in YYYYMMDD format
     * @return array|false Array of studies or false on error
     */
    public function queryStudies($patient_id, $study_date) {
        $data = [
            'Level' => 'Study',
            'Expand' => true,
            'Query' => [
                'StudyDate' => $study_date, // Format: YYYYMMDD or YYYYMMDD-YYYYMMDD
                'PatientID' => $patient_id
            ]
        ];
        
        // DEBUG: Log query
        error_log("ORTHANC API - queryStudies: " . json_encode($data));
        
        $result = $this->executeRequest('POST', '/tools/find', $data);
        
        // DEBUG: Log result
        if ($result === false) {
            error_log("ORTHANC API - queryStudies FAILED for PatientID={$patient_id}, Date={$study_date}");
            return false;
        }
        
        error_log("ORTHANC API - queryStudies SUCCESS: " . count($result) . " studies found");
        error_log("ORTHANC API - Studies data: " . json_encode($result));
        
        return $result;
    }
    
    /**
     * Get Series Info
     * 
     * @param string $series_id Series UUID
     * @return array|false Series info or false on error
     */
    public function getSeriesInfo($series_id) {
        return $this->executeRequest('GET', '/series/' . $series_id);
    }
    
    /**
     * Get All Series from Studies
     * 
     * @param string $patient_id Patient ID (No. RM)
     * @param string $study_date Study date in YYYYMMDD format
     * @return array Array of series with info
     */
    public function getAllSeries($patient_id, $study_date) {
        $studies = $this->queryStudies($patient_id, $study_date);
        
        if ($studies === false || empty($studies)) {
            return [];
        }
        
        $series_list = [];
        
        foreach ($studies as $study) {
            if (isset($study['Series']) && is_array($study['Series'])) {
                foreach ($study['Series'] as $series_id) {
                    $series_info = $this->getSeriesInfo($series_id);
                    if ($series_info !== false) {
                        $series_list[] = [
                            'series_id' => $series_id,
                            'study_id' => $study['ID'],
                            'description' => $series_info['MainDicomTags']['SeriesDescription'] ?? 'No Description',
                            'modality' => $series_info['MainDicomTags']['Modality'] ?? 'Unknown',
                            'instances_count' => count($series_info['Instances'] ?? []),
                            'viewer_url' => $this->getWebViewerUrl($series_id)
                        ];
                    }
                }
            }
        }
        
        return $series_list;
    }
    
    /**
     * Get Instance Preview URL
     * 
     * @param string $instance_id Instance UUID
     * @param string $format Image format (png/jpg)
     * @return string Preview URL
     */
    public function getInstancePreviewUrl($instance_id, $format = 'png') {
        return $this->getBaseUrl() . '/instances/' . $instance_id . '/preview';
    }
    
    /**
     * Get Web Viewer URL
     * 
     * @param string $series_id Series UUID
     * @return string Web viewer URL
     */
    public function getWebViewerUrl($series_id) {
        return $this->getBaseUrl() . '/web-viewer/app/viewer.html?series=' . $series_id;
    }
    
    /**
     * Download Instance Preview Image
     * 
     * @param string $instance_id Instance UUID
     * @param string $save_path Path to save image
     * @return bool Success status
     */
    public function downloadInstancePreview($instance_id, $save_path) {
        $url = $this->getBaseUrl() . '/instances/' . $instance_id . '/preview';
        
        $ch = curl_init($url);
        
        $headers = [
            $this->auth_header,
            'Accept: image/png'
        ];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($http_code === 200 && $image_data !== false) {
            return file_put_contents($save_path, $image_data) !== false;
        }
        
        return false;
    }
    
    /**
     * Get Instance Preview as Base64
     * 
     * @param string $instance_id Instance UUID
     * @return string|false Base64 encoded image or false on error
     */
    public function getInstancePreviewBase64($instance_id) {
        $url = $this->getBaseUrl() . '/instances/' . $instance_id . '/preview';
        
        $ch = curl_init($url);
        
        $headers = [
            $this->auth_header,
            'Accept: image/png'
        ];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($http_code === 200 && $image_data !== false) {
            return base64_encode($image_data);
        }
        
        return false;
    }
    
    /**
     * Get Thumbnail Images for Display
     * 
     * @param string $patient_id Patient ID (No. RM)
     * @param string $study_date Study date in YYYYMMDD format
     * @param int $max_images Maximum number of images to return
     * @return array Array of image data (base64)
     */
    public function getThumbnails($patient_id, $study_date, $max_images = 10) {
        $series_list = $this->getAllSeries($patient_id, $study_date);
        
        if (empty($series_list)) {
            return [];
        }
        
        $thumbnails = [];
        $count = 0;
        
        foreach ($series_list as $series) {
            if ($count >= $max_images) {
                break;
            }
            
            $series_info = $this->getSeriesInfo($series['series_id']);
            
            if ($series_info && isset($series_info['Instances'])) {
                foreach ($series_info['Instances'] as $instance_id) {
                    if ($count >= $max_images) {
                        break;
                    }
                    
                    $base64 = $this->getInstancePreviewBase64($instance_id);
                    
                    if ($base64 !== false) {
                        $thumbnails[] = [
                            'instance_id' => $instance_id,
                            'series_id' => $series['series_id'],
                            'base64' => $base64,
                            'viewer_url' => $series['viewer_url']
                        ];
                        $count++;
                    }
                }
            }
        }
        
        return $thumbnails;
    }
}
?>