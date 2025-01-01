<?php
// File: /src/public_display.php

// Activer le reporting d'erreurs pour le développement (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db.php';

// 1) Déterminer l'année grégorienne pour le tableau
$currentYear = date('Y');

// 2) Définir la pagination
$limit = 20; // Nombre d'adhérents par page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// 3) Récupérer le nombre total d'adhérents
try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM Adherents");
    $countStmt->execute();
    $totalAdherents = $countStmt->fetchColumn();
} catch (PDOException $e) {
    die("Erreur lors du comptage des adhérents : " . htmlspecialchars($e->getMessage()));
}

// 4) Récupérer la liste des adhérents avec leur cotisation mensuelle pour la page courante
try {
    $adherents = $db->prepare("
        SELECT id, nom, prenom, monthly_fee, start_date, end_date
        FROM Adherents
        ORDER BY nom, prenom
        LIMIT :limit OFFSET :offset
    ");
    $adherents->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $adherents->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $adherents->execute();
    $adherents = $adherents->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des adhérents : " . htmlspecialchars($e->getMessage()));
}

// 5) Fonction optimisée pour récupérer les cotisations de tous les adhérents en une seule requête
function getAllPaidAmounts($db, $idAdherents, $year) {
    if (empty($idAdherents)) {
        return [];
    }

    // Préparer les paramètres pour la requête IN
    $placeholders = rtrim(str_repeat('?,', count($idAdherents)), ',');
    $query = "
        SELECT id_adherent, month, SUM(paid_amount) as total_paid
        FROM Cotisation_Months
        WHERE id_adherent IN ($placeholders)
          AND year = ?
        GROUP BY id_adherent, month
    ";

    try {
        $stmt = $db->prepare($query);
        $params = array_merge($idAdherents, [$year]);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erreur lors de la récupération des cotisations : " . htmlspecialchars($e->getMessage()));
    }

    // Organiser les résultats dans un tableau multidimensionnel
    $paidAmounts = [];
    foreach ($results as $row) {
        $paidAmounts[$row['id_adherent']][$row['month']] = (float)$row['total_paid'];
    }

    return $paidAmounts;
}

// Récupérer tous les ID des adhérents de la page courante
$idAdherents = array_column($adherents, 'id');

// Obtenir toutes les cotisations payées pour l'année en cours
$paidAmounts = getAllPaidAmounts($db, $idAdherents, $currentYear);

// 6) Calcul de l'année hijri côté client

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mosquée Errahma - Affichage Public</title>
    <link rel="stylesheet" href="styles/style-public-display.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Amiri&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="header">
        <h1>مسجد الرحمة (Mosquée Errahma)</h1>
        <!-- Affichage de l'année grégorienne et hijri -->
        <h2>
            LA CHARTE ANNÉE <?= htmlspecialchars($currentYear) ?> - Hijri 
            <span id="hijriYear">1445</span>
        </h2>
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
                        $num = $offset + 1;
                        // Pour chaque adhérent
                        foreach ($adherents as $ad) {
                            echo "<tr>";
                            echo "<td>{$num}</td>";
                            echo "<td class='name-col'>" . htmlspecialchars($ad['nom'] . " " . $ad['prenom']) . "</td>";

                            $monthlyFee = (float)$ad['monthly_fee'];
                            // Récupération du start_date et end_date
                            $start = $ad['start_date'] ? new DateTime($ad['start_date']) : null;
                            $end = $ad['end_date'] ? new DateTime($ad['end_date']) : null;

                            // Boucle sur les 12 mois de l’année $currentYear
                            for ($m=1; $m <= 12; $m++) {
                                // Construire la date du 1er du mois
                                $thisMonth = new DateTime("$currentYear-$m-01");

                                // 1) Vérifier si c’est avant la date d’arrivée ou après la date de fin
                                if ($start && $thisMonth < $start) {
                                    // Pas applicable => N/A
                                    echo "<td class='na'>N/A</td>";
                                    continue;
                                }
                                if ($end && $thisMonth > $end) {
                                    // Après la date de fin d'adhésion => N/A
                                    echo "<td class='na'>N/A</td>";
                                    continue;
                                }

                                // 2) Ensuite, la logique de paid_amount
                                $paid = isset($paidAmounts[$ad['id']][$m]) ? $paidAmounts[$ad['id']][$m] : 0.0;

                                if ($monthlyFee <= 0) {
                                    // L’adhérent n’a pas de monthlyFee => N/A ou “-”
                                    echo "<td class='na'>N/A</td>";
                                    continue;
                                }

                                if ($paid <= 0) {
                                    echo "<td class='nonpaye'>0€</td>";
                                } elseif ($paid >= $monthlyFee) {
                                    echo "<td class='paid'>" . formatAmount($paid) . "€</td>";
                                } else {
                                    // Partiel
                                    echo "<td class='partiel'>" . formatAmount($paid) . "/" . formatAmount($monthlyFee) . "€</td>";
                                }
                            }
                            echo "</tr>";
                            $num++;
                        }
                        ?>
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
            <?php
            $totalPages = ceil($totalAdherents / $limit);
            if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="pagination-btn">&laquo; Précédent</a>
                    <?php endif; ?>

                    <?php
                    // Afficher un maximum de 5 pages pour la navigation
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <?php if ($p == $page): ?>
                            <span class="current-page"><?= $p ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $p ?>" class="pagination-btn"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="pagination-btn">Suivant &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer>
        © 2024 Mosquée Errahma
    </footer>

    <!-- Inclusion de la librairie Um Al Qura depuis unpkg -->
    <script src="https://unpkg.com/@umalqura/core@0.0.7/dist/umalqura.min.js"></script>
    <script>
        /**
         * Calcul de l'année Hijri actuelle basée sur la date du jour.
         * Utilise la librairie Um Al Qura pour une précision accrue.
         */
        document.addEventListener('DOMContentLoaded', () => {
            try {
                // Date du jour
                const today = new Date();
                
                // Conversion en date hijri
                const hijriDate = umalqura(today);
                const hijriYear = hijriDate.hy;
                const hijriMonth = hijriDate.hm;
                
                // Si nous sommes dans les derniers mois de l'année hijri (Dhul Qa'dah et Dhul Hijjah)
                // nous affichons l'année en cours
                // Dhul Qa'dah = 11, Dhul Hijjah = 12
                const finalHijriYear = hijriMonth >= 1 && hijriMonth <= 12 ? hijriYear : hijriYear - 1;
                
                // Afficher l'année dans le span
                const hijriYearElement = document.getElementById('hijriYear');
                if (hijriYearElement) {
                    hijriYearElement.textContent = finalHijriYear;
                }
                
                // Debug - Afficher les informations dans la console
                console.log('Date actuelle:', today);
                console.log('Mois Hijri:', hijriMonth);
                console.log('Année Hijri calculée:', finalHijriYear);
                
            } catch (error) {
                console.error('Erreur lors du calcul de l\'année Hijri:', error);
                document.getElementById('hijriYear').textContent = 'N/A';
            }
        });

        /**
         * Fonction pour formater les montants.
         * Affiche les cents seulement s'ils ne sont pas nuls.
         */
        function formatAmount(amount) {
            return amount % 1 === 0 ? amount.toFixed(0) : amount.toFixed(2);
        }
    </script>
</body>
</html>

<?php
// Fonction pour formater les montants sans les cents si inutiles
function formatAmount($amount) {
    return ($amount == floor($amount)) ? number_format($amount, 0) : number_format($amount, 2);
}
?>
