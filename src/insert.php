<?php
// FICHIER: insert.php
include 'db.php';

$type_contribution = $_POST['type_contribution'];
$montant = (float)$_POST['montant'];
$type_paiement = $_POST['type_paiement'] ?? '';
$mois = $_POST['mois'] ?? null;  // ex. "2024-03" si l'utilisateur a choisi un mois indicatif
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

// Vérifications pour cotisation/don/projet (inchangé) ...
if ($type_contribution === 'cotisation') {
    if ($anonyme == 1) {
        die("Erreur : une cotisation ne peut pas être anonyme.");
    }
    if (!$id_adherent) {
        die("Erreur : aucun adhérent sélectionné pour la cotisation.");
    }
    $nom_donateur = $prenom_donateur = $email_donateur = $telephone_donateur = null;
} else if ($type_contribution === 'don' || $type_contribution === 'projet') {
    if ($anonyme == 1) {
        $id_adherent = null;
        $nom_donateur = $prenom_donateur = $email_donateur = $telephone_donateur = null;
    } else {
        if ($id_adherent) {
            // Don/Projet adhérent => rien de plus
            $nom_donateur = $prenom_donateur = $email_donateur = $telephone_donateur = null;
        } else {
            // Don/Projet non-adhérent => vérifier nom & prénom
            if (empty($nom_donateur) || empty($prenom_donateur)) {
                die("Erreur : nom et prénom du donateur/contributeur non-adhérent obligatoires.");
            }
        }
    }
} else {
    die("Type de contribution invalide.");
}

// 1) Insertion de la contribution dans la table "Contributions"
$stmt = $db->prepare("
    INSERT INTO Contributions 
    (id_adherent, type_contribution, montant, type_paiement, mois, jour_paiement, heure_paiement, anonyme, 
     nom_donateur, prenom_donateur, email_donateur, telephone_donateur) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $id_adherent, $type_contribution, $montant, $type_paiement, $mois,
    $jour_paiement, $heure_paiement, $anonyme, 
    $nom_donateur, $prenom_donateur, $email_donateur, $telephone_donateur
]);

// 2) Répartition si c'est une cotisation
if ($type_contribution === 'cotisation' && $id_adherent) {
    // Récupérer monthly_fee
    $st = $db->prepare("SELECT monthly_fee, start_date, end_date FROM Adherents WHERE id=?");
    $st->execute([$id_adherent]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $monthly_fee = (float)$row['monthly_fee'];
    $startDate = $row['start_date'] ? new DateTime($row['start_date']) : null;
    $endDate   = $row['end_date']   ? new DateTime($row['end_date']) : null;

    if ($monthly_fee > 0) {
        $reste = $montant;

        // On va chercher les mois impayés dans l'ordre (depuis le start_date de l'adhérent, jusqu'au end_date ou l'année courante)
        // Dans cet exemple, on part du year= startDate->format('Y'), month= startDate->format('m') ... 
        // On parcourt mois par mois, on check s'il y a déjà un enregistrement en DB, etc.

        // Pour faire simple : on boucle du startDate jusqu'à endDate (ou date courante) en incrémentant de +1 month.
        // On s'arrête si $reste = 0.
        // On ignore tout si l'adhérent n'a pas de start_date (à adapter selon votre cas).
        
        if ($startDate) {
            $current = clone $startDate; 
            // on fixe une limite (par ex. +5 ans)
            $maxDate = $endDate ?? (new DateTime())->modify('+1 year'); 
            
            while ($reste > 0 && $current <= $maxDate) {
                // year & month
                $y = (int)$current->format('Y');
                $m = (int)$current->format('m');

                // Vérifier si l'adhérent est toujours actif (si endDate existe et qu'on dépasse)
                // => déjà géré par $current <= $maxDate
                
                // Récupérer la ligne existante (si elle existe)
                $check = $db->prepare("
                    SELECT id, paid_amount 
                    FROM Cotisation_Months 
                    WHERE id_adherent=? AND year=? AND month=?
                ");
                $check->execute([$id_adherent, $y, $m]);
                $line = $check->fetch(PDO::FETCH_ASSOC);
                
                if ($line) {
                    // On a déjà un enregistrement => vérifier si c'est soldé ?
                    $paid = (float)$line['paid_amount'];
                    if ($paid < $monthly_fee) {
                        $need = $monthly_fee - $paid;
                        if ($reste >= $need) {
                            $new_paid = $paid + $need;  // = monthly_fee (soldé)
                            $reste -= $need;
                        } else {
                            $new_paid = $paid + $reste;
                            $reste = 0;
                        }
                        // Mise à jour
                        $up = $db->prepare("UPDATE Cotisation_Months SET paid_amount=? WHERE id=?");
                        $up->execute([$new_paid, $line['id']]);
                    }
                } else {
                    // Pas encore de ligne => on la crée avec paid_amount=0
                    $paid = 0;
                    $need = $monthly_fee - $paid;
                    if ($reste >= $need) {
                        $new_paid = $paid + $need; // = monthly_fee
                        $reste -= $need;
                    } else {
                        $new_paid = $paid + $reste;
                        $reste = 0;
                    }
                    $ins = $db->prepare("
                        INSERT INTO Cotisation_Months (id_adherent, year, month, paid_amount)
                        VALUES (?, ?, ?, ?)
                    ");
                    $ins->execute([$id_adherent, $y, $m, $new_paid]);
                }

                // Passage au mois suivant
                $current->modify('+1 month');
            }
        }
    }
}

echo "Contribution enregistrée avec succès.";
?>
<a href="index.php">Retour</a>
