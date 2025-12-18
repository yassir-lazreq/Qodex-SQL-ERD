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

// Handle quiz creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_quiz') {
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $categorie_id = $_POST['categorie_id'];
    
    if (!empty($titre) && !empty($categorie_id)) {
        try {
            $conn->beginTransaction();
            
            // Insert quiz
            $stmt = $conn->prepare("INSERT INTO quizzes (titre, description, categorie_id, enseignant_id, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$titre, $description, $categorie_id, $user_id]);
            $quiz_id = $conn->lastInsertId();
            
            // Insert questions
            if (isset($_POST['questions']) && is_array($_POST['questions'])) {
                $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question, option1, option2, option3, option4, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($_POST['questions'] as $q) {
                    if (!empty($q['question'])) {
                        $stmt->execute([
                            $quiz_id,
                            $q['question'],
                            $q['option1'],
                            $q['option2'],
                            $q['option3'],
                            $q['option4'],
                            $q['correct']
                        ]);
                    }
                }
            }
            
            $conn->commit();
            $_SESSION['success_message'] = "Quiz créé avec succès!";
            header("Location: manage_quizzes.php");
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $_SESSION['error_message'] = "Erreur lors de la création du quiz.";
            header("Location: manage_quizzes.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Le titre et la catégorie sont requis.";
        header("Location: manage_quizzes.php");
        exit();
    }
}

// Handle quiz deletion
if (isset($_GET['delete'])) {
    $quiz_id = $_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ? AND enseignant_id = ?");
        $stmt->execute([$quiz_id, $user_id]);
        $_SESSION['success_message'] = "Quiz supprimé avec succès!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Erreur lors de la suppression.";
    }
    header("Location: manage_quizzes.php");
    exit();
}

// Handle toggle active status
if (isset($_GET['toggle'])) {
    $quiz_id = $_GET['toggle'];
    try {
        $stmt = $conn->prepare("UPDATE quizzes SET is_active = NOT is_active WHERE id = ? AND enseignant_id = ?");
        $stmt->execute([$quiz_id, $user_id]);
        $_SESSION['success_message'] = "Statut du quiz mis à jour!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Erreur lors de la mise à jour.";
    }
    header("Location: manage_quizzes.php");
    exit();
}

// Get all quizzes with stats
try {
    $stmt = $conn->prepare("
        SELECT 
            q.*,
            c.nom as categorie_nom,
            COUNT(DISTINCT qs.id) as question_count,
            COUNT(DISTINCT r.id) as attempt_count,
            AVG(r.score * 100.0 / r.total_questions) as avg_score
        FROM quizzes q
        LEFT JOIN categories c ON q.categorie_id = c.id
        LEFT JOIN questions qs ON q.id = qs.quiz_id
        LEFT JOIN results r ON q.id = r.quiz_id
        WHERE q.enseignant_id = ?
        GROUP BY q.id
        ORDER BY q.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $quizzes = $stmt->fetchAll();
} catch (PDOException $e) {
    $quizzes = [];
    $error_message = "Erreur lors du chargement des quiz.";
}

// Get categories for dropdown
try {
    $stmt = $conn->prepare("SELECT id, nom FROM categories WHERE created_by = ? ORDER BY nom ASC");
    $stmt->execute([$user_id]);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Retrieve flash messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$page_title = 'Mes Quiz - Qodex';
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

<!-- Quiz Management Section -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Mes Quiz</h2>
            <p class="text-gray-600 mt-2">Créez et gérez vos quiz</p>
        </div>
        <button onclick="openModal('createQuizModal')" class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
            <i class="fas fa-plus mr-2"></i>Créer un Quiz
        </button>
    </div>

    <!-- Quiz List -->
    <?php if (count($quizzes) > 0): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($quizzes as $quiz): ?>
        <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <span class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">
                        <?php echo htmlspecialchars($quiz['categorie_nom'] ?: 'Sans catégorie'); ?>
                    </span>
                    <div class="flex gap-2">
                        <button onclick="toggleQuiz(<?php echo $quiz['id']; ?>)" class="text-gray-600 hover:text-gray-700" title="Activer/Désactiver">
                            <i class="fas fa-power-off <?php echo $quiz['is_active'] ? 'text-green-600' : 'text-gray-400'; ?>"></i>
                        </button>
                        <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="text-blue-600 hover:text-blue-700">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="?delete=<?php echo $quiz['id']; ?>" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce quiz?')" class="text-red-600 hover:text-red-700">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
                
                <h3 class="text-xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($quiz['titre']); ?></h3>
                <p class="text-gray-600 mb-4 text-sm line-clamp-2"><?php echo htmlspecialchars($quiz['description'] ?: 'Aucune description'); ?></p>
                
                <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                    <span><i class="fas fa-question-circle mr-1"></i><?php echo $quiz['question_count']; ?> questions</span>
                    <span><i class="fas fa-user-friends mr-1"></i><?php echo $quiz['attempt_count']; ?> participants</span>
                </div>
                
                <?php if ($quiz['attempt_count'] > 0): ?>
                <div class="mb-4">
                    <div class="flex justify-between text-xs text-gray-600 mb-1">
                        <span>Score moyen</span>
                        <span><?php echo number_format($quiz['avg_score'], 1); ?>%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $quiz['avg_score']; ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <button onclick="window.location.href='view_results.php?quiz_id=<?php echo $quiz['id']; ?>'" class="w-full bg-indigo-600 text-white py-2 rounded-lg font-semibold hover:bg-indigo-700 transition">
                    <i class="fas fa-eye mr-2"></i>Voir les résultats
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-md p-12 text-center">
        <i class="fas fa-clipboard-list text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Aucun quiz</h3>
        <p class="text-gray-600 mb-6">Créez votre premier quiz pour évaluer vos étudiants</p>
        <button onclick="openModal('createQuizModal')" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
            <i class="fas fa-plus mr-2"></i>Créer un quiz
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Créer Quiz -->
<div id="createQuizModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-900">Créer un Quiz</h3>
                <button onclick="closeModal('createQuizModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_quiz">
                
                <div class="grid md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Titre du quiz *
                        </label>
                        <input type="text" name="titre" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Ex: Les Bases de HTML5">
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            Catégorie *
                        </label>
                        <select name="categorie_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <option value="">Sélectionner une catégorie</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        Description
                    </label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Décrivez votre quiz..."></textarea>
                </div>

                <hr class="my-6">

                <!-- Questions Section -->
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-xl font-bold text-gray-900">Questions</h4>
                        <button type="button" onclick="addQuestion()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
                            <i class="fas fa-plus mr-2"></i>Ajouter une question
                        </button>
                    </div>

                    <div id="questionsContainer">
                        <!-- Question 1 -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-4 question-block">
                            <div class="flex justify-between items-center mb-4">
                                <h5 class="font-bold text-gray-900">Question 1</h5>
                                <button type="button" onclick="removeQuestion(this)" class="text-red-600 hover:text-red-700">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>

                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Question *</label>
                                <input type="text" name="questions[0][question]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Posez votre question...">
                            </div>

                            <div class="grid md:grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="block text-gray-700 text-sm mb-2">Option 1 *</label>
                                    <input type="text" name="questions[0][option1]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-2">Option 2 *</label>
                                    <input type="text" name="questions[0][option2]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-2">Option 3 *</label>
                                    <input type="text" name="questions[0][option3]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-2">Option 4 *</label>
                                    <input type="text" name="questions[0][option4]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </div>
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Réponse correcte *</label>
                                <select name="questions[0][correct]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    <option value="">Sélectionner la bonne réponse</option>
                                    <option value="1">Option 1</option>
                                    <option value="2">Option 2</option>
                                    <option value="3">Option 3</option>
                                    <option value="4">Option 4</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeModal('createQuizModal')" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Annuler
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-check mr-2"></i>Créer le Quiz
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let questionCount = 1;

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function addQuestion() {
    questionCount++;
    const container = document.getElementById('questionsContainer');
    const questionHTML = `
        <div class="bg-gray-50 rounded-lg p-4 mb-4 question-block">
            <div class="flex justify-between items-center mb-4">
                <h5 class="font-bold text-gray-900">Question ${questionCount}</h5>
                <button type="button" onclick="removeQuestion(this)" class="text-red-600 hover:text-red-700">
                    <i class="fas fa-trash"></i>
                </button>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Question *</label>
                <input type="text" name="questions[${questionCount-1}][question]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Posez votre question...">
            </div>

            <div class="grid md:grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-gray-700 text-sm mb-2">Option 1 *</label>
                    <input type="text" name="questions[${questionCount-1}][option1]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm mb-2">Option 2 *</label>
                    <input type="text" name="questions[${questionCount-1}][option2]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm mb-2">Option 3 *</label>
                    <input type="text" name="questions[${questionCount-1}][option3]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm mb-2">Option 4 *</label>
                    <input type="text" name="questions[${questionCount-1}][option4]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Réponse correcte *</label>
                <select name="questions[${questionCount-1}][correct]" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="">Sélectionner la bonne réponse</option>
                    <option value="1">Option 1</option>
                    <option value="2">Option 2</option>
                    <option value="3">Option 3</option>
                    <option value="4">Option 4</option>
                </select>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', questionHTML);
}

function removeQuestion(button) {
    const questionBlock = button.closest('.question-block');
    questionBlock.remove();
    
    // Renumber questions
    const questions = document.querySelectorAll('.question-block');
    questions.forEach((q, index) => {
        const title = q.querySelector('h5');
        title.textContent = `Question ${index + 1}`;
    });
    questionCount = questions.length;
}

function toggleQuiz(quizId) {
    window.location.href = `?toggle=${quizId}`;
}

window.onclick = function(event) {
    if (event.target.classList.contains('bg-opacity-50')) {
        event.target.classList.add('hidden');
        event.target.classList.remove('flex');
    }
}
</script>

<?php include '../includes_enseignant/footer.php'; ?>
