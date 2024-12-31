<?php
// File: /src/delete_adherent.php
header('Content-Type: application/json');

include 'db.php';

// Récupérer les données envoyées via POST
// Si vous utilisez FormData, utilisez $_POST
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID invalide.']);
    exit;
}

try {
    // Vérifier si l'adhérent a des contributions
    $check = $db->prepare("SELECT COUNT(*) FROM Contributions WHERE id_adherent=?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Impossible de supprimer, cet adhérent a des contributions.']);
        exit;
    }

    // Suppression
    $stmt = $db->prepare("DELETE FROM Adherents WHERE id=?");
    $stmt->execute([$id]);

    // Supprimer les mois de cotisation
    $delMois = $db->prepare("DELETE FROM Cotisation_Months WHERE id_adherent=?");
    $delMois->execute([$id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression de l\'adhérent.']);
}
?>
