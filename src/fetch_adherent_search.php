<?php
// File: /src/fetch_adherent_search.php
header('Content-Type: application/json');

include 'db.php';

$term = $_GET['term'] ?? '';
$term = trim($term);

try {
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

    echo json_encode($adherents);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la recherche des adhérents.']);
}
?>
