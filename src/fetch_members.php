<?php
include 'db.php';

$query = $db->query("SELECT id, nom, prenom FROM Adherents");
$members = $query->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($members);
?>
