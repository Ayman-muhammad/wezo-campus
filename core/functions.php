<?php
/**
 * WEZO CAMPUS HUB - Core Helper Functions
 * Powered by AYGLOBE INC
 */

if (!function_exists('env')) {
    /**
     * Get environment variable with fallback
     */
    function env($key, $default = null) {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }
        
        return $value;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config($key, $default = null) {
        static $config = null;
        
        if ($config === null) {
            $configFile = __DIR__ . '/../../config/app.php';
            if (file_exists($configFile)) {
                $config = require $configFile;
            } else {
                $config = [];
            }
        }
        
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die
     */
    function dd(...$args) {
        foreach ($args as $arg) {
            echo '<pre>';
            var_dump($arg);
            echo '</pre>';
        }
        die();
    }
}

if (!function_exists('app_path')) {
    /**
     * Get application path
     */
    function app_path($path = '') {
        return __DIR__ . '/../..' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get storage path
     */
    function storage_path($path = '') {
        return app_path('storage') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('public_path')) {
    /**
     * Get public path
     */
    function public_path($path = '') {
        return app_path('public') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('upload_path')) {
    /**
     * Get upload path
     */
    function upload_path($path = '') {
        return public_path('uploads') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('asset')) {
    /**
     * Generate asset URL
     */
    function asset($path) {
        $baseUrl = rtrim(APP_URL, '/');
        return $baseUrl . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Generate URL
     */
    function url($path = '') {
        $baseUrl = rtrim(APP_URL, '/');
        return $path ? $baseUrl . '/' . ltrim($path, '/') : $baseUrl;
    }
}

if (!function_exists('route')) {
    /**
     * Generate route URL
     */
    function route($name, $params = []) {
        // Simple implementation - you might want to use a router
        $routes = [
            'home' => '/',
            'login' => '/login.php',
            'register' => '/register.php',
            'profile' => '/profile.php',
            'notes.index' => '/notes/',
            'notes.create' => '/notes/create.php',
            'notes.view' => '/notes/view.php',
            'marketplace.index' => '/marketplace/',
            'hostels.index' => '/hostels/',
            'resources.index' => '/resources/',
        ];
        
        if (!isset($routes[$name])) {
            return url();
        }
        
        $url = $routes[$name];
        
        if ($params) {
            $url .= '?' . http_build_query($params);
        }
        
        return url($url);
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect to URL
     */
    function redirect($url, $statusCode = 302) {
        header("Location: $url", true, $statusCode);
        exit;
    }
}

if (!function_exists('back')) {
    /**
     * Redirect back
     */
    function back() {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        redirect($referer);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generate CSRF token
     */
    function csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate CSRF hidden field
     */
    function csrf_field() {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('method_field')) {
    /**
     * Generate method field for spoofing HTTP verbs
     */
    function method_field($method) {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}

if (!function_exists('old')) {
    /**
     * Get old input value
     */
    function old($key, $default = null) {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}

if (!function_exists('flash')) {
    /**
     * Flash data to session
     */
    function flash($key, $value) {
        $_SESSION['_flash'][$key] = $value;
    }
}

if (!function_exists('session_get')) {
    /**
     * Get session value
     */
    function session_get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
}

if (!function_exists('session_set')) {
    /**
     * Set session value
     */
    function session_set($key, $value) {
        $_SESSION[$key] = $value;
    }
}

if (!function_exists('session_has')) {
    /**
     * Check if session has key
     */
    function session_has($key) {
        return isset($_SESSION[$key]);
    }
}

if (!function_exists('session_forget')) {
    /**
     * Remove session key
     */
    function session_forget($key) {
        unset($_SESSION[$key]);
    }
}

if (!function_exists('abort')) {
    /**
     * Abort with HTTP error
     */
    function abort($code, $message = '') {
        http_response_code($code);
        
        $errorPages = [
            404 => 'errors/404.php',
            403 => 'errors/403.php',
            500 => 'errors/500.php',
        ];
        
        if (isset($errorPages[$code]) && file_exists(app_path($errorPages[$code]))) {
            require app_path($errorPages[$code]);
        } else {
            echo "<h1>Error $code</h1>";
            if ($message) {
                echo "<p>$message</p>";
            }
        }
        
        exit;
    }
}

if (!function_exists('view')) {
    /**
     * Render view
     */
    function view($view, $data = []) {
        extract($data);
        $viewFile = app_path("views/$view.php");
        
        if (!file_exists($viewFile)) {
            throw new Exception("View [$view] not found");
        }
        
        require $viewFile;
    }
}

if (!function_exists('response')) {
    /**
     * Create response
     */
    function response($content = '', $status = 200, $headers = []) {
        http_response_code($status);
        
        foreach ($headers as $key => $value) {
            header("$key: $value");
        }
        
        echo $content;
        exit;
    }
}

if (!function_exists('json_response')) {
    /**
     * Create JSON response
     */
    function json_response($data, $status = 200, $headers = []) {
        $headers['Content-Type'] = 'application/json';
        return response(json_encode($data), $status, $headers);
    }
}

if (!function_exists('success_response')) {
    /**
     * Create success JSON response
     */
    function success_response($data = null, $message = 'Success', $status = 200) {
        return json_response([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }
}

if (!function_exists('error_response')) {
    /**
     * Create error JSON response
     */
    function error_response($message = 'Error', $errors = null, $status = 400) {
        return json_response([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $status);
    }
}

if (!function_exists('validate')) {
    /**
     * Validate data
     */
    function validate($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $rulesArray = is_string($rule) ? explode('|', $rule) : $rule;
            
            foreach ($rulesArray as $singleRule) {
                if ($singleRule === 'required' && empty($value)) {
                    $errors[$field][] = "The $field field is required.";
                }
                
                if (strpos($singleRule, 'min:') === 0) {
                    $min = (int) substr($singleRule, 4);
                    if (strlen($value) < $min) {
                        $errors[$field][] = "The $field must be at least $min characters.";
                    }
                }
                
                if (strpos($singleRule, 'max:') === 0) {
                    $max = (int) substr($singleRule, 4);
                    if (strlen($value) > $max) {
                        $errors[$field][] = "The $field may not be greater than $max characters.";
                    }
                }
                
                if ($singleRule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "The $field must be a valid email address.";
                }
                
                if (strpos($singleRule, 'unique:') === 0) {
                    [$_, $table, $column] = explode(':', $singleRule);
                    $column = $column ?: $field;
                    
                    $db = \Core\Database::getInstance();
                    $exists = $db->fetchColumn("SELECT COUNT(*) FROM $table WHERE $column = ?", [$value]);
                    
                    if ($exists > 0) {
                        $errors[$field][] = "The $field has already been taken.";
                    }
                }
                
                if ($singleRule === 'confirmed') {
                    $confirmationField = $field . '_confirmation';
                    if (!isset($data[$confirmationField]) || $value !== $data[$confirmationField]) {
                        $errors[$field][] = "The $field confirmation does not match.";
                    }
                }
            }
        }
        
        return empty($errors) ? true : $errors;
    }
}

if (!function_exists('slugify')) {
    /**
     * Create URL-friendly slug
     */
    function slugify($text) {
        // Replace non-letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        
        // Transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        
        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        
        // Trim
        $text = trim($text, '-');
        
        // Remove duplicate -
        $text = preg_replace('~-+~', '-', $text);
        
        // Lowercase
        $text = strtolower($text);
        
        if (empty($text)) {
            return 'n-a';
        }
        
        return $text;
    }
}

if (!function_exists('generate_token')) {
    /**
     * Generate random token
     */
    function generate_token($length = 32) {
        return bin2hex(random_bytes($length));
    }
}

if (!function_exists('format_date')) {
    /**
     * Format date
     */
    function format_date($date, $format = 'Y-m-d H:i:s') {
        if (!$date instanceof DateTime) {
            $date = new DateTime($date);
        }
        return $date->format($format);
    }
}

if (!function_exists('time_ago')) {
    /**
     * Convert datetime to "time ago" format
     */
    function time_ago($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;
        
        $string = [
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];
        
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }
        
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}

if (!function_exists('format_file_size')) {
    /**
     * Format file size
     */
    function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return '1 byte';
        } else {
            return '0 bytes';
        }
    }
}

if (!function_exists('sanitize_input')) {
    /**
     * Sanitize input data
     */
    function sanitize_input($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = sanitize_input($value);
            }
        } else {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }
}

if (!function_exists('escape_html')) {
    /**
     * Escape HTML special characters
     */
    function escape_html($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('str_limit')) {
    /**
     * Limit string length
     */
    function str_limit($string, $limit = 100, $end = '...') {
        if (mb_strlen($string) <= $limit) {
            return $string;
        }
        return mb_substr($string, 0, $limit) . $end;
    }
}

if (!function_exists('array_get')) {
    /**
     * Get array value with dot notation
     */
    function array_get($array, $key, $default = null) {
        if (is_null($key)) {
            return $array;
        }
        
        if (isset($array[$key])) {
            return $array[$key];
        }
        
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            
            $array = $array[$segment];
        }
        
        return $array;
    }
}

if (!function_exists('array_set')) {
    /**
     * Set array value with dot notation
     */
    function array_set(&$array, $key, $value) {
        if (is_null($key)) {
            return $array = $value;
        }
        
        $keys = explode('.', $key);
        
        while (count($keys) > 1) {
            $key = array_shift($keys);
            
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            
            $array = &$array[$key];
        }
        
        $array[array_shift($keys)] = $value;
        
        return $array;
    }
}

if (!function_exists('array_has')) {
    /**
     * Check if array has key with dot notation
     */
    function array_has($array, $key) {
        if (empty($array) || is_null($key)) {
            return false;
        }
        
        if (array_key_exists($key, $array)) {
            return true;
        }
        
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            
            $array = $array[$segment];
        }
        
        return true;
    }
}

if (!function_exists('is_email')) {
    /**
     * Check if string is valid email
     */
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('is_url')) {
    /**
     * Check if string is valid URL
     */
    function is_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

if (!function_exists('is_ip')) {
    /**
     * Check if string is valid IP address
     */
    function is_ip($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}

if (!function_exists('get_client_ip')) {
    /**
     * Get client IP address
     */
    function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        
        return $ip;
    }
}

if (!function_exists('get_user_agent')) {
    /**
     * Get user agent
     */
    function get_user_agent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}

if (!function_exists('is_ajax')) {
    /**
     * Check if request is AJAX
     */
    function is_ajax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

if (!function_exists('is_post')) {
    /**
     * Check if request method is POST
     */
    function is_post() {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}

if (!function_exists('is_get')) {
    /**
     * Check if request method is GET
     */
    function is_get() {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }
}

if (!function_exists('generate_password')) {
    /**
     * Generate random password
     */
    function generate_password($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
}

if (!function_exists('generate_username')) {
    /**
     * Generate random username
     */
    function generate_username($firstName, $lastName) {
        $base = strtolower($firstName[0] . $lastName);
        $username = $base;
        $counter = 1;
        
        $db = \Core\Database::getInstance();
        
        while ($db->fetchColumn("SELECT COUNT(*) FROM users WHERE username = ?", [$username]) > 0) {
            $username = $base . $counter;
            $counter++;
        }
        
        return $username;
    }
}

if (!function_exists('upload_file')) {
    /**
     * Upload file
     */
    function upload_file($file, $directory = 'uploads', $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'], $maxSize = 5242880) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File size exceeds limit'];
        }
        
        // Check file type
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedTypes)) {
            return ['success' => false, 'message' => 'File type not allowed'];
        }
        
        // Generate unique filename
        $filename = uniqid() . '_' . time() . '.' . $fileExt;
        $uploadPath = upload_path($directory . '/' . $filename);
        
        // Create directory if it doesn't exist
        $dir = dirname($uploadPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $directory . '/' . $filename,
                'full_path' => $uploadPath,
                'size' => $file['size'],
                'type' => $fileExt
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

if (!function_exists('delete_file')) {
    /**
     * Delete file
     */
    function delete_file($path) {
        $fullPath = upload_path($path);
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        
        return false;
    }
}

if (!function_exists('resize_image')) {
    /**
     * Resize image
     */
    function resize_image($source, $destination, $width, $height, $quality = 80) {
        $info = getimagesize($source);
        
        if (!$info) {
            return false;
        }
        
        list($origWidth, $origHeight, $type) = $info;
        
        // Calculate new dimensions
        $ratio = $origWidth / $origHeight;
        
        if ($width / $height > $ratio) {
            $width = $height * $ratio;
        } else {
            $height = $width / $ratio;
        }
        
        // Create image from source
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($source);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($source);
                break;
            default:
                return false;
        }
        
        // Create new image
        $newImage = imagecreatetruecolor($width, $height);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }
        
        // Resize image
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
        
        // Save image
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($newImage, $destination, $quality);
                break;
            case IMAGETYPE_PNG:
                imagepng($newImage, $destination, 9);
                break;
            case IMAGETYPE_GIF:
                imagegif($newImage, $destination);
                break;
        }
        
        // Free memory
        imagedestroy($image);
        imagedestroy($newImage);
        
        return true;
    }
}

if (!function_exists('send_email')) {
    /**
     * Send email
     */
    function send_email($to, $subject, $body, $from = null, $fromName = null) {
        $settings = \Core\Database::getInstance()->fetch("SELECT * FROM settings WHERE id = 1");
        
        if (!$from) {
            $from = $settings['from_email'] ?? 'noreply@wezocampushub.com';
        }
        
        if (!$fromName) {
            $fromName = $settings['from_name'] ?? 'WEZO CAMPUS HUB';
        }
        
        $headers = [
            'From' => "$fromName <$from>",
            'Reply-To' => $from,
            'X-Mailer' => 'PHP/' . phpversion(),
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8'
        ];
        
        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= "$key: $value\r\n";
        }
        
        return mail($to, $subject, $body, $headerString);
    }
}

if (!function_exists('log_activity')) {
    /**
     * Log user activity
     */
    function log_activity($userId, $action, $description, $ipAddress = null) {
        $db = \Core\Database::getInstance();
        
        return $db->insert('activity_logs', [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $ipAddress ?: get_client_ip(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}

if (!function_exists('get_notifications')) {
    /**
     * Get user notifications
     */
    function get_notifications($userId, $limit = 10, $unreadOnly = false) {
        $db = \Core\Database::getInstance();
        
        $query = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];
        
        if ($unreadOnly) {
            $query .= " AND is_read = 0";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return $db->fetchAll($query, $params);
    }
}

if (!function_exists('create_notification')) {
    /**
     * Create notification
     */
    function create_notification($userId, $title, $message, $type = 'info', $link = null) {
        $db = \Core\Database::getInstance();
        
        return $db->insert('notifications', [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}

if (!function_exists('format_currency')) {
    /**
     * Format currency
     */
    function format_currency($amount, $currency = 'KES') {
        $formats = [
            'KES' => 'KSh ' . number_format($amount, 2),
            'USD' => '$' . number_format($amount, 2),
            'EUR' => '€' . number_format($amount, 2),
        ];
        
        return $formats[$currency] ?? number_format($amount, 2);
    }
}

if (!function_exists('calculate_distance')) {
    /**
     * Calculate distance between two coordinates (in kilometers)
     */
    function calculate_distance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2) * sin($dLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
}

if (!function_exists('get_browser_info')) {
    /**
     * Get browser information
     */
    function get_browser_info() {
        $u_agent = $_SERVER['HTTP_USER_AGENT'];
        $browserName = 'Unknown';
        $platform = 'Unknown';
        $version = '';
        
        // Platform
        if (preg_match('/linux/i', $u_agent)) {
            $platform = 'Linux';
        } elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
            $platform = 'Mac';
        } elseif (preg_match('/windows|win32/i', $u_agent)) {
            $platform = 'Windows';
        }
        
        // Browser
        if (preg_match('/MSIE/i', $u_agent) && !preg_match('/Opera/i', $u_agent)) {
            $browserName = 'Internet Explorer';
            $ub = "MSIE";
        } elseif (preg_match('/Firefox/i', $u_agent)) {
            $browserName = 'Mozilla Firefox';
            $ub = "Firefox";
        } elseif (preg_match('/Chrome/i', $u_agent)) {
            $browserName = 'Google Chrome';
            $ub = "Chrome";
        } elseif (preg_match('/Safari/i', $u_agent)) {
            $browserName = 'Apple Safari';
            $ub = "Safari";
        } elseif (preg_match('/Opera/i', $u_agent)) {
            $browserName = 'Opera';
            $ub = "Opera";
        } elseif (preg_match('/Netscape/i', $u_agent)) {
            $browserName = 'Netscape';
            $ub = "Netscape";
        }
        
        // Version
        $known = array('Version', $ub, 'other');
        $pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
        
        if (!preg_match_all($pattern, $u_agent, $matches)) {
            // No matches
        }
        
        $i = count($matches['browser']);
        if ($i != 1) {
            if (strripos($u_agent, "Version") < strripos($u_agent, $ub)) {
                $version = $matches['version'][0];
            } else {
                $version = $matches['version'][1];
            }
        } else {
            $version = $matches['version'][0];
        }
        
        return [
            'userAgent' => $u_agent,
            'name' => $browserName,
            'version' => $version,
            'platform' => $platform,
            'pattern' => $pattern
        ];
    }
}

if (!function_exists('generate_qr_code')) {
    /**
     * Generate QR code
     */
    function generate_qr_code($data, $size = 200, $margin = 10) {
        // This is a simplified version
        // In production, use a library like phpqrcode
        
        $url = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'data' => $data,
            'size' => $size . 'x' . $size,
            'margin' => $margin
        ]);
        
        return $url;
    }
}

if (!function_exists('generate_barcode')) {
    /**
     * Generate barcode
     */
    function generate_barcode($data, $type = 'CODE128', $width = 2, $height = 30) {
        // This is a simplified version
        // In production, use a library like barcodegen
        
        $url = 'https://barcode.tec-it.com/barcode.ashx?' . http_build_query([
            'data' => $data,
            'code' => $type,
            'dpi' => 96,
            'dataseparator' => '',
            'qunit' => 'px',
            'quiet' => 0
        ]);
        
        return $url;
    }
}

if (!function_exists('array_to_csv')) {
    /**
     * Convert array to CSV
     */
    function array_to_csv($array, $filename = 'export.csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add header
        if (!empty($array)) {
            fputcsv($output, array_keys($array[0]));
        }
        
        // Add data
        foreach ($array as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
}

if (!function_exists('csv_to_array')) {
    /**
     * Convert CSV to array
     */
    function csv_to_array($filename, $delimiter = ',') {
        if (!file_exists($filename) || !is_readable($filename)) {
            return false;
        }
        
        $data = [];
        if (($handle = fopen($filename, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }
        
        return $data;
    }
}

if (!function_exists('is_mobile')) {
    /**
     * Check if user is on mobile device
     */
    function is_mobile() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $mobileAgents = [
            'Android', 'webOS', 'iPhone', 'iPad', 'iPod', 'BlackBerry',
            'Windows Phone', 'Mobile', 'Opera Mini', 'IEMobile'
        ];
        
        foreach ($mobileAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                return true;
            }
        }
        
        return false;
    }
}

if (!function_exists('get_days_between')) {
    /**
     * Get days between two dates
     */
    function get_days_between($startDate, $endDate) {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = $start->diff($end);
        
        return $interval->days;
    }
}

if (!function_exists('get_months_between')) {
    /**
     * Get months between two dates
     */
    function get_months_between($startDate, $endDate) {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        $years = $end->format('Y') - $start->format('Y');
        $months = $end->format('m') - $start->format('m');
        
        return ($years * 12) + $months;
    }
}

if (!function_exists('format_phone')) {
    /**
     * Format phone number
     */
    function format_phone($phone, $country = 'KE') {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        $formats = [
            'KE' => function($phone) {
                if (strlen($phone) == 9) {
                    return '+254' . $phone;
                } elseif (strlen($phone) == 10 && $phone[0] == '0') {
                    return '+254' . substr($phone, 1);
                } elseif (strlen($phone) == 12 && substr($phone, 0, 3) == '254') {
                    return '+' . $phone;
                }
                return $phone;
            },
            'US' => function($phone) {
                if (strlen($phone) == 10) {
                    return '+1' . $phone;
                }
                return $phone;
            }
        ];
        
        if (isset($formats[$country])) {
            return $formats[$country]($phone);
        }
        
        return $phone;
    }
}

if (!function_exists('mask_email')) {
    /**
     * Mask email address
     */
    function mask_email($email) {
        $parts = explode('@', $email);
        if (count($parts) != 2) return $email;
        
        $username = $parts[0];
        $domain = $parts[1];
        
        if (strlen($username) <= 2) {
            $maskedUsername = str_repeat('*', strlen($username));
        } else {
            $maskedUsername = $username[0] . str_repeat('*', strlen($username) - 2) . substr($username, -1);
        }
        
        $domainParts = explode('.', $domain);
        $tld = array_pop($domainParts);
        $domainName = implode('.', $domainParts);
        
        if (strlen($domainName) <= 2) {
            $maskedDomain = str_repeat('*', strlen($domainName));
        } else {
            $maskedDomain = $domainName[0] . str_repeat('*', strlen($domainName) - 2) . substr($domainName, -1);
        }
        
        return $maskedUsername . '@' . $maskedDomain . '.' . $tld;
    }
}

if (!function_exists('mask_phone')) {
    /**
     * Mask phone number
     */
    function mask_phone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) <= 4) {
            return str_repeat('*', strlen($phone));
        }
        
        $visible = substr($phone, -4);
        $masked = str_repeat('*', strlen($phone) - 4);
        
        return $masked . $visible;
    }
}

if (!function_exists('generate_invoice_number')) {
    /**
     * Generate invoice number
     */
    function generate_invoice_number() {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        
        return $prefix . '-' . $year . $month . $day . '-' . $random;
    }
}

if (!function_exists('generate_reference')) {
    /**
     * Generate reference number
     */
    function generate_reference($prefix = 'REF') {
        $timestamp = time();
        $random = rand(1000, 9999);
        
        return $prefix . '-' . $timestamp . '-' . $random;
    }
}

if (!function_exists('encrypt_data')) {
    /**
     * Encrypt data
     */
    function encrypt_data($data, $key = null) {
        if (!$key) {
            $key = env('APP_KEY', 'default_key_32_chars_long_here');
        }
        
        $method = 'AES-256-CBC';
        $ivLength = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $encrypted = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $encrypted, $key, true);
        
        return base64_encode($iv . $hmac . $encrypted);
    }
}

if (!function_exists('decrypt_data')) {
    /**
     * Decrypt data
     */
    function decrypt_data($data, $key = null) {
        if (!$key) {
            $key = env('APP_KEY', 'default_key_32_chars_long_here');
        }
        
        $data = base64_decode($data);
        $method = 'AES-256-CBC';
        $ivLength = openssl_cipher_iv_length($method);
        
        $iv = substr($data, 0, $ivLength);
        $hmac = substr($data, $ivLength, 32);
        $encrypted = substr($data, $ivLength + 32);
        
        $calculatedHmac = hash_hmac('sha256', $encrypted, $key, true);
        
        if (!hash_equals($hmac, $calculatedHmac)) {
            return false;
        }
        
        return openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);
    }
}

if (!function_exists('hash_password')) {
    /**
     * Hash password
     */
    function hash_password($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('verify_password')) {
    /**
     * Verify password
     */
    function verify_password($password, $hash) {
        return password_verify($password, $hash);
    }
}

if (!function_exists('generate_random_string')) {
    /**
     * Generate random string
     */
    function generate_random_string($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $randomString;
    }
}

if (!function_exists('get_file_extension')) {
    /**
     * Get file extension
     */
    function get_file_extension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
}

if (!function_exists('get_mime_type')) {
    /**
     * Get MIME type
     */
    function get_mime_type($filename) {
        $ext = get_file_extension($filename);
        
        $mimeTypes = [
            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',
            
            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            
            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',
            
            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            
            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            
            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        
        return $mimeTypes[$ext] ?? 'application/octet-stream';
    }
}

if (!function_exists('is_image')) {
    /**
     * Check if file is image
     */
    function is_image($filename) {
        $ext = get_file_extension($filename);
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];
        
        return in_array($ext, $imageExtensions);
    }
}

if (!function_exists('compress_image')) {
    /**
     * Compress image
     */
    function compress_image($source, $destination, $quality = 75) {
        $info = getimagesize($source);
        
        if ($info['mime'] == 'image/jpeg') {
            $image = imagecreatefromjpeg($source);
            imagejpeg($image, $destination, $quality);
        } elseif ($info['mime'] == 'image/png') {
            $image = imagecreatefrompng($source);
            imagepng($image, $destination, 9);
        } elseif ($info['mime'] == 'image/gif') {
            $image = imagecreatefromgif($source);
            imagegif($image, $destination);
        }
        
        if (isset($image)) {
            imagedestroy($image);
            return true;
        }
        
        return false;
    }
}

// Load additional helper files
$helperFiles = glob(__DIR__ . '/*_helper.php');
foreach ($helperFiles as $helperFile) {
    require_once $helperFile;
}