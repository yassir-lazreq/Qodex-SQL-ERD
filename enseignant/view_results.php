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

// Get filter parameters
$quiz_filter = $_GET['quiz_id'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query with filters
$where_conditions = ["q.enseignant_id = ?"];
$params = [$user_id];

if (!empty($quiz_filter)) {
    $where_conditions[] = "r.quiz_id = ?";
    $params[] = $quiz_filter;
}

if (!empty($category_filter)) {
    $where_conditions[] = "c.id = ?";
    $params[] = $category_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get results with student and quiz info
try {
    $sql = "
        SELECT 
            r.*,
            u.nom as etudiant_nom,
            u.email as etudiant_email,
            q.titre as quiz_titre,
            c.nom as categorie_nom,
            (r.score * 100.0 / r.total_questions) as percentage
        FROM results r
        INNER JOIN users u ON r.etudiant_id = u.id
        INNER JOIN quizzes q ON r.quiz_id = q.id
        LEFT JOIN categories c ON q.categorie_id = c.id
        WHERE $where_clause
        ORDER BY r.completed_at DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
} catch (PDOException $e) {
    $results = [];
    $error_message = "Erreur lors du chargement des résultats.";
}

// Apply status filter (after fetching)
if (!empty($status_filter)) {
    $results = array_filter($results, function($r) use ($status_filter) {
        $percentage = ($r['score'] * 100.0 / $r['total_questions']);
        if ($status_filter === 'passed') {
            return $percentage >= 50;
        } elseif ($status_filter === 'failed') {
            return $percentage < 50;
        }
        return true;
    });
}

// Get quizzes for filter dropdown
try {
    $stmt = $conn->prepare("SELECT id, titre FROM quizzes WHERE enseignant_id = ? ORDER BY titre ASC");
    $stmt->execute([$user_id]);
    $quizzes = $stmt->fetchAll();
} catch (PDOException $e) {
    $quizzes = [];
}

// Get categories for filter dropdown
try {
    $stmt = $conn->prepare("SELECT DISTINCT c.id, c.nom FROM categories c INNER JOIN quizzes q ON c.id = q.categorie_id WHERE q.enseignant_id = ? ORDER BY c.nom ASC");
    $stmt->execute([$user_id]);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Calculate stats
$total_attempts = count($results);
$passed_count = 0;
$failed_count = 0;
$total_score = 0;

foreach ($results as $r) {
    $percentage = ($r['score'] * 100.0 / $r['total_questions']);
    if ($percentage >= 50) {
        $passed_count++;
    } else {
        $failed_count++;
    }
    $total_score += $percentage;
}

$average_score = $total_attempts > 0 ? $total_score / $total_attempts : 0;
$success_rate = $total_attempts > 0 ? ($passed_count / $total_attempts) * 100 : 0;

// Retrieve flash messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$page_title = 'Résultats - Qodex';
include '../includes_enseignant/header.php';
?>

<!-- Success/Error Messages -->
<?php if ($success_message): ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
    </div>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
    </div>
</div>
<?php endif; ?>

<!-- Results Section -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-900">Résultats des Étudiants</h2>
        <p class="text-gray-600 mt-2">Consultez les performances de vos étudiants</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Tentatives</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo $total_attempts; ?></p>
                </div>
                <div class="bg-blue-100 p-3 rounded-lg">
                    <i class="fas fa-clipboard-check text-blue-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Réussis</p>
                    <p class="text-3xl font-bold text-green-600"><?php echo $passed_count; ?></p>
                </div>
                <div class="bg-green-100 p-3 rounded-lg">
                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Échoués</p>
                    <p class="text-3xl font-bold text-red-600"><?php echo $failed_count; ?></p>
                </div>
                <div class="bg-red-100 p-3 rounded-lg">
                    <i class="fas fa-times-circle text-red-600 text-2xl"></i>
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
                    <i class="fas fa-chart-bar text-indigo-600 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Quiz</label>
                <select name="quiz_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <option value="">Tous les quiz</option>
                    <?php foreach ($quizzes as $q): ?>
                    <option value="<?php echo $q['id']; ?>" <?php echo $quiz_filter == $q['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($q['titre']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Catégorie</label>
                <select name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['nom']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Statut</label>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <option value="">Tous les statuts</option>
                    <option value="passed" <?php echo $status_filter === 'passed' ? 'selected' : ''; ?>>Réussi</option>
                    <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Échoué</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-indigo-700 transition">
                    <i class="fas fa-filter mr-2"></i>Filtrer
                </button>
                <a href="view_results.php" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    <i class="fas fa-redo"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Results Table -->
    <?php if (count($results) > 0): ?>
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Étudiant</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($results as $result): 
                        $percentage = ($result['score'] * 100.0 / $result['total_questions']);
                        $status = $percentage >= 50 ? 'Réussi' : 'Échoué';
                        $status_class = $percentage >= 50 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                        $score_class = $percentage >= 70 ? 'text-green-600' : ($percentage >= 50 ? 'text-yellow-600' : 'text-red-600');
                        $initials = strtoupper(substr($result['etudiant_nom'], 0, 2));
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold mr-3">
                                    <?php echo htmlspecialchars($initials); ?>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($result['etudiant_nom']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($result['etudiant_email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($result['quiz_titre']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($result['categorie_nom'] ?: 'Sans catégorie'); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-lg font-bold <?php echo $score_class; ?>">
                                <?php echo $result['score']; ?>/<?php echo $result['total_questions']; ?>
                            </div>
                            <div class="text-xs text-gray-500"><?php echo number_format($percentage, 1); ?>%</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('d M Y', strtotime($result['completed_at'])); ?>
                            <br>
                            <span class="text-xs"><?php echo date('H:i', strtotime($result['completed_at'])); ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                <i class="fas fa-<?php echo $percentage >= 50 ? 'check' : 'times'; ?> mr-1"></i><?php echo $status; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-md p-12 text-center">
        <i class="fas fa-chart-bar text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Aucun résultat</h3>
        <p class="text-gray-600 mb-6">Aucun étudiant n'a encore passé vos quiz</p>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes_enseignant/footer.php'; ?>
