<?php
/**
 * notif_cache.php - File-Based Cache untuk Notifikasi
 * 
 * Menyimpan status read/unread notifikasi tanpa database
 * Cache disimpan per dokter dan auto-expire setiap hari
 */

class NotifCache {
    
    private $cacheDir;
    private $kdDokter;
    private $cacheFile;
    private $cacheData;
    
    /**
     * Constructor
     * @param string $kdDokter - Kode dokter yang login
     */
    public function __construct($kdDokter) {
        $this->cacheDir = __DIR__ . '/../cache';
        $this->kdDokter = $kdDokter;
        $this->cacheFile = $this->cacheDir . '/notif_' . md5($kdDokter) . '.json';
        
        // Pastikan folder cache ada
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Load cache data
        $this->loadCache();
    }
    
    /**
     * Load cache dari file
     */
    private function loadCache() {
        if (file_exists($this->cacheFile)) {
            $content = file_get_contents($this->cacheFile);
            $this->cacheData = json_decode($content, true);
            
            // Cek apakah cache masih valid (hari yang sama)
            if (!isset($this->cacheData['date']) || $this->cacheData['date'] !== date('Y-m-d')) {
                // Cache expired, reset
                $this->resetCache();
            }
        } else {
            $this->resetCache();
        }
    }
    
    /**
     * Reset cache untuk hari baru
     */
    private function resetCache() {
        $this->cacheData = [
            'date' => date('Y-m-d'),
            'kd_dokter' => $this->kdDokter,
            'read_lab' => [],           // Array ID hasil lab yang sudah dibaca
            'read_rad_hasil' => [],      // Array ID hasil bacaan radiologi yang sudah dibaca
            'read_rad_gambar' => [],     // Array ID gambar radiologi yang sudah dibaca
            'last_seen' => null,         // Timestamp terakhir buka notifikasi
            'dismissed_all' => false     // Flag jika user dismiss semua
        ];
        $this->saveCache();
    }
    
