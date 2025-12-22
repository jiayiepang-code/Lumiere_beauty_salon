<?php
session_start();
require_once '../config/database.php';

// Fetch staff from database
$database = new Database();
$db = $database->getConnection();

// Fetch staff with their primary services (exclude admin, only show staff role)
// Join with staff_service and service to get primary services
$staffQuery = "SELECT 
    s.staff_email, 
    s.first_name, 
    s.last_name, 
    s.role, 
    s.staff_image, 
    GROUP_CONCAT(DISTINCT sv.service_name ORDER BY ss.proficiency_level DESC, sv.service_name SEPARATOR ' & ') as primary_services,
    GROUP_CONCAT(DISTINCT sv.service_category ORDER BY sv.service_category SEPARATOR ', ') as service_categories
FROM staff s
LEFT JOIN staff_service ss ON s.staff_email = ss.staff_email AND ss.is_active = 1
LEFT JOIN service sv ON ss.service_id = sv.service_id AND sv.is_active = 1
WHERE s.is_active = 1 AND s.role = 'staff'
GROUP BY s.staff_email
ORDER BY s.first_name ASC";

// #region agent log
file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['location' => 'team.php:20', 'message' => 'Fetching staff with services', 'data' => ['query' => $staffQuery], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND);
// #endregion

$staffStmt = $db->prepare($staffQuery);
$staffStmt->execute();
$allStaff = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

// #region agent log
file_put_contents(__DIR__ . '/../.cursor/debug.log', json_encode(['location' => 'team.php:28', 'message' => 'Staff fetched', 'data' => ['count' => count($allStaff), 'sample_staff' => !empty($allStaff) ? ['email' => $allStaff[0]['staff_email'], 'name' => $allStaff[0]['first_name'], 'services' => $allStaff[0]['primary_services'] ?? 'none', 'image' => $allStaff[0]['staff_image'] ?? 'none'] : []], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'A']) . "\n", FILE_APPEND);
// #endregion

// Map staff names to their specific images (based on user's requirements)
$staffImageMap = [
    'Jay' => '../images/42.png',
    'Mei' => '../images/47.png',
    'Ken' => '../images/48.png',
    'Chloe' => '../images/60.png',  // Changed from 62.png to 60.png
    'Sarah' => '../images/65.png',
    'Nisha' => '../images/66.png',
    'Rizal' => '../images/67.png',
    'Siti' => '../images/68.png',
    'Jessica' => '../images/69.png',
    'Yuna' => '../images/71.png'
];

// Map staff names to their specific primary services text (override database values)
$staffPrimaryServicesMap = [
    'Jay' => 'Haircuts & Hair Styling',
    'Mei' => 'Hair Styling & Hair Colouring',
    'Ken' => 'Haircuts & Hair Treatments',
    'Chloe' => 'Anti-Aging & Brightening',
    'Sarah' => 'Deep Cleansing & Hydrating',
    'Nisha' => 'Aromatherapy Massage & Hot Stone Massage',
    'Rizal' => 'Deep Tissue Massage & Traditional Massage',
    'Jessica' => 'Nail Extensions & Nail Gelish',
    'Siti' => 'Classic Manicure & Add-ons',
    'Yuna' => 'Nail Art Design & Gelish'
];

// Group staff by service category for filtering
$staffByRole = [];
foreach ($allStaff as $member) {
    // Use service categories to determine filter class
    $categories = !empty($member['service_categories']) ? explode(', ', $member['service_categories']) : [];
    $filterClass = 'all'; // Default
    
    // Map service categories to filter classes
    foreach ($categories as $cat) {
        $catLower = strtolower(trim($cat));
        if (strpos($catLower, 'hair') !== false) {
            $filterClass = 'hair';
            break;
        } elseif (strpos($catLower, 'beauty') !== false || strpos($catLower, 'facial') !== false || strpos($catLower, 'skin') !== false) {
            $filterClass = 'beauty';
            break;
        } elseif (strpos($catLower, 'massage') !== false) {
            $filterClass = 'massage';
            break;
        } elseif (strpos($catLower, 'nail') !== false || strpos($catLower, 'manicure') !== false || strpos($catLower, 'pedicure') !== false) {
            $filterClass = 'nail';
            break;
        }
    }
    
    // If no category match, try to infer from service names
    if ($filterClass === 'all' && !empty($member['primary_services'])) {
        $services = strtolower($member['primary_services']);
        if (strpos($services, 'hair') !== false || strpos($services, 'cut') !== false || strpos($services, 'styl') !== false || strpos($services, 'colouring') !== false) {
            $filterClass = 'hair';
        } elseif (strpos($services, 'facial') !== false || strpos($services, 'skin') !== false || strpos($services, 'beauty') !== false || strpos($services, 'anti-aging') !== false || strpos($services, 'brightening') !== false || strpos($services, 'cleansing') !== false || strpos($services, 'hydrating') !== false) {
            $filterClass = 'beauty';
        } elseif (strpos($services, 'massage') !== false || strpos($services, 'aromatherapy') !== false || strpos($services, 'hot stone') !== false || strpos($services, 'deep tissue') !== false || strpos($services, 'traditional') !== false) {
            $filterClass = 'massage';
        } elseif (strpos($services, 'nail') !== false || strpos($services, 'manicure') !== false || strpos($services, 'pedicure') !== false || strpos($services, 'gelish') !== false || strpos($services, 'extension') !== false || strpos($services, 'art') !== false) {
            $filterClass = 'nail';
        }
    }
    
    if (!isset($staffByRole[$filterClass])) {
        $staffByRole[$filterClass] = [];
    }
    $staffByRole[$filterClass][] = $member;
}

