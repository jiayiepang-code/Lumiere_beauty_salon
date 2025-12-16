<?php
session_start();
// LOAD SHARED HEADER
include "../includes/header.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services – Lumière Beauty Salon</title>

    <link rel="stylesheet" href="/Lumiere_beauty_salon/css/style.css">
    <link rel="stylesheet" href="/Lumiere_beauty_salon/css/home.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="services.css">
</head>

<body>

<section class="services-header">
    <h1 class="services-title">Our Services</h1>
    <p class="services-subtitle">Explore our full range of beauty treatments</p>
</section>

<div class="service-category-buttons">
    <button class="service-cat" onclick="selectCategory('haircut')">Haircut</button>
    <button class="service-cat" onclick="selectCategory('facial')">Facial</button>
    <button class="service-cat" onclick="selectCategory('manicure')">Manicure</button>
    <button class="service-cat" onclick="selectCategory('massage')">Massage</button>
</div>

<div id="serviceDetails"></div>

<?php include "../includes/footer.php"; ?>

<script src="../user/services.js"></script>

</body>
</html>