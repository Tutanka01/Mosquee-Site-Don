<?php
// /src/insert.php
include 'db.php';

$type_contribution = $_POST['type_contribution'] ?? '';
$montant = (float)($_POST['montant'] ?? 0);
$type_paiement = $_POST['type_paiement'] ?? '';
$mois = $_POST['mois'] ?? null;  // facultatif
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

// Préparation journalière
$jour_paiement = date('Y-m-d');
$heure_paiement = date('H:i:s');

if ($type_contribution === 'cotisation') {
    if ($anonyme == 1) {
        die("Erreur : une cotisation ne peut pas être anonyme.");
    }
    if (!$id_adherent) {
        die("Erreur : aucun adhérent sélectionné pour la cotisation.");
    }
    // On annule tout champ donateur
    $nom_donateur = $prenom_donateur = $email_donateur = $telephone_donateur = null;
} elseif ($type_contribution === 'don' || $type_contribution === 'projet') {
    if ($anonyme == 1) {
        // anonyme => pas d'adhérent
        $id_adherent = null;
        $nom_donateur = $prenom_donateur = $email_donateur = $telephone_donateur = null;
    } else {
        // pas anonyme => soit adhérent, soit donateur non adhérent
        if ($id_adherent) {
            // Don/projet adhérent
            $nom_donateur = $prenom_donateur = $email_donateur = $telephone_donateur = null;
        } else {
            // Don/projet non-adhérent => nom & prénom obligatoires
            if (!$nom_donateur || !$prenom_donateur) {
                die("Erreur : nom et prénom du donateur non-adhérent sont obligatoires.");
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
    VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $id_adherent, $type_contribution, $montant, $type_paiement, $mois,
    $jour_paiement, $heure_paiement, $anonyme,
    $nom_donateur, $prenom_donateur, $email_donateur, $telephone_donateur
]);

// 2) Si cotisation, on répartit le montant sur les mois non soldés
if ($type_contribution === 'cotisation' && $id_adherent) {
    // Récupérer monthly_fee, start_date, end_date
    $st = $db->prepare("SELECT monthly_fee, start_date, end_date FROM Adherents WHERE id=?");
    $st->execute([$id_adherent]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $monthly_fee = (float)$row['monthly_fee'];
    $startDate   = $row['start_date'] ? new DateTime($row['start_date']) : null;
    $endDate     = $row['end_date']   ? new DateTime($row['end_date']) : null;

    // On ne fait la répartition que si monthly_fee > 0 et qu’il y a un start_date
    if ($monthly_fee > 0 && $startDate) {
        $reste = $montant;

        // On démarre depuis startDate
        $current = clone $startDate;

        // Tant qu’il reste de l’argent à répartir, on avance mois par mois
        while ($reste > 0) {
            // Vérifier si on a une end_date ET qu’on a dépassé end_date => on arrête
            if ($endDate && $current > $endDate) {
                // L’adhérent n’est plus actif après end_date
                break;
            }

            // Sécurité éventuelle pour éviter la boucle infinie 
            // si end_date est NULL (adhésion "à vie") :
            // on peut par exemple dire qu’on s’arrête si on dépasse l’an 9999 
            // (au cas où quelqu’un a versé un montant faramineux qui couvrirait 8000 ans).
            if ((int)$current->format('Y') > 9999) {
                break;
            }

            $y = (int)$current->format('Y');
            $m = (int)$current->format('m');

            // Récupérer la ligne Cotisation_Months correspondante
            $check = $db->prepare("
                SELECT id, paid_amount 
                FROM Cotisation_Months
                WHERE id_adherent = ? AND year = ? AND month = ?
            ");
            $check->execute([$id_adherent, $y, $m]);
            $line = $check->fetch(PDO::FETCH_ASSOC);

            if ($line) {
                $paid = (float)$line['paid_amount'];
                if ($paid < $monthly_fee) {
                    $need = $monthly_fee - $paid;
                    if ($reste >= $need) {
                        $new_paid = $paid + $need;
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
                // Créer la ligne
                $paid = 0;
                $need = $monthly_fee - $paid;
                $new_paid = 0;
                if ($reste >= $need) {
                    $new_paid = $need; 
                    $reste -= $need;
                } else {
                    $new_paid = $reste;
                    $reste = 0;
                }
                $ins = $db->prepare("
                    INSERT INTO Cotisation_Months (id_adherent, year, month, paid_amount)
                    VALUES (?, ?, ?, ?)
                ");
                $ins->execute([$id_adherent, $y, $m, $new_paid]);
            }

            // Mois suivant
            $current->modify('+1 month');
        }
    }
}

echo "Contribution enregistrée avec succès.";
?>
<a href="index.php">Retour</a>
