<?php
// /src/insert.php

include 'db.php';

// 1) Inclure Dompdf (sans Composer), en tenant compte de votre arborescence
//    On remonte d'un dossier avec ..
//    "libs/dompdf/autoload.inc.php" doit exister
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
if ($type_paiement === '') {
    die("Erreur : le type de paiement est obligatoire.");
}
if ($montant <= 0) {
    die("Erreur : le montant doit être supérieur à 0.");
}

// ==================================================================
// VALIDATION SELON LE TYPE DE CONTRIBUTION
// ==================================================================
$jour_paiement = date('Y-m-d');
$heure_paiement = date('H:i:s');

if ($type_contribution === 'cotisation') {
    if ($anonyme == 1) {
        die("Erreur : une cotisation ne peut pas être anonyme.");
    }
    if (!$id_adherent) {
        die("Erreur : aucun adhérent sélectionné pour la cotisation.");
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
                die("Erreur : nom et prénom du donateur non-adhérent sont obligatoires.");
            }
        }
    }
}
else {
    die("Type de contribution invalide.");
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

// ID de la nouvelle contribution (si vous voulez le réutiliser)
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
// 3) GÉNÉRATION DU PDF VIA DOMPDF (ex: on l’enregistre et on propose un lien)
// ==================================================================

// Préparer les données du reçu
$receiptType        = htmlspecialchars($type_contribution);
$receiptMontant     = number_format($montant, 2).' €';
$receiptDate        = date('d/m/Y H:i');
$receiptContributor = "Anonyme";
if ($type_contribution === 'cotisation' && $id_adherent) {
    $receiptContributor = ($adherentFullName !== '') ? $adherentFullName : ("ID #".$id_adherent);
}
elseif (($type_contribution==='don' || $type_contribution==='projet') && !$anonyme) {
    if ($id_adherent) {
        $receiptContributor = ($adherentFullName !== '') ? $adherentFullName : ("ID #".$id_adherent);
    } else {
        $receiptContributor = trim(($nom_donateur ?? '').' '.($prenom_donateur ?? ''));
    }
}

// On construit un HTML stylé
$mosqueeName     = "Mosquée Errahma - Mont-de-Marsan";
$adresseMosquee  = "262 Av. du Capitaine Michel Lespine, 40000 Mont-de-Marsan";

// HTML
$html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Reçu de Contribution</title>
  <style>
    body {
      font-family: DejaVu Sans, Arial, sans-serif;
      margin: 0; 
      padding: 20px;
      color: #333;
    }
    .header {
      text-align: center;
      border-bottom: 2px solid #000;
      margin-bottom: 20px;
    }
    .header h1 { margin: 0; font-size: 1.3em; }
    .section { margin: 20px 0; line-height: 1.4em; }
    .info-line { margin: 5px 0; }
    .info-label { font-weight: bold; display: inline-block; width: 120px; }
    .footer {
      margin-top: 40px;
      text-align: center;
      font-size: 0.9em;
      color: #555;
      border-top: 1px solid #999;
      padding-top: 10px;
    }
    .title { font-size: 1.2em; margin-bottom: 10px; text-decoration: underline; }
  </style>
</head>
<body>
  <div class="header">
    <h1>$mosqueeName</h1>
    <p>$adresseMosquee</p>
  </div>

  <div class="section">
    <div class="title">Reçu de Contribution</div>
    <div class="info-line">
      <span class="info-label">Date :</span>
      <span>$receiptDate</span>
    </div>
    <div class="info-line">
      <span class="info-label">Type :</span>
      <span>$receiptType</span>
    </div>
    <div class="info-line">
      <span class="info-label">Montant :</span>
      <span>$receiptMontant</span>
    </div>
    <div class="info-line">
      <span class="info-label">Contributeur :</span>
      <span>$receiptContributor</span>
    </div>
  </div>

  <div class="section">
    <p>Merci de votre générosité. Conservez ce reçu pour vos archives.</p>
  </div>

  <div class="footer">
    Ce reçu est généré automatiquement par Mosquée Errahma.
  </div>
</body>
</html>
HTML;

// Configurer Dompdf
$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A5', 'portrait');
$dompdf->render();

// Enregistrer le PDF dans /receipts (assurez-vous que ce dossier existe)
$pdfOutput = $dompdf->output();
$filename  = "receipt_" . time() . ".pdf";
file_put_contents(__DIR__ . '/receipts/' . $filename, $pdfOutput);

// OU si vous voulez forcer direct le téléchargement + exit :
// $dompdf->stream("Recu_MosqueeErrahma.pdf", ["Attachment" => true]);
// exit;

// ==================================================================
// PAGE DE CONFIRMATION HTML
// ==================================================================
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
      <?php elseif (($type_contribution==='don' || $type_contribution==='projet') && !$anonyme): ?>
        <li><strong>Donateur :</strong> 
          <?= htmlspecialchars(trim(($nom_donateur ?? '').' '.($prenom_donateur ?? ''))) ?>
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

    <!-- Lien pour télécharger le PDF -->
    <a class="button" href="../receipts/<?= htmlspecialchars($filename) ?>" target="_blank">
      Télécharger le Reçu PDF
    </a>
  </div>
</div>

</body>
</html>
