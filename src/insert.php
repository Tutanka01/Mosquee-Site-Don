<?php
// insert.php

include 'db.php';

// Inclure Dompdf
require_once __DIR__ . '/libs/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// ==================================================================
// RÉCUPÉRATION DES DONNÉES DU FORMULAIRE
// ==================================================================
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

// Pour afficher le nom adhérent s'il existe
$adherentFullName = '';
if ($type_contribution === 'cotisation' && $id_adherent) {
    $sth = $db->prepare("SELECT nom, prenom FROM Adherents WHERE id=?");
    $sth->execute([$id_adherent]);
    $adInfo = $sth->fetch(PDO::FETCH_ASSOC);
    if ($adInfo) {
        $adherentFullName = $adInfo['nom'].' '.$adInfo['prenom'];
    }
}

// Vérifications basiques
$errors = [];

if ($type_paiement === '') {
    $errors[] = "Le type de paiement est obligatoire.";
}
if ($montant <= 0) {
    $errors[] = "Le montant doit être supérieur à 0.";
}

// ==================================================================
// VALIDATION SELON LE TYPE DE CONTRIBUTION
// ==================================================================
$jour_paiement = date('Y-m-d');
$heure_paiement = date('H:i:s');

if ($type_contribution === 'cotisation') {
    if ($anonyme == 1) {
        $errors[] = "Une cotisation ne peut pas être anonyme.";
    }
    if (!$id_adherent) {
        $errors[] = "Aucun adhérent sélectionné pour la cotisation.";
    }
    // Annuller tout champ donateur
    $nom_donateur = $prenom_donateur = $email_donateur = $telephone_donateur = null;
}
elseif ($type_contribution === 'don' || $type_contribution === 'projet') {
    if ($anonyme == 1) {
        // Anonyme => pas d'adhérent
        $id_adherent = null;
        $nom_donateur = $prenom_donateur = $email_donateur = $telephone_donateur = null;
    } else {
        // Pas anonyme => soit adhérent, soit donateur non adhérent
        if ($id_adherent) {
            // Don/Projet adhérent
            $nom_donateur = $prenom_donateur = $email_donateur = $telephone_donateur = null;
        } else {
            // Don/Projet non adhérent => nom & prénom obligatoires
            if (!$nom_donateur || !$prenom_donateur) {
                $errors[] = "Nom et prénom du donateur non-adhérent sont obligatoires.";
            }
        }
    }
}
else {
    $errors[] = "Type de contribution invalide.";
}

// Si des erreurs existent, afficher la page d'erreur
if (!empty($errors)) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Erreur - Contribution</title>
        <link rel="stylesheet" href="styles/style-index-confirmation.css">
        <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
        <style>
            .error-container {
                max-width: 600px;
                margin: 50px auto;
                background: #ffe6e6;
                border: 1px solid #ff4d4d;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                font-family: 'Open Sans', sans-serif;
            }
            .error-container h2 {
                color: #cc0000;
                text-align: center;
                margin-bottom: 20px;
            }
            .error-container ul {
                list-style-type: disc;
                padding-left: 20px;
            }
            .error-container a {
                display: inline-block;
                margin-top: 20px;
                padding: 10px 20px;
                background: #2b6cb0; /* Remplacement de var(--primary-color) */
                color: white;
                text-decoration: none;
                border-radius: 5px; /* Remplacement de var(--border-radius) */
                font-weight: 600;
                transition: background-color 0.3s;
            }
            .error-container a:hover {
                background: #1D4ED8;
            }
        </style>
    </head>
    <body>
        <header>

            <h1>Gestion des Contributions</h1>
        </header>
        <div class="error-container">
            <h2>Erreur lors de l'enregistrement</h2>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="index.php">Retour au formulaire</a>
        </div>
        <footer>
            © 2025 Mosquée Errahma
        </footer>
    </body>
    </html>
    <?php
    exit;
}

