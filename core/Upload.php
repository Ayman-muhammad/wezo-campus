<?php
/**
 * WEZO CAMPUS HUB - File Upload Handler
 * Powered by AYGLOBE INC
 */

namespace Core;

class Upload {
    private $allowedExtensions = [];
    private $maxSize;
    private $uploadDir;
    
    public function __construct($type = 'all') {
        $this->allowedExtensions = Config::getAllowedFileTypes($type);
        $this->maxSize = Config::MAX_UPLOAD_SIZE;
        $this->uploadDir = Config::UPLOAD_DIR;
    }
    
    /**
     * Upload profile picture
     */
    public function profilePicture($file) {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded or upload error.'];
        }
        
        // Validate file
        $validation = $this->validateFile($file, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $uploadPath = $this->uploadDir . 'profiles/' . $filename;
        
        // Create directory if not exists
        if (!is_dir(dirname($uploadPath))) {
            mkdir(dirname($uploadPath), 0755, true);
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Resize image for consistency
            $this->resizeImage($uploadPath, 500, 500);
            
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $uploadPath,
                'size' => filesize($uploadPath)
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to move uploaded file.'];
    }
    
    /**
     * Upload note file
     */
    public function noteFile($file) {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded or upload error.'];
        }
        
        // Validate file
        $validation = $this->validateFile($file, Config::getAllowedFileTypes('document'));
        if (!$validation['success']) {
            return $validation;
        }
        
        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'note_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $uploadPath = $this->uploadDir . 'notes/' . $filename;
        
        // Create directory if not exists
        if (!is_dir(dirname($uploadPath))) {
            mkdir(dirname($uploadPath), 0755, true);
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $uploadPath,
                'size' => filesize($uploadPath),
                'type' => $this->getFileType($ext)
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to move uploaded file.'];
    }
    
    /**
     * Upload marketplace images
     */
    public function marketplaceImages($files) {
        $uploadedFiles = [];
        $errors = [];
        
        // Handle single file or array
        if (!is_array($files['name'])) {
            $files = [
                'name' => [$files['name']],
                'type' => [$files['type']],
                'tmp_name' => [$files['tmp_name']],
                'error' => [$files['error']],
                'size' => [$files['size']]
            ];
        }
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            // Validate file
            $validation = $this->validateFile($file, Config::ALLOWED_IMAGE_TYPES);
            if (!$validation['success']) {
                $errors[] = $file['name'] . ': ' . $validation['message'];
                continue;
            }
            
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'marketplace_' . time() . '_' . $i . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $uploadPath = $this->uploadDir . 'marketplace/' . $filename;
            
            // Create directory if not exists
            if (!is_dir(dirname($uploadPath))) {
                mkdir(dirname($uploadPath), 0755, true);
            }
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Resize image for consistency
                $this->resizeImage($uploadPath, 800, 600);
                
                $uploadedFiles[] = [
                    'filename' => $filename,
                    'original_name' => $file['name'],
                    'path' => $uploadPath,
                    'size' => filesize($uploadPath)
                ];
            }
        }
        
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => implode(', ', $errors),
                'files' => $uploadedFiles
            ];
        }
        
        return [
            'success' => true,
            'files' => $uploadedFiles
        ];
    }
    
    /**
     * Validate file
     */
    private function validateFile($file, $allowedExtensions = null) {
        if ($file['size'] > $this->maxSize) {
            return [
                'success' => false,
                'message' => 'File size exceeds maximum allowed size of ' . $this->formatBytes($this->maxSize)
            ];
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = $allowedExtensions ?: $this->allowedExtensions;
        
        if (!in_array($ext, $allowed)) {
            return [
                'success' => false,
                'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowed)
            ];
        }
        
        // Additional security check
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (isset($allowedMimes[$ext]) && $allowedMimes[$ext] !== $mime) {
            return [
                'success' => false,
                'message' => 'File MIME type mismatch.'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Resize image
     */
    private function resizeImage($path, $maxWidth, $maxHeight) {
        if (!file_exists($path)) {
            return false;
        }
        
        $info = getimagesize($path);
        if (!$info) {
            return false;
        }
        
        list($width, $height, $type) = $info;
        
        // Calculate new dimensions
        $ratio = $width / $height;
        
        if ($width > $maxWidth) {
            $width = $maxWidth;
            $height = $width / $ratio;
        }
        
        if ($height > $maxHeight) {
            $height = $maxHeight;
            $width = $height * $ratio;
        }
        
        // Create new image
        switch ($type) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($path);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($path);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($path);
                break;
            case IMAGETYPE_WEBP:
                $src = imagecreatefromwebp($path);
                break;
            default:
                return false;
        }
        
        $dst = imagecreatetruecolor($width, $height);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, imagesx($src), imagesy($src));
        
        // Save resized image
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($dst, $path, 90);
                break;
            case IMAGETYPE_PNG:
                imagepng($dst, $path);
                break;
            case IMAGETYPE_GIF:
                imagegif($dst, $path);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($dst, $path);
                break;
        }
        
        imagedestroy($src);
        imagedestroy($dst);
        
        return true;
    }
    
    /**
     * Get file type from extension
     */
    private function getFileType($extension) {
        $types = [
            'pdf' => 'PDF Document',
            'doc' => 'Word Document',
            'docx' => 'Word Document',
            'txt' => 'Text File',
            'ppt' => 'PowerPoint',
            'pptx' => 'PowerPoint',
            'jpg' => 'Image',
            'jpeg' => 'Image',
            'png' => 'Image',
            'gif' => 'Image'
        ];
        
        return $types[strtolower($extension)] ?? 'Unknown';
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Delete file
     */
    public static function deleteFile($path) {
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }
    
    /**
     * Get file extension from mime type
     */
    public static function getExtensionFromMime($mime) {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
        ];
        
        return $mimeMap[$mime] ?? null;
    }
}