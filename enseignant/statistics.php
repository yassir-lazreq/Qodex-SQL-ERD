<?php
session_start();
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'enseignant') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['nom'];

try {
    $stmt = $conn->prepare('SELECT id, nom, email FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header("Location: ../auth/login.php");
        exit();
    }
} catch (PDOException $e) {
    die("Error verifying user: " . $e->getMessage());
}

// Get overall statistics
try {
    // Total quizzes
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM quizzes WHERE enseignant_id = ?");
    $stmt->execute([$user_id]);
    $total_quizzes = $stmt->fetch()['count'];

    // Total questions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM questions q INNER JOIN quizzes qz ON q.quiz_id = qz.id WHERE qz.enseignant_id = ?");
    $stmt->execute([$user_id]);
    $total_questions = $stmt->fetch()['count'];

    // Total categories
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM categories WHERE created_by = ?");
    $stmt->execute([$user_id]);
    $total_categories = $stmt->fetch()['count'];

    // Total students who attempted
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT r.etudiant_id) as count FROM results r INNER JOIN quizzes q ON r.quiz_id = q.id WHERE q.enseignant_id = ?");
    $stmt->execute([$user_id]);
    $total_students = $stmt->fetch()['count'];

    // Total attempts
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM results r INNER JOIN quizzes q ON r.quiz_id = q.id WHERE q.enseignant_id = ?");
    $stmt->execute([$user_id]);
    $total_attempts = $stmt->fetch()['count'];

    // Average score
    $stmt = $conn->prepare("SELECT AVG(r.score * 100.0 / r.total_questions) as avg FROM results r INNER JOIN quizzes q ON r.quiz_id = q.id WHERE q.enseignant_id = ?");
    $stmt->execute([$user_id]);
    $average_score = $stmt->fetch()['avg'] ?? 0;

    // Success rate
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN (r.score * 100.0 / r.total_questions) >= 50 THEN 1 ELSE 0 END) as passed,
            COUNT(*) as total
        FROM results r 
        INNER JOIN quizzes q ON r.quiz_id = q.id 
        WHERE q.enseignant_id = ?
    ");
    $stmt->execute([$user_id]);
    $success_data = $stmt->fetch();
    $success_rate = $success_data['total'] > 0 ? ($success_data['passed'] / $success_data['total']) * 100 : 0;

} catch (PDOException $e) {
    $total_quizzes = $total_questions = $total_categories = $total_students = $total_attempts = 0;
    $average_score = $success_rate = 0;
}

