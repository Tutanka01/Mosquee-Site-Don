<?php
include 'db.php';
$currentMonth = date('Y-m');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Contributions - Mosquée Errahma</title>
    <link rel="stylesheet" href="style.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <div class="logo">
            <img src="mosque_logo.png" alt="Mosquée Errahma">
        </div>
        <h1>Gestion des Contributions</h1>
    </header>
    <main>
        <div class="container">
            <h2>Formulaire de Contribution</h2>
            <p id="infoMessage" class="info-message"></p>
            <form id="contributionForm" action="insert.php" method="POST">
                <fieldset>
                    <legend>Type de Contribution</legend>
                    <label for="type_contribution">Type de contribution :</label>
                    <select name="type_contribution" id="type_contribution">
                        <option value="cotisation">Cotisation</option>
                        <option value="don">Don</option>
                        <option value="projet">Projet</option>
                    </select>
                </fieldset>

                <fieldset>
                    <legend>Informations sur le Contributeur</legend>
                    
                    <div class="flex-row">
                        <div class="flex-item">
                            <label for="adherent_select">Adhérent existant :</label>
                            <select id="adherent_select" name="id_adherent">
                                <option value="">-- Sélectionner un adhérent --</option>
                            </select>
                            <button type="button" id="addAdherentBtn" class="secondary-btn">Ajouter un adhérent</button>
                            <p id="noAdherentMessage" class="error-message">Aucun adhérent disponible. Veuillez en ajouter un.</p>
                        </div>
                        <div class="flex-item">
                            <label for="nom">Nom Adhérent :</label>
                            <input type="text" name="nom" id="nom" placeholder="Nom de l'adhérent" disabled>
                        </div>
                        <div class="flex-item">
                            <label for="prenom">Prénom Adhérent :</label>
                            <input type="text" name="prenom" id="prenom" placeholder="Prénom de l'adhérent" disabled>
                        </div>
                        <div class="flex-item">
                            <label for="email">Email Adhérent :</label>
                            <input type="email" name="email" id="email" placeholder="Email" disabled>
                        </div>
                        <div class="flex-item">
                            <label for="telephone">Téléphone Adhérent :</label>
                            <input type="text" name="telephone" id="telephone" placeholder="Téléphone" disabled>
                        </div>
                    </div>

                    <label class="checkbox-label">
                        <input type="checkbox" name="anonyme" id="anonyme" value="1"> Rendre anonyme (pour dons/projets non adhérent)
                    </label>

                    <div id="nonAdherentFields" class="hidden">
                        <h3>Informations Donateur/Contributeur Non-Adhérent</h3>
                        <div class="flex-row">
                            <div class="flex-item">
                                <label for="nom_donateur">Nom du Donateur :</label>
                                <input type="text" name="nom_donateur" id="nom_donateur" placeholder="Nom">
                            </div>
                            <div class="flex-item">
                                <label for="prenom_donateur">Prénom du Donateur :</label>
                                <input type="text" name="prenom_donateur" id="prenom_donateur" placeholder="Prénom">
                            </div>
                            <div class="flex-item">
                                <label for="email_donateur">Email (facultatif) :</label>
                                <input type="email" name="email_donateur" id="email_donateur" placeholder="ex: email@domaine.com">
                            </div>
                            <div class="flex-item">
                                <label for="telephone_donateur">Téléphone (facultatif) :</label>
                                <input type="text" name="telephone_donateur" id="telephone_donateur" placeholder="ex: 0601020304">
                            </div>
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Détails de la Contribution</legend>
                    <div class="flex-row">
                        <div class="flex-item">
                            <label for="montant">Montant (€) :</label>
                            <input type="number" step="0.01" name="montant" id="montant" required placeholder="Montant en €">
                        </div>
                        <div class="flex-item">
                            <label for="type_paiement">Type de paiement :</label>
                            <select name="type_paiement" id="type_paiement" required>
                                <option value="">-- Choisir --</option>
                                <option value="espèces">Espèces</option>
                                <option value="carte">Carte</option>
                                <option value="virement">Virement</option>
                            </select>
                        </div>
                        <div class="flex-item">
                            <label for="mois">Mois (pour cotisation) :</label>
                            <input type="month" name="mois" id="mois" value="<?= htmlspecialchars($currentMonth); ?>">
                        </div>
                    </div>
                </fieldset>

                <button type="submit" class="primary-btn">Enregistrer</button>
            </form>
        </div>
    </main>
    <footer>
        © 2024 Mosquée Errahma
    </footer>

    <!-- Modal Add Adherent -->
    <div id="modalAddAdherent" class="modal hidden">
        <div class="modal-content">
            <span id="closeModal" class="close">&times;</span>
            <h2>Ajouter un Nouvel Adhérent</h2>
            <form id="newAdherentForm">
                <div class="flex-row">
                    <div class="flex-item">
                        <label for="new_nom">Nom :</label>
                        <input type="text" name="nom" id="new_nom" required placeholder="Nom">
                    </div>
                    <div class="flex-item">
                        <label for="new_prenom">Prénom :</label>
                        <input type="text" name="prenom" id="new_prenom" required placeholder="Prénom">
                    </div>
                </div>
                <div class="flex-row">
                    <div class="flex-item">
                        <label for="new_email">Email :</label>
                        <input type="email" name="email" id="new_email" required placeholder="email@exemple.com">
                    </div>
                    <div class="flex-item">
                        <label for="new_telephone">Téléphone :</label>
                        <input type="text" name="telephone" id="new_telephone" required placeholder="ex: 0601020304">
                    </div>
                </div>
                <div class="flex-row">
                    <div class="flex-item">
                        <label for="monthly_fee">Montant Mensuel (€) :</label>
                        <input type="number" step="0.01" name="monthly_fee" id="monthly_fee" placeholder="ex: 15.00">
                    </div>
                    <div class="flex-item">
                        <label for="start_date">Date Début Adhésion :</label>
                        <input type="date" name="start_date" id="start_date">
                    </div>
                    <div class="flex-item">
                        <label for="end_date">Date Fin Adhésion :</label>
                        <input type="date" name="end_date" id="end_date">
                    </div>
                </div>
                <button type="submit" class="primary-btn">Ajouter</button>
                <div id="adherentError" class="error-message"></div>
            </form>
        </div>
    </div>

    <!-- JavaScript Intégré -->
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
            const nonAdherentFields = document.getElementById('nonAdherentFields');
            const noAdherentMessage = document.getElementById('noAdherentMessage');
            const infoMessage = document.getElementById('infoMessage');

            // Charger tous les adhérents
            fetch('fetch_member.php')
                .then(res => res.json())
                .then(data => {
                    if (data.length === 0) {
                        noAdherentMessage.style.display = 'block';
                    } else {
                        noAdherentMessage.style.display = 'none';
                        data.forEach(adherent => {
                            const option = document.createElement('option');
                            option.value = adherent.id;
                            option.textContent = adherent.nom + ' ' + adherent.prenom;
                            adherentSelect.appendChild(option);
                        });
                    }
                })
                .catch(err => console.error(err));

            adherentSelect.addEventListener('change', () => {
                const adherentID = adherentSelect.value;
                if (adherentID) {
                    fetch('fetch_member_details.php?id=' + adherentID)
                        .then(r => r.json())
                        .then(info => {
                            nomField.value = info.nom || '';
                            prenomField.value = info.prenom || '';
                            emailField.value = info.email || '';
                            telField.value = info.telephone || '';
                            
                            nomField.disabled = true;
                            prenomField.disabled = true;
                            emailField.disabled = true;
                            telField.disabled = true;
                            updateFormState();
                        });
                } else {
                    nomField.value = '';
                    prenomField.value = '';
                    emailField.value = '';
                    telField.value = '';
                    nomField.disabled = true;
                    prenomField.disabled = true;
                    emailField.disabled = true;
                    telField.disabled = true;
                    updateFormState();
                }
            });

            addAdherentBtn.addEventListener('click', () => {
                modal.classList.remove('hidden');
            });

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
                        noAdherentMessage.style.display = 'none';
                        infoMessage.textContent = "Adhérent ajouté avec succès.";
                        setTimeout(() => {infoMessage.textContent = "";}, 3000);
                        updateFormState();
                    } else {
                        adherentError.textContent = resp.message;
                    }
                })
                .catch(err => console.error(err));
            });

            function updateFormState() {
                const isAnonyme = anonymeCheck.checked;
                const type = typeContribution.value;
                const adherentID = adherentSelect.value;

                if (type === 'cotisation') {
                    anonymeCheck.checked = false;
                    anonymeCheck.disabled = true;
                    adherentSelect.disabled = false;
                    addAdherentBtn.disabled = false;
                    nonAdherentFields.classList.add('hidden');
                } else {
                    anonymeCheck.disabled = false;
                    if (isAnonyme) {
                        adherentSelect.disabled = true;
                        addAdherentBtn.disabled = true;
                        nonAdherentFields.classList.add('hidden');
                    } else {
                        adherentSelect.disabled = false;
                        addAdherentBtn.disabled = false;
                        if (adherentID) {
                            nonAdherentFields.classList.add('hidden');
                        } else {
                            nonAdherentFields.classList.remove('hidden');
                        }
                    }
                }
            }

            anonymeCheck.addEventListener('change', updateFormState);
            typeContribution.addEventListener('change', updateFormState);

            document.getElementById('contributionForm').addEventListener('submit', (e) => {
                const type = typeContribution.value;
                const isAnonyme = anonymeCheck.checked;
                const montant = parseFloat(document.getElementById('montant').value);
                const paiement = document.getElementById('type_paiement').value;
                const adherentID = adherentSelect.value;

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
                } else if (type === 'don' || type === 'projet') {
                    if (isAnonyme) {
                        // ok pour don/projet anonyme
                    } else {
                        if (adherentID) {
                            // Don/Projet adhérent ok
                        } else {
                            // Don/Projet non adhérent
                            const nomDon = document.getElementById('nom_donateur').value.trim();
                            const prenomDon = document.getElementById('prenom_donateur').value.trim();
                            if (!nomDon || !prenomDon) {
                                alert("Pour un don/projet non adhérent, veuillez renseigner le nom et le prénom.");
                                e.preventDefault();
                                return;
                            }
                        }
                    }
                }
            });

            updateFormState();
        });
    </script>
</body>
</html>
