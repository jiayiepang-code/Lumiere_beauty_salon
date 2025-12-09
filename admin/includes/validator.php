<?php
/**
 * Centralized Validation Library for Admin Module
 * Provides reusable validation functions
 */

class Validator {
    
    /**
     * Validate required field
     * 
     * @param mixed $value Field value
     * @param string $field_name Field name for error message
     * @return string|null Error message or null if valid
     */
    public static function required($value, $field_name) {
        if (empty($value) && $value !== '0' && $value !== 0) {
            return ucfirst($field_name) . ' is required';
        }
        return null;
    }
    
    /**
     * Validate email format
     * 
     * @param string $email Email address
     * @return string|null Error message or null if valid
     */
    public static function email($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email format';
        }
        return null;
    }
    
    /**
     * Validate string length
     * 
     * @param string $value String value
     * @param int $min Minimum length
     * @param int $max Maximum length
     * @param string $field_name Field name for error message
     * @return string|null Error message or null if valid
     */
    public static function length($value, $min = null, $max = null, $field_name = 'Field') {
        $len = strlen($value);
        
        if ($min !== null && $len < $min) {
            return $field_name . ' must be at least ' . $min . ' characters';
        }
        
        if ($max !== null && $len > $max) {
            return $field_name . ' must not exceed ' . $max . ' characters';
        }
        
        return null;
    }
    
    /**
     * Validate numeric range
     * 
     * @param mixed $value Numeric value
     * @param float $min Minimum value
     * @param float $max Maximum value
     * @param string $field_name Field name for error message
     * @return string|null Error message or null if valid
     */
    public static function range($value, $min = null, $max = null, $field_name = 'Value') {
        if (!is_numeric($value)) {
            return $field_name . ' must be a number';
        }
        
        $num = floatval($value);
        
        if ($min !== null && $num < $min) {
            return $field_name . ' must be at least ' . $min;
        }
        
        if ($max !== null && $num > $max) {
            return $field_name . ' must not exceed ' . $max;
        }
        
        return null;
    }
    
    /**
     * Validate Malaysia phone number format
     * 
     * @param string $phone Phone number
     * @return string|null Error message or null if valid
     */
    public static function phoneNumber($phone) {
        // Remove spaces and dashes
        $phone = preg_replace('/[\s\-]/', '', $phone);
        
        // Check for Malaysia format: 01X-XXXXXXX or 60XXXXXXXXX
        if (preg_match('/^(01[0-9]{8,9})$/', $phone)) {
            return null;
        }
        if (preg_match('/^(60[0-9]{9,10})$/', $phone)) {
            return null;
        }
        
        return 'Invalid phone format. Use Malaysia format (01X-XXXXXXX or 60XXXXXXXXX)';
    }
    
    /**
     * Validate password strength
     * 
     * @param string $password Password
     * @return array Array of error messages (empty if valid)
     */
    public static function passwordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return $errors;
    }
    
    /**
     * Validate enum value
     * 
     * @param mixed $value Value to check
     * @param array $allowed_values Array of allowed values
     * @param string $field_name Field name for error message
     * @return string|null Error message or null if valid
     */
    public static function enum($value, $allowed_values, $field_name = 'Value') {
        if (!in_array($value, $allowed_values)) {
            return $field_name . ' must be one of: ' . implode(', ', $allowed_values);
        }
        return null;
    }
    
    /**
     * Validate file upload
     * 
     * @param array $file $_FILES array element
     * @param array $allowed_types Allowed MIME types
     * @param int $max_size Maximum file size in bytes
     * @return string|null Error message or null if valid
     */
    public static function fileUpload($file, $allowed_types = ['image/jpeg', 'image/png'], $max_size = 2097152) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return 'No file uploaded';
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'File upload failed with error code: ' . $file['error'];
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $max_mb = $max_size / 1048576;
            return 'File size must not exceed ' . $max_mb . 'MB';
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types);
        }
        
        return null;
    }
    
    /**
     * Validate date format
     * 
     * @param string $date Date string
     * @param string $format Expected format (default: Y-m-d)
     * @return string|null Error message or null if valid
     */
    public static function date($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        if (!$d || $d->format($format) !== $date) {
            return 'Invalid date format. Expected format: ' . $format;
        }
        return null;
    }
    
    /**
     * Validate time format
     * 
     * @param string $time Time string
     * @param string $format Expected format (default: H:i:s)
     * @return string|null Error message or null if valid
     */
    public static function time($time, $format = 'H:i:s') {
        $t = DateTime::createFromFormat($format, $time);
        if (!$t || $t->format($format) !== $time) {
            return 'Invalid time format. Expected format: ' . $format;
        }
        return null;
    }
    
    /**
     * Validate decimal number
     * 
     * @param mixed $value Value to check
     * @param int $precision Total digits
     * @param int $scale Decimal places
     * @param string $field_name Field name for error message
     * @return string|null Error message or null if valid
     */
    public static function decimal($value, $precision = 10, $scale = 2, $field_name = 'Value') {
        if (!is_numeric($value)) {
            return $field_name . ' must be a number';
        }
        
        $parts = explode('.', strval($value));
        $integer_part = $parts[0];
        $decimal_part = isset($parts[1]) ? $parts[1] : '';
        
        $total_digits = strlen(str_replace('-', '', $integer_part)) + strlen($decimal_part);
        
        if ($total_digits > $precision) {
            return $field_name . ' exceeds maximum precision of ' . $precision . ' digits';
        }
        
        if (strlen($decimal_part) > $scale) {
            return $field_name . ' must have at most ' . $scale . ' decimal places';
        }
        
        return null;
    }
    
    /**
     * Sanitize string for output
     * 
     * @param string $value String to sanitize
     * @return string Sanitized string
     */
    public static function sanitize($value) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate and sanitize input data
     * 
     * @param array $data Input data
     * @param array $rules Validation rules
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public static function validate($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $field_rules) {
            $value = isset($data[$field]) ? $data[$field] : null;
            
            foreach ($field_rules as $rule => $params) {
                $error = null;
                
                switch ($rule) {
                    case 'required':
                        $error = self::required($value, $field);
                        break;
                    
                    case 'email':
                        if (!empty($value)) {
                            $error = self::email($value);
                        }
                        break;
                    
                    case 'length':
                        if (!empty($value)) {
                            $error = self::length($value, $params['min'] ?? null, $params['max'] ?? null, $field);
                        }
                        break;
                    
                    case 'range':
                        if (!empty($value) || $value === 0 || $value === '0') {
                            $error = self::range($value, $params['min'] ?? null, $params['max'] ?? null, $field);
                        }
                        break;
                    
                    case 'phone':
                        if (!empty($value)) {
                            $error = self::phoneNumber($value);
                        }
                        break;
                    
                    case 'password':
                        if (!empty($value)) {
                            $password_errors = self::passwordStrength($value);
                            if (!empty($password_errors)) {
                                $error = implode('. ', $password_errors);
                            }
                        }
                        break;
                    
                    case 'enum':
                        if (!empty($value)) {
                            $error = self::enum($value, $params['values'], $field);
                        }
                        break;
                    
                    case 'date':
                        if (!empty($value)) {
                            $error = self::date($value, $params['format'] ?? 'Y-m-d');
                        }
                        break;
                    
                    case 'time':
                        if (!empty($value)) {
                            $error = self::time($value, $params['format'] ?? 'H:i:s');
                        }
                        break;
                    
                    case 'decimal':
                        if (!empty($value) || $value === 0 || $value === '0') {
                            $error = self::decimal($value, $params['precision'] ?? 10, $params['scale'] ?? 2, $field);
                        }
                        break;
                }
                
                if ($error !== null) {
                    $errors[$field] = $error;
                    break; // Stop checking other rules for this field
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
