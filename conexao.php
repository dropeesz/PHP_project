<?php
$conn = new mysqli("localhost", "root", "", "caixinha");

if ($conn->connect_error) {
    die("Erro: " . $conn->connect_error);
}
?>