// ==================================================================
// 1) INSÉRER LA CONTRIBUTION
// ==================================================================
$stmt = $db->prepare("
    INSERT INTO Contributions 
        (id_adherent, type_contribution, montant, type_paiement, mois, jour_paiement, heure_paiement, anonyme,
         nom_donateur, prenom_donateur, email_donateur, telephone_donateur)
    VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $id_adherent,
    $type_contribution,
    $montant,
    $type_paiement,
    $mois,
    $jour_paiement,
    $heure_paiement,
    $anonyme,
    $nom_donateur,
    $prenom_donateur,
    $email_donateur,
    $telephone_donateur
]);

// ID de la nouvelle contribution
$contribID = $db->lastInsertId();

// ==================================================================
// 2) SI COTISATION => RÉPARTITION DANS COTISATION_MONTHS
// ==================================================================
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
        $current = clone $startDate;
        
        while ($reste > 0) {
            if ($endDate && $current > $endDate) break;
            if ((int)$current->format('Y') > 9999) break;

            $y = (int)$current->format('Y');
            $m = (int)$current->format('m');

            // Chercher la ligne
            $check = $db->prepare("
                SELECT id, paid_amount 
                FROM Cotisation_Months
                WHERE id_adherent=? AND year=? AND month=?
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
                    $up = $db->prepare("UPDATE Cotisation_Months SET paid_amount=? WHERE id=?");
                    $up->execute([$new_paid, $line['id']]);
                }
            } else {
                $paid = 0;
                $need = $monthly_fee - $paid;
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

            $current->modify('+1 month');
        }
    }
}

// ==================================================================
// 3) GÉNÉRATION DU PDF VIA DOMPDF
// ==================================================================

// Préparer les données du reçu
$receiptType        = htmlspecialchars(ucfirst($type_contribution));
$receiptMontant     = number_format($montant, 2).' €';
$receiptDate        = date('d/m/Y H:i');

// Initialiser le contributeur
$receiptContributor = "Anonyme";

// Déterminer le contributeur
if ($type_contribution === 'cotisation' && $id_adherent) {
    if ($adInfo) {
        $receiptContributor = htmlspecialchars($adInfo['nom'].' '.$adInfo['prenom']);
    } else {
        $receiptContributor = "ID #".$id_adherent;
    }
}
elseif (($type_contribution === 'don' || $type_contribution === 'projet') && !$anonyme) {
    if ($id_adherent) {
        if ($adInfo) {
            $receiptContributor = htmlspecialchars($adInfo['nom'].' '.$adInfo['prenom']);
        } else {
            $receiptContributor = "ID #".$id_adherent;
        }
    } else {
        $contribName = trim(($nom_donateur ?? '').' '.($prenom_donateur ?? ''));
        $receiptContributor = htmlspecialchars($contribName ?: "Non-adhérent");
    }
}

// Type de paiement en clair
$typePaiement = "";
switch ($type_paiement) {
    case 'espèces':
        $typePaiement = "Espèces";
        break;
    case 'carte':
        $typePaiement = "Carte bancaire";
        break;
    case 'virement':
        $typePaiement = "Virement bancaire";
        break;
    default:
        $typePaiement = "Inconnu";
}

// Mois concernés (pour cotisation)
$moisConcernes = "";
if ($type_contribution === 'cotisation' && $mois) {
    $dateMois = DateTime::createFromFormat('Y-m', $mois);
    if ($dateMois) {
        $moisConcernes = "Mois concerné : " . $dateMois->format('F Y');
    }
}

// Informations de la mosquée
$mosqueeName     = "Mosquée Errahma - Mont-de-Marsan";
$adresseMosquee  = "262 Av. du Capitaine Michel Lespine, 40000 Mont-de-Marsan";

