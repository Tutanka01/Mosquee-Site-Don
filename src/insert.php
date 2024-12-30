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

// On initialise $receiptContributor à "Anonyme" par défaut
$receiptContributor = "Anonyme";

// Si c'est une cotisation ET l'id_adherent est défini
if ($type_contribution === 'cotisation' && $id_adherent) {
    // On récupère les infos de l'adhérent
    $sth = $db->prepare("SELECT nom, prenom FROM Adherents WHERE id=?");
    $sth->execute([$id_adherent]);
    $adInfo = $sth->fetch(PDO::FETCH_ASSOC);

    // Si l'adhérent existe, on utilise son nom et prénom
    if ($adInfo) {
        $receiptContributor = $adInfo['nom'].' '.$adInfo['prenom'];
    } else {
        // Si l'adhérent n'existe pas (ne devrait pas arriver en théorie), on affiche "ID #<id>"
        $receiptContributor = "ID #".$id_adherent;
    }
}
// Si c'est un don ou un projet ET pas anonyme
elseif (($type_contribution === 'don' || $type_contribution === 'projet') && !$anonyme) {
    // Si id_adherent est défini, on récupère les infos de l'adhérent
    if ($id_adherent) {
        $sth = $db->prepare("SELECT nom, prenom FROM Adherents WHERE id=?");
        $sth->execute([$id_adherent]);
        $adInfo = $sth->fetch(PDO::FETCH_ASSOC);

        // Si l'adhérent existe, on utilise son nom et prénom
        if ($adInfo) {
            $receiptContributor = $adInfo['nom'].' '.$adInfo['prenom'];
        } else {
            // Si l'adhérent n'existe pas (ne devrait pas arriver en théorie), on affiche "ID #<id>"
            $receiptContributor = "ID #".$id_adherent;
        }
    } else {
        // Sinon, on utilise les noms et prénoms du donateur
        $receiptContributor = trim(($nom_donateur ?? '').' '.($prenom_donateur ?? ''));
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
    $moisConcernes = "Mois concerné : " . $mois;
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
  <title>Reçu de Contribution - Mosquée Errahma</title>
  <style>
    @page {
      margin: 0;
    }
    body {
      font-family: DejaVu Sans, Arial, sans-serif;
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
      border-image: repeating-linear-gradient(45deg, #1B4F72, #1B4F72 10px, transparent 10px, transparent 20px) 15;
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
    .mosque-icon {
      width: 60px;
      height: 60px;
      margin-bottom: 10px;
    }
    .header h1 {
      margin: 0;
      font-size: 28px;
      color: #1B4F72;
      font-weight: bold;
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
      color: #1B4F72;
      margin-bottom: 20px;
      text-align: center;
      border-bottom: 2px solid #1B4F72;
      padding-bottom: 10px;
    }
    .info-line {
      margin: 12px 0;
      display: flex;
      align-items: center;
    }
    .info-label {
      font-weight: bold;
      width: 180px;
      color: #34495E;
    }
    .info-value {
      flex: 1;
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
      border: 2px solid #1B4F72;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: #1B4F72;
      font-weight: bold;
      transform: rotate(-15deg);
      opacity: 0.8;
    }
  </style>
</head>
<body>
  <div class="border-pattern"></div>
  <div class="content">
    <div class="header">
      <svg class="mosque-icon" viewBox="0 0 100 100">
        <path d="M50 10 L80 40 L80 90 L20 90 L20 40 Z" fill="none" stroke="#1B4F72" stroke-width="3"/>
        <path d="M45 90 L45 70 L55 70 L55 90" fill="none" stroke="#1B4F72" stroke-width="3"/>
        <circle cx="50" cy="30" r="10" fill="none" stroke="#1B4F72" stroke-width="3"/>
      </svg>
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
      <div class="info-line">
        <span class="info-value">$moisConcernes</span>
      </div>
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