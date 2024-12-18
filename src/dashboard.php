<?php
include 'db.php';

$currentYear = date('Y');

// Requêtes pour les listes
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

// Projets
$query_projets = $db->query("
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
$data_projets = $query_projets->fetchAll(PDO::FETCH_ASSOC);

// Pour les graphiques et stats globales
$query_month = $db->query("SELECT strftime('%Y-%m', jour_paiement) AS mois, SUM(montant) AS total FROM Contributions GROUP BY mois");
$data_month = $query_month->fetchAll(PDO::FETCH_ASSOC);

$query_type = $db->query("SELECT type_contribution, SUM(montant) AS total FROM Contributions GROUP BY type_contribution");
$data_type = $query_type->fetchAll(PDO::FETCH_ASSOC);

// Stats supplémentaires
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
$projet_count = 0;
foreach ($type_counts as $tc) {
    if ($tc['type_contribution'] === 'cotisation') $cot_count = $tc['count_type'];
    if ($tc['type_contribution'] === 'don') $don_count = $tc['count_type'];
    if ($tc['type_contribution'] === 'projet') $projet_count = $tc['count_type'];
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
                    ELSE 'Donateur/Contributeur Non-Adhérent'
                END
            ELSE 'Inconnu'
        END AS nom_complet,
        C.montant,
        C.jour_paiement,
        C.type_contribution
    FROM Contributions C
    LEFT JOIN Adherents A ON C.id_adherent = A.id
    ORDER BY C.jour_paiement DESC
    LIMIT :limit OFFSET :offset
");

$query_paginated->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$query_paginated->bindValue(':offset', $offset, PDO::PARAM_INT);
$query_paginated->execute();
$data_paginated = $query_paginated->fetchAll(PDO::FETCH_ASSOC);

$total = $db->query("SELECT COUNT(*) as count FROM Contributions")->fetch(PDO::FETCH_ASSOC)['count'];
$total_pages = ceil($total / $items_per_page);

// Cotisations Mensuelles
$year = $currentYear;
$adherents = $db->query("SELECT id, nom, prenom, monthly_fee, start_date, end_date FROM Adherents ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);
$mois_labels = ["Janv","Fevr","Mars","Avril","Mai","Juin","Juill","Aout","Sept","Octo","Nove","Dece"];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Administrateur - Amélioré</title>
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
        <button onclick="showTab('projets')">Projets</button>
        <button onclick="showTab('list')">Liste des Contributions</button>
        <button onclick="showTab('adherents')">Recherche Adhérent</button>
        <button onclick="showTab('cotisations_mensuelles')">Cotisations Mensuelles</button>
        <button onclick="showTab('membres')">Membres</button>
    </div>

    <!-- Statistiques Globales -->
    <div id="stats" class="tab-content">
        <h2>Statistiques Globales - Année <?= htmlspecialchars($currentYear) ?></h2>
        <p>Total des contributions cette année : <strong><?= number_format($total_year, 2) ?> €</strong></p>
        <p>Moyenne mensuelle des contributions : <strong><?= number_format($avg_month, 2) ?> €</strong></p>
        <p>Nombre total d'adhérents : <strong><?= $count_adherents ?></strong></p>
        <p>Contributions : Cotisations: <strong><?= $cot_count ?></strong>, Dons: <strong><?= $don_count ?></strong>, Projets: <strong><?= $projet_count ?></strong></p>
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

    <!-- Projets -->
    <div id="projets" class="tab-content" style="display:none;">
        <h2>Liste des Contributions Projets</h2>
        <table>
            <tr>
                <th>Nom</th>
                <th>Montant (€)</th>
                <th>Date</th>
            </tr>
            <?php foreach ($data_projets as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['nom_complet']) ?></td>
                <td><?= number_format($p['montant'], 2) ?></td>
                <td><?= $p['jour_paiement'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Liste des Contributions (pagination) -->
    <div id="list" class="tab-content" style="display:none;">
        <h2>Liste des Contributions</h2>
        <p>Ici, vous pouvez ajouter des filtres et un bouton d'export.</p>
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

    <!-- Recherche Adhérent -->
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

    <!-- Cotisations Mensuelles -->
    <div id="cotisations_mensuelles" class="tab-content" style="display:none;">
        <h2>Cotisations Mensuelles - Année <?= htmlspecialchars($year) ?></h2>
        <table>
            <tr>
                <th>#</th>
                <th>Nom et Prénom</th>
                <?php foreach ($mois_labels as $ml): ?>
                    <th><?= $ml ?></th>
                <?php endforeach; ?>
            </tr>
            <?php $num=1; foreach ($adherents as $ad): 
                $idAd = $ad['id'];
                $monthly_fee = (float)$ad['monthly_fee'];
                $st = $db->prepare("SELECT month, paid_amount FROM Cotisation_Months WHERE id_adherent=? AND year=? ORDER BY month");
                $st->execute([$idAd, $year]);
                $mois_data = $st->fetchAll(PDO::FETCH_ASSOC);
                $mois_status = [];
                foreach ($mois_data as $md) {
                    $mois_status[$md['month']] = $md['paid_amount'];
                }

                $start = $ad['start_date'] ? new DateTime($ad['start_date']) : null;
                $end = $ad['end_date'] ? new DateTime($ad['end_date']) : null;
                ?>
                <tr>
                    <td><?= $num++ ?></td>
                    <td><?= htmlspecialchars($ad['nom']." ".$ad['prenom']) ?></td>
                    <?php for ($m=1; $m<=12; $m++):
                        $dateM = new DateTime("$year-$m-01");
                        $applicable = true;
                        if ($start && $dateM < $start) $applicable = false;
                        if ($end && $dateM > $end) $applicable = false;

                        if (!$applicable) {
                            echo "<td style='background-color:#ccc'>N/A</td>";
                        } else {
                            $paid_amount = $mois_status[$m] ?? 0;
                            $monthly_fee = (float)$ad['monthly_fee'];
                            if ($monthly_fee <= 0) {
                                // Pas de cotisation mensuelle
                                echo "<td style='background-color:#ccc'>N/A</td>";
                            } else {
                                $reste = $monthly_fee - $paid_amount;
                                if ($reste == 0) {
                                    // Mois entièrement payé
                                    echo "<td style='background-color:#aaffaa'>0€</td>";
                                } else {
                                    // Mois pas entièrement réglé, afficher le montant manquant en négatif
                                    // Par exemple, s'il manque 5€, afficher "-5€"
                                    echo "<td style='background-color:#ffaaaa'>-{$reste}€</td>";
                                }
                            }
                        }
                        
                    endfor; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div id="membres" class="tab-content" style="display:none;">
        <h2>Gestion des Membres</h2>
        <div style="margin-bottom:10px;">
            <input type="text" id="searchMembre" placeholder="Rechercher un membre par nom/email..." style="width:300px;margin-right:10px;">
            <button id="addMembreBtn">Ajouter un adhérent</button>
        </div>
        <table id="membresTable">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Monthly Fee</th>
                    <th>Début Adhésion</th>
                    <th>Fin Adhésion</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Rempli via JS -->
            </tbody>
        </table>
    </div>
    

</div>
<!-- Modale Ajouter/Modifier Adhérent -->
<div id="membreModal" class="modal hidden">
    <div class="modal-content">
        <span id="closeMembreModal" class="close">&times;</span>
        <h2 id="membreModalTitle">Ajouter un adhérent</h2>
        <form id="membreForm">
            <input type="hidden" name="id" id="membre_id">
            <label>Nom : <input type="text" name="nom" id="membre_nom" required></label>
            <label>Prénom : <input type="text" name="prenom" id="membre_prenom" required></label>
            <label>Email : <input type="email" name="email" id="membre_email" required></label>
            <label>Téléphone : <input type="text" name="telephone" id="membre_telephone" required></label>
            <label>Montant Mensuel (monthly_fee) : <input type="number" step="0.01" name="monthly_fee" id="membre_monthly_fee"></label>
            <label>Date début adhésion : <input type="date" name="start_date" id="membre_start_date"></label>
            <label>Date fin adhésion : <input type="date" name="end_date" id="membre_end_date"></label>
            <button type="submit" id="membreSaveBtn">Enregistrer</button>
        </form>
        <div id="membreError" class="error-message"></div>
    </div>
</div>

<!-- Modale Suppression -->
<div id="deleteModal" class="modal hidden">
    <div class="modal-content">
        <span id="closeDeleteModal" class="close">&times;</span>
        <h2>Supprimer Adhérent</h2>
        <p>Êtes-vous sûr de vouloir supprimer cet adhérent ?</p>
        <input type="hidden" id="delete_id">
        <button id="confirmDeleteBtn">Supprimer</button>
        <button id="cancelDeleteBtn">Annuler</button>
        <div id="deleteError" class="error-message"></div>
    </div>
</div>

<script>
    function showTab(tab) {
        document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
        document.getElementById(tab).style.display = 'block';
    }

    const searchMembre = document.getElementById('searchMembre');
    const membresTableBody = document.querySelector('#membresTable tbody');
    const addMembreBtn = document.getElementById('addMembreBtn');
    const membreModal = document.getElementById('membreModal');
    const closeMembreModal = document.getElementById('closeMembreModal');
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
    const closeDeleteModal = document.getElementById('closeDeleteModal');
    const delete_id = document.getElementById('delete_id');
    const deleteError = document.getElementById('deleteError');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');



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
                backgroundColor: ['green', 'orange', 'purple']
            }]
        }
    });

    // Recherche adhérent
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
    // MEMBRES-------------------
    function fetchAdherents(term='') {
        fetch('fetch_adherents.php?term='+encodeURIComponent(term))
            .then(r=>r.json())
            .then(data=>{
                membresTableBody.innerHTML = '';
                data.forEach(ad => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${ad.nom} ${ad.prenom}</td>
                        <td>${ad.email}</td>
                        <td>${ad.telephone}</td>
                        <td>${ad.monthly_fee ?? ''}</td>
                        <td>${ad.start_date ?? ''}</td>
                        <td>${ad.end_date ?? ''}</td>
                        <td>
                            <button class="editBtn" data-id="${ad.id}">Modifier</button>
                            <button class="deleteBtn" data-id="${ad.id}">Supprimer</button>
                        </td>
                    `;
                    membresTableBody.appendChild(tr);
                });

                document.querySelectorAll('.editBtn').forEach(btn=>{
                    btn.addEventListener('click', ()=>{
                        const id = btn.getAttribute('data-id');
                        editAdherent(id);
                    });
                });

                document.querySelectorAll('.deleteBtn').forEach(btn=>{
                    btn.addEventListener('click', ()=>{
                        const id = btn.getAttribute('data-id');
                        delete_id.value = id;
                        deleteError.textContent = '';
                        deleteModal.classList.remove('hidden');
                    });
                });
            })
            .catch(err=>console.error(err));
    }

    function editAdherent(id) {
        // Récupérer les infos de l'adhérent, on peut réutiliser fetch_adherents.php?term et filtrer côté client
        // Ou créer un endpoint fetch_one_adherent.php
        // Pour simplifier, on va filtrer côté client après avoir fetch la liste, ou faire un endpoint rapide.

        fetch('fetch_adherents.php?term=')
            .then(r=>r.json())
            .then(data=>{
                const adh = data.find(a=>a.id==id);
                if(!adh) return;
                membre_id.value = adh.id;
                membre_nom.value = adh.nom;
                membre_prenom.value = adh.prenom;
                membre_email.value = adh.email;
                membre_telephone.value = adh.telephone;
                membre_monthly_fee.value = adh.monthly_fee ?? '';
                membre_start_date.value = adh.start_date ?? '';
                membre_end_date.value = adh.end_date ?? '';
                membreModalTitle.textContent = "Modifier l'adhérent";
                membreError.textContent = '';
                membreModal.classList.remove('hidden');
            });
    }

    searchMembre.addEventListener('input', ()=>{
        fetchAdherents(searchMembre.value);
    });

    addMembreBtn.addEventListener('click', ()=>{
        membre_id.value='';
        membre_nom.value='';
        membre_prenom.value='';
        membre_email.value='';
        membre_telephone.value='';
        membre_monthly_fee.value='';
        membre_start_date.value='';
        membre_end_date.value='';
        membreModalTitle.textContent = "Ajouter un adhérent";
        membreError.textContent = '';
        membreModal.classList.remove('hidden');
    });

    closeMembreModal.addEventListener('click', ()=>{
        membreModal.classList.add('hidden');
    });

    membreForm.addEventListener('submit', (e)=>{
        e.preventDefault();
        const formData = new FormData(membreForm);
        let url = 'insert_adherent.php';
        if(membre_id.value) {
            // Si id existe => update
            url = 'update_adherent.php';
        }
        fetch(url, {
            method:'POST',
            body:formData
        })
        .then(r=>r.json())
        .then(resp=>{
            if(resp.success) {
                membreModal.classList.add('hidden');
                fetchAdherents(searchMembre.value);
            } else {
                membreError.textContent = resp.message;
            }
        })
        .catch(err=>console.error(err));
    });

    closeDeleteModal.addEventListener('click', ()=>{
        deleteModal.classList.add('hidden');
    });

    cancelDeleteBtn.addEventListener('click', ()=>{
        deleteModal.classList.add('hidden');
    });

    confirmDeleteBtn.addEventListener('click', ()=>{
        const formData = new FormData();
        formData.append('id', delete_id.value);
        fetch('delete_adherent.php', {
            method:'POST',
            body:formData
        })
        .then(r=>r.json())
        .then(resp=>{
            if(resp.success) {
                deleteModal.classList.add('hidden');
                fetchAdherents(searchMembre.value);
            } else {
                deleteError.textContent = resp.message;
            }
        })
        .catch(err=>console.error(err));
    });

    // Onglet par défaut
    showTab('stats');
    fetchAdherents();
</script>
</body>
</html>
