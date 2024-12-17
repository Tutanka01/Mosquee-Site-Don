<?php
include 'db.php';

$term = $_GET['term'] ?? '';
$term = '%'.$term.'%';
$stmt = $db->prepare("SELECT id, nom, prenom, email FROM Adherents WHERE nom LIKE ? OR prenom LIKE ? OR email LIKE ? ORDER BY nom, prenom");
$stmt->execute([$term, $term, $term]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($data);
