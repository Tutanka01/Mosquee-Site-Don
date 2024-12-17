<?php
include 'db.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT nom, prenom, email, telephone FROM Adherents WHERE id=?");
$stmt->execute([$id]);
$adherent = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($adherent ?: []);
