<?php
// File: /src/public_display.php

include 'db.php';

$currentYear = date('Y');
$limit = 20; // Adhérents par page (ajustable)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Récupérer le nombre total d'adhérents *pertinents* (non anonymes, avec cotisation, actifs cette année)
try {
    $countStmt = $db->prepare("
        SELECT COUNT(DISTINCT A.id)
        FROM Adherents A
        WHERE A.anonyme = 0  -- Non anonymes
          AND A.monthly_fee > 0  -- Avec une cotisation mensuelle définie
          AND (
              (A.start_date IS NULL OR A.start_date <= :yearEnd)  -- Commencé avant la fin de l'année
              AND (A.end_date IS NULL OR A.end_date >= :yearStart) -- Terminé après le début de l'année
              OR EXISTS (
                  SELECT 1
                  FROM Cotisation_Months CM
                  WHERE CM.id_adherent = A.id
                    AND CM.year = :year  -- Ou avec des cotisations enregistrées pour l'année
              )
          )
    ");
    $countStmt->execute([':yearStart' => $currentYear . '-01-01', ':yearEnd' => $currentYear . '-12-31', ':year' => $currentYear]);
    $totalAdherents = $countStmt->fetchColumn();
} catch (PDOException $e) {
    die("Erreur lors du comptage des adhérents : " . htmlspecialchars($e->getMessage())); // Gestion d'erreur améliorée
}

// Récupérer les adhérents *pertinents* pour la page courante (AVEC FILTRES et PAGINATION)
try {
    $adherents = $db->prepare("
        SELECT DISTINCT A.id, A.nom, A.prenom, A.monthly_fee, A.start_date, A.end_date
        FROM Adherents A
        WHERE A.anonyme = 0
          AND A.monthly_fee > 0
          AND (
              (A.start_date IS NULL OR A.start_date <= :yearEnd)
              AND (A.end_date IS NULL OR A.end_date >= :yearStart)
              OR EXISTS (
                  SELECT 1
                  FROM Cotisation_Months CM
                  WHERE CM.id_adherent = A.id
                    AND CM.year = :year
              )
          )
        ORDER BY A.nom, A.prenom  -- Tri par nom et prénom
        LIMIT :limit OFFSET :offset  -- Pagination
    ");
    $adherents->bindValue(':yearStart', $currentYear . '-01-01');
    $adherents->bindValue(':yearEnd', $currentYear . '-12-31');
    $adherents->bindValue(':year', $currentYear);
    $adherents->bindValue(':limit', $limit, PDO::PARAM_INT);
    $adherents->bindValue(':offset', $offset, PDO::PARAM_INT);
    $adherents->execute();
    $adherents = $adherents->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des adhérents : " . htmlspecialchars($e->getMessage())); // Gestion d'erreur améliorée
}

// Fonction optimisée pour récupérer les cotisations (utilise l'année en paramètre)
function getAllPaidAmounts($db, $idAdherents, $year) {
    if (empty($idAdherents)) { return []; }

    $placeholders = rtrim(str_repeat('?,', count($idAdherents)), ',');
    $query = "
        SELECT id_adherent, month, SUM(paid_amount) as total_paid
        FROM Cotisation_Months
        WHERE id_adherent IN ($placeholders) AND year = ?
        GROUP BY id_adherent, month
    ";

    try {
        $stmt = $db->prepare($query);
        $params = array_merge($idAdherents, [$year]); // Utilisation de l'année
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $paidAmounts = [];
        foreach ($results as $row) {
            $paidAmounts[$row['id_adherent']][$row['month']] = (float)$row['total_paid'];
        }
        return $paidAmounts;
    } catch (PDOException $e) {
        die("Erreur SQL: " . $e->getMessage()); // Meilleure gestion des erreurs
    }
}

// Récupération des cotisations pour les adhérents de la page
$idAdherents = array_column($adherents, 'id');
$paidAmounts = getAllPaidAmounts($db, $idAdherents, $currentYear);

// Mois pour les en-têtes du tableau
$mois_labels = ["Janv", "Févr", "Mars", "Avril", "Mai", "Juin", "Juil", "Août", "Sept", "Oct", "Nov", "Déc"];

// Fonction de formatage des montants (REMISE EN PLACE)
function formatAmount($amount) {
    return ($amount == floor($amount)) ? number_format($amount, 0, ',', ' ') : number_format($amount, 2, ',', ' ');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  <!-- Important pour le responsive -->
    <title>Mosquée Errahma - Affichage Public</title>
    <link rel="stylesheet" href="styles/style-public-display.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Amiri&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="header">
        <h1>مسجد الرحمة (Mosquée Errahma)</h1>
        <h2>LA CHARTE ANNÉE <?= htmlspecialchars($currentYear) ?> - Hijri <span id="hijriYear"></span></h2>
    </div>

    <div class="container">
        <div class="table-title">Suivi des Cotisations Mensuelles</div>

        <?php if (empty($adherents)): ?>
            <p class="no-data">Aucun adhérent trouvé.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th class="name-col">Nom & Prénom</th>
                            <?php foreach ($mois_labels as $mois): ?>
                                <th><?= htmlspecialchars($mois) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $num = $offset + 1; // Numérotation continue
                        foreach ($adherents as $ad): ?>
                            <tr>
                                <td><?= $num++ ?></td>
                                <td class='name-col'><?= htmlspecialchars($ad['nom'] . " " . $ad['prenom']) ?></td>
                                <?php
                                $monthlyFee = (float)$ad['monthly_fee'];
                                $start = $ad['start_date'] ? new DateTime($ad['start_date']) : null;
                                $end = $ad['end_date'] ? new DateTime($ad['end_date']) : null;

                                // Boucle sur les mois
                                for ($m = 1; $m <= 12; $m++) {
                                    $thisMonth = new DateTime("$currentYear-$m-01");

                                    // Vérification des dates (simplifiée)
                                    if (($start && $thisMonth < $start) || ($end && $thisMonth > $end)) {
                                        echo "<td class='na'>N/A</td>";
                                        continue;
                                    }

                                     // Si pas de cotisation mensuelle, N/A
                                     if ($monthlyFee <= 0) {
                                        echo "<td class='na'>N/A</td>";
                                        continue;
                                    }

                                    $paid = $paidAmounts[$ad['id']][$m] ?? 0.0; // Montant payé pour ce mois
                                    $reste = $monthlyFee - $paid;

                                    if ($paid <= 0) {
                                        echo "<td class='nonpaye'>0€</td>";
                                    } elseif ($reste <= 0) {
                                        echo "<td class='paid'>" . formatAmount($paid) . "€</td>";
                                    } else {
                                        echo "<td class='partiel'>" . formatAmount($paid) . "/" . formatAmount($monthlyFee) . "€</td>";
                                    }
                                }
                                ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Légende -->
            <div class="legend">
                <span class="paid-label">Payé</span>
                <span class="partiel-label">Partiel</span>
                <span class="nonpaye-label">Non Payé</span>
                <span class="na-label">N/A</span>
            </div>

            <!-- Pagination -->
            <?php $totalPages = ceil($totalAdherents / $limit);
            if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="pagination-btn">« Précédent</a>
                    <?php endif;
                    // Affichage simplifié (page courante et quelques pages autour)
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <a href="?page=<?= $p ?>" class="pagination-btn <?= ($p == $page) ? 'current-page' : '' ?>"><?= $p ?></a>
                    <?php endfor;
                    if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="pagination-btn">Suivant »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer>© 2024 Mosquée Errahma</footer>

    <!-- Calcul de l'année Hijri (côté client) -->
    <script src="https://unpkg.com/@umalqura/core@0.0.7/dist/umalqura.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            try {
                const today = new Date();
                const hijriDate = umalqura(today);
                const hijriYear = hijriDate.hy;
                const hijriMonth = hijriDate.hm;
                // Simplification: on affiche l'année Hijri directement
                const finalHijriYear = hijriMonth >= 1 && hijriMonth <= 12 ? hijriYear : hijriYear -1;
                document.getElementById('hijriYear').textContent = finalHijriYear;
            } catch (error) {
                console.error('Erreur Hijri:', error); // Gestion d'erreur
                document.getElementById('hijriYear').textContent = 'N/A'; // Valeur par défaut
            }
        });
    </script>
</body>
</html>