# mPDF Setup Instructions

## Quick Setup (No Composer Required)

1. **Download mPDF:**
   - Visit: https://github.com/mpdf/mpdf/releases
   - Download the latest release ZIP file (e.g., `mpdf-8.2.0.zip`)
   - Extract the ZIP file

2. **Install:**
   - Copy the extracted `mpdf` folder contents into this `vendor/mpdf/` directory
   - The structure should be: `vendor/mpdf/vendor/mpdf/mpdf/src/Mpdf.php`

3. **Verify Installation:**
   - Check that `vendor/mpdf/vendor/mpdf/mpdf/src/Mpdf.php` exists
   - The PDF export endpoints will automatically detect and use mPDF

## Alternative: Manual Download

If you prefer, you can download directly:
- Latest stable: https://github.com/mpdf/mpdf/archive/refs/tags/v8.2.0.zip
- Extract and place contents in `vendor/mpdf/`

## Note

The PDF export will work once mPDF is properly installed in this directory.


