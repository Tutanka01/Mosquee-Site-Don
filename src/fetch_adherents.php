<?php
include 'db.php';

$term = $_GET['term'] ?? '';
$term = trim($term);
if ($term !== '') {
    $like = "%$term%";
    $stmt = $db->prepare("SELECT id, nom, prenom, email, telephone, monthly_fee, start_date, end_date FROM Adherents 
                           WHERE nom LIKE ? OR prenom LIKE ? OR email LIKE ?
                           ORDER BY nom, prenom");
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $db->query("SELECT id, nom, prenom, email, telephone, monthly_fee, start_date, end_date FROM Adherents ORDER BY nom, prenom");
}

$adherents = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($adherents);
