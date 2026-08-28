<?php
/**
 * Upload Helper Functions
 *
 * Centralizes all file upload logic so that the upload storage location
 * can be changed from one place: config/app.php (UPLOAD_PHYSICAL_PATH & UPLOAD_URL).
 *
 * Files are stored OUTSIDE the git repository so they survive git pull.
 */

/**
 * Get the absolute filesystem path for a given upload type.
 *
 * @param  string $type  'cv' | 'statements' | 'certifications'
 * @return string        Absolute path (with trailing slash)
 */
if (!function_exists('upload_physical_dir')) {
    function upload_physical_dir(string $type): string {
        $base = rtrim(str_replace('\\', '/', UPLOAD_PHYSICAL_PATH), '/');
        $dir  = $base . '/' . $type . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

/**
 * Move an uploaded file to the correct upload directory.
 *
 * @param  array  $file_array   Entry from $_FILES (e.g. $_FILES['cv_file'])
 * @param  string $type         'cv' | 'statements' | 'certifications'
 * @param  string $prefix       Filename prefix (e.g. employee_code)
 * @return string|false         DB-storable relative path on success, false on failure
 */
if (!function_exists('handle_upload')) {
    function handle_upload(array $file_array, string $type, string $prefix): string|false {
        $ext      = strtolower(pathinfo($file_array['name'], PATHINFO_EXTENSION));
        $filename = $prefix . '_' . $type . '_' . time() . '.' . $ext;
        $dir      = upload_physical_dir($type);
        $dest     = $dir . $filename;

        if (!move_uploaded_file($file_array['tmp_name'], $dest)) {
            return false;
        }

        // Return a DB-storable identifier (relative to UPLOAD_PHYSICAL_PATH)
        return $type . '/' . $filename;
    }
}

/**
 * Build the public URL for a stored file.
 *
 * @param  string|null $db_path  Value stored in DB (e.g. 'cv/emp001_cv_1234.pdf')
 *                                or old-style 'public/assets/uploads/cv/...'
 * @return string|null
 */
if (!function_exists('upload_url')) {
    function upload_url(?string $db_path): ?string {
        if (empty($db_path)) {
            return null;
        }
        $base = rtrim(UPLOAD_URL, '/');

        // Handle legacy paths stored with various prefixes
        foreach (['public/assets/uploads/', 'assets/uploads/', 'uploads/'] as $prefix) {
            if (strpos($db_path, $prefix) === 0) {
                $db_path = substr($db_path, strlen($prefix));
                break;
            }
        }

        return $base . '/' . ltrim($db_path, '/');
    }
}

/**
 * Delete a stored file from the physical upload directory.
 *
 * @param  string|null $db_path  Value stored in DB
 * @return bool
 */
if (!function_exists('delete_upload')) {
    function delete_upload(?string $db_path): bool {
        if (empty($db_path)) {
            return false;
        }
        foreach (['public/assets/uploads/', 'assets/uploads/', 'uploads/'] as $prefix) {
            if (strpos($db_path, $prefix) === 0) {
                $db_path = substr($db_path, strlen($prefix));
                break;
            }
        }
        $base = rtrim(str_replace('\\', '/', UPLOAD_PHYSICAL_PATH), '/');
        $path = $base . '/' . ltrim($db_path, '/');
        if (file_exists($path)) {
            return @unlink($path);
        }
        return false;
    }
}

/**
 * Validate MIME type using finfo before moving the uploaded file.
 * This function mitigates arbitrary file upload vulnerabilities.
 *
 * @param string $tmp_name
 * @param string $destination
 * @return bool
 */
if (!function_exists('safe_move_uploaded_file')) {
    function safe_move_uploaded_file(string $tmp_name, string $destination): bool {
        if (!file_exists($tmp_name)) {
            return false;
        }

        // Initialize finfo for MIME validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp_name);
        finfo_close($finfo);

        // Define securely allowed MIME types
        $allowed_mimes = [
            'application/pdf',
            'image/jpeg', 
            'image/jpg', 
            'image/png',
            'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        if (!in_array($mime, $allowed_mimes)) {
            // Log security warning if needed, but for now just return false
            return false;
        }

        // If MIME type is valid, proceed with the actual move
        return move_uploaded_file($tmp_name, $destination);
    }
}
