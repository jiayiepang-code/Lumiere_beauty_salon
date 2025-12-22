<?php
/**
 * HTML Helper Functions for PDF Export
 * Provides utility functions for generating HTML templates (no mPDF dependency)
 */

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
        'logo_path' => '/images/16.png' // Relative path for HTML
    ];
}

/**
 * Generate PDF header HTML (for html2pdf.js)
 * @return string HTML for header
 */
function generateHTMLHeader() {
    $company = getCompanyInfo();
    
    // Convert logo to base64 data URI for reliable rendering in temporary div
    $logo_data_uri = '';
    $logo_file = __DIR__ . '/../../../images/16.png';
    if (file_exists($logo_file)) {
        $logo_data = file_get_contents($logo_file);
        $logo_mime = mime_content_type($logo_file) ?: 'image/png';
        $logo_base64 = base64_encode($logo_data);
        $logo_data_uri = 'data:' . $logo_mime . ';base64,' . $logo_base64;
    }
    
    $html = '<div style="margin-bottom: 20px; border-bottom: 2px solid #D4A574; padding-bottom: 15px;">';
    
    $html .= '<table style="width: 100%; margin-bottom: 10px;"><tr>';
    $html .= '<td style="width: 80px; vertical-align: top;">';
    if ($logo_data_uri) {
        $html .= '<img src="' . htmlspecialchars($logo_data_uri) . '" style="height: 50px; width: auto; max-width: 80px;" />';
    }
    $html .= '</td>';
    $html .= '<td style="vertical-align: top; padding-left: 15px;">';
    
    $html .= '<h1 style="margin: 0; padding: 0; font-size: 20px; font-weight: bold; color: #2d2d2d; font-family: Arial, sans-serif;">' . htmlspecialchars($company['name']) . '</h1>';
    $html .= '<p style="margin: 5px 0 0 0; font-size: 10px; color: #666; font-family: Arial, sans-serif;">' . htmlspecialchars($company['address_line1']) . '</p>';
    $html .= '<p style="margin: 2px 0 0 0; font-size: 10px; color: #666; font-family: Arial, sans-serif;">' . htmlspecialchars($company['address_line2']) . '</p>';
    $html .= '<p style="margin: 5px 0 0 0; font-size: 10px; color: #666; font-family: Arial, sans-serif;">';
    $html .= 'Email: ' . htmlspecialchars($company['email']) . ' | ';
    $html .= 'Tel: ' . htmlspecialchars($company['phone']) . ' / ' . htmlspecialchars($company['office_phone']);
    $html .= '</p>';
    
    $html .= '</td></tr></table>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Generate PDF footer HTML (for html2pdf.js)
 * @param string $report_type Type of report (Business/ESG)
 * @return string HTML for footer
 */
function generateHTMLFooter($report_type = 'Report') {
    $html = '<div style="text-align: center; font-size: 9px; color: #666; font-family: Arial, sans-serif; padding-top: 10px; border-top: 1px solid #e0e0e0; margin-top: 20px;">';
    $html .= '<p style="margin: 5px 0;">Lumière Beauty Salon - ' . htmlspecialchars($report_type) . ' - Confidential</p>';
    $html .= '<p style="margin: 5px 0;">Generated on ' . date('F j, Y g:i A') . '</p>';
    $html .= '</div>';
    return $html;
}

