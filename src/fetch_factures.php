<?php
include 'db.php';

// Récupérer les factures
$stmt = $db->query("
    SELECT 
        C.id AS id_facture,
        CASE 
            WHEN C.anonyme = 1 THEN 'Anonyme'
            WHEN A.nom IS NOT NULL THEN A.nom || ' ' || A.prenom
            ELSE C.nom_donateur || ' ' || C.prenom_donateur
        END AS nom_contributeur,
        C.type_contribution,
        C.montant,
        C.jour_paiement AS date,
        C.receipt_path AS path
    FROM Contributions C
    LEFT JOIN Adherents A ON C.id_adherent = A.id
    WHERE C.receipt_path IS NOT NULL
    ORDER BY C.jour_paiement DESC
");

$factures = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($factures);
?>
