<?php
include 'db.php';

$stmt = $db->prepare("SELECT id, nom, prenom FROM Adherents ORDER BY id ASC");
$stmt->execute();
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($members);
