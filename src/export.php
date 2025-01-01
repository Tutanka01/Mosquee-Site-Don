<?php
// export.php

include 'db.php';

// Inclure Dompdf
require_once __DIR__ . '/libs/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Récupérer les paramètres
$tab = $_GET['tab'] ?? 'list';
$filter_type = $_GET['filter_type'] ?? '';
$filter_year = $_GET['filter_year'] ?? date('Y');

// Définir les requêtes en fonction de l'onglet
switch ($tab) {
    case 'list':
        // Requête pour la liste des contributions
        $query = "
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
        ";

        $stmt = $db->prepare($query);
        if ($filter_type) {
            $stmt->bindValue(':type', $filter_type);
        }
        if ($filter_year) {
            $stmt->bindValue(':year', $filter_year);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    // Vous pouvez ajouter d'autres cas pour exporter d'autres onglets si nécessaire

    default:
        $data = [];
        break;
}

// Générer le contenu HTML du PDF
$html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Exportation des Contributions</title>
    <style>
        body {
            font-family: 'Open Sans', Arial, sans-serif;
            font-size: 12px;
            color: #333;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #2B6CB0;
        }
        .header p {
            margin: 5px 0;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th {
            background-color: #2B6CB0;
            color: white;
            padding: 10px;
            text-align: left;
        }
        td {
            padding: 8px;
            text-align: left;
        }
        .footer {
            text-align: center;
            font-size: 10px;
            color: #777;
            position: fixed;
            bottom: 10px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Exportation des Contributions</h1>
        <p>Année : $filter_year</p>
        <p>Type de Contribution : " . ($filter_type ? ucfirst($filter_type) : 'Tous') . "</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nom Complet</th>
                <th>Type</th>
                <th>Montant (€)</th>
                <th>Date de Paiement</th>
            </tr>
        </thead>
        <tbody>
HTML;

foreach ($data as $contribution) {
    $nom_complet = htmlspecialchars($contribution['nom_complet']);
    $type_contribution = htmlspecialchars(ucfirst($contribution['type_contribution']));
    $montant = number_format($contribution['montant'], 2);
    $jour_paiement = htmlspecialchars($contribution['jour_paiement']);

    $html .= <<<HTML
            <tr>
                <td>{$nom_complet}</td>
                <td>{$type_contribution}</td>
                <td>{$montant}</td>
                <td>{$jour_paiement}</td>
            </tr>
HTML;
}

$html .= <<<HTML
        </tbody>
    </table>

    <div class="footer">
        <p>© 2025 Mosquée Errahma - Exportation des Données</p>
    </div>
</body>
</html>
HTML;

// Initialiser Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true); // Pour charger les polices via Google Fonts ou autres ressources externes
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// (Optionnel) Définir le format et l'orientation du papier
$dompdf->setPaper('A4', 'portrait');

// Rendre le HTML en PDF
$dompdf->render();

// Générer le nom du fichier
$filename = "export_contributions_" . $filter_year . ".pdf";

// Envoyer le PDF au navigateur pour téléchargement
$dompdf->stream($filename, ["Attachment" => true]);
exit;
?>
