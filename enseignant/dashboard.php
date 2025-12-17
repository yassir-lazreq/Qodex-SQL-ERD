<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'enseignant') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['nom'];

$stmt = $conn->prepare('SELECT id, nom, email FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}


$stmt = $conn->prepare("SELECT COUNT(*) as total FROM quizzes WHERE enseignant_id = ?");
$stmt->execute([$user_id]);
$total_quizzes = $stmt->fetch()['total'];


$stmt = $conn->prepare("SELECT COUNT(*) as total FROM questions q JOIN quizzes qz ON q.quiz_id = qz.id WHERE qz.enseignant_id = ?");
$stmt->execute([$user_id]);
$total_questions = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM results r JOIN quizzes q ON r.quiz_id = q.id WHERE q.enseignant_id = ?");
$stmt->execute([$user_id]);
$total_attempts = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT AVG(r.score / r.total_questions * 100) as avg_score FROM results r JOIN quizzes q ON r.quiz_id = q.id WHERE q.enseignant_id = ?");
$stmt->execute([$user_id]);
$avg_score_result = $stmt->fetch();
if ($avg_score_result['avg_score']) {
    $avg_score = round($avg_score_result['avg_score'], 1);
} else {
    $avg_score = 0;
}

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM categories WHERE created_by = ?");
$stmt->execute([$user_id]);
$total_categories = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(DISTINCT r.etudiant_id) as total FROM results r JOIN quizzes q ON r.quiz_id = q.id WHERE q.enseignant_id = ?");
$stmt->execute([$user_id]);
$total_students = $stmt->fetch()['total'];

$stmt = $conn->prepare("
    SELECT q.*, c.nom as category_name, 
            (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as question_count,
            (SELECT COUNT(*) FROM results WHERE quiz_id = q.id) as attempt_count
    FROM quizzes q 
    LEFT JOIN categories c ON q.categorie_id = c.id 
    WHERE q.enseignant_id = ? 
    ORDER BY q.created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_quizzes = $stmt->fetchAll();

$stmt = $conn->prepare("
    SELECT r.*, q.titre as quiz_title, u.nom as student_name
    FROM results r 
    JOIN quizzes q ON r.quiz_id = q.id 
    JOIN users u ON r.etudiant_id = u.id
    WHERE q.enseignant_id = ? 
    ORDER BY r.completed_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_results = $stmt->fetchAll();

$page_title = "Tableau de bord - Qodex";
include_once '../includes_enseignant/header.php';
?>

<!-- Hero Section -->
<div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-12 mb-8 rounded-lg">
    <h1 class="text-4xl font-bold mb-4">👋 Bienvenue, <?php echo htmlspecialchars($user_name); ?> !</h1>
    <p class="text-xl text-indigo-100 mb-6">Voici un aperçu de votre activité sur Qodex</p>
    <div class="flex flex-wrap gap-4">
        <a href="categories.php" class="bg-white text-indigo-600 px-6 py-3 rounded-lg font-semibold hover:bg-indigo-50 transition">
            <i class="fas fa-folder-plus mr-2"></i>Gérer les Catégories
        </a>
        <a href="add_quiz.php" class="bg-indigo-700 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-800 transition">
            <i class="fas fa-plus-circle mr-2"></i>Créer un Quiz
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-xl transition">
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
    
    <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-xl transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Questions</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $total_questions; ?></p>
            </div>
            <div class="bg-green-100 p-3 rounded-lg">
                <i class="fas fa-question-circle text-green-600 text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-xl transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Catégories</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $total_categories; ?></p>
            </div>
            <div class="bg-purple-100 p-3 rounded-lg">
                <i class="fas fa-folder text-purple-600 text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-xl transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Étudiants Actifs</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $total_students; ?></p>
            </div>
            <div class="bg-cyan-100 p-3 rounded-lg">
                <i class="fas fa-user-graduate text-cyan-600 text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-xl transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Tentatives</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $total_attempts; ?></p>
            </div>
            <div class="bg-pink-100 p-3 rounded-lg">
                <i class="fas fa-check-circle text-pink-600 text-2xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-xl transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Score Moyen</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $avg_score; ?>%</p>
            </div>
            <div class="bg-yellow-100 p-3 rounded-lg">
                <i class="fas fa-chart-line text-yellow-600 text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Recent Quizzes & Results -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Quizzes -->
        <div class="card">
            <div class="card-header">
                <h2>📝 Quiz récents</h2>
                <a href="manage_quizzes.php" class="view-all">Voir tout →</a>
            </div>

            <?php if (empty($recent_quizzes)): ?>
                <div class="empty-state">
                    <p>Aucun quiz créé pour le moment</p>
                    <a href="add_quiz.php" class="btn-small">Créer votre premier quiz</a>
                </div>
            <?php else: ?>
                <div class="quiz-list">
                    <?php foreach ($recent_quizzes as $quiz): ?>
                        <div class="quiz-item">
                            <div class="quiz-info">
                                <h4><?php echo htmlspecialchars($quiz['titre']); ?></h4>
                                <div class="quiz-meta">
                                    <span class="badge"><?php echo $quiz['category_name'] ?? 'Sans catégorie'; ?></span>
                                    <span><?php echo $quiz['question_count']; ?> questions</span>
                                    <span><?php echo $quiz['attempt_count']; ?> tentatives</span>
                                </div>
                            </div>
                            <div class="quiz-status <?php echo $quiz['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $quiz['is_active'] ? '🟢 Actif' : '🔴 Inactif'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Results -->
        <div class="card">
            <div class="card-header">
                <h2>🏆 Résultats récents</h2>
                <a href="view_results.php" class="view-all">Voir tout →</a>
            </div>

            <?php if (empty($recent_results)): ?>
                <div class="empty-state">
                    <p>Aucun résultat pour le moment</p>
                    <span class="text-muted">Les résultats apparaîtront ici quand vos étudiants passeront vos quiz</span>
                </div>
            <?php else: ?>
                <div class="results-list">
                    <?php foreach ($recent_results as $result): ?>
                        <div class="result-item">
                            <div class="result-info">
                                <h4><?php echo htmlspecialchars($result['student_name']); ?></h4>
                                <p><?php echo htmlspecialchars($result['quiz_title']); ?></p>
                            </div>
                            <div class="result-score">
                                <?php
                                $percentage = round(($result['score'] / $result['total_questions']) * 100);
                                $score_class = $percentage >= 70 ? 'good' : ($percentage >= 50 ? 'medium' : 'poor');
                                ?>
                                <span class="score <?php echo $score_class; ?>">
                                    <?php echo $result['score']; ?>/<?php echo $result['total_questions']; ?>
                                </span>
                                <small><?php echo date('d/m/Y H:i', strtotime($result['completed_at'])); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
        }

        .stat-info h3 {
            font-size: 1.8em;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #7f8c8d;
            font-size: 0.95em;
        }

        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 25px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .action-btn.primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .action-btn.secondary {
            background: #f8f9fa;
            color: #2c3e50;
            border: 2px solid #e9ecef;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .action-icon {
            font-size: 1.3em;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-header h2 {
            color: #2c3e50;
            font-size: 1.3em;
        }

        .view-all {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95em;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .quiz-list,
        .results-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .quiz-item,
        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: background 0.3s;
        }

        .quiz-item:hover,
        .result-item:hover {
            background: #e9ecef;
        }

        .quiz-info h4,
        .result-info h4 {
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .quiz-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85em;
            color: #7f8c8d;
        }

        .badge {
            background: #667eea;
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8em;
        }

        .quiz-status {
            font-size: 0.9em;
            font-weight: 500;
        }

        .result-info p {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .result-score {
            text-align: right;
        }

        .score {
            display: block;
            font-size: 1.2em;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .score.good {
            color: #27ae60;
        }

        .score.medium {
            color: #f39c12;
        }

        .score.poor {
            color: #e74c3c;
        }

        .result-score small {
            color: #95a5a6;
            font-size: 0.8em;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }

        .empty-state p {
            margin-bottom: 15px;
        }

        .btn-small {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-small:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .text-muted {
            display: block;
            color: #95a5a6;
            font-size: 0.9em;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                flex-direction: column;
            }

            .action-btn {
                justify-content: center;
            }
        }
    </style>

<?php include_once '../includes_enseignant/footer.php'; ?>