    /**
     * Simpan cache ke file
     */
    private function saveCache() {
        $this->cacheData['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents($this->cacheFile, json_encode($this->cacheData, JSON_PRETTY_PRINT));
    }
    
    /**
     * Tandai notifikasi lab sebagai sudah dibaca
     * @param string $notifId - Format: {no_rawat}_{tgl_periksa}_{jam}
     */
    public function markLabAsRead($notifId) {
        if (!in_array($notifId, $this->cacheData['read_lab'])) {
            $this->cacheData['read_lab'][] = $notifId;
            $this->saveCache();
        }
    }
    
    /**
     * Tandai semua notifikasi lab sebagai sudah dibaca
     * @param array $notifIds - Array ID notifikasi
     */
    public function markAllLabAsRead($notifIds) {
        foreach ($notifIds as $id) {
            if (!in_array($id, $this->cacheData['read_lab'])) {
                $this->cacheData['read_lab'][] = $id;
            }
        }
        $this->saveCache();
    }
    
    /**
     * Cek apakah notifikasi lab sudah dibaca
     * @param string $notifId
     * @return bool
     */
    public function isLabRead($notifId) {
        return in_array($notifId, $this->cacheData['read_lab']);
    }
    
    /**
     * Get semua ID lab yang sudah dibaca
     * @return array
     */
    public function getReadLabIds() {
        return $this->cacheData['read_lab'];
    }
    
    // =============================================
    // RADIOLOGI - HASIL BACAAN
    // =============================================
    
    /**
     * Tandai notifikasi hasil radiologi sebagai sudah dibaca
     */
    public function markRadHasilAsRead($notifId) {
        if (!isset($this->cacheData['read_rad_hasil'])) {
            $this->cacheData['read_rad_hasil'] = [];
        }
        if (!in_array($notifId, $this->cacheData['read_rad_hasil'])) {
            $this->cacheData['read_rad_hasil'][] = $notifId;
            $this->saveCache();
        }
    }
    
    /**
     * Tandai semua notifikasi hasil radiologi sebagai sudah dibaca
     */
    public function markAllRadHasilAsRead($notifIds) {
        if (!isset($this->cacheData['read_rad_hasil'])) {
            $this->cacheData['read_rad_hasil'] = [];
        }
        foreach ($notifIds as $id) {
            if (!in_array($id, $this->cacheData['read_rad_hasil'])) {
                $this->cacheData['read_rad_hasil'][] = $id;
            }
        }
        $this->saveCache();
    }
    
    /**
     * Cek apakah notifikasi hasil radiologi sudah dibaca
     */
    public function isRadHasilRead($notifId) {
        if (!isset($this->cacheData['read_rad_hasil'])) {
            return false;
        }
        return in_array($notifId, $this->cacheData['read_rad_hasil']);
    }
    
    /**
     * Get semua ID hasil radiologi yang sudah dibaca
     */
    public function getReadRadHasilIds() {
        return isset($this->cacheData['read_rad_hasil']) ? $this->cacheData['read_rad_hasil'] : [];
    }
    
    // =============================================
    // RADIOLOGI - GAMBAR
    // =============================================
    
    /**
     * Tandai notifikasi gambar radiologi sebagai sudah dibaca
     */
    public function markRadGambarAsRead($notifId) {
        if (!isset($this->cacheData['read_rad_gambar'])) {
            $this->cacheData['read_rad_gambar'] = [];
        }
        if (!in_array($notifId, $this->cacheData['read_rad_gambar'])) {
            $this->cacheData['read_rad_gambar'][] = $notifId;
            $this->saveCache();
        }
    }
    
    /**
     * Tandai semua notifikasi gambar radiologi sebagai sudah dibaca
     */
    public function markAllRadGambarAsRead($notifIds) {
        if (!isset($this->cacheData['read_rad_gambar'])) {
            $this->cacheData['read_rad_gambar'] = [];
        }
        foreach ($notifIds as $id) {
            if (!in_array($id, $this->cacheData['read_rad_gambar'])) {
                $this->cacheData['read_rad_gambar'][] = $id;
            }
        }
        $this->saveCache();
    }
    
    /**
     * Cek apakah notifikasi gambar radiologi sudah dibaca
     */
    public function isRadGambarRead($notifId) {
        if (!isset($this->cacheData['read_rad_gambar'])) {
            return false;
        }
        return in_array($notifId, $this->cacheData['read_rad_gambar']);
    }
    
    /**
     * Get semua ID gambar radiologi yang sudah dibaca
     */
    public function getReadRadGambarIds() {
        return isset($this->cacheData['read_rad_gambar']) ? $this->cacheData['read_rad_gambar'] : [];
    }
    
    /**
     * Update timestamp terakhir buka notifikasi
     */
    public function updateLastSeen() {
        $this->cacheData['last_seen'] = date('Y-m-d H:i:s');
        $this->saveCache();
    }
    
    /**
     * Get timestamp terakhir buka notifikasi
     * @return string|null
     */
    public function getLastSeen() {
        return $this->cacheData['last_seen'];
    }
    
    /**
     * Dismiss semua notifikasi
     */
    public function dismissAll() {
        $this->cacheData['dismissed_all'] = true;
        $this->cacheData['dismissed_at'] = date('Y-m-d H:i:s');
        $this->saveCache();
    }
    
    /**
     * Cek apakah semua sudah di-dismiss
     * @return bool
     */
    public function isAllDismissed() {
        return isset($this->cacheData['dismissed_all']) && $this->cacheData['dismissed_all'] === true;
    }
    
    /**
     * Reset dismiss status (untuk notifikasi baru)
     */
    public function resetDismiss() {
        $this->cacheData['dismissed_all'] = false;
        unset($this->cacheData['dismissed_at']);
        $this->saveCache();
    }
    
    /**
     * Hitung jumlah notifikasi yang belum dibaca
     * @param array $allNotifIds - Semua ID notifikasi
     * @return int
     */
    public function countUnread($allNotifIds) {
        $unread = 0;
        foreach ($allNotifIds as $id) {
            if (!$this->isLabRead($id)) {
                $unread++;
            }
        }
        return $unread;
    }
    
    /**
     * Get cache data (untuk debugging)
     * @return array
     */
    public function getCacheData() {
        return $this->cacheData;
    }
    
    /**
     * Static method untuk membersihkan cache lama (lebih dari 7 hari)
     * Bisa dipanggil via cron job
     */
    public static function cleanOldCache($cacheDir = null) {
        if ($cacheDir === null) {
            $cacheDir = __DIR__ . '/../cache';
        }
        
        if (!is_dir($cacheDir)) {
            return 0;
        }
        
        $deleted = 0;
        $files = glob($cacheDir . '/notif_*.json');
        $now = time();
        $maxAge = 7 * 24 * 60 * 60; // 7 hari
        
        foreach ($files as $file) {
            if (filemtime($file) < ($now - $maxAge)) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }
}
?>
