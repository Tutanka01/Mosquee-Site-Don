DROP TABLE IF EXISTS Cotisation_Months;
DROP TABLE IF EXISTS Contributions;
DROP TABLE IF EXISTS Adherents;

CREATE TABLE Adherents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT,
    prenom TEXT,
    email TEXT UNIQUE,
    telephone TEXT,
    date_inscription DATE DEFAULT (CURRENT_DATE),
    anonyme BOOLEAN DEFAULT 0,
    donateur_temporaire BOOLEAN DEFAULT 0,
    monthly_fee DECIMAL(10,2) DEFAULT 0,
    start_date DATE,
    end_date DATE
);

CREATE TABLE Contributions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_adherent INT NULL,
    type_contribution TEXT NOT NULL CHECK (type_contribution IN ('cotisation', 'don', 'projet')),
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
    FOREIGN KEY (id_adherent) REFERENCES Adherents(id)
);

CREATE TABLE Cotisation_Months (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_adherent INT NOT NULL,
    year INT NOT NULL,
    month INT NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0, -- Montant déjà payé pour ce mois
    FOREIGN KEY (id_adherent) REFERENCES Adherents(id)
);
