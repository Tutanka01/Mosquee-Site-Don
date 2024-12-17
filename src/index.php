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
        <form id="contributionForm" action="insert.php" method="POST">
            <!-- Détails Adhérent -->
            <fieldset>
                <legend>Informations de l'Adhérent</legend>
                
                <!-- Liste déroulante des adhérents existants -->
                <label for="adherent_select">Adhérent existant :
                    <select id="adherent_select" name="id_adherent">
                        <option value="">-- Sélectionner un adhérent --</option>
                    </select>
                </label>
                <button type="button" id="addAdherentBtn">Ajouter un nouvel adhérent</button>

                <label>Nom : <input type="text" name="nom" id="nom" disabled></label>
                <label>Prénom : <input type="text" name="prenom" id="prenom" disabled></label>
                <label>Email : <input type="email" name="email" id="email" disabled></label>
                <label>Téléphone : <input type="text" name="telephone" id="telephone" disabled></label>

                <label>
                    <input type="checkbox" name="anonyme" id="anonyme" value="1"> Rendre anonyme
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
                <label>Montant : <input type="number" step="0.01" name="montant" id="montant" required></label>
                <label>Type de paiement :
                    <select name="type_paiement" id="type_paiement" required>
                        <option value="">-- Choisir --</option>
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

    <!-- Modal pour ajout d'un nouvel adhérent -->
    <div id="modalAddAdherent" class="modal hidden">
        <div class="modal-content">
            <span id="closeModal" class="close">&times;</span>
            <h2>Ajouter un nouvel adhérent</h2>
            <form id="newAdherentForm">
                <label>Nom : <input type="text" name="nom" required></label>
                <label>Prénom : <input type="text" name="prenom" required></label>
                <label>Email : <input type="email" name="email" required></label>
                <label>Téléphone : <input type="text" name="telephone" required></label>
                <button type="submit">Ajouter</button>
            </form>
            <div id="adherentError" class="error-message"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const adherentSelect = document.getElementById('adherent_select');
            const addAdherentBtn = document.getElementById('addAdherentBtn');
            const modal = document.getElementById('modalAddAdherent');
            const closeModal = document.getElementById('closeModal');
            const newAdherentForm = document.getElementById('newAdherentForm');
            const adherentError = document.getElementById('adherentError');
            
            const nomField = document.getElementById('nom');
            const prenomField = document.getElementById('prenom');
            const emailField = document.getElementById('email');
            const telField = document.getElementById('telephone');
            const anonymeCheck = document.getElementById('anonyme');
            const typeContribution = document.getElementById('type_contribution');
            const form = document.getElementById('contributionForm');

            // Fetch des adhérents existants
            fetch('fetch_member.php')
                .then(res => res.json())
                .then(data => {
                    data.forEach(adherent => {
                        const option = document.createElement('option');
                        option.value = adherent.id;
                        option.textContent = adherent.nom + ' ' + adherent.prenom;
                        adherentSelect.appendChild(option);
                    });
                })
                .catch(err => console.error(err));

            // Quand on change l'adhérent sélectionné
            adherentSelect.addEventListener('change', () => {
                const adherentID = adherentSelect.value;
                if (adherentID) {
                    fetch('fetch_member_details.php?id=' + adherentID)
                        .then(r => r.json())
                        .then(info => {
                            nomField.value = info.nom;
                            prenomField.value = info.prenom;
                            emailField.value = info.email;
                            telField.value = info.telephone;
                            
                            nomField.disabled = true;
                            prenomField.disabled = true;
                            emailField.disabled = true;
                            telField.disabled = true;
                        });
                } else {
                    // Aucun adhérent sélectionné
                    nomField.value = '';
                    prenomField.value = '';
                    emailField.value = '';
                    telField.value = '';

                    nomField.disabled = true;
                    prenomField.disabled = true;
                    emailField.disabled = true;
                    telField.disabled = true;
                }
            });

            // Ouverture du modal
            addAdherentBtn.addEventListener('click', () => {
                modal.classList.remove('hidden');
            });

            // Fermeture du modal
            closeModal.addEventListener('click', () => {
                modal.classList.add('hidden');
                newAdherentForm.reset();
                adherentError.textContent = '';
            });

            window.addEventListener('click', (e) => {
                if (e.target == modal) {
                    modal.classList.add('hidden');
                    newAdherentForm.reset();
                    adherentError.textContent = '';
                }
            });

            // Soumission du nouveau adhérent
            newAdherentForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(newAdherentForm);
                fetch('insert_adherent.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(resp => {
                    if (resp.success) {
                        // Ajouter l'adhérent dans la liste
                        const option = document.createElement('option');
                        option.value = resp.id;
                        option.textContent = resp.nom + ' ' + resp.prenom;
                        adherentSelect.appendChild(option);
                        adherentSelect.value = resp.id;

                        nomField.value = resp.nom;
                        prenomField.value = resp.prenom;
                        emailField.value = resp.email;
                        telField.value = resp.telephone;
                        nomField.disabled = true;
                        prenomField.disabled = true;
                        emailField.disabled = true;
                        telField.disabled = true;

                        modal.classList.add('hidden');
                        newAdherentForm.reset();
                        adherentError.textContent = '';
                    } else {
                        adherentError.textContent = resp.message;
                    }
                })
                .catch(err => console.error(err));
            });

            // Gestion de l'anonymat et du type de contribution
            function updateFormState() {
                const isAnonyme = anonymeCheck.checked;
                const type = typeContribution.value;

                if (type === 'cotisation') {
                    // Cotisation = pas d’anonyme possible
                    anonymeCheck.disabled = false;
                    anonymeCheck.checked = false;
                    anonymeCheck.disabled = true;
                    // Adhérent obligatoire
                    adherentSelect.disabled = false;
                    addAdherentBtn.disabled = false;
                } else {
                    // don
                    anonymeCheck.disabled = false;
                    if (isAnonyme) {
                        adherentSelect.disabled = true;
                        addAdherentBtn.disabled = true;
                        nomField.value = '';
                        prenomField.value = '';
                        emailField.value = '';
                        telField.value = '';

                        nomField.disabled = true;
                        prenomField.disabled = true;
                        emailField.disabled = true;
                        telField.disabled = true;
                    } else {
                        adherentSelect.disabled = false;
                        addAdherentBtn.disabled = false;
                    }
                }
            }

            anonymeCheck.addEventListener('change', updateFormState);
            typeContribution.addEventListener('change', updateFormState);
            updateFormState(); // Initialiser l'état

            // Validation avant envoi
            form.addEventListener('submit', (e) => {
                const type = typeContribution.value;
                const isAnonyme = anonymeCheck.checked;
                const montant = parseFloat(document.getElementById('montant').value);
                const paiement = document.getElementById('type_paiement').value;
                const adherentID = adherentSelect.value;

                // Vérifications
                if (!paiement) {
                    alert("Veuillez choisir un type de paiement.");
                    e.preventDefault();
                    return;
                }

                if (isNaN(montant) || montant <= 0) {
                    alert("Le montant doit être un nombre positif.");
                    e.preventDefault();
                    return;
                }

                if (type === 'cotisation') {
                    if (isAnonyme) {
                        alert("Une cotisation ne peut pas être anonyme.");
                        e.preventDefault();
                        return;
                    }
                    if (!adherentID) {
                        alert("Veuillez sélectionner ou ajouter un adhérent pour une cotisation.");
                        e.preventDefault();
                        return;
                    }
                } else {
                    // don
                    if (!isAnonyme && !adherentID) {
                        alert("Pour un don non anonyme, veuillez sélectionner ou ajouter un adhérent.");
                        e.preventDefault();
                        return;
                    }
                }
            });
        });
    </script>
</body>
</html>
