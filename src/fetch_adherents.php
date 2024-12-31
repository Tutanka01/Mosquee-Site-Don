<?php
// File: /src/fetch_adherents.php
header('Content-Type: application/json');

include 'db.php';

// Récupérer le terme de recherche
$term = isset($_GET['term']) ? trim($_GET['term']) : '';

try {
    if ($term !== '') {
        // Recherche par nom, prénom ou email
        $stmt = $db->prepare("
            SELECT id, nom, prenom, email, telephone, monthly_fee, start_date, end_date 
            FROM Adherents 
            WHERE (nom LIKE :term OR prenom LIKE :term OR email LIKE :term)
            ORDER BY nom, prenom
        ");
        $searchTerm = '%' . $term . '%';
        $stmt->bindValue(':term', $searchTerm);
    } else {
        // Récupérer tous les adhérents
        $stmt = $db->prepare("
            SELECT id, nom, prenom, email, telephone, monthly_fee, start_date, end_date 
            FROM Adherents 
            WHERE anonyme = 0
            ORDER BY nom, prenom
        ");
    }

    $stmt->execute();
    $adherents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($adherents);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode([]);
}
?>
