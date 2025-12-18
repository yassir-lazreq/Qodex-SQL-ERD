<?php
session_start();
require_once '../config/database.php';

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

// Handle form submissions with Post/Redirect/Get pattern
// Create category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    
    if (!empty($nom)) {
        try {
            $stmt = $conn->prepare("INSERT INTO categories (nom, description, created_by, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$nom, $description, $user_id]);
            $_SESSION['success_message'] = "Catégorie créée avec succès!";
            header("Location: categories.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Erreur lors de la création de la catégorie.";
            header("Location: categories.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Le nom de la catégorie est requis.";
        header("Location: categories.php");
        exit();
    }
}

// Update category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $category_id = $_POST['category_id'];
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    
    if (!empty($nom)) {
        try {
            $stmt = $conn->prepare("UPDATE categories SET nom = ?, description = ? WHERE id = ? AND created_by = ?");
            $stmt->execute([$nom, $description, $category_id, $user_id]);
            $_SESSION['success_message'] = "Catégorie mise à jour avec succès!";
            header("Location: categories.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Erreur lors de la mise à jour.";
            header("Location: categories.php");
            exit();
        }
    }
}

// Delete category
if (isset($_GET['delete'])) {
    $category_id = $_GET['delete'];
    try {
        // Check if category has quizzes
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM quizzes WHERE categorie_id = ?");
        $stmt->execute([$category_id]);
        $quiz_count = $stmt->fetch()['count'];
        
        if ($quiz_count > 0) {
            $_SESSION['error_message'] = "Impossible de supprimer cette catégorie car elle contient des quiz.";
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ? AND created_by = ?");
            $stmt->execute([$category_id, $user_id]);
            $_SESSION['success_message'] = "Catégorie supprimée avec succès!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Erreur lors de la suppression.";
    }
    header("Location: categories.php");
    exit();
}

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$page_title = 'Catégories - Qodex';
include '../includes_enseignant/header.php';
?>

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

    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Gestion des Catégories</h2>
            <p class="text-gray-600 mt-2">Organisez vos quiz par catégories</p>
        </div>
        <button onclick="openModal('createCategoryModal')" class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
            <i class="fas fa-plus mr-2"></i>Nouvelle Catégorie
        </button>
    </div>

    <!-- Categories List -->
    <?php
    try {
        // Get categories with statistics
        $stmt = $conn->prepare("
            SELECT 
                c.*,
                COUNT(DISTINCT q.id) as quiz_count,
                COUNT(DISTINCT r.etudiant_id) as student_count
            FROM categories c
            LEFT JOIN quizzes q ON c.id = q.categorie_id
            LEFT JOIN results r ON q.id = r.quiz_id
            WHERE c.created_by = ?
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $categories = $stmt->fetchAll();
        
        $colors = ['blue', 'purple', 'green', 'yellow', 'red', 'indigo', 'pink', 'teal'];
        
        if (count($categories) > 0):
    ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($categories as $index => $category): 
            $color = $colors[$index % count($colors)];
        ?>
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-<?php echo $color; ?>-500 hover:shadow-lg transition">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($category['nom']); ?></h3>
                    <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($category['description'] ?: 'Aucune description'); ?></p>
                </div>
                <div class="flex gap-2">
                    <button onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars(addslashes($category['nom'])); ?>', '<?php echo htmlspecialchars(addslashes($category['description'])); ?>')" class="text-blue-600 hover:text-blue-700">
                        <i class="fas fa-edit"></i>
                    </button>
                    <a href="?delete=<?php echo $category['id']; ?>" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-500"><i class="fas fa-clipboard-list mr-2"></i><?php echo $category['quiz_count']; ?> quiz</span>
                <span class="text-gray-500"><i class="fas fa-user-friends mr-2"></i><?php echo $category['student_count']; ?> étudiants</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-md p-12 text-center">
        <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Aucune catégorie</h3>
        <p class="text-gray-600 mb-6">Créez votre première catégorie pour organiser vos quiz</p>
        <button onclick="openModal('createCategoryModal')" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
            <i class="fas fa-plus mr-2"></i>Créer une catégorie
        </button>
    </div>
    <?php 
        endif;
    } catch (PDOException $e) {
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">';
        echo '<p><strong>Erreur:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
    }
    ?>

<!-- Modal: Créer Catégorie -->
<div id="createCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-900">Nouvelle Catégorie</h3>
                <button onclick="closeModal('createCategoryModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        Nom de la catégorie *
                    </label>
                    <input type="text" name="nom" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Ex: HTML/CSS">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        Description
                    </label>
                    <textarea name="description" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Décrivez cette catégorie..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeModal('createCategoryModal')" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Annuler
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-check mr-2"></i>Créer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Éditer Catégorie -->
<div id="editCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-900">Éditer Catégorie</h3>
                <button onclick="closeModal('editCategoryModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="category_id" id="edit_category_id">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        Nom de la catégorie *
                    </label>
                    <input type="text" name="nom" id="edit_category_nom" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        Description
                    </label>
                    <textarea name="description" id="edit_category_description" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeModal('editCategoryModal')" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Annuler
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-save mr-2"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal functions
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

// Edit category function
function editCategory(id, nom, description) {
    document.getElementById('edit_category_id').value = id;
    document.getElementById('edit_category_nom').value = nom;
    document.getElementById('edit_category_description').value = description;
    openModal('editCategoryModal');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('bg-opacity-50')) {
        event.target.classList.add('hidden');
        event.target.classList.remove('flex');
    }
}
</script>

<?php include '../includes_enseignant/footer.php'; ?>