<?php
// File: /src/fetch_adherent_details.php
header('Content-Type: application/json');

include 'db.php';

// Vérifier si l'ID est fourni via GET
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de l\'adhérent manquant.']);
    exit;
}

$id = intval($_GET['id']);

try {
    // Préparer la requête pour récupérer les détails de l'adhérent
    $stmt = $db->prepare("
        SELECT id, nom, prenom, email, telephone, monthly_fee, start_date, end_date 
        FROM Adherents 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $adherent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($adherent) {
        // Retourner les détails de l'adhérent
        echo json_encode(['success' => true, 'data' => $adherent]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Adhérent non trouvé.']);
    }
} catch (PDOException $e) {
    // En cas d'erreur SQL
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des détails de l\'adhérent.']);
}
?>
