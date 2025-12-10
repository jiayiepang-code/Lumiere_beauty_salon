<?php
session_start();
// 1. Include the Header
require_once '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meet The Team – Lumière Beauty Salon</title>

    <link rel="stylesheet" href="/Lumiere_beauty_salon/css/style.css">
    <link rel="stylesheet" href="/Lumiere_beauty_salon/css/home.css">

    <link rel="stylesheet" href="team.css">

    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
</head>

<body>

<div class="page-wrapper">

    <div class="team-header-section">
        <h1>Our Team</h1>
        <div id="myBtnContainer" class="filter-buttons">
            <button class="btn active" onclick="filterSelection('all')">All</button>
            <button class="btn" onclick="filterSelection('hair')">Hair Stylists</button>
            <button class="btn" onclick="filterSelection('beauty')">Beauticians</button>
            <button class="btn" onclick="filterSelection('massage')">Massage Therapists</button>
            <button class="btn" onclick="filterSelection('nail')">Nail Technicians</button>
        </div>
    </div>

    <div class="team-container">

        <div class="flip-card filterDiv hair">
            <div class="flip-card-inner">
                <div class="flip-card-front">
                    <img src="../images/42.png" alt="Jay" class="team-photo">
                    <h3>Jay</h3>
                    <p class="primary-service">Primary: Haircuts & Styling</p>
                    <p class="hover-hint">(Hover for details)</p>
                </div>
                <div class="flip-card-back">
                    <h3>Jay</h3>
                    <p>Focuses on textured cuts and formal styling. The expert for modern shapes and long-lasting event styles.</p>
                    <button class="fav-btn">Add to Favourites</button>
                </div>
            </div>
        </div>

        <div class="flip-card filterDiv hair">
            <div class="flip-card-inner">
                <div class="flip-card-front">
                    <img src="../images/47.png" alt="Mei" class="team-photo">
                    <h3>Mei</h3>
                    <p class="primary-service">Primary: Styling & Colouring</p>
                    <p class="hover-hint">(Hover for details)</p>
                </div>
                <div class="flip-card-back">
                    <h3>Mei</h3>
                    <p>Focuses on formal styling and vibrant fashion coloring.</p>
                    <button class="fav-btn">Add to Favourites</button>
                </div>
            </div>
        </div>

        <div class="flip-card filterDiv hair">
            <div class="flip-card-inner">
                <div class="flip-card-front">
                    <img src="../images/48.png" alt="Ken" class="team-photo">
                    <h3>Ken</h3>
                    <p class="primary-service">Primary: Technical Cuts & Treatments</p>
                    <p class="hover-hint">(Hover for details)</p>
                </div>
                <div class="flip-card-back">
                    <h3>Ken</h3>
                    <p>Specializes in precision haircuts and hair treatments. Expert in classic shapes and hair health restoration.</p>
                    <button class="fav-btn">Add to Favourites</button>
                </div>
            </div>
        </div>

        <div class="flip-card filterDiv beauty">
            <div class="flip-card-inner">
                <div class="flip-card-front">
                    <img src="../images/60.png" alt="Chloe" class="team-photo">
                    <h3>Chloe</h3>
                    <p class="primary-service">Primary: Anti-Aging & Brightening</p>
                    <p class="hover-hint">(Hover for details)</p>
                </div>
                <div class="flip-card-back">
                    <h3>Chloe</h3>
                    <p>Senior Aesthetician. Combines advanced lifting methods with brightening peels for mature skin types.</p>
                    <button class="fav-btn">Add to Favourites</button>
                </div>
            </div>
        </div>

        <div class="flip-card filterDiv beauty">
            <div class="flip-card-inner">
                <div class="flip-card-front">
                    <img src="../images/65.png" alt="Sarah" class="team-photo">
                    <h3>Sarah</h3>
                    <p class="primary-service">Primary: Deep Cleansing & Hydrating</p>
                    <p class="hover-hint">(Hover for details)</p>
                </div>
                <div class="flip-card-back">
                    <h3>Sarah</h3>
                    <p>Skin Balance Specialist. Focuses on maintaining healthy skin barriers without drying out the skin.</p>
                    <button class="fav-btn">Add to Favourites</button>
                </div>
            </div>
        </div>

        <div class="flip-card filterDiv massage">
            <div class="flip-card-inner">
                <div class="flip-card-front">
                    <img src="../images/66.png" alt="Nisha" class="team-photo">
                    <h3>Nisha</h3>
                    <p class="primary-service">Primary: Aromatherapy & Hot Stone</p>
                    <p class="hover-hint">(Hover for details)</p>
                </div>
                <div class="flip-card-back">
                    <h3>Nisha</h3>
                    <p>Specializes in relaxation and stress relief using essential oils to calm the nervous system.</p>
                    <button class="fav-btn">Add to Favourites</button>
                </div>
            </div>
        </div>

        <div class="flip-card filterDiv massage">
            <div class="flip-card-inner">
                <div class="flip-card-front">
                    <img src="../images/67.png" alt="Rizal" class="team-photo">
                    <h3>Rizal</h3>
                    <p class="primary-service">Primary: Deep Tissue & Traditional</p>
                    <p class="hover-hint">(Hover for details)</p>
                </div>
                <div class="flip-card-back">
                    <h3>Rizal</h3>
                    <p>Focuses on muscle recovery and pain relief. Expert for deep pressure techniques targeting knots.</p>
                    <button class="fav-btn">Add to Favourites</button>
                </div>
            </div>
        </div>

        <div class="flip-card filterDiv nail">
            <div class="flip-card-inner">
                <div class="flip-card-front">
                    <img src="../images/71.png" alt="Yuna" class="team-photo">
                    <h3>Yuna</h3>
                    <p class="primary-service">Primary: Nail Art & Gelish</p>
                    <p class="hover-hint">(Hover for details)</p>
                </div>
                <div class="flip-card-back">
                    <h3>Yuna</h3>
                    <p>The Creative Artist. Specializes in intricate designs, from 3D Art to Chrome and Cat Eyes.</p>
                    <button class="fav-btn">Add to Favourites</button>
                </div>
            </div>
        </div>

        <div class="flip-card filterDiv nail">
            <div class="flip-card-inner">
                <div class="flip-card-front">
                    <img src="../images/69.png" alt="Jessica" class="team-photo">
                    <h3>Jessica</h3>
                    <p class="primary-service">Primary: Extensions & Gelish</p>
                    <p class="hover-hint">(Hover for details)</p>
                </div>
                <div class="flip-card-back">
                    <h3>Jessica</h3>
                    <p>The Structure Specialist. She focuses on Acrylic and Tip extensions, ensuring perfect shaping.</p>
                    <button class="fav-btn">Add to Favourites</button>
                </div>
            </div>
        </div>

        <div class="flip-card filterDiv nail">
            <div class="flip-card-inner">
                <div class="flip-card-front">
                    <img src="../images/68.png" alt="Siti" class="team-photo">
                    <h3>Siti</h3>
                    <p class="primary-service">Primary: Classic Manicure</p>
                    <p class="hover-hint">(Hover for details)</p>
                </div>
                <div class="flip-card-back">
                    <h3>Siti</h3>
                    <p>Natural Nail Health Specialist. Focuses on detailed cuticle care and relaxation.</p>
                    <button class="fav-btn">Add to Favourites</button>
                </div>
            </div>
        </div>

    </div> </div> <?php
