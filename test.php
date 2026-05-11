<?php
$conn = new mysqli("sql206.infinityfree.com", "if0_41880704", "Likeaboss165", "if0_41880704_flightbooking");

// Check case sensitivity setting
$r = $conn->query("SHOW VARIABLES LIKE 'lower_case_table_names'");
$lctn = $r->fetch_assoc();
echo "lower_case_table_names: " . $lctn['Value'] . "\n\n";

// Test exact queries AS WRITTEN in the PHP files (mixed-case table names)
$queries = [
    "FROM user (lowercase)"         => "SELECT COUNT(*) c FROM user WHERE User_Role='user'",
    "FROM flight (lowercase)"       => "SELECT COUNT(*) c FROM flight WHERE Flght_Status='SCHEDULED'",
    "FROM booking (lowercase)"      => "SELECT COUNT(*) c FROM booking WHERE Book_Status='CONFIRMED'",
    "FROM bookingdetails (lower)"   => "SELECT COUNT(*) c FROM bookingdetails",
    "JOIN user u (lowercase)"       => "SELECT bk.Book_ID FROM booking bk JOIN user u ON bk.Book_UserID=u.User_ID LIMIT 1",
];

foreach ($queries as $label => $sql) {
    $r = $conn->query($sql);
    echo "$label: " . ($r ? "OK" : "FAIL - " . $conn->error) . "\n";
}
