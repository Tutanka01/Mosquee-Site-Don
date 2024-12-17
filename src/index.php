<?php
include 'db.php';

// Obtenir le mois actuel pour le pré-remplissage
$currentMonth = date('Y-m');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Contributions - Mosquée Errahma</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Formulaire de Contribution</h1>
        <form action="insert.php" method="POST">
            <!-- Détails Adhérent -->
            <fieldset>
                <legend>Informations de l'Adhérent</legend>
                <label>Nom : <input type="text" name="nom"></label>
                <label>Prénom : <input type="text" name="prenom"></label>
                <label>Email : <input type="email" name="email"></label>
                <label>Téléphone : <input type="text" name="telephone"></label>
                <label>
                    <input type="checkbox" name="anonyme" value="1"> Rendre anonyme
                </label>
            </fieldset>

            <!-- Détails Contribution -->
            <fieldset>
                <legend>Contribution</legend>
                <label>Type de contribution :
                    <select name="type_contribution" id="type_contribution">
                        <option value="cotisation">Cotisation</option>
                        <option value="don">Don</option>
                    </select>
                </label>
                <label>Montant : <input type="number" step="0.01" name="montant" required></label>
                <label>Type de paiement :
                    <select name="type_paiement">
                        <option value="espèces">Espèces</option>
                        <option value="carte">Carte</option>
                        <option value="virement">Virement</option>
                    </select>
                </label>
                <label>Mois : 
                    <input type="month" name="mois" value="<?= $currentMonth; ?>">
                </label>
            </fieldset>

            <button type="submit">Enregistrer</button>
        </form>
    </div>
    <footer>&copy; 2024 Mosquée Errahma</footer>
</body>
</html>