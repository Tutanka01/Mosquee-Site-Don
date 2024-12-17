<?php
include 'db.php';

$currentYear = date('Y');

// On réutilise les requêtes initiales pour récupérer les données existantes
$query_cotisations = $db->query("
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
$data_cotisations = $query_cotisations->fetchAll(PDO::FETCH_ASSOC);

$query_dons = $db->query("
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
$data_dons = $query_dons->fetchAll(PDO::FETCH_ASSOC);

$query_month = $db->query("SELECT strftime('%Y-%m', jour_paiement) AS mois, SUM(montant) AS total FROM Contributions GROUP BY mois");
$data_month = $query_month->fetchAll(PDO::FETCH_ASSOC);

$query_type = $db->query("SELECT type_contribution, SUM(montant) AS total FROM Contributions GROUP BY type_contribution");
$data_type = $query_type->fetchAll(PDO::FETCH_ASSOC);

// Statistiques supplémentaires
$stmt_total_year = $db->prepare("SELECT SUM(montant) as total_year FROM Contributions WHERE strftime('%Y', jour_paiement) = ?");
$stmt_total_year->execute([$currentYear]);
$total_year = (float)$stmt_total_year->fetch(PDO::FETCH_ASSOC)['total_year'];

$stmt_month_avg = $db->prepare("SELECT AVG(mensuel) as avg_month FROM (
    SELECT strftime('%Y-%m', jour_paiement) as mois, SUM(montant) as mensuel
    FROM Contributions
    WHERE strftime('%Y', jour_paiement) = ?
    GROUP BY mois
)");
$stmt_month_avg->execute([$currentYear]);
$avg_month = (float)$stmt_month_avg->fetch(PDO::FETCH_ASSOC)['avg_month'];

$stmt_top_donors = $db->prepare("
    SELECT
        CASE 
            WHEN C.anonyme = 1 THEN 'Anonyme'
            WHEN C.id_adherent IS NOT NULL AND A.anonyme = 0 THEN A.nom || ' ' || A.prenom
            WHEN C.id_adherent IS NOT NULL AND A.anonyme = 1 THEN 'Anonyme'
            WHEN C.id_adherent IS NULL AND C.anonyme = 0 THEN 
                CASE WHEN C.nom_donateur IS NOT NULL THEN C.nom_donateur || ' ' || C.prenom_donateur ELSE 'Donateur Non-Adhérent' END
            ELSE 'Inconnu'
        END AS nom_complet,
        SUM(montant) as total_donne
    FROM Contributions C
    LEFT JOIN Adherents A ON C.id_adherent = A.id
    WHERE strftime('%Y', jour_paiement) = ?
    GROUP BY nom_complet
    ORDER BY total_donne DESC
    LIMIT 5
");
$stmt_top_donors->execute([$currentYear]);
$top_donors = $stmt_top_donors->fetchAll(PDO::FETCH_ASSOC);

$count_adherents = (int)$db->query("SELECT COUNT(*) FROM Adherents WHERE anonyme = 0")->fetchColumn();

$query_type_count = $db->query("SELECT type_contribution, COUNT(*) as count_type FROM Contributions GROUP BY type_contribution");
$type_counts = $query_type_count->fetchAll(PDO::FETCH_ASSOC);

$cot_count = 0;
$don_count = 0;
foreach ($type_counts as $tc) {
    if ($tc['type_contribution'] === 'cotisation') $cot_count = $tc['count_type'];
    if ($tc['type_contribution'] === 'don') $don_count = $tc['count_type'];
}

$stmt_ratio = $db->prepare("
SELECT 
    (SELECT SUM(montant) FROM Contributions WHERE type_contribution='don' AND strftime('%Y', jour_paiement)=?) as total_don,
    (SELECT SUM(montant) FROM Contributions WHERE type_contribution='cotisation' AND strftime('%Y', jour_paiement)=?) as total_cot
");
$stmt_ratio->execute([$currentYear, $currentYear]);
$ratio_data = $stmt_ratio->fetch(PDO::FETCH_ASSOC);
$total_don_year = (float)$ratio_data['total_don'];
$total_cot_year = (float)$ratio_data['total_cot'];
$ratio_don_cot = $total_cot_year > 0 ? round(($total_don_year / $total_cot_year)*100, 2) : 0;

// Pagination pour la liste des contributions
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

$query_paginated = $db->prepare("
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
        C.jour_paiement,
        C.type_contribution
    FROM Contributions C
    LEFT JOIN Adherents A ON C.id_adherent = A.id
    LIMIT :limit OFFSET :offset
");

$query_paginated->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$query_paginated->bindValue(':offset', $offset, PDO::PARAM_INT);
$query_paginated->execute();
$data_paginated = $query_paginated->fetchAll(PDO::FETCH_ASSOC);

$total = $db->query("SELECT COUNT(*) as count FROM Contributions")->fetch(PDO::FETCH_ASSOC)['count'];
$total_pages = ceil($total / $items_per_page);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Administrateur Amélioré</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <h1>Dashboard Administrateur</h1>

        <div class="tabs">
            <button onclick="showTab('stats')">Statistiques Globales</button>
            <button onclick="showTab('cotisations')">Cotisations</button>
            <button onclick="showTab('dons')">Dons</button>
            <button onclick="showTab('list')">Liste des Contributions</button>
            <button onclick="showTab('adherents')">Recherche Adhérent</button>
        </div>

        <div id="stats" class="tab-content">
            <h2>Statistiques Globales - Année <?= htmlspecialchars($currentYear) ?></h2>
            <p>Total des contributions cette année : <strong><?= number_format($total_year, 2) ?> €</strong></p>
            <p>Moyenne mensuelle des contributions : <strong><?= number_format($avg_month, 2) ?> €</strong></p>
            <p>Nombre total d'adhérents : <strong><?= $count_adherents ?></strong></p>
            <p>Contributions - Cotisations : <strong><?= $cot_count ?></strong> | Dons : <strong><?= $don_count ?></strong></p>
            <p>Ratio Dons/Cotisations (en montant) cette année : <strong><?= $ratio_don_cot ?>%</strong></p>
            
            <h3>Top 5 Donateurs (Année <?= $currentYear ?>)</h3>
            <table>
                <tr><th>Nom</th><th>Total Donné (€)</th></tr>
                <?php foreach ($top_donors as $donor): ?>
                <tr>
                    <td><?= htmlspecialchars($donor['nom_complet']) ?></td>
                    <td><?= number_format($donor['total_donne'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <h3>Évolution Mensuelle</h3>
            <canvas id="contributionsMois"></canvas>

            <h3>Répartition par Type</h3>
            <canvas id="repartitionType"></canvas>
        </div>

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

        <div id="list" class="tab-content" style="display:none;">
            <h2>Liste des Contributions</h2>
            <!-- Filtres, export, etc. -->
            <p>Filtres (à implémenter) et export (à implémenter)</p>
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

            <div>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>" <?= $i == $page ? 'class="active"' : '' ?>><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>

        <div id="adherents" class="tab-content" style="display:none;">
            <h2>Recherche d'Adhérent</h2>
            <label>Nom/Email :
                <input type="text" id="searchAdherent" placeholder="Rechercher un adhérent">
            </label>
            <button id="searchAdherentBtn">Rechercher</button>

            <div id="adherentResults"></div>

            <h3>Historique de l'Adhérent</h3>
            <div id="adherentHistory"></div>
        </div>
    </div>

    <script>
        function showTab(tab) {
            var tabs = document.querySelectorAll('.tab-content');
            for (var i=0; i<tabs.length; i++){
                tabs[i].style.display = 'none';
            }
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

        // Recherche adhérent (fonctionnement indicatif)
        document.getElementById('searchAdherentBtn').addEventListener('click', () => {
            const term = document.getElementById('searchAdherent').value.trim();
            if (term === '') return;
            fetch('fetch_adherent_search.php?term=' + encodeURIComponent(term))
                .then(r => r.json())
                .then(data => {
                    const resultsDiv = document.getElementById('adherentResults');
                    resultsDiv.innerHTML = '';
                    if (data.length === 0) {
                        resultsDiv.textContent = 'Aucun adhérent trouvé.';
                    } else {
                        const ul = document.createElement('ul');
                        data.forEach(ad => {
                            const li = document.createElement('li');
                            li.textContent = ad.nom + ' ' + ad.prenom + ' (' + ad.email + ')';
                            li.style.cursor = 'pointer';
                            li.addEventListener('click', () => {
                                fetch('fetch_adherent_history.php?id=' + ad.id)
                                    .then(r => r.json())
                                    .then(hist => {
                                        const histDiv = document.getElementById('adherentHistory');
                                        histDiv.innerHTML = '<h4>Historique de ' + ad.nom + ' ' + ad.prenom + '</h4>';
                                        if (hist.length === 0) {
                                            histDiv.innerHTML += '<p>Aucune contribution.</p>';
                                        } else {
                                            const table = document.createElement('table');
                                            table.innerHTML = '<tr><th>Type</th><th>Montant</th><th>Date</th></tr>';
                                            hist.forEach(c => {
                                                const row = document.createElement('tr');
                                                row.innerHTML = '<td>' + c.type_contribution + '</td><td>' + c.montant + '</td><td>' + c.jour_paiement + '</td>';
                                                table.appendChild(row);
                                            });
                                            histDiv.appendChild(table);
                                        }
                                    });
                            });
                            ul.appendChild(li);
                        });
                        resultsDiv.appendChild(ul);
                    }
                });
        });

        // Afficher l'onglet Statistiques Globales par défaut
        showTab('stats');
    </script>
</body>
</html>
