<?php
session_start();
// 1. Include the Header (Connects to Home/Nav)
// We use "../" to go up one folder to find the includes
require_once '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meet The Team – Lumière Beauty Salon</title>

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/home.css">

    <link rel="stylesheet" href="../user/contact.css">

    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
</head>

<body>
<section class="contact-wrapper">

    <div class="contact-image-box">
        <img src="../images/39.png" alt="Shop Photo">
    </div>

    <div class="contact-info-box">
        <h1 class="contact-title">Lumière Beauty Salon</h1>

        <p class="contact-label">Address:</p>
        <p class="contact-text">
            Lumiere beauty salon, Ground Floor Block B,<br>
            Phase 2, Jln Lintas, Kolam Centre,<br>
            88300 Kota Kinabalu, Sabah.
        </p>

        <p class="contact-label">Email:</p>
        <p class="contact-text">Lumiere@gmail.com</p>

        <p class="contact-label">Contact:</p>
        <p class="contact-text">
            Tel: 012 345 6789<br>
            Office: 088 978 8977
        </p>
    </div>
</section>

<section class="contact-map-section">
    <div class="map-box">
        <iframe 
            src="https://maps.google.com/maps?width=100%25&amp;height=600&amp;hl=en&amp;q=Kolam%20Centre%20Phase%202%2C%20Jln%20Lintas%2C%2088300%20Kota%20Kinabalu%2C%20Sabah&amp;t=&amp;z=17&amp;ie=UTF8&amp;iwloc=B&amp;output=embed"
            width="600" 
            height="450" 
            style="border:0;" 
            allowfullscreen="" 
            loading="lazy" 
            referrerpolicy="no-referrer-when-downgrade">
        </iframe>
    </div>
</section>

<?php
// 4. Include the Footer
require_once '../includes/footer.php';
?>

</body>
</html>