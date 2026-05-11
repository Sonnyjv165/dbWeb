<?php
$conn = new mysqli("sql206.infinityfree.com", "if0_41880704", "Likeaboss165", "if0_41880704_flightbooking");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
