# QUICK FIX: Use mPDF 7.x Instead (No Dependencies!)

If downloading FPDI takes too long, we can switch to mPDF 7.x which works without FPDI.

## Option: Switch to mPDF 7.x (5 minutes)

1. Delete current mPDF: `vendor/mpdf/` folder
2. Download mPDF 7.4.2: https://github.com/mpdf/mpdf/releases/tag/v7.4.2
3. Extract to `vendor/mpdf/`
4. Update helper to use old class name `mPDF` instead of `\Mpdf\Mpdf`

This version doesn't require FPDI and will work immediately!

