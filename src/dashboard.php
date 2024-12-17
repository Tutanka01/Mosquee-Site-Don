<?php
include 'db.php';

// Récupération des contributions par type
// Récupération des cotisations avec noms gérés correctement
$query_cotisations = $db->query("
    SELECT 
        CASE 
            WHEN A.anonyme = 1 THEN 'Anonyme'
            ELSE A.nom || ' ' || A.prenom
        END AS nom_complet,
        C.montant,
        C.jour_paiement
    FROM Contributions C
    JOIN Adherents A ON C.id_adherent = A.id
    WHERE C.type_contribution = 'cotisation'
");
$data_cotisations = $query_cotisations->fetchAll(PDO::FETCH_ASSOC);

// Récupération des dons avec noms gérés correctement
$query_dons = $db->query("
    SELECT 
        CASE 
            WHEN A.anonyme = 1 THEN 'Anonyme'
            ELSE A.nom || ' ' || A.prenom
        END AS nom_complet,
        C.montant,
        C.jour_paiement
    FROM Contributions C
    JOIN Adherents A ON C.id_adherent = A.id
    WHERE C.type_contribution = 'don'
");
$data_dons = $query_dons->fetchAll(PDO::FETCH_ASSOC);

// Contributions par mois
$query_month = $db->query("SELECT strftime('%Y-%m', jour_paiement) AS mois, SUM(montant) AS total FROM Contributions GROUP BY mois");
$data_month = $query_month->fetchAll(PDO::FETCH_ASSOC);

// Répartition par type
$query_type = $db->query("SELECT type_contribution, SUM(montant) AS total FROM Contributions GROUP BY type_contribution");
$data_type = $query_type->fetchAll(PDO::FETCH_ASSOC);

// Pagination des contributions
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

$query_paginated = $db->prepare("
    SELECT 
        CASE 
            WHEN A.anonyme = 1 THEN 'Anonyme'
            ELSE A.nom || ' ' || A.prenom
        END AS nom_complet,
        C.montant,
        C.jour_paiement,
        C.type_contribution
    FROM Contributions C
    JOIN Adherents A ON C.id_adherent = A.id
    LIMIT :limit OFFSET :offset
");

$query_paginated->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$query_paginated->bindValue(':offset', $offset, PDO::PARAM_INT);
$query_paginated->execute();
$data_paginated = $query_paginated->fetchAll(PDO::FETCH_ASSOC);

// Total des pages
$total = $db->query("SELECT COUNT(*) as count FROM Contributions")->fetch(PDO::FETCH_ASSOC)['count'];
$total_pages = ceil($total / $items_per_page);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Administrateur</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <h1>Dashboard Administrateur</h1>

        <!-- Onglets -->
        <div class="tabs">
            <button onclick="showTab('stats')">Statistiques Globales</button>
            <button onclick="showTab('cotisations')">Cotisations</button>
            <button onclick="showTab('dons')">Dons</button>
            <button onclick="showTab('list')">Liste des Contributions</button>
        </div>

        <!-- Statistiques globales -->
        <div id="stats" class="tab-content">
            <h2>Statistiques Globales</h2>
            <canvas id="contributionsMois"></canvas>
            <canvas id="repartitionType"></canvas>
        </div>

        <!-- Cotisations -->
        <div id="cotisations" class="tab-content" style="display:none;">
            <h2>Liste des Cotisations</h2>
            <table>
                <tr>
                    <th>Nom</th>
                    <th>Montant (€)</th>
                    <th>Date</th>
                </tr>
            <?php foreach ($data_cotisations as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['nom_complet']) ?></td>
                    <td><?= number_format($c['montant'], 2) ?></td>
                    <td><?= $c['jour_paiement'] ?></td>
                </tr>
            <?php endforeach; ?>
            </table>
        </div>


        <!-- Dons -->
        <div id="dons" class="tab-content" style="display:none;">
            <h2>Liste des Dons</h2>
            <table>
                <tr>
                    <th>Nom</th>
                    <th>Montant (€)</th>
                    <th>Date</th>
                </tr>
                <?php foreach ($data_dons as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['nom_complet']) ?></td>
                    <td><?= number_format($d['montant'], 2) ?></td>
                    <td><?= $d['jour_paiement'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>


        <!-- Liste des contributions -->
        <div id="list" class="tab-content" style="display:none;">
            <h2>Liste des Contributions</h2>
            <table>
                <tr>
                    <th>Nom</th><th>Type</th><th>Montant (€)</th><th>Date</th>
                </tr>
                <?php foreach ($data_paginated as $contribution): ?>
                <tr>
                    <td><?= htmlspecialchars($contribution['nom_complet']) ?></td>
                    <td><?= htmlspecialchars($contribution['type_contribution']) ?></td>
                    <td><?= number_format($contribution['montant'], 2) ?></td>
                    <td><?= $contribution['jour_paiement'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <!-- Pagination -->
            <div>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>" <?= $i == $page ? 'class="active"' : '' ?>><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <script>
        // Gestion des onglets
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.getElementById(tab).style.display = 'block';
        }

        // Graphiques
        const ctxMois = document.getElementById('contributionsMois').getContext('2d');
        new Chart(ctxMois, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($data_month, 'mois')) ?>,
                datasets: [{ 
                    label: 'Montant Collecté (€)',
                    data: <?= json_encode(array_column($data_month, 'total')) ?>,
                    borderColor: 'blue',
                    backgroundColor: 'rgba(0, 0, 255, 0.1)',
                    fill: true
                }]
            }
        });

        const ctxType = document.getElementById('repartitionType').getContext('2d');
        new Chart(ctxType, {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_column($data_type, 'type_contribution')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($data_type, 'total')) ?>,
                    backgroundColor: ['green', 'orange']
                }]
            }
        });
    </script>
</body>
</html>
