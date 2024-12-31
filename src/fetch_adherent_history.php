<?php
// File: /src/fetch_adherent_history.php

include 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID invalide.']);
    exit;
}

$id = (int)$_GET['id'];

// Récupérer les détails de l'adhérent
$query_adherent = $db->prepare("
    SELECT telephone
    FROM Adherents
    WHERE id = :id
");
$query_adherent->execute([':id' => $id]);
$adherent = $query_adherent->fetch(PDO::FETCH_ASSOC);

if (!$adherent) {
    echo json_encode(['success' => false, 'message' => 'Adhérent non trouvé.']);
    exit;
}

// Récupérer l'historique des contributions
$query_history = $db->prepare("
    SELECT type_contribution, montant, jour_paiement
    FROM Contributions
    WHERE id_adherent = :id
    ORDER BY jour_paiement DESC
");
$query_history->execute([':id' => $id]);
$history = $query_history->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'telephone' => htmlspecialchars($adherent['telephone']),
    'history' => $history
]);
?>
