<?php
/**
 * mPDF Helper Functions
 * Provides initialization and utility functions for mPDF
 */

/**
 * Initialize mPDF library
 * @return object|false mPDF instance or false if not available
 */
function initMPDF() {
    // OPTION 0: Try main vendor/autoload.php (Composer autoloader - includes PSR Log)
    $vendor_autoload = __DIR__ . '/../../../vendor/autoload.php';
    if (file_exists($vendor_autoload)) {
        try {
            require_once $vendor_autoload;
            
            // Check if mPDF class is available after autoloader
            if (class_exists('\Mpdf\Mpdf')) {
                // Get mPDF path for temp directory
                $mpdf_path = __DIR__ . '/../../../vendor/mpdf/mpdf';
                $temp_dir = $mpdf_path . '/tmp';
                if (!is_dir($temp_dir)) {
                    @mkdir($temp_dir, 0755, true);
                }
                
                try {
                    // Configure default font (DejaVuSans is available in mPDF)
                    $fontdata = [
                        'dejavusans' => [
                            'R' => 'DejaVuSans.ttf',
                            'B' => 'DejaVuSans-Bold.ttf',
                            'I' => 'DejaVuSans-Oblique.ttf',
                            'BI' => 'DejaVuSans-BoldOblique.ttf',
                        ],
                        'dejavuserif' => [
                            'R' => 'DejaVuSerif.ttf',
                            'B' => 'DejaVuSerif-Bold.ttf',
                            'I' => 'DejaVuSerif-Italic.ttf',
                            'BI' => 'DejaVuSerif-BoldItalic.ttf',
                        ],
                    ];
                    
                    $mpdf_instance = new \Mpdf\Mpdf([
                        'mode' => 'utf-8',
                        'format' => 'A4',
                        'orientation' => 'P',
                        'margin_left' => 15,
                        'margin_right' => 15,
                        'margin_top' => 15,
                        'margin_bottom' => 20,
                        'margin_header' => 25,
                        'margin_footer' => 10,
                        'tempDir' => $temp_dir,
                        'fontDir' => [$mpdf_path . '/ttfonts'],
                        'fontdata' => $fontdata,
                        'default_font' => 'dejavusans'
                    ]);
                    return $mpdf_instance;
                } catch (\Exception $e) {
                    error_log("mPDF Composer initialization error: " . $e->getMessage());
                    // Fall through to manual loading
                }
            }
        } catch (\Exception $e) {
            error_log("Error loading Composer autoloader: " . $e->getMessage());
            // Fall through to manual loading
        }
    }
    
    // OPTION 1: Try vendor_custom/pdf_generator/vendor (Composer autoloader with all dependencies)
    $vendor_custom_autoload = __DIR__ . '/../../../vendor_custom/pdf_generator/vendor/autoload.php';
    if (file_exists($vendor_custom_autoload)) {
        try {
            require_once $vendor_custom_autoload;
            
            // Check if mPDF class is available after autoloader
            if (class_exists('\Mpdf\Mpdf')) {
                // Get mPDF path for temp directory
                $mpdf_path = __DIR__ . '/../../../vendor_custom/pdf_generator/vendor/mpdf/mpdf';
                $temp_dir = $mpdf_path . '/tmp';
                if (!is_dir($temp_dir)) {
                    @mkdir($temp_dir, 0755, true);
                }
                
                try {
                    // Configure default font (DejaVuSans is available in mPDF)
                    $fontdata = [
                        'dejavusans' => [
                            'R' => 'DejaVuSans.ttf',
                            'B' => 'DejaVuSans-Bold.ttf',
                            'I' => 'DejaVuSans-Oblique.ttf',
                            'BI' => 'DejaVuSans-BoldOblique.ttf',
                        ],
                        'dejavuserif' => [
                            'R' => 'DejaVuSerif.ttf',
                            'B' => 'DejaVuSerif-Bold.ttf',
                            'I' => 'DejaVuSerif-Italic.ttf',
                            'BI' => 'DejaVuSerif-BoldItalic.ttf',
                        ],
                    ];
                    
                    $mpdf_instance = new \Mpdf\Mpdf([
                        'mode' => 'utf-8',
                        'format' => 'A4',
                        'orientation' => 'P',
                        'margin_left' => 15,
                        'margin_right' => 15,
                        'margin_top' => 15,
                        'margin_bottom' => 20,
                        'margin_header' => 25,
                        'margin_footer' => 10,
                        'tempDir' => $temp_dir,
                        'fontDir' => [$mpdf_path . '/ttfonts'],
                        'fontdata' => $fontdata,
                        'default_font' => 'dejavusans'
                    ]);
                    return $mpdf_instance;
                } catch (\Exception $e) {
                    error_log("mPDF Composer initialization error: " . $e->getMessage());
                    // Fall through to manual loading
                }
            }
        } catch (\Exception $e) {
            error_log("Error loading Composer autoloader: " . $e->getMessage());
            // Fall through to manual loading
        }
    }
    
    // OPTION 2: Fallback to manual loading from vendor/mpdf
    // Base path for vendor directory
    $vendor_base = __DIR__ . '/../../../vendor';
    
    // Try to find mPDF in different locations
    $mpdf_base_paths = [
        $vendor_base . '/mpdf',
        $vendor_base . '/mpdf-8.2.0/mpdf-8.2.0'
    ];
    
    $mpdf_path = null;
    foreach ($mpdf_base_paths as $base) {
        $check_file = $base . '/src/Mpdf.php';
        if (file_exists($check_file)) {
            $mpdf_path = $base;
            break;
        }
    }
    
    if (!$mpdf_path) {
        return false;
    }
    
    // Set up autoloader for mPDF and dependencies
    spl_autoload_register(function ($class) use ($mpdf_path, $vendor_base) {
        // First, check if the class/interface already exists
        // This prevents autoloader from interfering with already-defined classes
        if (class_exists($class, false) || interface_exists($class, false)) {
            return true; // Already exists, no need to load
        }
        
        // Handle Mpdf namespace
        if (strpos($class, 'Mpdf\\') === 0) {
            $file = str_replace('\\', DIRECTORY_SEPARATOR, $class);
            $file = str_replace('Mpdf' . DIRECTORY_SEPARATOR, '', $file);
            $file_path = $mpdf_path . '/src/' . $file . '.php';
            if (file_exists($file_path)) {
                require_once $file_path;
                return true;
            }
        }
        // Handle FPDI namespace (required dependency)
        if (strpos($class, 'setasign\\Fpdi\\') === 0) {
            $file = str_replace('\\', DIRECTORY_SEPARATOR, $class);
            $file = str_replace('setasign' . DIRECTORY_SEPARATOR . 'Fpdi' . DIRECTORY_SEPARATOR, '', $file);
            // Try multiple possible locations
            $fpdi_paths = [
                $vendor_base . '/setasign/fpdi/src/' . $file . '.php',
                $vendor_base . '/setasign/fpdi/' . $file . '.php',
                $vendor_base . '/fpdi/src/' . $file . '.php'
            ];
            foreach ($fpdi_paths as $fpdi_path) {
                if (file_exists($fpdi_path)) {
                    require_once $fpdi_path;
                    return true;
                }
            }
        }
        // Handle other namespaces that mPDF might use
        // For PSR Log, the stub file should have already been loaded
        // Just check if it exists - don't try to autoload interfaces (they can't be autoloaded)
        if (strpos($class, 'Psr\\Log\\') === 0) {
            // Interfaces/classes should already be defined by the stub file loaded earlier
            // Return false to let PHP handle the error naturally if they don't exist
            // This way we get a clear error message instead of silent failure
            return false;
        }
        if (strpos($class, 'DeepCopy\\') === 0) {
            // These are dependencies - try to load if available, but don't fail
            return false;
        }
        return false;
    }, true, true); // prepend=true, suppress errors
    
    // Set temp directory for mPDF
    $temp_dir = $mpdf_path . '/tmp';
    if (!is_dir($temp_dir)) {
        @mkdir($temp_dir, 0755, true);
    }
    
    // Check if file exists and is readable
    $mpdf_file = $mpdf_path . '/src/Mpdf.php';
    if (!file_exists($mpdf_file) || !is_readable($mpdf_file)) {
        return false;
    }
    
    // Load PSR Log stub if missing (mPDF requires psr/log but it may not be installed)
    // NOTE: Moved to export files - loaded earlier to prevent output issues
    // Commenting out to avoid duplicate loading
    /*
    if (!class_exists('Psr\Log\NullLogger')) {
        $psr_stub_file = __DIR__ . '/psr_log_stub.php';
        if (file_exists($psr_stub_file)) {
            require_once $psr_stub_file;
        }
    }
    */
    
    // Use output buffering to catch any output/errors
    ob_start();
    $error_occurred = false;
    $last_error = null;
    
    // Set error handler to capture errors
    $prev_error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$error_occurred, &$last_error) {
        $last_error = ['errno' => $errno, 'errstr' => $errstr, 'errfile' => $errfile, 'errline' => $errline];
        $error_occurred = true;
        return false; // Let PHP handle the error normally
    }, E_ALL);
    
    // Register shutdown function to catch fatal errors
    register_shutdown_function(function() use (&$error_occurred) {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $error_occurred = true;
        }
    });
    
    // Suppress errors during include and check result
    $included = @include_once $mpdf_file;
    $output = ob_get_clean();
    
    // Restore error handler
    if ($prev_error_handler !== null) {
        set_error_handler($prev_error_handler);
    } else {
        restore_error_handler();
    }
    
    if ($included === false || $error_occurred) {
        return false;
    }
    
    // Check if class exists (mPDF 8.x)
    if (class_exists('\Mpdf\Mpdf')) {
        try {
            // Configure default font (DejaVuSans is available in mPDF)
            $fontdata = [
                'dejavusans' => [
                    'R' => 'DejaVuSans.ttf',
                    'B' => 'DejaVuSans-Bold.ttf',
                    'I' => 'DejaVuSans-Oblique.ttf',
                    'BI' => 'DejaVuSans-BoldOblique.ttf',
                ],
                'dejavuserif' => [
                    'R' => 'DejaVuSerif.ttf',
                    'B' => 'DejaVuSerif-Bold.ttf',
                    'I' => 'DejaVuSerif-Italic.ttf',
                    'BI' => 'DejaVuSerif-BoldItalic.ttf',
                ],
            ];
            
            $mpdf_instance = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 20,
                'margin_header' => 25,
                'margin_footer' => 10,
                'tempDir' => $temp_dir,
                'fontDir' => [$mpdf_path . '/ttfonts'],
                'fontdata' => $fontdata,
                'default_font' => 'dejavusans'
            ]);
            
            return $mpdf_instance;
        } catch (\Exception $e) {
            error_log("mPDF 8.x initialization error: " . $e->getMessage());
            // Try with minimal config if full config fails
            try {
                $mpdf_instance = new \Mpdf\Mpdf([
                    'tempDir' => $temp_dir
                ]);
                return $mpdf_instance;
            } catch (\Exception $e2) {
                error_log("mPDF minimal config also failed: " . $e2->getMessage());
                return false;
            }
        }
    }
    
    // Fallback: Try mPDF 7.x (old class name, no FPDI needed)
    if (file_exists($mpdf_path . '/mpdf.php')) {
        require_once $mpdf_path . '/mpdf.php';
        if (class_exists('mPDF')) {
            try {
                // Use variable to avoid IDE type checking issues with dynamically loaded class
                $mpdf_class = 'mPDF';
                /** @var object $mpdf_instance */
                $mpdf_instance = new $mpdf_class('utf-8', 'A4', 0, '', 15, 15, 15, 15, 9, 9);
                return $mpdf_instance;
            } catch (\Exception $e) {
                error_log("mPDF 7.x initialization error: " . $e->getMessage());
                return false;
            }
        }
    }
    
    return false;
}

