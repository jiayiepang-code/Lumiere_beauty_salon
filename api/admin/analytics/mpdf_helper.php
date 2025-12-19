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
    // #region agent log
    $log_path = __DIR__ . '/../../../.cursor/debug.log';
    @file_put_contents($log_path, json_encode(['id'=>'log_'.time().'_mpdf1','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:11','message'=>'initMPDF started','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
    // #endregion
    
    // Base path for vendor directory
    $vendor_base = __DIR__ . '/../../../vendor';
    
    // #region agent log
    @file_put_contents($log_path, json_encode(['id'=>'log_'.time().'_mpdf2','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:16','message'=>'Checking mPDF paths','data'=>['vendorBase'=>$vendor_base],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
    // #endregion
    
    // Try to find mPDF in different locations
    $mpdf_base_paths = [
        $vendor_base . '/mpdf',
        $vendor_base . '/mpdf-8.2.0/mpdf-8.2.0'
    ];
    
    $mpdf_path = null;
    foreach ($mpdf_base_paths as $base) {
        $check_file = $base . '/src/Mpdf.php';
        // #region agent log
        @file_put_contents($log_path, json_encode(['id'=>'log_'.time().'_mpdf3','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:25','message'=>'Checking mPDF path','data'=>['path'=>$base,'fileExists'=>file_exists($check_file)],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
        // #endregion
        if (file_exists($check_file)) {
            $mpdf_path = $base;
            break;
        }
    }
    
    // #region agent log
    @file_put_contents($log_path, json_encode(['id'=>'log_'.time().'_mpdf4','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:32','message'=>'mPDF path result','data'=>['mpdfPath'=>$mpdf_path],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
    // #endregion
    
    if (!$mpdf_path) {
        // #region agent log
        @file_put_contents($log_path, json_encode(['id'=>'log_'.time().'_mpdf5','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:35','message'=>'mPDF not found, returning false','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
        // #endregion
        return false;
    }
    
    // Set up autoloader for mPDF and dependencies
    spl_autoload_register(function ($class) use ($mpdf_path, $vendor_base) {
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
        if (strpos($class, 'Psr\\') === 0 || strpos($class, 'DeepCopy\\') === 0) {
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
    
    // Set data directory
    $data_dir = $mpdf_path . '/data';
    
    // Require the main Mpdf class
    // #region agent log
    @file_put_contents($log_path, json_encode(['id'=>'log_'.time().'_mpdf6','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:82','message'=>'Before requiring Mpdf.php','data'=>['mpdfFile'=>$mpdf_path . '/src/Mpdf.php'],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
    // #endregion
    
    require_once $mpdf_path . '/src/Mpdf.php';
    
    // #region agent log
    @file_put_contents($log_path, json_encode(['id'=>'log_'.time().'_mpdf7','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:86','message'=>'After requiring Mpdf.php','data'=>['classExists'=>class_exists('\Mpdf\Mpdf')],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
    // #endregion
    
    // Check if class exists (mPDF 8.x)
    if (class_exists('\Mpdf\Mpdf')) {
        // #region agent log
        @file_put_contents($log_path, json_encode(['id'=>'log_'.time().'_mpdf8','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:89','message'=>'Trying mPDF 8.x initialization','data'=>['tempDir'=>$temp_dir],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
        // #endregion
        try {
            $mpdf_instance = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 20,
                'margin_header' => 10,
                'margin_footer' => 10,
                'tempDir' => $temp_dir,
                'fontDir' => [$mpdf_path . '/ttfonts'],
                'fontdata' => []
            ]);
            // #region agent log
            @file_put_contents($log_path, json_encode(['id'=>'log_'.time().'_mpdf9','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:105','message'=>'mPDF 8.x initialized successfully','data'=>['class'=>get_class($mpdf_instance)],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
            // #endregion
            return $mpdf_instance;
        } catch (\Exception $e) {
            // #region agent log
            @file_put_contents($log_path, json_encode(['id'=>'log_'.time().'_mpdf10','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:110','message'=>'mPDF 8.x initialization error','data'=>['error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
            // #endregion
            error_log("mPDF 8.x initialization error: " . $e->getMessage());
            // Try with minimal config if full config fails
            try {
                // #region agent log
                @file_put_contents($log_path, json_encode(['id'=>'log_'.time().'_mpdf11','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:115','message'=>'Trying mPDF 8.x minimal config','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
                // #endregion
                $mpdf_instance = new \Mpdf\Mpdf([
                    'tempDir' => $temp_dir
                ]);
                // #region agent log
                @file_put_contents($log_path, json_encode(['id'=>'log_'.time().'_mpdf12','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:120','message'=>'mPDF 8.x minimal config succeeded','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
                // #endregion
                return $mpdf_instance;
            } catch (\Exception $e2) {
                // #region agent log
                @file_put_contents($log_path, json_encode(['id'=>'log_'.time().'_mpdf13','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:123','message'=>'mPDF 8.x minimal config also failed','data'=>['error'=>$e2->getMessage()],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H1'])."\n", FILE_APPEND);
                // #endregion
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
                return new mPDF('utf-8', 'A4', 0, '', 15, 15, 15, 15, 9, 9);
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
        'email' => 'Lumiere@gmail.com',
        'phone' => '012 345 6789',
        'office_phone' => '088 978 8977',
        'logo_path' => __DIR__ . '/../../../images/16.png'
    ];
}

/**
 * Generate PDF header HTML
 * @param object $mpdf mPDF instance
 * @return string HTML for header
 */
function generatePDFHeader($mpdf = null) {
    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_17','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:150','message'=>'generatePDFHeader called','data'=>['mpdfIsNull'=>$mpdf===null],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H4'])."\n", FILE_APPEND);
    // #endregion
    
    $company = getCompanyInfo();
    $logo_exists = file_exists($company['logo_path']);
    
    // #region agent log
    file_put_contents(__DIR__ . '/../../../.cursor/debug.log', json_encode(['id'=>'log_'.time().'_18','timestamp'=>time()*1000,'location'=>'mpdf_helper.php:155','message'=>'Logo path check','data'=>['logoPath'=>$company['logo_path'],'logoExists'=>$logo_exists],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'H4'])."\n", FILE_APPEND);
    // #endregion
    
    $html = '<div style="margin-bottom: 20px; border-bottom: 2px solid #D4A574; padding-bottom: 15px;">';
    
    if ($logo_exists && $mpdf) {
        // Add logo as image
        $html .= '<table style="width: 100%; margin-bottom: 10px;"><tr>';
        $html .= '<td style="width: 80px; vertical-align: top;">';
        $html .= '<img src="' . $company['logo_path'] . '" style="height: 50px; width: auto;" />';
        $html .= '</td>';
        $html .= '<td style="vertical-align: top; padding-left: 15px;">';
    } else {
        $html .= '<div style="padding-left: 0;">';
    }
    
    $html .= '<h1 style="margin: 0; padding: 0; font-size: 20px; font-weight: bold; color: #2d2d2d; font-family: Arial, sans-serif;">' . htmlspecialchars($company['name']) . '</h1>';
    $html .= '<p style="margin: 5px 0 0 0; font-size: 10px; color: #666; font-family: Arial, sans-serif;">' . htmlspecialchars($company['address_line1']) . '</p>';
    $html .= '<p style="margin: 2px 0 0 0; font-size: 10px; color: #666; font-family: Arial, sans-serif;">' . htmlspecialchars($company['address_line2']) . '</p>';
    $html .= '<p style="margin: 5px 0 0 0; font-size: 10px; color: #666; font-family: Arial, sans-serif;">';
    $html .= 'Email: ' . htmlspecialchars($company['email']) . ' | ';
    $html .= 'Tel: ' . htmlspecialchars($company['phone']) . ' / ' . htmlspecialchars($company['office_phone']);
    $html .= '</p>';
    
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
    $html = '<div style="text-align: center; font-size: 9px; color: #666; font-family: Arial, sans-serif; padding-top: 10px; border-top: 1px solid #e0e0e0;">';
    $html .= '<p style="margin: 5px 0;">Page {PAGENO} of {nbpg}</p>';
    $html .= '<p style="margin: 5px 0;">Lumière Beauty Salon - ' . htmlspecialchars($report_type) . ' - Confidential</p>';
    $html .= '</div>';
    return $html;
}


