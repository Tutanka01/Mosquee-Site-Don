<?php
include 'db.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT type_contribution, montant, jour_paiement FROM Contributions WHERE id_adherent=? ORDER BY jour_paiement DESC");
$stmt->execute([$id]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($data);