require_once '../includes/footer.php';
?>

<script>
// 1. READ URL PARAMETER FROM HEADER NAVIGATION
const urlParams = new URLSearchParams(window.location.search);
const category = urlParams.get('cat');

// 2. Initialize Filter
if (category) {
    filterSelection(category);
    // Auto-highlight the correct button
    setTimeout(function() {
        var btnContainer = document.getElementById("myBtnContainer");
        var btns = btnContainer.getElementsByClassName("btn");
        for (var i = 0; i < btns.length; i++) {
            btns[i].className = btns[i].className.replace(" active", "");
        }
        if(category == 'hair') btns[1].className += " active";
        if(category == 'beauty') btns[2].className += " active";
        if(category == 'massage') btns[3].className += " active";
        if(category == 'nail') btns[4].className += " active";
    }, 100);
} else {
    filterSelection("all");
}

function filterSelection(c) {
  var x, i;
  x = document.getElementsByClassName("filterDiv");
  if (c == "all") c = "";
  for (i = 0; i < x.length; i++) {
    w3RemoveClass(x[i], "show");
    if (x[i].className.indexOf(c) > -1) w3AddClass(x[i], "show");
  }
}

function w3AddClass(element, name) {
  var i, arr1, arr2;
  arr1 = element.className.split(" ");
  arr2 = name.split(" ");
  for (i = 0; i < arr2.length; i++) {
    if (arr1.indexOf(arr2[i]) == -1) {element.className += " " + arr2[i];}
  }
}

function w3RemoveClass(element, name) {
  var i, arr1, arr2;
  arr1 = element.className.split(" ");
  arr2 = name.split(" ");
  for (i = 0; i < arr2.length; i++) {
    while (arr1.indexOf(arr2[i]) > -1) {
      arr1.splice(arr1.indexOf(arr2[i]), 1);     
    }
  }
  element.className = arr1.join(" ");
}

// Button Click Logic
var btnContainer = document.getElementById("myBtnContainer");
var btns = btnContainer.getElementsByClassName("btn");
for (var i = 0; i < btns.length; i++) {
  btns[i].addEventListener("click", function(){
    var current = document.getElementsByClassName("active");
    current[0].className = current[0].className.replace(" active", "");
    this.className += " active";
  });
}
</script>

</body>
</html>