/**
 * Get company information for PDF headers
 * @return array Company details
 */
function getCompanyInfo() {
    return [
        'name' => 'Lumière Beauty Salon',
        'address_line1' => 'No. 10, Ground Floor Block B, Phase 2, Jln Lintas, Kolam Centre',
        'address_line2' => '88300 Kota Kinabalu, Sabah',
        'email' => 'lumierebeautysalon2022@gmail.com',
        'phone' => '012 345 6789',
        'office_phone' => '088 978 8977',
        'registration_number' => 'SSM: SA0123456-A',
        'logo_path' => __DIR__ . '/../../../images/16.png'
    ];
}

/**
 * Generate PDF header HTML
 * @param object $mpdf mPDF instance
 * @return string HTML for header
 */
function generatePDFHeader($mpdf = null) {
    $company = getCompanyInfo();
    
    // Check if logo file exists and is readable
    $logo_path = $company['logo_path'] ?? '';
    $logo_exists = false;
    $logo_html = '';
    
    if (!empty($logo_path) && file_exists($logo_path) && is_readable($logo_path) && $mpdf) {
        try {
            // Read logo data
            $logo_data = file_get_contents($logo_path);
            if ($logo_data !== false && strlen($logo_data) > 0) {
                // Get mPDF temp directory - use the one configured in mPDF
                $temp_dir = null;
                if (property_exists($mpdf, 'tempDir') && !empty($mpdf->tempDir)) {
                    $temp_dir = $mpdf->tempDir;
                }
                
                // Fallback to vendor/mpdf/tmp if available
                if (empty($temp_dir) || !is_dir($temp_dir)) {
                    $vendor_mpdf_tmp = __DIR__ . '/../../../vendor/mpdf/mpdf/tmp';
                    if (is_dir($vendor_mpdf_tmp)) {
                        $temp_dir = $vendor_mpdf_tmp;
                    } else {
                        $temp_dir = sys_get_temp_dir();
                    }
                }
                
                // Ensure temp directory exists
                if (!is_dir($temp_dir)) {
                    @mkdir($temp_dir, 0755, true);
                }
                
                // Try using absolute file path first (most reliable for mPDF)
                // Convert Windows backslashes to forward slashes for mPDF
                $absolute_logo_path = str_replace('\\', '/', realpath($logo_path));
                
                // Detect MIME type
                $logo_mime = 'image/png';
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detected_mime = finfo_file($finfo, $logo_path);
                    if ($detected_mime && strpos($detected_mime, 'image/') === 0) {
                        $logo_mime = $detected_mime;
                    }
                    finfo_close($finfo);
                }
                
                // Always use base64 encoding for reliability (works on all systems)
                $logo_base64 = base64_encode($logo_data);
                $logo_html = '<img src="data:' . $logo_mime . ';base64,' . $logo_base64 . '" style="height: 28px; max-width: 55px; width: auto;" />';
                $logo_exists = true;
            }
        } catch (Exception $e) {
            // If logo loading fails, log error but continue without logo
            error_log("Logo loading error in header: " . $e->getMessage());
        }
    }
    
    // Compact header to fit within margin_header space
    $html = '<div style="margin: 0; padding: 0; border-bottom: 2px solid #D4A574; padding-bottom: 5px;">';
    
    if ($logo_exists && !empty($logo_html)) {
        // Compact table layout with logo - smaller logo
        $html .= '<table style="width: 100%; border-collapse: collapse; margin: 0; padding: 0;"><tr>';
        $html .= '<td style="width: 55px; vertical-align: middle; padding: 0; margin: 0;">';
        // Make logo smaller in header
        $logo_html_small = str_replace('height: 35px; max-width: 70px;', 'height: 28px; max-width: 55px;', $logo_html);
        $html .= $logo_html_small;
        $html .= '</td>';
        $html .= '<td style="vertical-align: middle; padding-left: 6px; margin: 0;">';
    } else {
        $html .= '<div style="padding: 0; margin: 0;">';
    }
    
    // Even more compact company info to prevent overlap - smaller fonts
    $html .= '<h1 style="margin: 0; padding: 0; font-size: 11px; font-weight: bold; color: #2d2d2d; font-family: dejavusans, sans-serif; line-height: 1.0;">' . htmlspecialchars($company['name']) . '</h1>';
    $html .= '<p style="margin: 0.5px 0 0 0; padding: 0; font-size: 7px; color: #666; font-family: dejavusans, sans-serif; line-height: 1.0;">' . htmlspecialchars($company['address_line1'] ?? '') . ', ' . htmlspecialchars($company['address_line2'] ?? '') . '</p>';
    $html .= '<p style="margin: 0.5px 0 0 0; padding: 0; font-size: 7px; color: #666; font-family: dejavusans, sans-serif; line-height: 1.0;">Email: ' . htmlspecialchars($company['email'] ?? '') . ' | Tel: ' . htmlspecialchars($company['phone'] ?? '') . ' / ' . htmlspecialchars($company['office_phone'] ?? '') . '</p>';
    
    if ($logo_exists) {
        $html .= '</td></tr></table>';
    } else {
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Generate PDF footer HTML
 * @param string $report_type Type of report (Business/ESG)
 * @return string HTML for footer
 */
function generatePDFFooter($report_type = 'Report') {
    $html = '<div style="text-align: center; font-size: 9px; color: #666; font-family: dejavusans, sans-serif; padding-top: 10px; border-top: 1px solid #e0e0e0;">';
    $html .= '<p style="margin: 5px 0;">Page {PAGENO} of {nbpg}</p>';
    $html .= '<p style="margin: 5px 0;">Lumière Beauty Salon - ' . htmlspecialchars($report_type) . ' - Confidential</p>';
    $html .= '</div>';
    return $html;
}


