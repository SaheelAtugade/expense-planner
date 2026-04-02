<?php
$serverName = "localhost";
$userName = "root";
$password = "";
$databaseName = "expense_planner";
$port = 3307;

$conn = mysqli_connect($serverName, $userName, $password, $databaseName, $port);

if (!$conn) {
    die("Database connection failed");
}
