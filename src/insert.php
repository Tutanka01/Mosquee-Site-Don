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

$adherentFullName = '';
if ($type_contribution === 'cotisation' && $id_adherent) {
    $sth = $db->prepare("SELECT nom, prenom FROM Adherents WHERE id=?");
    $sth->execute([$id_adherent]);
    $adInfo = $sth->fetch(PDO::FETCH_ASSOC);
    if ($adInfo) {
        $adherentFullName = $adInfo['nom'].' '.$adInfo['prenom'];
    }
}

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

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Contribution Enregistrée</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f4f9;
      margin:0; 
      padding:0;
    }
    .container {
      max-width: 600px;
      margin: 50px auto;
      background: #fff;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h1 {
      text-align: center;
      color: #007BFF;
      margin-top: 0;
    }
    .recap {
      margin: 20px 0;
      font-size: 1.1em;
      line-height: 1.4em;
      background: #f9f9f9;
      border: 1px solid #ddd;
      padding: 15px;
      border-radius: 5px;
    }
    .recap strong {
      color: #333;
    }
    .btn-group {
      text-align: center;
      margin-top: 20px;
    }
    a.button {
      display: inline-block;
      margin: 5px;
      padding: 10px 20px;
      background: #007BFF;
      color: #fff;
      text-decoration: none;
      border-radius: 5px;
      font-weight: bold;
    }
    a.button:hover {
      background: #0056b3;
    }
  </style>
</head>
<body>

<div class="container">
  <h1>Contribution Enregistrée</h1>

  <div class="recap">
    <p>La contribution suivante a été ajoutée avec succès :</p>
    <ul>
      <li><strong>Type :</strong> <?= htmlspecialchars($type_contribution) ?></li>
      <li><strong>Montant :</strong> <?= number_format($montant, 2) ?> €</li>
      <li><strong>Mode de paiement :</strong> <?= htmlspecialchars($type_paiement) ?></li>

      <?php if ($type_contribution === 'cotisation' && $id_adherent): ?>
        <li><strong>Adhérent :</strong>
          <?= htmlspecialchars($adherentFullName ?: "ID #".$id_adherent) ?>
        </li>
      <?php elseif ($type_contribution !== 'cotisation' && !$anonyme): ?>
        <li><strong>Donateur :</strong> 
          <?= htmlspecialchars($nom_donateur . ' ' . $prenom_donateur) ?>
        </li>
      <?php else: ?>
        <li><strong>Donateur :</strong> Anonyme</li>
      <?php endif; ?>

      <li><em>Date d'enregistrement :</em> <?= date('d/m/Y H:i') ?></li>
    </ul>
  </div>

  <div class="btn-group">
    <a class="button" href="index.php">Nouvelle Contribution</a>
    <a class="button" href="dashboard.php">Aller au Tableau de Bord</a>
    <a class="button" href="public_display.php">Voir l'Affichage Public</a>
  </div>
</div>

</body>
</html>
