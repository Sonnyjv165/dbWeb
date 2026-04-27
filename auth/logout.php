<?php
session_start();
session_destroy();
header("Location: /dbweb/index.php");
exit();
?>