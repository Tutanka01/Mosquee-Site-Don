-- Table des adhérents (inchangée)
CREATE TABLE Adherents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT,
    prenom TEXT,
    email TEXT UNIQUE,
    telephone TEXT,
    date_inscription DATE DEFAULT (CURRENT_DATE),
    anonyme BOOLEAN DEFAULT FALSE, -- Pour les dons anonymes
    donateur_temporaire BOOLEAN DEFAULT FALSE -- Pour les utilisateurs ponctuels
);

-- Table des contributions (cotisations et dons)
CREATE TABLE Contributions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_adherent INT NULL, -- Null pour les dons anonymes
    type_contribution TEXT NOT NULL CHECK (type_contribution IN ('cotisation', 'don')),
    montant DECIMAL(10, 2) NOT NULL,
    type_paiement TEXT NOT NULL CHECK (type_paiement IN ('espèces', 'carte', 'virement')),
    mois TEXT,                     -- Pour les cotisations
    jour_paiement DATE DEFAULT (CURRENT_DATE), -- Pour les dons/cotisations
    heure_paiement TIME,           -- Heure pour les dons
    FOREIGN KEY (id_adherent) REFERENCES Adherents(id) ON DELETE CASCADE
);
