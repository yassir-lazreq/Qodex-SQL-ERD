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
('Quiz d''Histoire Mondiale', 'Un quiz couvrant des événements clés de l''histoire mondiale.', 2, 4, 45),
('Quiz de Sciences Générales', 'Un quiz pour évaluer vos connaissances en sciences générales.', 3, 1, 40),
('Quiz de Littérature Classique', 'Un quiz sur les œuvres classiques de la littérature.', 4, 4, 35);

INSERT INTO Questions (texte_question, reponse_correcte, points, id_quiz) VALUES
('Quelle est la capitale de la France?', 'Paris', 10, 2),
('Combien de continents y a-t-il sur Terre?', '7', 10, 2),
('Quel est le résultat de 5 + 7?', '12', 5, 1),
('Qui a écrit "Roméo et Juliette"?', 'William Shakespeare', 15, 4),
('Quelle est la formule chimique de l''eau?', 'H2O', 10, 3);

INSERT INTO Resultats (score, id_etudiant, id_quiz) VALUES
(85, 2, 1),
(90, 3, 2),
(75, 2, 3),
(80, 3, 4);

--@block
UPDATE Quiz SET duree_minutes = 25 WHERE id_quiz = 1;

--@block
SELECT * FROM Utilisateurs;

--@block
SELECT nom, email FROM Utilisateurs ;

--@block
SELECT * FROM quiz;

--@block
SELECT titre_quiz FROM Quiz;

--@block
SELECT * FROM categories;

--@block
SELECT * FROM utilisateurs WHERE role = 'enseignant';

--@block
SELECT * FROM utilisateurs WHERE role = 'etudiant';

--@block
SELECT * FROM quiz WHERE duree_minutes > 30;

--@block
SELECT * FROM quiz WHERE duree_minutes <= 45;

--@block
SELECT * FROM questions WHERE points >= 5;

--@block
SELECT * FROM quiz WHERE duree_minutes BETWEEN 20 AND 40;

--@block
SELECT * FROM resultats WHERE score >= 60;

--@block
SELECT * FROM resultats WHERE score < 50;

--@block
SELECT * FROM questions WHERE points BETWEEN 5 AND 15;

--@block
SELECT * FROM questions WHERE id_enseignant = 1;

--@block
SELECT * FROM quiz ORDER BY duree_minutes ASC;

--@block
SELECT * FROM resultats ORDER BY score DESC;

--@block
SELECT * FROM resultats ORDER BY score DESC LIMIT 3;

--@block
SELECT * FROM questions ORDER BY points ASC;

--@block
SELECT * FROM resultats ORDER BY date_passage DESC LIMIT 3;

--@block
SELECT q.titre_quiz, c.nom_categorie FROM Quiz q JOIN categories c ON q.id_categorie = c.id_categorie;

--@block
