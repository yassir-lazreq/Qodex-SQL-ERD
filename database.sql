--@block
DROP TABLE IF EXISTS Resultats;
DROP TABLE IF EXISTS Questions;
DROP TABLE IF EXISTS Quiz;
DROP TABLE IF EXISTS Categories;
DROP TABLE IF EXISTS Utilisateurs;

--@block
CREATE TABLE Utilisateurs (
    id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    email VARCHAR(70) UNIQUE NOT NULL,
    motdepasse VARCHAR(255) NOT NULL,
    role ENUM('enseignant', 'etudiant') NOT NULL
);

CREATE TABLE Categories (
    id_categorie INT AUTO_INCREMENT PRIMARY KEY,
    nom_categorie VARCHAR(100) NOT NULL
);

CREATE TABLE Quiz (
    id_quiz INT AUTO_INCREMENT PRIMARY KEY,
    titre_quiz VARCHAR(100) NOT NULL,
    description TEXT,
    id_categorie INT NOT NULL,
    id_enseignant INT NOT NULL,
    duree_minutes INT NOT NULL,

    FOREIGN KEY (id_categorie) REFERENCES Categories(id_categorie)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (id_enseignant) REFERENCES Utilisateurs(id_utilisateur)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

CREATE TABLE Questions (
    id_question INT AUTO_INCREMENT PRIMARY KEY,
    texte_question TEXT NOT NULL,
    reponse_correcte VARCHAR(255) NOT NULL,
    points INT NOT NULL,
    id_quiz INT NOT NULL,

    FOREIGN KEY (id_quiz) REFERENCES Quiz(id_quiz)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE Resultats (
    id_resultat INT AUTO_INCREMENT PRIMARY KEY,
    score INT NOT NULL,
    date_passage DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_etudiant INT NOT NULL,
    id_quiz INT NOT NULL,

    FOREIGN KEY (id_etudiant) REFERENCES Utilisateurs(id_utilisateur)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (id_quiz) REFERENCES Quiz(id_quiz)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

--@block
INSERT INTO Categories (nom_categorie) VALUES
('Mathématiques'),
('Histoire'),
('Sciences'),
('Littérature');

INSERT INTO Utilisateurs (nom, email, motdepasse, role) VALUES
('Alice Dupont', 'alice.dupont@example.com', 'password123', 'enseignant'),
('Bob Martin', 'bob.martin@example.com', 'password456', 'etudiant'),
('Claire Bernard', 'claire.bernard@example.com', 'password789', 'etudiant'),
('David Leroy', 'david.leroy@example.com', 'password101', 'enseignant');

INSERT INTO Quiz (titre_quiz, description, id_categorie, id_enseignant, duree_minutes) VALUES
('Quiz de Mathématiques de Base', 'Un quiz pour tester vos connaissances en mathématiques de base.', 1, 1, 30),
('Quiz d\'Histoire Mondiale', 'Un quiz couvrant des événements clés de l\'histoire mondiale.', 2, 4, 45),
('Quiz de Sciences Générales', 'Un quiz pour évaluer vos connaissances en sciences générales.', 3, 1, 40),
('Quiz de Littérature Classique', 'Un quiz sur les œuvres classiques de la littérature.', 4, 4, 35);

INSERT INTO Questions (texte_question, reponse_correcte, points, id_quiz) VALUES
('Quelle est la valeur de π (pi) arrondie à deux décimales ?', '3.14', 5, 1),
('Qui a écrit "Les Misérables" ?', 'Victor Hugo', 5, 4),
('Quel est l élément chimique avec le symbole O ?', 'Oxygène', 5, 3),
('En quelle année a eu lieu la Révolution française ?', '1789', 5, 2);

INSERT INTO Resultats (score, date_passage, id_etudiant, id_quiz) VALUES
(85, '2024-01-15 10:30:00', 2, 1),
(90, '2024-01-16 11:00:00', 3, 2),
(75, '2024-01-17 09:45:00', 2, 3),
(88, '2024-01-18 14:20:00', 3, 4);

--@block
SELECT * FROM Utilisateurs;
SELECT * FROM Categories;
SELECT * FROM Quiz;
SELECT * FROM Questions;
SELECT * FROM Resultats;