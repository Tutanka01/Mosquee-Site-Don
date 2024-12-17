<?php
include 'db.php';

$type_contribution = $_POST['type_contribution'];
$montant = $_POST['montant'];
$type_paiement = $_POST['type_paiement'];
$mois = $_POST['mois'] ?? null;
$jour_paiement = date('Y-m-d');
$heure_paiement = date('H:i:s');

$id_adherent = null;

if ($type_contribution === 'cotisation') {
    $id_adherent = $_POST['id_adherent'];
} elseif (!isset($_POST['don_anonyme'])) {
    $stmt = $db->prepare("INSERT INTO Adherents (nom, prenom, email, anonyme, donateur_temporaire) VALUES (?, ?, ?, 0, 1)");
    $stmt->execute([$_POST['nom'], $_POST['prenom'], $_POST['email']]);
    $id_adherent = $db->lastInsertId();
}

$stmt = $db->prepare("INSERT INTO Contributions (id_adherent, type_contribution, montant, type_paiement, mois, jour_paiement, heure_paiement) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$id_adherent, $type_contribution, $montant, $type_paiement, $mois, $jour_paiement, $heure_paiement]);

echo "Contribution enregistrée avec succès.";
?>
<a href="index.php">Retour</a>
