    </main>
    
    <footer style="background: #2c3e50; color: white; padding: 30px 20px; margin-top: auto;">
        <div style="max-width: 1400px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
            <div>
                <h3 style="margin-bottom: 15px; font-size: 1.3em;">📚 Qodex</h3>
                <p style="color: #bdc3c7; line-height: 1.6;">
                    Plateforme de quiz en ligne pour faciliter l'apprentissage et l'évaluation des étudiants.
                </p>
            </div>
            
            <div>
                <h4 style="margin-bottom: 15px; font-size: 1.1em;">Liens rapides</h4>
                <ul style="list-style: none; color: #bdc3c7; line-height: 2;">
                    <li><a href="dashboard.php" style="color: #bdc3c7; text-decoration: none;">Tableau de bord</a></li>
                    <li><a href="categories.php" style="color: #bdc3c7; text-decoration: none;">Catégories</a></li>
                    <li><a href="add_quiz.php" style="color: #bdc3c7; text-decoration: none;">Créer un Quiz</a></li>
                    <li><a href="manage_quizzes.php" style="color: #bdc3c7; text-decoration: none;">Gérer mes Quiz</a></li>
                </ul>
            </div>
            
            <div>
                <h4 style="margin-bottom: 15px; font-size: 1.1em;">Support</h4>
                <ul style="list-style: none; color: #bdc3c7; line-height: 2;">
                    <li><a href="#" style="color: #bdc3c7; text-decoration: none;">Documentation</a></li>
                    <li><a href="#" style="color: #bdc3c7; text-decoration: none;">Aide</a></li>
                    <li><a href="#" style="color: #bdc3c7; text-decoration: none;">Contact</a></li>
                    <li><a href="#" style="color: #bdc3c7; text-decoration: none;">Signaler un bug</a></li>
                </ul>
            </div>
            
            <div>
                <h4 style="margin-bottom: 15px; font-size: 1.1em;">Statistiques</h4>
                <div style="color: #bdc3c7; line-height: 2;">
                    <?php
                    // Get teacher's statistics
                    if (isset($user_id)) {
                        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM quizzes WHERE enseignant_id = ?");
                        $stmt->execute([$user_id]);
                        $quiz_count = $stmt->fetch()['total'];
                        
                        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM questions q JOIN quizzes qz ON q.quiz_id = qz.id WHERE qz.enseignant_id = ?");
                        $stmt->execute([$user_id]);
                        $question_count = $stmt->fetch()['total'];
                        
                        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM results r JOIN quizzes q ON r.quiz_id = q.id WHERE q.enseignant_id = ?");
                        $stmt->execute([$user_id]);
                        $attempt_count = $stmt->fetch()['total'];
                        
                        echo "<p>📝 $quiz_count Quiz créés</p>";
                        echo "<p>❓ $question_count Questions</p>";
                        echo "<p>✅ $attempt_count Tentatives</p>";
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div style="max-width: 1400px; margin: 30px auto 0; padding-top: 20px; border-top: 1px solid #34495e; text-align: center; color: #bdc3c7;">
            <p>&copy; <?php echo date('Y'); ?> Qodex - Tous droits réservés | Espace Enseignant</p>
        </div>
    </footer>
</body>
</html>
