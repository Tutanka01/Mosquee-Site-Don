<?php
// File: /src/public_display.php
include 'db.php';

// Définir l'année que l'on veut afficher : ex. l'année en cours
$currentYear = date('Y');

// 1) Récupérer la liste des adhérents
//    On peut filtrer si on veut seulement ceux qui ont start_date <= now et (end_date >= now OR end_date IS NULL).
//    Ici, on prend tout le monde pour l'exemple.
$adherents = $db->query("
    SELECT id, nom, prenom, monthly_fee, start_date, end_date
    FROM Adherents
    ORDER BY nom, prenom
")->fetchAll(PDO::FETCH_ASSOC);

// Pour l'affichage du nom, on fusionne "nom + prenom"
// Mais vous pouvez l'afficher séparé si vous voulez
// On utilisera direct `$ad['nom']` et `$ad['prenom']` ci-dessous.

// 2) Fonctions d'aide : Récupérer la situation d'un adhérent pour un mois donné
function getPaidAmount($db, $idAdherent, $year, $month) {
    // On cherche la ligne dans Cotisation_Months
    $st = $db->prepare("
        SELECT paid_amount
        FROM Cotisation_Months
        WHERE id_adherent = ?
          AND year = ?
          AND month = ?
    ");
    $st->execute([$idAdherent, $year, $month]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (float)$row['paid_amount'];
    }
    return 0.0; // Si aucune ligne => 0€ payé
}

// 3) Préparer le tableau HTML
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Mosquée Errahma - Affichage Public</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0; 
      padding: 0;
      background-color: #f2f2f2;
    }
    .header {
      background: #9bd1ff; 
      text-align: center;
      padding: 20px;
      color: #000; 
    }
    .header h1 {
      margin: 0;
      font-size: 2.2em;
      font-weight: bold;
    }
    .header h2 {
      margin: 5px 0 0 0;
      font-size: 1.4em;
    }
    .container {
      max-width: 1400px;
      margin: 0 auto;
      background: white;
      padding: 15px;
    }
    .table-title {
      text-align: center;
      margin-bottom: 10px;
      font-size: 1.5em;
      font-weight: bold;
      color: #0066aa;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 1.2em;
    }
    thead th {
      background: #9bd1ff;
      color: #000;
      text-align: center;
      padding: 10px;
      border: 1px solid #999;
    }
    tbody td {
      text-align: center;
      padding: 10px;
      border: 1px solid #ccc;
    }
    td.name-col {
      text-align: left;
      padding-left: 15px;
      font-weight: bold;
    }
    th.name-col {
      text-align: left;
    }
    tbody tr:nth-child(even) {
      background: #f9f9f9; 
    }
    .paid {
      background-color: #ccffcc; /* vert clair */
      color: #000;
    }
    .partiel {
      background-color: #fff0b3; /* jaune clair */
      color: #333;
    }
    .nonpaye {
      background-color: #ffcccc; /* rose clair */
      color: #333;
    }
    .legend {
      margin-top: 10px;
      font-size: 0.9em;
    }
    .legend span {
      display: inline-block;
      margin-right: 20px;
      padding: 3px 8px;
      border-radius: 4px;
    }
    .legend .paid-label {
      background-color: #ccffcc;
    }
    .legend .partiel-label {
      background-color: #fff0b3;
    }
    .legend .nonpaye-label {
      background-color: #ffcccc;
    }
  </style>
</head>
<body>
  <!-- En-tête style PDF -->
  <div class="header">
    <h1>مسجد الرحمة  (Mosquée Errahma)</h1>
    <!-- Vous pouvez mettre l'année hijri calculée ou saisie -->
    <h2>LA CHARTE ANNÉE <?= htmlspecialchars($currentYear) ?> - 1446/1445 Hijri</h2>
  </div>

  <div class="container">
    <div class="table-title">Suivi des Cotisations Mensuelles</div>

    <table>
      <thead>
        <tr>
          <th style="width:40px;">N°</th>
          <th class="name-col" style="width:250px;">Nom & Prénom</th>
          <th>Janv</th>
          <th>Févr</th>
          <th>Mars</th>
          <th>Avril</th>
          <th>Mai</th>
          <th>Juin</th>
          <th>Juill</th>
          <th>Août</th>
          <th>Sept</th>
          <th>Oct</th>
          <th>Nov</th>
          <th>Déc</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $num = 1;
        foreach ($adherents as $ad) {   
            // On peut décider si on veut ignorer ceux qui n'ont pas de start_date (?)
            // ou si on veut vérifier que le start_date <= 1er Janv de $currentYear
            // à vous de voir. Ici on affiche tout.

            echo "<tr>";
            echo "<td>{$num}</td>";
            echo "<td class='name-col'>" . htmlspecialchars($ad['nom'] . " " . $ad['prenom']) . "</td>";

            // monthly_fee
            $monthlyFee = (float)$ad['monthly_fee'];

            // Pour chaque mois (1..12)
            for ($m=1; $m <= 12; $m++) {
                $paid = getPaidAmount($db, $ad['id'], $currentYear, $m);

                if ($monthlyFee <= 0) {
                    // Pas de cotisation
                    echo "<td class='nonpaye'>-</td>";
                    continue;
                }

                if ($paid <= 0) {
                    // 0€ => Non payé
                    echo "<td class='nonpaye'>0€</td>";
                } elseif ($paid >= $monthlyFee) {
                    // Complètement payé
                    // On affiche le montant (ex: "15€") ou "OK"
                    echo "<td class='paid'>" . $paid . "€</td>";
                } else {
                    // Partiel => par ex "10/15€"
                    echo "<td class='partiel'>" . $paid . "/" . $monthlyFee . "€</td>";
                }
            }

            echo "</tr>";
            $num++;
        }
        ?>
      </tbody>
    </table>

    <div class="legend">
      <span class="paid-label">Payé</span>
      <span class="partiel-label">Partiel</span>
      <span class="nonpaye-label">Non Payé</span>
    </div>
  </div>
</body>
</html>