// Fetch user's favourites (if logged in)
$userFavorites = [];
if (isset($_SESSION['customer_phone'])) {
    try {
        $phone = $_SESSION['customer_phone'];
        $emailQuery = "SELECT customer_email FROM customer WHERE phone = ? LIMIT 1";
        $emailStmt = $db->prepare($emailQuery);
        $emailStmt->execute([$phone]);
        $cust = $emailStmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($cust['customer_email'])) {
            $custEmail = $cust['customer_email'];
            $favQuery = "SELECT staff_email FROM customer_favourites WHERE customer_email = ?";
            $favStmt = $db->prepare($favQuery);
            $favStmt->execute([$custEmail]);
            $favRows = $favStmt->fetchAll(PDO::FETCH_ASSOC);
            $userFavorites = array_map(function($r){ return $r['staff_email']; }, $favRows);
        }
    } catch (Exception $e) {
        // ignore and leave empty
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meet The Team – Lumière Beauty Salon</title>

    <link rel="stylesheet" href="/Lumiere_beauty_salon-main/css/style.css">
    <link rel="stylesheet" href="/Lumiere_beauty_salon-main/css/home.css">
    <link rel="stylesheet" href="/Lumiere_beauty_salon-main/css/header.css">

    <link rel="stylesheet" href="team.css">

    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
</head>

<body>

<div class="page-wrapper">

<?php
// 1. Include the Header (after body tag)
require_once '../includes/header.php';
?>

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
        <?php if (empty($allStaff)): ?>
            <p style="text-align: center; padding: 40px; color: #8f8986;">No staff members available at the moment.</p>
        <?php else: ?>
            <?php foreach ($staffByRole as $filterClass => $staffMembers): 
                foreach ($staffMembers as $member):
                    $staffEmail = $member['staff_email'];
                    $staffName = htmlspecialchars($member['first_name']);
                    $fullName = htmlspecialchars($member['first_name'] . ' ' . $member['last_name']);
                    // Use mapped primary services if available, otherwise use from database, or default message
                    $bio = $staffPrimaryServicesMap[$staffName] ?? htmlspecialchars($member['primary_services'] ?? 'Professional staff member');
                    
                    // Use mapped primary services if available, otherwise use from database
                    $primaryServices = $staffPrimaryServicesMap[$staffName] ?? htmlspecialchars($member['primary_services'] ?? 'Various Services');
                    
                    // Use mapped image if available, otherwise use staff_image from DB, fallback to default
                    $image = $staffImageMap[$staffName] ?? ($member['staff_image'] ?? '../images/42.png');
                    
                    $isFavorite = in_array($staffEmail, $userFavorites);
                    $favButtonText = $isFavorite ? 'Remove from Favourites' : 'Add to Favourites';
            ?>
            <div class="flip-card filterDiv <?= $filterClass ?>">
                <div class="flip-card-inner">
                    <div class="flip-card-front">
                        <img src="<?= htmlspecialchars($image) ?>" alt="<?= $staffName ?>" class="team-photo" onerror="this.src='../images/42.png'">
                        <h3><?= $staffName ?></h3>
                        <p class="primary-service"><strong>Primary Services:</strong> <?= $primaryServices ?></p>
                        <p class="hover-hint">(Hover for details)</p>
                    </div>
                    <div class="flip-card-back">
                        <h3><?= $fullName ?></h3>
                        <p><?= $bio ?></p>
                        <div class="card-actions">
                            <button class="fav-btn <?= $isFavorite ? 'favorited' : '' ?>" data-staff-email="<?= htmlspecialchars($staffEmail, ENT_QUOTES) ?>" onclick="toggleFavorite(this, '<?= htmlspecialchars($staffEmail, ENT_QUOTES) ?>')"><?= $favButtonText ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
if (file_exists('../includes/footer.php')) {
require_once '../includes/footer.php';
}
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
  
  // Update button active states
  var btnContainer = document.getElementById("myBtnContainer");
  if (btnContainer) {
    var btns = btnContainer.getElementsByClassName("btn");
    // Remove active class from all buttons first
    for (i = 0; i < btns.length; i++) {
      w3RemoveClass(btns[i], "active");
    }
    
    // Add active class to the correct button
    if (c == "" || c == "all") {
      // "All" button is first (index 0)
      if (btns[0]) w3AddClass(btns[0], "active");
    } else if (c == "hair") {
      // "Hair Stylists" button is second (index 1)
      if (btns[1]) w3AddClass(btns[1], "active");
    } else if (c == "beauty") {
      // "Beauticians" button is third (index 2)
      if (btns[2]) w3AddClass(btns[2], "active");
    } else if (c == "massage") {
      // "Massage Therapists" button is fourth (index 3)
      if (btns[3]) w3AddClass(btns[3], "active");
    } else if (c == "nail") {
      // "Nail Technicians" button is fifth (index 4)
      if (btns[4]) w3AddClass(btns[4], "active");
    }
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

// Button Click Logic - buttons already have onclick handlers that call filterSelection
// The filterSelection function now handles button state updates

// Toggle Favorite Function
function toggleFavorite(button, staffEmail) {
    if (!staffEmail) {
        staffEmail = button.getAttribute('data-staff-email');
    }
    
    if (!staffEmail) {
        alert('Staff information not available');
        return;
    }
    
    const isFavorited = button.classList.contains('favorited');
    const url = isFavorited ? '../remove_favorite.php' : '../add_favorite.php';
    const action = isFavorited ? 'remove' : 'add';
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ staff_email: staffEmail })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (isFavorited) {
                button.classList.remove('favorited');
                button.textContent = 'Add to Favourites';
            } else {
                button.classList.add('favorited');
                button.textContent = 'Remove from Favourites';
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error updating favorites');
    });
}
</script>

</body>
</html>