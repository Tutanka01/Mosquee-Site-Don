<?php
// /src/fetch_receipts.php

include 'db.php';

$receipts = [];
$receiptsDir = __DIR__ . '/receipts/';
if (is_dir($receiptsDir)) {
    $files = scandir($receiptsDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'pdf') {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $timestamp = str_replace('receipt_', '', $filename); // Supposant que le format est "receipt_timestamp"
            $receiptDate = date('Y-m-d H:i:s', $timestamp);

            // On recherche la contribution associée
            $stmt = $db->prepare("SELECT * FROM Contributions WHERE strftime('%s', jour_paiement || ' ' || heure_paiement) = ?");
            $stmt->execute([$timestamp]);
            $contribution = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($contribution) {
                $receipts[] = [
                    'filename' => $file,
                    'date' => $receiptDate,
                    'type' => $contribution['type_contribution'],
                    'contributor' => getContributorName($db, $contribution),
                    'amount' => $contribution['montant'],
                ];
            } else {
                // Si on ne trouve pas la contribution, on utilise des valeurs par défaut
                $receipts[] = [
                    'filename' => $file,
                    'date' => $receiptDate,
                    'type' => 'Inconnu',
                    'contributor' => 'Inconnu',
                    'amount' => '0',
                ];
            }
        }
    }
}

function getContributorName($db, $contribution) {
    if ($contribution['anonyme']) {
        return 'Anonyme';
    }
    if ($contribution['id_adherent']) {
        $stmt = $db->prepare("SELECT nom, prenom FROM Adherents WHERE id = ?");
        $stmt->execute([$contribution['id_adherent']]);
        $adherent = $stmt->fetch(PDO::FETCH_ASSOC);
        return $adherent ? $adherent['nom'] . ' ' . $adherent['prenom'] : 'ID #' . $contribution['id_adherent'];
    }
    return trim($contribution['nom_donateur'] . ' ' . $contribution['prenom_donateur']);
}

header('Content-Type: application/json');
echo json_encode($receipts);