// Quiz performance stats
try {
    $stmt = $conn->prepare("
        SELECT 
            q.id,
            q.titre,
            c.nom as categorie,
            COUNT(DISTINCT qs.id) as question_count,
            COUNT(DISTINCT r.id) as attempt_count,
            AVG(r.score * 100.0 / r.total_questions) as avg_score,
            MAX(r.score * 100.0 / r.total_questions) as max_score,
            MIN(r.score * 100.0 / r.total_questions) as min_score
        FROM quizzes q
        LEFT JOIN categories c ON q.categorie_id = c.id
        LEFT JOIN questions qs ON q.id = qs.quiz_id
        LEFT JOIN results r ON q.id = r.quiz_id
        WHERE q.enseignant_id = ?
        GROUP BY q.id
        ORDER BY attempt_count DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $quiz_stats = $stmt->fetchAll();
} catch (PDOException $e) {
    $quiz_stats = [];
}

// Category performance
try {
    $stmt = $conn->prepare("
        SELECT 
            c.nom as categorie,
            COUNT(DISTINCT q.id) as quiz_count,
            COUNT(DISTINCT r.id) as attempt_count,
            AVG(r.score * 100.0 / r.total_questions) as avg_score
        FROM categories c
        LEFT JOIN quizzes q ON c.id = q.categorie_id AND q.enseignant_id = ?
        LEFT JOIN results r ON q.id = r.quiz_id
        WHERE c.created_by = ?
        GROUP BY c.id
        HAVING quiz_count > 0
        ORDER BY attempt_count DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $category_stats = $stmt->fetchAll();
} catch (PDOException $e) {
    $category_stats = [];
}

// Top performing students
try {
    $stmt = $conn->prepare("
        SELECT 
            u.nom as etudiant,
            u.email,
            COUNT(r.id) as attempt_count,
            AVG(r.score * 100.0 / r.total_questions) as avg_score,
            SUM(CASE WHEN (r.score * 100.0 / r.total_questions) >= 50 THEN 1 ELSE 0 END) as passed_count
        FROM users u
        INNER JOIN results r ON u.id = r.etudiant_id
        INNER JOIN quizzes q ON r.quiz_id = q.id
        WHERE q.enseignant_id = ?
        GROUP BY u.id
        ORDER BY avg_score DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $top_students = $stmt->fetchAll();
} catch (PDOException $e) {
    $top_students = [];
}

// Recent activity
try {
    $stmt = $conn->prepare("
        SELECT 
            r.completed_at,
            u.nom as etudiant,
            q.titre as quiz,
            r.score,
            r.total_questions,
            (r.score * 100.0 / r.total_questions) as percentage
        FROM results r
        INNER JOIN users u ON r.etudiant_id = u.id
        INNER JOIN quizzes q ON r.quiz_id = q.id
        WHERE q.enseignant_id = ?
        ORDER BY r.completed_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_activity = $stmt->fetchAll();
} catch (PDOException $e) {
    $recent_activity = [];
}

$page_title = 'Statistiques - Qodex';
include '../includes_enseignant/header.php';
?>

<!-- Statistics Section -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-900">Statistiques & Analyses</h2>
        <p class="text-gray-600 mt-2">Vue d'ensemble des performances</p>
    </div>

    <!-- Overview Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Quiz</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo $total_quizzes; ?></p>
                </div>
                <div class="bg-blue-100 p-3 rounded-lg">
                    <i class="fas fa-clipboard-list text-blue-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Questions</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo $total_questions; ?></p>
                </div>
                <div class="bg-purple-100 p-3 rounded-lg">
                    <i class="fas fa-question-circle text-purple-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Catégories</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo $total_categories; ?></p>
                </div>
                <div class="bg-indigo-100 p-3 rounded-lg">
                    <i class="fas fa-folder text-indigo-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Étudiants</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo $total_students; ?></p>
                </div>
                <div class="bg-green-100 p-3 rounded-lg">
                    <i class="fas fa-user-graduate text-green-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Tentatives</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo $total_attempts; ?></p>
                </div>
                <div class="bg-yellow-100 p-3 rounded-lg">
                    <i class="fas fa-clipboard-check text-yellow-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Score Moyen</p>
                    <p class="text-3xl font-bold text-indigo-600"><?php echo number_format($average_score, 1); ?>%</p>
                </div>
                <div class="bg-indigo-100 p-3 rounded-lg">
                    <i class="fas fa-chart-line text-indigo-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Taux Réussite</p>
                    <p class="text-3xl font-bold text-green-600"><?php echo number_format($success_rate, 1); ?>%</p>
                </div>
                <div class="bg-green-100 p-3 rounded-lg">
                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Avg Questions/Quiz</p>
                    <p class="text-3xl font-bold text-gray-900">
                        <?php echo $total_quizzes > 0 ? number_format($total_questions / $total_quizzes, 1) : 0; ?>
                    </p>
                </div>
                <div class="bg-pink-100 p-3 rounded-lg">
                    <i class="fas fa-list text-pink-600 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Quiz Performance -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-chart-bar text-indigo-600 mr-2"></i>Performance par Quiz
            </h3>
            
            <?php if (count($quiz_stats) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($quiz_stats as $stat): ?>
                <div class="border-l-4 border-indigo-500 pl-4 py-2">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h4 class="font-bold text-gray-900"><?php echo htmlspecialchars($stat['titre']); ?></h4>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($stat['categorie'] ?: 'Sans catégorie'); ?></p>
                        </div>
                        <span class="text-sm font-semibold text-indigo-600">
                            <?php echo $stat['attempt_count']; ?> tentatives
                        </span>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <span class="text-gray-600">
                            <i class="fas fa-question-circle mr-1"></i><?php echo $stat['question_count']; ?> Q
                        </span>
                        <span class="text-green-600 font-semibold">
                            Moy: <?php echo number_format($stat['avg_score'] ?? 0, 1); ?>%
                        </span>
                        <?php if ($stat['attempt_count'] > 0): ?>
                        <span class="text-gray-500 text-xs">
                            Min: <?php echo number_format($stat['min_score'], 0); ?>% | Max: <?php echo number_format($stat['max_score'], 0); ?>%
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-center py-8">Aucune donnée disponible</p>
            <?php endif; ?>
        </div>

        <!-- Category Performance -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-folder-open text-purple-600 mr-2"></i>Performance par Catégorie
            </h3>
            
            <?php if (count($category_stats) > 0): ?>
            <div class="space-y-4">
                <?php foreach ($category_stats as $stat): ?>
                <div class="border-l-4 border-purple-500 pl-4 py-2">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-bold text-gray-900"><?php echo htmlspecialchars($stat['categorie']); ?></h4>
                        <span class="text-sm font-semibold text-purple-600">
                            <?php echo $stat['quiz_count']; ?> quiz
                        </span>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <span class="text-gray-600">
                            <i class="fas fa-user-friends mr-1"></i><?php echo $stat['attempt_count']; ?> tentatives
                        </span>
                        <span class="text-green-600 font-semibold">
                            Moy: <?php echo number_format($stat['avg_score'] ?? 0, 1); ?>%
                        </span>
                    </div>
                    <div class="mt-2">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-purple-600 h-2 rounded-full" style="width: <?php echo $stat['avg_score'] ?? 0; ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-center py-8">Aucune donnée disponible</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Students -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-trophy text-yellow-500 mr-2"></i>Meilleurs Étudiants
            </h3>
            
            <?php if (count($top_students) > 0): ?>
            <div class="space-y-3">
                <?php foreach ($top_students as $index => $student): 
                    $medal_colors = ['text-yellow-500', 'text-gray-400', 'text-orange-600'];
                    $medal_color = $medal_colors[$index] ?? 'text-gray-300';
                ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl <?php echo $medal_color; ?>">
                            <i class="fas fa-medal"></i>
                        </span>
                        <div>
                            <p class="font-bold text-gray-900"><?php echo htmlspecialchars($student['etudiant']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($student['email']); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-green-600"><?php echo number_format($student['avg_score'], 1); ?>%</p>
                        <p class="text-xs text-gray-500">
                            <?php echo $student['attempt_count']; ?> quiz | <?php echo $student['passed_count']; ?> réussis
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-center py-8">Aucune donnée disponible</p>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-clock text-blue-600 mr-2"></i>Activité Récente
            </h3>
            
            <?php if (count($recent_activity) > 0): ?>
            <div class="space-y-3">
                <?php foreach ($recent_activity as $activity): 
                    $score_class = $activity['percentage'] >= 70 ? 'text-green-600' : ($activity['percentage'] >= 50 ? 'text-yellow-600' : 'text-red-600');
                ?>
                <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                    <div class="w-2 h-2 mt-2 rounded-full <?php echo $activity['percentage'] >= 50 ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($activity['etudiant']); ?></p>
                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($activity['quiz']); ?></p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-sm font-semibold <?php echo $score_class; ?>">
                                <?php echo $activity['score']; ?>/<?php echo $activity['total_questions']; ?>
                            </span>
                            <span class="text-xs text-gray-400">
                                <?php echo date('d M Y H:i', strtotime($activity['completed_at'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-center py-8">Aucune activité récente</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes_enseignant/footer.php'; ?>
