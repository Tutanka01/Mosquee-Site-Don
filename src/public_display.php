<?php
// File: /src/public_display.php

include 'db.php';

// 1) Déterminer l'année grégorienne pour le tableau
$currentYear = date('Y');

// 2) Récupérer la liste des adhérents
$adherents = $db->query("
    SELECT id, nom, prenom, monthly_fee, start_date, end_date
    FROM Adherents
    ORDER BY nom, prenom
")->fetchAll(PDO::FETCH_ASSOC);

// 3) Fonction getPaidAmount identique à avant
function getPaidAmount($db, $idAdherent, $year, $month) {
    $st = $db->prepare("
        SELECT paid_amount
        FROM Cotisation_Months
        WHERE id_adherent = ?
          AND year = ?
          AND month = ?
    ");
    $st->execute([$idAdherent, $year, $month]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (float)$row['paid_amount'] : 0.0;
}

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
  <div class="header">
    <h1>مسجد الرحمة  (Mosquée Errahma)</h1>
    <!-- On affiche l'année en cours (grégorienne), et on a un span pour l'année hijri -->
    <h2>
      LA CHARTE ANNÉE <?= htmlspecialchars($currentYear) ?> - Hijri 
      <span id="hijriYear">1445</span>
    </h2>
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
    // Pour chaque adhérent
    foreach ($adherents as $ad) {
        echo "<tr>";
        echo "<td>{$num}</td>";
        echo "<td class='name-col'>" . htmlspecialchars($ad['nom'] . " " . $ad['prenom']) . "</td>";

        $monthlyFee = (float)$ad['monthly_fee'];
        // Récupération du start_date
        $start = $ad['start_date'] ? new DateTime($ad['start_date']) : null;

        // Boucle sur les 12 mois de l’année $currentYear
        for ($m=1; $m <= 12; $m++) {
            // Construire la date du 1er du mois
            $thisMonth = new DateTime("$currentYear-$m-01");

            // 1) Vérifier si c’est avant la date d’arrivée
            if ($start && $thisMonth < $start) {
                // Pas applicable => N/A
                echo "<td style='background:#ccc'>N/A</td>";
                continue;
            }

            // 2) Ensuite, la logique de paid_amount
            $paid = getPaidAmount($db, $ad['id'], $currentYear, $m);

            if ($monthlyFee <= 0) {
                // L’adhérent n’a pas de monthlyFee => N/A ou “-”
                echo "<td style='background:#ccc'>N/A</td>";
                continue;
            }

            if ($paid <= 0) {
                echo "<td class='nonpaye'>0€</td>";
            } elseif ($paid >= $monthlyFee) {
                echo "<td class='paid'>" . $paid . "€</td>";
            } else {
                // Partiel
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

  <!-- On inclut la librairie Um Al Qura depuis unpkg -->
  <script src="https://unpkg.com/@umalqura/core@0.0.7/dist/umalqura.min.js"></script>
  <script>
    /**
     * ICI : on calcule l'année hijri du "jour de la requête" (= date maintenant).
     * 1) On récupère la date "Aujourd'hui"
     * 2) On regarde l'année hijri "hyNow" via umalqura
     * 3) On compare la date du jour avec la date de 1 Muharram de "hyNow + 1"
     *    => si "today" >= ce 1 Muharram => on est déjà dans la nouvelle année
     *    => sinon on reste dans hyNow
     */

    // 1) Date du jour
    const today = new Date(); // ex. 10 mars 2024

    // 2) On obtient l'année hijri "actuelle" d'après le calcul
    const dHijri = umalqura(today);
    const hijriNow = dHijri.hy; // par ex. 1445

    // On considère la prochaine année hijri
    const nextHijri = hijriNow + 1;

    // 3) Trouver la date (en grégorien) du "1 Muharram" de nextHijri
    const dateOneMuharramNext = umalqura(nextHijri, 1, 1).date;

    let finalHijriYear;
    if (today >= dateOneMuharramNext) {
      // On a déjà franchi 1er Muharram => on est dans nextHijri
      finalHijriYear = nextHijri;
    } else {
      // Sinon on reste dans hijriNow
      finalHijriYear = hijriNow;
    }

    // On affiche dans le span
    document.getElementById('hijriYear').textContent = finalHijriYear;
  </script>
</body>
</html>
