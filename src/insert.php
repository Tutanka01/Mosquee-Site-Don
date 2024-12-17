<?php
include 'db.php';

$type_contribution = $_POST['type_contribution'];
$montant = (float)$_POST['montant'];
$type_paiement = $_POST['type_paiement'] ?? '';
$mois = $_POST['mois'] ?? null;
$anonyme = isset($_POST['anonyme']) ? 1 : 0;

$id_adherent = !empty($_POST['id_adherent']) ? (int)$_POST['id_adherent'] : null;
$nom_donateur = $_POST['nom_donateur'] ?? null;
$prenom_donateur = $_POST['prenom_donateur'] ?? null;
$email_donateur = $_POST['email_donateur'] ?? null;
$telephone_donateur = $_POST['telephone_donateur'] ?? null;

if ($type_paiement === '') {
    die("Erreur : le type de paiement est obligatoire.");
}
if ($montant <= 0) {
    die("Erreur : le montant doit être supérieur à 0.");
}

$jour_paiement = date('Y-m-d');
$heure_paiement = date('H:i:s');

if ($type_contribution === 'cotisation') {
    if ($anonyme == 1) {
        die("Erreur : une cotisation ne peut pas être anonyme.");
    }
    if (!$id_adherent) {
        die("Erreur : aucun adhérent sélectionné pour la cotisation.");
    }
    $nom_donateur = $prenom_donateur = $email_donateur = $telephone_donateur = null;
} else {
    // don
    if ($anonyme == 1) {
        $id_adherent = null;
        $nom_donateur = $prenom_donateur = $email_donateur = $telephone_donateur = null;
    } else {
        if ($id_adherent) {
            // Don adhérent
            $nom_donateur = $prenom_donateur = $email_donateur = $telephone_donateur = null;
        } else {
            // Don non adhérent
            if (empty($nom_donateur) || empty($prenom_donateur)) {
                die("Erreur : nom et prénom du donateur non-adhérent sont obligatoires.");
            }
        }
    }
}

$stmt = $db->prepare("INSERT INTO Contributions (id_adherent, type_contribution, montant, type_paiement, mois, jour_paiement, heure_paiement, anonyme, nom_donateur, prenom_donateur, email_donateur, telephone_donateur) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$id_adherent, $type_contribution, $montant, $type_paiement, $mois, $jour_paiement, $heure_paiement, $anonyme, $nom_donateur, $prenom_donateur, $email_donateur, $telephone_donateur]);

echo "Contribution enregistrée avec succès.";
?>
<a href="index.php">Retour</a>
