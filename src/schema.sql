CREATE TABLE IF NOT EXISTS Adherents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT,
    prenom TEXT,
    email TEXT UNIQUE,
    telephone TEXT,
    date_inscription DATE DEFAULT (CURRENT_DATE),
    anonyme BOOLEAN DEFAULT 0,
    donateur_temporaire BOOLEAN DEFAULT 0
);

CREATE TABLE IF NOT EXISTS Contributions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_adherent INT NULL,
    type_contribution TEXT NOT NULL CHECK (type_contribution IN ('cotisation', 'don')),
    montant DECIMAL(10, 2) NOT NULL,
    type_paiement TEXT NOT NULL CHECK (type_paiement IN ('espèces', 'carte', 'virement')),
    mois TEXT,
    jour_paiement DATE DEFAULT (CURRENT_DATE),
    heure_paiement TIME,
    anonyme BOOLEAN DEFAULT 0,
    nom_donateur TEXT,
    prenom_donateur TEXT,
    email_donateur TEXT,
    telephone_donateur TEXT,
    FOREIGN KEY (id_adherent) REFERENCES Adherents(id) ON DELETE CASCADE
);
