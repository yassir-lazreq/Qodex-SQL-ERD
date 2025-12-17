        </main>
    </div>
    
    <footer class="bg-gray-800 text-white mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <i class="fas fa-graduation-cap text-3xl text-indigo-400"></i>
                        <span class="ml-2 text-2xl font-bold">Qodex</span>
                    </div>
                    <p class="text-gray-400 text-sm">
                        Plateforme de quiz en ligne pour faciliter l'apprentissage et l'évaluation des étudiants.
                    </p>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Liens rapides</h4>
                    <ul class="space-y-2">
                        <li><a href="dashboard.php" class="text-gray-400 hover:text-white transition"><i class="fas fa-home mr-2"></i>Tableau de bord</a></li>
                        <li><a href="categories.php" class="text-gray-400 hover:text-white transition"><i class="fas fa-folder mr-2"></i>Catégories</a></li>
                        <li><a href="add_quiz.php" class="text-gray-400 hover:text-white transition"><i class="fas fa-plus-circle mr-2"></i>Créer un Quiz</a></li>
                        <li><a href="manage_quizzes.php" class="text-gray-400 hover:text-white transition"><i class="fas fa-clipboard-list mr-2"></i>Gérer mes Quiz</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Support</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition"><i class="fas fa-book mr-2"></i>Documentation</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition"><i class="fas fa-question-circle mr-2"></i>Aide</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition"><i class="fas fa-envelope mr-2"></i>Contact</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition"><i class="fas fa-bug mr-2"></i>Signaler un bug</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Vos Statistiques</h4>
                    <div class="space-y-2 text-gray-400">
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
                            
                            echo "<p><i class='fas fa-clipboard-list mr-2 text-indigo-400'></i>$quiz_count Quiz créés</p>";
                            echo "<p><i class='fas fa-question-circle mr-2 text-indigo-400'></i>$question_count Questions</p>";
                            echo "<p><i class='fas fa-user-check mr-2 text-indigo-400'></i>$attempt_count Tentatives</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> Qodex - Tous droits réservés | Espace Enseignant</p>
            </div>
        </div>
    </footer>
</body>
</html>
