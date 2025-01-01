<?php
// File: /src/dashboard.php
include 'db.php';

// Récupération de l'année en cours pour les stats
$currentYear = date('Y');

// Définir les options d'année pour les filtres (de 10 ans en arrière à 5 ans en avant)
$yearOptions = range($currentYear + 5, $currentYear - 10, -1);

// Récupération des filtres depuis l'URL
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filter_year = isset($_GET['filter_year']) ? $_GET['filter_year'] : $currentYear;

// Récupération des cotisations
$query_cotisations = $db->prepare("
    SELECT 
        CASE 
            WHEN C.id_adherent IS NOT NULL AND A.anonyme = 1 THEN 'Anonyme'
            WHEN C.id_adherent IS NOT NULL THEN A.nom || ' ' || A.prenom
            ELSE 'Inconnu'
        END AS nom_complet,
        C.montant,
        C.jour_paiement
    FROM Contributions C
    LEFT JOIN Adherents A ON C.id_adherent = A.id
    WHERE C.type_contribution = 'cotisation'
");
$query_cotisations->execute();
$data_cotisations = $query_cotisations->fetchAll(PDO::FETCH_ASSOC);

// Récupération des dons
$query_dons = $db->prepare("
    SELECT 
        CASE 
            WHEN C.anonyme = 1 THEN 'Anonyme'
            WHEN C.id_adherent IS NOT NULL AND A.anonyme = 0 THEN A.nom || ' ' || A.prenom
            WHEN C.id_adherent IS NOT NULL AND A.anonyme = 1 THEN 'Anonyme'
            WHEN C.id_adherent IS NULL AND C.anonyme = 0 THEN 
                CASE 
                    WHEN C.nom_donateur IS NOT NULL THEN C.nom_donateur || ' ' || C.prenom_donateur 
                    ELSE 'Donateur Non-Adhérent'
                END
            ELSE 'Inconnu'
        END AS nom_complet,
        C.montant,
        C.jour_paiement
    FROM Contributions C
    LEFT JOIN Adherents A ON C.id_adherent = A.id
    WHERE C.type_contribution = 'don'
");
$query_dons->execute();
$data_dons = $query_dons->fetchAll(PDO::FETCH_ASSOC);

// Récupération des projets
$query_projets = $db->prepare("
    SELECT 
        CASE 
            WHEN C.anonyme = 1 THEN 'Anonyme'
            WHEN C.id_adherent IS NOT NULL AND A.anonyme = 0 THEN A.nom || ' ' || A.prenom
            WHEN C.id_adherent IS NOT NULL AND A.anonyme = 1 THEN 'Anonyme'
            WHEN C.id_adherent IS NULL AND C.anonyme = 0 THEN 
                CASE 
                    WHEN C.nom_donateur IS NOT NULL THEN C.nom_donateur || ' ' || C.prenom_donateur 
                    ELSE 'Contributeur Non-Adhérent'
                END
            ELSE 'Inconnu'
        END AS nom_complet,
        C.montant,
        C.jour_paiement
    FROM Contributions C
    LEFT JOIN Adherents A ON C.id_adherent = A.id
    WHERE C.type_contribution = 'projet'
");
$query_projets->execute();
$data_projets = $query_projets->fetchAll(PDO::FETCH_ASSOC);

// Pour les graphiques et stats globales
$query_month = $db->prepare("
    SELECT strftime('%Y-%m', jour_paiement) AS mois, SUM(montant) AS total 
    FROM Contributions 
    GROUP BY mois
");
$query_month->execute();
$data_month = $query_month->fetchAll(PDO::FETCH_ASSOC);

$query_type = $db->prepare("
    SELECT type_contribution, SUM(montant) AS total 
    FROM Contributions 
    GROUP BY type_contribution
");
$query_type->execute();
$data_type = $query_type->fetchAll(PDO::FETCH_ASSOC);

// Stats supplémentaires
$stmt_total_year = $db->prepare("
    SELECT SUM(montant) AS total_year 
    FROM Contributions 
    WHERE strftime('%Y', jour_paiement) = ?
");
$stmt_total_year->execute([$filter_year]);
$result_total_year = $stmt_total_year->fetch(PDO::FETCH_ASSOC);
$total_year = $result_total_year && $result_total_year['total_year'] !== null ? (float)$result_total_year['total_year'] : 0.0;

$stmt_month_avg = $db->prepare("
    SELECT AVG(mensuel) AS avg_month 
    FROM (
        SELECT strftime('%Y-%m', jour_paiement) as mois, SUM(montant) as mensuel
        FROM Contributions
        WHERE strftime('%Y', jour_paiement) = ?
        GROUP BY mois
    )
");
$stmt_month_avg->execute([$filter_year]);
$result_avg_month = $stmt_month_avg->fetch(PDO::FETCH_ASSOC);
$avg_month = $result_avg_month && $result_avg_month['avg_month'] !== null ? (float)$result_avg_month['avg_month'] : 0.0;

// Top donateurs
$stmt_top_donors = $db->prepare("
    SELECT
        CASE 
            WHEN C.anonyme = 1 THEN 'Anonyme'
            WHEN C.id_adherent IS NOT NULL AND A.anonyme = 0 THEN A.nom || ' ' || A.prenom
            WHEN C.id_adherent IS NOT NULL AND A.anonyme = 1 THEN 'Anonyme'
            WHEN C.id_adherent IS NULL AND C.anonyme = 0 THEN 
                CASE 
                    WHEN C.nom_donateur IS NOT NULL THEN C.nom_donateur || ' ' || C.prenom_donateur
                    ELSE 'Donateur Non-Adhérent'
                END
            ELSE 'Inconnu'
        END AS nom_complet,
        SUM(montant) AS total_donne
    FROM Contributions C
    LEFT JOIN Adherents A ON C.id_adherent = A.id
    WHERE strftime('%Y', jour_paiement) = ?
    GROUP BY nom_complet
    ORDER BY total_donne DESC
    LIMIT 5
");
$stmt_top_donors->execute([$filter_year]);
$top_donors = $stmt_top_donors->fetchAll(PDO::FETCH_ASSOC);

// Nombre total d'adhérents
$stmt_count_adherents = $db->prepare("
    SELECT COUNT(*) 
    FROM Adherents 
    WHERE anonyme = 0
");
$stmt_count_adherents->execute();
$count_adherents = (int)$stmt_count_adherents->fetchColumn();

// Nombre de chaque type
$query_type_count = $db->prepare("
    SELECT type_contribution, COUNT(*) AS count_type 
    FROM Contributions 
    GROUP BY type_contribution
");
$query_type_count->execute();
$type_counts = $query_type_count->fetchAll(PDO::FETCH_ASSOC);

$cot_count = 0;
$don_count = 0;
$projet_count = 0;
foreach ($type_counts as $tc) {
    if ($tc['type_contribution'] === 'cotisation') $cot_count = $tc['count_type'];
    if ($tc['type_contribution'] === 'don') $don_count = $tc['count_type'];
    if ($tc['type_contribution'] === 'projet') $projet_count = $tc['count_type'];
}

// Ratio dons/cotisations (montant)
$stmt_ratio = $db->prepare("
SELECT 
    (SELECT SUM(montant) FROM Contributions WHERE type_contribution='don' AND strftime('%Y', jour_paiement)=?) as total_don,
    (SELECT SUM(montant) FROM Contributions WHERE type_contribution='cotisation' AND strftime('%Y', jour_paiement)=?) as total_cot
");
$stmt_ratio->execute([$filter_year, $filter_year]);
$ratio_data = $stmt_ratio->fetch(PDO::FETCH_ASSOC);
$total_don_year = isset($ratio_data['total_don']) && $ratio_data['total_don'] !== null ? (float)$ratio_data['total_don'] : 0.0;
$total_cot_year = isset($ratio_data['total_cot']) && $ratio_data['total_cot'] !== null ? (float)$ratio_data['total_cot'] : 0.0;
$ratio_don_cot = ($total_cot_year > 0) 
    ? round(($total_don_year / $total_cot_year)*100, 2) 
    : 0;

// Pagination sur la liste globale des contributions
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Préparer la requête paginée avec filtres
$query_paginated = $db->prepare("
    SELECT 
        CASE 
            WHEN C.anonyme = 1 THEN 'Anonyme'
            WHEN C.id_adherent IS NOT NULL AND A.anonyme = 0 THEN A.nom || ' ' || A.prenom
            WHEN C.id_adherent IS NOT NULL AND A.anonyme = 1 THEN 'Anonyme'
            WHEN C.id_adherent IS NULL AND C.anonyme = 0 THEN 
                CASE 
                    WHEN C.nom_donateur IS NOT NULL THEN C.nom_donateur || ' ' || C.prenom_donateur
                    ELSE 'Donateur/Contributeur Non-Adhérent'
                END
            ELSE 'Inconnu'
        END AS nom_complet,
        C.type_contribution,
        C.montant,
        C.jour_paiement
    FROM Contributions C
    LEFT JOIN Adherents A ON C.id_adherent = A.id
    WHERE 1=1
    " . ($filter_type ? " AND C.type_contribution = :type" : "") . "
    " . ($filter_year ? " AND strftime('%Y', C.jour_paiement) = :year" : "") . "
    ORDER BY C.jour_paiement DESC
    LIMIT :limit OFFSET :offset
");
if ($filter_type) {
    $query_paginated->bindValue(':type', $filter_type);
}
if ($filter_year) {
    $query_paginated->bindValue(':year', $filter_year);
}
$query_paginated->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$query_paginated->bindValue(':offset', $offset, PDO::PARAM_INT);
$query_paginated->execute();
$data_paginated = $query_paginated->fetchAll(PDO::FETCH_ASSOC);

// Recalculer le total et les pages en fonction des filtres
$total_query = "SELECT COUNT(*) as count FROM Contributions C LEFT JOIN Adherents A ON C.id_adherent = A.id WHERE 1=1 ";
if ($filter_type) {
    $total_query .= " AND C.type_contribution = :type";
}
if ($filter_year) {
    $total_query .= " AND strftime('%Y', C.jour_paiement) = :year";
}
$stmt_total = $db->prepare($total_query);
if ($filter_type) {
    $stmt_total->bindValue(':type', $filter_type);
}
if ($filter_year) {
    $stmt_total->bindValue(':year', $filter_year);
}
$stmt_total->execute();
$total = (int)$stmt_total->fetch(PDO::FETCH_ASSOC)['count'];
$total_pages = ceil($total / $items_per_page);

// Gestion du tableau des cotisations mensuelles
// Modification de la requête des adhérents pour les cotisations mensuelles
$adherents = $db->prepare("
    SELECT DISTINCT A.id, A.nom, A.prenom, A.monthly_fee, A.start_date, A.end_date 
    FROM Adherents A
    WHERE A.anonyme = 0 
    AND A.monthly_fee > 0
    AND (
        -- Adhésion active pendant l'année sélectionnée
        (
            (A.start_date IS NULL OR A.start_date <= :yearEnd)
            AND 
            (A.end_date IS NULL OR A.end_date >= :yearStart)
        )
        OR
        -- Ou a au moins une cotisation dans l'année
        EXISTS (
            SELECT 1 
            FROM Cotisation_Months CM 
            WHERE CM.id_adherent = A.id 
            AND CM.year = :year
        )
    )
    ORDER BY A.nom, A.prenom
");

$adherents->execute([
    ':yearStart' => $filter_year . '-01-01',
    ':yearEnd' => $filter_year . '-12-31',
    ':year' => $filter_year
]);
$adherents = $adherents->fetchAll(PDO::FETCH_ASSOC);

// Labels pour les mois
$mois_labels = ["Janv","Févr","Mars","Avril","Mai","Juin","Juil","Août","Sept","Octo","Nove","Dece"];

// Récupération des cotisations mensuelles pour chaque adhérent
$cotisations_mensuelles = [];
foreach ($adherents as $ad) {
    $idAd = $ad['id'];
    $monthly_fee = (float)$ad['monthly_fee'];
    
    // Récupérer les cotisations mensuelles
    $stmt = $db->prepare("
        SELECT month, paid_amount 
        FROM Cotisation_Months 
        WHERE id_adherent = ? AND year = ? 
        ORDER BY month
    ");
    $stmt->execute([$idAd, $filter_year]);
    $mois_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Indexer par mois
    $mois_status = [];
    foreach ($mois_data as $md) {
        $mois_status[intval($md['month'])] = (float)$md['paid_amount'];
    }

    // Vérifier si l'adhérent doit apparaître dans le tableau
    $start = $ad['start_date'] ? new DateTime($ad['start_date']) : null;
    $end = $ad['end_date'] ? new DateTime($ad['end_date']) : null;

    // Vérifier si l'adhérent a au moins un mois applicable dans l'année
    $hasApplicableMonth = false;
    foreach ($mois_labels as $index => $label) {
        $m = $index + 1;
        $dateM = new DateTime("{$filter_year}-{$m}-01");
        
        if ((!$start || $dateM >= $start) && (!$end || $dateM <= $end)) {
            $hasApplicableMonth = true;
            break;
        }
    }

    // Ne pas ajouter l'adhérent s'il n'a aucun mois applicable
    if (!$hasApplicableMonth) {
        continue;
    }

    $cotisations_mensuelles[] = [
        'nom_complet' => $ad['nom'] . ' ' . $ad['prenom'],
        'cotisations' => []
    ];

    foreach ($mois_labels as $index => $label) {
        $m = $index + 1;
        $dateM = new DateTime("{$filter_year}-{$m}-01");

        // Vérifier si la cotisation est applicable pour ce mois
        $applicable = true;
        if ($start && $dateM < $start) $applicable = false;
        if ($end && $dateM > $end)     $applicable = false;

        if (!$applicable) {
            $cotisations_mensuelles[count($cotisations_mensuelles)-1]['cotisations'][] = 'N/A';
        } else {
            $paid_amount = $mois_status[$m] ?? 0;
            $reste = $monthly_fee - $paid_amount;

            if ($reste <= 0) {
                $cotisations_mensuelles[count($cotisations_mensuelles)-1]['cotisations'][] = 'Payé';
            } else {
                $cotisations_mensuelles[count($cotisations_mensuelles)-1]['cotisations'][] = "-{$reste}€";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Administrateur - Mosquée Errahma</title>
    <link rel="stylesheet" href="styles/style-dashboard.css">
    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
<header>
    <div class="logo">
        <img src="../images/mosque_logo.png" alt="Mosquée Errahma">
    </div>
    <h1>Dashboard Administrateur</h1>
</header>
<main>
    <div class="container">
        <!-- Navigation par Onglets -->
        <div class="tabs">
            <button class="tab-btn active" data-tab="stats"><i class="fas fa-chart-line"></i> Statistiques Globales</button>
            <button class="tab-btn" data-tab="cotisations"><i class="fas fa-money-bill-wave"></i> Cotisations</button>
            <button class="tab-btn" data-tab="dons"><i class="fas fa-donate"></i> Dons</button>
            <button class="tab-btn" data-tab="projets"><i class="fas fa-project-diagram"></i> Projets</button>
            <button class="tab-btn" data-tab="list"><i class="fas fa-list"></i> Liste des Contributions</button>
            <button class="tab-btn" data-tab="adherents"><i class="fas fa-search"></i> Recherche Adhérent</button>
            <button class="tab-btn" data-tab="cotisations_mensuelles"><i class="fas fa-calendar-alt"></i> Cotisations Mensuelles</button>
            <button class="tab-btn" data-tab="membres"><i class="fas fa-users"></i> Gestion des Membres</button>
        </div>

        <!-- Onglet : Statistiques Globales -->
        <div id="stats" class="tab-content active">
            <h2>Statistiques Globales - Année <?= htmlspecialchars($filter_year) ?></h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total des Contributions</h3>
                    <p><?= number_format($total_year, 2) ?> €</p>
                </div>
                <div class="stat-card">
                    <h3>Moyenne Mensuelle</h3>
                    <p><?= number_format($avg_month, 2) ?> €</p>
                </div>
                <div class="stat-card">
                    <h3>Nombre d'Adhérents</h3>
                    <p><?= $count_adherents ?></p>
                </div>
                <div class="stat-card">
                    <h3>Ratio Dons/Cotisations</h3>
                    <p><?= $ratio_don_cot ?>%</p>
                </div>
            </div>

            <h3>Top 5 Donateurs (Année <?= htmlspecialchars($filter_year) ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Total Donné (€)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_donors as $donor): ?>
                    <tr>
                        <td><?= htmlspecialchars($donor['nom_complet']) ?></td>
                        <td><?= number_format($donor['total_donne'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3>Évolution Mensuelle des Contributions</h3>
            <canvas id="contributionsMois"></canvas>

            <h3>Répartition des Contributions par Type</h3>
            <canvas id="repartitionType"></canvas>
        </div>

        <!-- Onglet : Cotisations -->
        <div id="cotisations" class="tab-content">
            <h2>Liste des Cotisations</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Montant (€)</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data_cotisations as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['nom_complet']) ?></td>
                        <td><?= number_format($c['montant'], 2) ?></td>
                        <td><?= htmlspecialchars($c['jour_paiement']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Onglet : Dons -->
        <div id="dons" class="tab-content">
            <h2>Liste des Dons</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Montant (€)</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data_dons as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['nom_complet']) ?></td>
                        <td><?= number_format($d['montant'], 2) ?></td>
                        <td><?= htmlspecialchars($d['jour_paiement']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Onglet : Projets -->
        <div id="projets" class="tab-content">
            <h2>Liste des Contributions Projets</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Montant (€)</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data_projets as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nom_complet']) ?></td>
                        <td><?= number_format($p['montant'], 2) ?></td>
                        <td><?= htmlspecialchars($p['jour_paiement']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Onglet : Liste des Contributions (avec pagination et filtres) -->
        <div id="list" class="tab-content">
            <h2>Liste des Contributions</h2>
            <div class="filter-group">
                <label for="filterType">Type de Contribution :</label>
                <select id="filterType">
                    <option value="">Tous</option>
                    <option value="cotisation" <?= ($filter_type === 'cotisation') ? 'selected' : '' ?>>Cotisation</option>
                    <option value="don" <?= ($filter_type === 'don') ? 'selected' : '' ?>>Don</option>
                    <option value="projet" <?= ($filter_type === 'projet') ? 'selected' : '' ?>>Projet</option>
                </select>

                <label for="filterYear">Année :</label>
                <select id="filterYear">
                    <?php foreach ($yearOptions as $y): ?>
                        <option value="<?= $y ?>" <?= ($y == $filter_year ? 'selected' : '') ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>

                <button id="applyFilters"><i class="fas fa-filter"></i> Appliquer</button>
            </div>
            <table id="contributionsTable">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Montant (€)</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data_paginated as $contribution): ?>
                    <tr>
                        <td><?= htmlspecialchars($contribution['nom_complet']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($contribution['type_contribution'])) ?></td>
                        <td><?= number_format($contribution['montant'], 2) ?></td>
                        <td><?= htmlspecialchars($contribution['jour_paiement']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Bouton d'exportation en PDF -->
            <div class="export-group">
                <a href="export.php?tab=list&filter_type=<?= urlencode($filter_type) ?>&filter_year=<?= urlencode($filter_year) ?>" class="export-btn"><i class="fas fa-file-pdf"></i> Exporter en PDF</a>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?tab=list&filter_type=<?= urlencode($filter_type) ?>&filter_year=<?= urlencode($filter_year) ?>&page=<?= $i ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Onglet : Recherche Adhérent -->
        <div id="adherents" class="tab-content">
            <h2>Recherche d'Adhérent</h2>
            <div class="search-group">
                <input type="text" id="searchAdherent" placeholder="Rechercher un adhérent par nom ou email...">
                <button id="searchAdherentBtn"><i class="fas fa-search"></i> Rechercher</button>
            </div>
            <div id="adherentResults"></div>

            <h3>Historique de l'Adhérent</h3>
            <div id="adherentHistory"></div>
        </div>

        <!-- Onglet : Cotisations Mensuelles -->
        <div id="cotisations_mensuelles" class="tab-content">
            <h2>Cotisations Mensuelles - Année <?= htmlspecialchars($filter_year) ?></h2>

            <!-- Sélecteur d'année pour naviguer -->
            <form method="GET" action="dashboard.php" class="year-selector">
                <!-- pour rester sur le bon onglet après validation -->
                <input type="hidden" name="tab" value="cotisations_mensuelles">
                <input type="hidden" name="filter_type" value="<?= htmlspecialchars($filter_type) ?>">

                <label for="filter_year">Choisir une année :</label>
                <select name="filter_year" id="cot_year">
                    <?php foreach ($yearOptions as $y): ?>
                        <option value="<?= $y ?>" <?= ($y == $filter_year ? 'selected' : '') ?>>
                            <?= $y ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><i class="fas fa-filter"></i> Afficher</button>
            </form>

            <table class="cotisations-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom et Prénom</th>
                        <?php foreach ($mois_labels as $ml): ?>
                            <th><?= $ml ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $num=1;
                    foreach ($cotisations_mensuelles as $ad): ?>
                    <tr>
                        <td><?= $num++ ?></td>
                        <td><?= htmlspecialchars($ad['nom_complet']) ?></td>
                        <?php foreach ($ad['cotisations'] as $cot): 
                            // Déterminer la classe CSS en fonction de la valeur
                            if ($cot === 'Payé') {
                                $class = 'paid';
                            } elseif ($cot === 'N/A') {
                                $class = 'nonpaye';
                            } elseif (strpos($cot, '-') === 0) {
                                $class = 'partiel';
                            } elseif ($cot === '0€') {
                                $class = 'nonpaye';
                            } else {
                                $class = '';
                            }
                        ?>
                            <td class="<?= $class ?>"><?= htmlspecialchars($cot) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Onglet : Gestion des Membres -->
        <div id="membres" class="tab-content">
            <h2>Gestion des Membres</h2>
            <div class="membre-actions">
                <input type="text" id="searchMembre" placeholder="Rechercher un membre par nom/email...">
                <button id="addMembreBtn"><i class="fas fa-user-plus"></i> Ajouter un adhérent</button>
            </div>
            <table id="membresTable">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Montant Mensuel (€)</th>
                        <th>Début Adhésion</th>
                        <th>Fin Adhésion</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Rempli via JS (fetchAdherents) -->
                </tbody>
            </table>
        </div>
    </div>
</main>

<footer>
    © 2025 Mosquée Errahma
</footer>

<!-- Modale Ajouter/Modifier Adhérent -->
<div id="membreModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="membreModalTitle">Ajouter un adhérent</h2>
        <form id="membreForm">
            <input type="hidden" name="id" id="membre_id">
            <label for="membre_nom">Nom :</label>
            <input type="text" name="nom" id="membre_nom" required placeholder="Nom">

            <label for="membre_prenom">Prénom :</label>
            <input type="text" name="prenom" id="membre_prenom" required placeholder="Prénom">

            <label for="membre_email">Email :</label>
            <input type="email" name="email" id="membre_email" required placeholder="email@exemple.com">

            <label for="membre_telephone">Téléphone :</label>
            <input type="text" name="telephone" id="membre_telephone" required placeholder="ex: 0601020304">

            <label for="membre_monthly_fee">Montant Mensuel (€) :</label>
            <input type="number" step="0.01" name="monthly_fee" id="membre_monthly_fee" placeholder="ex: 15.00">

            <label for="membre_start_date">Date Début Adhésion :</label>
            <input type="date" name="start_date" id="membre_start_date">

            <label for="membre_end_date">Date Fin Adhésion :</label>
            <input type="date" name="end_date" id="membre_end_date">

            <button type="submit"><i class="fas fa-save"></i> Enregistrer</button>
        </form>
        <div id="membreError" class="error-message"></div>
    </div>
</div>

<!-- Modale Suppression -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Supprimer Adhérent</h2>
        <p>Êtes-vous sûr de vouloir supprimer cet adhérent ? Cette action est irréversible.</p>
        <input type="hidden" id="delete_id">
        <button id="confirmDeleteBtn"><i class="fas fa-trash-alt"></i> Supprimer</button>
        <button id="cancelDeleteBtn" class="cancel-btn"><i class="fas fa-times"></i> Annuler</button>
        <div id="deleteError" class="error-message"></div>
    </div>
</div>

<!-- JavaScript Intégré -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Gestion des Onglets
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Supprimer la classe active de tous les boutons
            tabButtons.forEach(btn => btn.classList.remove('active'));
            // Ajouter la classe active au bouton cliqué
            button.classList.add('active');

            // Cacher tous les contenus
            tabContents.forEach(content => content.classList.remove('active'));
            // Afficher le contenu correspondant
            const tab = button.getAttribute('data-tab');
            document.getElementById(tab).classList.add('active');
        });
    });

    // Initialiser le bon onglet basé sur l'URL
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab && document.getElementById(activeTab)) {
        showTab(activeTab);
    } else {
        showTab('stats');
    }

    function showTab(tab) {
        tabButtons.forEach(btn => btn.classList.remove('active'));
        const activeButton = document.querySelector(`.tab-btn[data-tab="${tab}"]`);
        if (activeButton) {
            activeButton.classList.add('active');
        }

        tabContents.forEach(content => content.classList.remove('active'));
        const activeContent = document.getElementById(tab);
        if (activeContent) {
            activeContent.classList.add('active');
        }
    }

    // Graphiques (stats globales)
    const ctxMois = document.getElementById('contributionsMois').getContext('2d');
    const contributionsMoisData = <?= json_encode(array_column($data_month, 'total')) ?>;
    const contributionsMoisLabels = <?= json_encode(array_column($data_month, 'mois')) ?>;
    new Chart(ctxMois, {
        type: 'line',
        data: {
            labels: contributionsMoisLabels,
            datasets: [{
                label: 'Montant Collecté (€)',
                data: contributionsMoisData,
                borderColor: 'rgba(37, 99, 235, 1)',
                backgroundColor: 'rgba(37, 99, 235, 0.2)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                },
            },
            interaction: {
                mode: 'nearest',
                intersect: false
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Mois'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Montant (€)'
                    },
                    beginAtZero: true
                }
            }
        }
    });

    const ctxType = document.getElementById('repartitionType').getContext('2d');
    const repartitionTypeData = <?= json_encode(array_column($data_type, 'total')) ?>;
    const repartitionTypeLabels = <?= json_encode(array_map('ucfirst', array_column($data_type, 'type_contribution'))) ?>;
    new Chart(ctxType, {
        type: 'pie',
        data: {
            labels: repartitionTypeLabels,
            datasets: [{
                data: repartitionTypeData,
                backgroundColor: [
                    'rgba(37, 99, 235, 0.6)',    // Cotisations - Bleu
                    'rgba(16, 185, 129, 0.6)',   // Dons - Vert
                    'rgba(218, 112, 214, 0.6)'   // Projets - Violet
                ],
                borderColor: [
                    'rgba(37, 99, 235, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(218, 112, 214, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed !== null) {
                                label += new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(context.parsed);
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });

    // Recherche Adhérent
    const searchAdherentBtn = document.getElementById('searchAdherentBtn');
    const searchAdherentInput = document.getElementById('searchAdherent');
    const adherentResults = document.getElementById('adherentResults');
    const adherentHistory = document.getElementById('adherentHistory');

    searchAdherentBtn.addEventListener('click', () => {
        const term = searchAdherentInput.value.trim();
        if (term === '') {
            adherentResults.innerHTML = '<p>Veuillez entrer un terme de recherche.</p>';
            adherentHistory.innerHTML = '';
            return;
        }

        fetch(`fetch_adherent_search.php?term=${encodeURIComponent(term)}`)
            .then(response => response.json())
            .then(data => {
                adherentResults.innerHTML = '';
                adherentHistory.innerHTML = '';
                if (data.length === 0) {
                    adherentResults.innerHTML = '<p>Aucun adhérent trouvé.</p>';
                } else {
                    const table = document.createElement('table');
                    table.innerHTML = `
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(ad => `
                                <tr data-id="${ad.id}" style="cursor:pointer;">
                                    <td>${ad.nom} ${ad.prenom}</td>
                                    <td>${ad.email}</td>
                                    <td>${ad.telephone}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    `;
                    adherentResults.appendChild(table);

                    // Ajouter les écouteurs pour afficher l'historique au clic
                    table.querySelectorAll('tbody tr').forEach(row => {
                        row.addEventListener('click', () => {
                            const id = row.getAttribute('data-id');
                            fetch(`fetch_adherent_history.php?id=${id}`)
                                .then(response => response.json())
                                .then(resp => {
                                    adherentHistory.innerHTML = '<h4>Historique de l\'adhérent</h4>';
                                    if (!resp.success) {
                                        adherentHistory.innerHTML += `<p>${resp.message}</p>`;
                                        return;
                                    }

                                    // Afficher le téléphone
                                    adherentHistory.innerHTML += `<p><strong>Téléphone :</strong> ${resp.telephone}</p>`;

                                    if (resp.history.length === 0) {
                                        adherentHistory.innerHTML += '<p>Aucune contribution.</p>';
                                    } else {
                                        const histTable = document.createElement('table');
                                        histTable.innerHTML = `
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Montant (€)</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${resp.history.map(c => `
                                                    <tr>
                                                        <td>${c.type_contribution}</td>
                                                        <td>${parseFloat(c.montant).toFixed(2)}</td>
                                                        <td>${c.jour_paiement}</td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        `;
                                        adherentHistory.appendChild(histTable);
                                    }
                                })
                                .catch(err => {
                                    adherentHistory.innerHTML = '<p>Erreur lors de la récupération de l\'historique.</p>';
                                    console.error(err);
                                });
                        });
                    });
                }
            })
            .catch(err => {
                adherentResults.innerHTML = '<p>Erreur lors de la recherche.</p>';
                console.error(err);
            });
    });

    // Gestion des Membres
    const searchMembreInput = document.getElementById('searchMembre');
    const membresTableBody = document.querySelector('#membresTable tbody');
    const addMembreBtn = document.getElementById('addMembreBtn');
    const membreModal = document.getElementById('membreModal');
    const closeMembreModal = membreModal.querySelector('.close');
    const membreForm = document.getElementById('membreForm');
    const membreError = document.getElementById('membreError');
    const membreModalTitle = document.getElementById('membreModalTitle');
    const membre_id = document.getElementById('membre_id');
    const membre_nom = document.getElementById('membre_nom');
    const membre_prenom = document.getElementById('membre_prenom');
    const membre_email = document.getElementById('membre_email');
    const membre_telephone = document.getElementById('membre_telephone');
    const membre_monthly_fee = document.getElementById('membre_monthly_fee');
    const membre_start_date = document.getElementById('membre_start_date');
    const membre_end_date = document.getElementById('membre_end_date');

    const deleteModal = document.getElementById('deleteModal');
    const closeDeleteModal = deleteModal.querySelector('.close');
    const delete_id = document.getElementById('delete_id');
    const deleteError = document.getElementById('deleteError');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

    // Fonction pour fetch les adhérents
    function fetchAdherents(term='') {
        fetch(`fetch_adherents.php?term=${encodeURIComponent(term)}`)
            .then(response => response.json())
            .then(data => {
                membresTableBody.innerHTML = '';
                if (data.length === 0) {
                    membresTableBody.innerHTML = '<tr><td colspan="7">Aucun adhérent trouvé.</td></tr>';
                } else {
                    data.forEach(ad => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${ad.nom} ${ad.prenom}</td>
                            <td>${ad.email}</td>
                            <td>${ad.telephone}</td>
                            <td>${ad.monthly_fee ? ad.monthly_fee.toFixed(2) : ''}</td>
                            <td>${ad.start_date ? new Date(ad.start_date).toLocaleDateString('fr-FR') : ''}</td>
                            <td>${ad.end_date ? new Date(ad.end_date).toLocaleDateString('fr-FR') : ''}</td>
                            <td>
                                <button class="editBtn" data-id="${ad.id}"><i class="fas fa-edit"></i> Modifier</button>
                                <button class="deleteBtn" data-id="${ad.id}"><i class="fas fa-trash-alt"></i> Supprimer</button>
                            </td>
                        `;
                        membresTableBody.appendChild(tr);
                    });

                    // Ajouter les écouteurs pour les boutons Modifier et Supprimer
                    document.querySelectorAll('.editBtn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const id = btn.getAttribute('data-id');
                            editAdherent(id);
                        });
                    });
                    document.querySelectorAll('.deleteBtn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const id = btn.getAttribute('data-id');
                            delete_id.value = id;
                            deleteError.textContent = '';
                            deleteModal.style.display = 'flex';
                        });
                    });
                }
            })
            .catch(err => {
                membresTableBody.innerHTML = '<tr><td colspan="7">Erreur lors de la récupération des adhérents.</td></tr>';
                console.error(err);
            });
    }

    // Fonction pour éditer un adhérent
    function editAdherent(id) {
        // Récupérer les détails de l'adhérent
        fetch(`fetch_adherent_details.php?id=${id}`)
            .then(response => response.json())
            .then(resp => {
                if (resp.success) {
                    const ad = resp.data;
                    membre_id.value = ad.id;
                    membre_nom.value = ad.nom;
                    membre_prenom.value = ad.prenom;
                    membre_email.value = ad.email;
                    membre_telephone.value = ad.telephone;
                    membre_monthly_fee.value = ad.monthly_fee ? ad.monthly_fee.toFixed(2) : '';
                    membre_start_date.value = ad.start_date;
                    membre_end_date.value = ad.end_date;
                    membreModalTitle.textContent = "Modifier l'adhérent";
                    membreError.textContent = '';
                    membreModal.style.display = 'flex';
                } else {
                    alert(resp.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erreur lors de la récupération des détails de l\'adhérent.');
            });
    }

    // Recherche dynamique des membres
    searchMembreInput.addEventListener('input', () => {
        fetchAdherents(searchMembreInput.value);
    });

    // Ajouter un membre
    addMembreBtn.addEventListener('click', () => {
        membre_id.value = '';
        membre_nom.value = '';
        membre_prenom.value = '';
        membre_email.value = '';
        membre_telephone.value = '';
        membre_monthly_fee.value = '';
        membre_start_date.value = '';
        membre_end_date.value = '';
        membreModalTitle.textContent = "Ajouter un adhérent";
        membreError.textContent = '';
        membreModal.style.display = 'flex';
    });

    // Fermer la modale d'adhérent
    closeMembreModal.addEventListener('click', () => {
        membreModal.style.display = 'none';
    });

    // Fermer la modale de suppression
    closeDeleteModal.addEventListener('click', () => {
        deleteModal.style.display = 'none';
    });
    cancelDeleteBtn.addEventListener('click', () => {
        deleteModal.style.display = 'none';
    });

    // Soumettre le formulaire d'adhérent (ajout ou modification)
    membreForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(membreForm);
        let url = 'insert_adherent.php';
        if (membre_id.value) {
            // Si un ID existe => update
            url = 'update_adherent.php';
        }
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(resp => {
            if (resp.success) {
                membreModal.style.display = 'none';
                fetchAdherents(searchMembreInput.value);
            } else {
                membreError.textContent = resp.message;
            }
        })
        .catch(err => {
            console.error(err);
            membreError.textContent = 'Erreur lors de l\'enregistrement.';
        });
    });

    // Gestion de la suppression d'un adhérent
    confirmDeleteBtn.addEventListener('click', () => {
        const id = delete_id.value;
        if (!id) {
            deleteError.textContent = 'ID de l\'adhérent manquant.';
            return;
        }

        const formData = new FormData();
        formData.append('id', id);

        fetch('delete_adherent.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(resp => {
            if (resp.success) {
                deleteModal.style.display = 'none';
                fetchAdherents(searchMembreInput.value);
            } else {
                deleteError.textContent = resp.message;
            }
        })
        .catch(err => {
            console.error(err);
            deleteError.textContent = 'Erreur lors de la suppression.';
        });
    });

    // Initialiser la liste des membres
    fetchAdherents();

    // Gestion des filtres pour la liste des contributions
    const applyFiltersBtn = document.getElementById('applyFilters');
    const filterTypeSelect = document.getElementById('filterType');
    const filterYearSelect = document.getElementById('filterYear');

    applyFiltersBtn.addEventListener('click', () => {
        const type = filterTypeSelect.value;
        const year = filterYearSelect.value;
        let url = `dashboard.php?tab=list&filter_year=${encodeURIComponent(year)}`;
        if (type) {
            url += `&filter_type=${encodeURIComponent(type)}`;
        }
        window.location.href = url;
    });
});
</script>
</body>
</html>