// HTML pour le PDF
$html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Reçu de Contribution - Mosquée Errahma</title>
  <style>
    @page {
      margin: 0;
    }
    body {
      font-family: 'Open Sans', Arial, sans-serif;
      margin: 0;
      padding: 30px;
      color: #2C3E50;
      background: #fff;
    }
    .border-pattern {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      border: 15px solid transparent;
      border-image: linear-gradient(45deg, #2563EB, #FFD700) 15;
      z-index: -1;
    }
    .content {
      position: relative;
      z-index: 1;
    }
    .header {
      text-align: center;
      margin-bottom: 40px;
      position: relative;
    }
    .header h1 {
      margin: 0;
      font-size: 28px;
      color: #2563EB;
      font-weight: 600;
    }
    .header p {
      margin: 5px 0;
      color: #34495E;
    }
    .section {
      background: #F8F9F9;
      border-radius: 8px;
      padding: 25px;
      margin: 20px 0;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .title {
      font-size: 20px;
      color: #2563EB;
      margin-bottom: 20px;
      text-align: center;
      border-bottom: 2px solid #2563EB;
      padding-bottom: 10px;
    }
    .info-line {
      margin: 12px 0;
      display: flex;
      align-items: center;
    }
    .info-label {
      font-weight: 600;
      width: 180px;
      color: #34495E;
    }
    .info-value {
      flex: 1;
      color: #2C3E50;
    }
    .footer {
      margin-top: 40px;
      text-align: center;
      color: #7F8C8D;
      font-size: 0.9em;
      border-top: 1px solid #BDC3C7;
      padding-top: 20px;
    }
    .stamp {
      position: absolute;
      right: 40px;
      bottom: 40px;
      width: 120px;
      height: 120px;
      border: 2px solid #2563EB;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: #2563EB;
      font-weight: 600;
      transform: rotate(-15deg);
      opacity: 0.8;
    }
  </style>
</head>
<body>
  <div class="border-pattern"></div>
  <div class="content">
    <div class="header">
      <h1>Mosquée Errahma</h1>
      <p>262 Av. du Capitaine Michel Lespine, 40000 Mont-de-Marsan</p>
    </div>

    <div class="section">
      <div class="title">Reçu de Contribution</div>
      <div class="info-line">
        <span class="info-label">Date et heure :</span>
        <span class="info-value">$receiptDate</span>
      </div>
      <div class="info-line">
        <span class="info-label">Type de contribution :</span>
        <span class="info-value">$receiptType</span>
      </div>
      <div class="info-line">
        <span class="info-label">Montant :</span>
        <span class="info-value">$receiptMontant</span>
      </div>
      <div class="info-line">
        <span class="info-label">Contributeur :</span>
        <span class="info-value">$receiptContributor</span>
      </div>
      <div class="info-line">
        <span class="info-label">Type de paiement :</span>
        <span class="info-value">$typePaiement</span>
      </div>
HTML;

// Inclusion conditionnelle du mois concerné
if ($moisConcernes) {
    $html .= <<<HTML
      <div class="info-line">
        <span class="info-label">Mois concerné :</span>
        <span class="info-value">$moisConcernes</span>
      </div>
HTML;
}

$html .= <<<HTML
    </div>

    <div class="footer">
      <p>Qu'Allah vous récompense</p>
      <p>Ce reçu est généré automatiquement par la Mosquée Errahma</p>
    </div>

    <div class="stamp">
      Mosquée<br>Errahma<br>Mont-de-Marsan
    </div>
  </div>
</body>
</html>
HTML;

// ==================================================================
// GÉNÉRATION DU PDF VIA DOMPDF
// ==================================================================

// Configurer Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true); // Pour charger les polices via Google Fonts
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A5', 'portrait');
$dompdf->render();

// Enregistrer le PDF dans /receipts (assurez-vous que ce dossier existe et est writable)
$pdfOutput = $dompdf->output();
$filename  = "receipt_" . time() . ".pdf";

// Définir le chemin complet vers le répertoire receipts
$receiptsDir = __DIR__ . '/receipts/';
if (!is_dir($receiptsDir)) {
    mkdir($receiptsDir, 0755, true); // Créer le répertoire s'il n'existe pas
}

// Enregistrer le fichier PDF
file_put_contents($receiptsDir . $filename, $pdfOutput);

// ==================================================================
// PAGE DE CONFIRMATION HTML
// ==================================================================

// Préparer les détails pour la confirmation
$confirmationType = ucfirst($type_contribution);
$confirmationMontant = number_format($montant, 2) . ' €';
$confirmationPayment = ucfirst($typePaiement);
$confirmationContributor = $receiptContributor;
$confirmationDate = $receiptDate;

// Générer le lien relatif vers le PDF
$pdfLink = "receipts/" . $filename;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contribution Enregistrée</title>
    <link rel="stylesheet" href="styles/style-index-confirmation.css">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 50px auto;
            background: #ffffff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            font-family: 'Open Sans', sans-serif;
            transition: all 0.3s ease-in-out;
        }
        .confirmation-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .confirmation-header h1 {
            color: #2b6cb0; /* Remplacement de var(--primary-color) */
            margin-bottom: 5px;
        }
        .confirmation-header p {
            color: #555;
        }
        .confirmation-details {
            margin-bottom: 30px;
        }
        .confirmation-details h2 {
            color: #2b6cb0; /* Remplacement de var(--primary-color) */
            border-bottom: 2px solid #2b6cb0; /* Remplacement de var(--primary-color) */
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .detail-item {
            margin-bottom: 15px;
        }
        .detail-item span {
            font-weight: 600;
            color: #333;
        }
        .button-group {
            text-align: center;
        }
        .button-group a {
            display: inline-block;
            margin: 10px;
            padding: 12px 25px;
            background: #2b6cb0; /* Remplacement de var(--primary-color) */
            color: white;
            text-decoration: none;
            border-radius: 5px; /* Remplacement de var(--border-radius) */
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .button-group a:hover {
            background: #1D4ED8;
        }
        .button-group .secondary-btn {
            background: #38a169; /* Remplacement de var(--secondary-color) */
        }
        .button-group .secondary-btn:hover {
            background: #2f855a;
        }
        @media (max-width: 600px) {
            .confirmation-container {
                padding: 20px;
            }
            .button-group a {
                width: 100%;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Gestion des Contributions</h1>
    </header>
    <div class="confirmation-container">
        <div class="confirmation-header">
            <h1>Merci pour votre contribution !</h1>
            <p>Votre soutien est précieux pour notre communauté.</p>
        </div>
        <div class="confirmation-details">
            <h2>Détails de la Contribution</h2>
            <div class="detail-item">
                <span>Type de Contribution :</span> <?= htmlspecialchars($confirmationType) ?>
            </div>
            <div class="detail-item">
                <span>Montant :</span> <?= htmlspecialchars($confirmationMontant) ?>
            </div>
            <div class="detail-item">
                <span>Type de Paiement :</span> <?= htmlspecialchars($confirmationPayment) ?>
            </div>
            <div class="detail-item">
                <span>Contributeur :</span> <?= htmlspecialchars($confirmationContributor) ?>
            </div>
            <div class="detail-item">
                <span>Date et Heure :</span> <?= htmlspecialchars($confirmationDate) ?>
            </div>
            <?php if ($moisConcernes): ?>
                <div class="detail-item">
                    <span>Mois Concerné :</span> <?= htmlspecialchars($moisConcernes) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="button-group">
            <a href="index.php" class="primary-btn">Nouvelle Contribution</a>
            <a href="dashboard.php" class="primary-btn">Tableau de Bord</a>
            <a href="public_display.php" class="secondary-btn">Voir l'Affichage Public</a>
            <a href="<?= htmlspecialchars($pdfLink) ?>" target="_blank" class="primary-btn">Télécharger le Reçu PDF</a>
        </div>
    </div>
    <footer>
        © 2024 Mosquée Errahma
    </footer>
</body>
</html>
