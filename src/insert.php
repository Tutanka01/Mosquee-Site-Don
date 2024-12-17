<?php
include 'db.php';

$type_contribution = $_POST['type_contribution'];
$montant = (float)$_POST['montant'];
$type_paiement = $_POST['type_paiement'] ?? '';
$mois = $_POST['mois'] ?? null;
$anonyme = isset($_POST['anonyme']) ? 1 : 0;

// Validation serveur
if ($type_paiement === '') {
    die("Erreur : le type de paiement est obligatoire.");
}

if ($montant <= 0) {
    die("Erreur : le montant doit être supérieur à 0.");
}

$jour_paiement = date('Y-m-d');
$heure_paiement = date('H:i:s');

$id_adherent = null;

if ($type_contribution === 'cotisation') {
    // Cotisation : pas d’anonyme, adhérent obligatoire
    if ($anonyme == 1) {
        die("Erreur : une cotisation ne peut pas être anonyme.");
    }
    if (empty($_POST['id_adherent'])) {
        die("Erreur : aucun adhérent sélectionné pour la cotisation.");
    }
    $id_adherent = (int)$_POST['id_adherent'];
} else {
    // don
    if ($anonyme == 1) {
        // Don anonyme
        $id_adherent = null;
    } else {
        // Don non anonyme => adhérent obligatoire
        if (empty($_POST['id_adherent'])) {
            die("Erreur : veuillez sélectionner ou ajouter un adhérent pour un don non anonyme.");
        }
        $id_adherent = (int)$_POST['id_adherent'];
    }
}

$stmt = $db->prepare("INSERT INTO Contributions (id_adherent, type_contribution, montant, type_paiement, mois, jour_paiement, heure_paiement, anonyme) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$id_adherent, $type_contribution, $montant, $type_paiement, $mois, $jour_paiement, $heure_paiement, $anonyme]);

echo "Contribution enregistrée avec succès.";
?>
<a href="index.php">Retour</a>
