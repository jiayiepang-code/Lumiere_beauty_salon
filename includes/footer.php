<?php
// Determine image path based on where footer is included from
$currentScript = $_SERVER['PHP_SELF'] ?? '';
$footerImagePath = '';
if (strpos($currentScript, '/booking.php') !== false) {
    // If included from booking.php (in root), use root-relative path
    $footerImagePath = 'images/';
} else {
    // If included from user pages, use relative path
    $footerImagePath = '../images/';
}
?>
<!-- ========== FOOTER ========== -->
<footer class="custom-footer">

    <!-- Left: Logo + Brand Name -->
    <div class="footer-left">
        <img src="<?php echo $footerImagePath; ?>16.png" class="footer-big-logo" alt="Lumière Beauty Salon Logo">
        <h2 class="footer-brand-name">Lumière Beauty Salon</h2>
        <br><br>
        <p class="footer-copyright">© 2025 Lumière beauty salon. All rights reserved.</p>
    </div>

    <!-- Middle: Contact Details -->
    <div class="footer-center">
        <h4>Contact Us</h4>
        <p><b>Address:</b> Lumiere beauty salon, Ground Floor Block B,<br>
            Phase 2, Jln Lintas, Kolam Centre,<br>
            88300 Kota Kinabalu, Sabah.</p>
        <br>
        <p><b>Tel:</b> 012-345 6789<br>
           <b>Office:</b> 088-978 8977
        <br><br>   
        <p><b>Email:</b> Lumiere@gmail.com</p>
    </div>

    <!-- Right: Operating Hours + Social -->
    <div class="footer-right">
        <h4>Operating Hours</h4>
        <p>Mon - Sun</p>
        <p>10:00 AM – 10:00 PM</p>
        <br><br> 

        <h4 style="margin-top:18px;">Follow Us</h4>
        <div class="footer-social-icons">
            <!-- Instagram -->
            <a href="#" class="footer-social-circle" aria-label="Visit our Instagram">
                <img src="<?php echo $footerImagePath; ?>77.png" alt="Instagram">
            </a>
            <!-- Facebook -->
            <a href="#" class="footer-social-circle" aria-label="Visit our Facebook">
                <img src="<?php echo $footerImagePath; ?>76.png" alt="Facebook">
            </a>
            <!-- TikTok -->
            <a href="#" class="footer-social-circle" aria-label="Visit our TikTok">
                <img src="<?php echo $footerImagePath; ?>78.png" alt="TikTok">
            </a>
        </div>
    </div>

</footer>

