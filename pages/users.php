<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

session_init();
$user = require_admin(); // Admin uniquement

$errors  = [];
$edit_user = null;
$edit_id   = (int)($_GET['edit'] ?? 0);

if ($edit_id) {
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_user = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $del_id = (int)($_POST['user_id'] ?? 0);
        if ($del_id === $user['id']) {
            flash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        } else {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$del_id]);
            flash('success', 'Utilisateur supprimé.');
        }
        redirect('/pages/users.php');
    }

    if ($action === 'save') {
        $uid_edit  = (int)($_POST['user_id'] ?? 0);
        $username  = trim($_POST['username']  ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email']     ?? '');
        $role      = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
        $password  = $_POST['password'] ?? '';

        if (!$username)                     $errors[] = "L'identifiant est obligatoire.";
        if (!$full_name)                    $errors[] = 'Le nom complet est obligatoire.';
        if (!$email)                        $errors[] = "L'email est obligatoire.";
        if (!$uid_edit && !$password)       $errors[] = 'Le mot de passe est obligatoire pour un nouvel utilisateur.';
        if ($password && strlen($password) < 8) $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';

        if (empty($errors)) {
            if ($uid_edit) {
                if ($password) {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    db()->prepare('UPDATE users SET username=?,full_name=?,email=?,role=?,password=? WHERE id=?')
                       ->execute([$username, $full_name, $email, $role, $hash, $uid_edit]);
                } else {
                    db()->prepare('UPDATE users SET username=?,full_name=?,email=?,role=? WHERE id=?')
                       ->execute([$username, $full_name, $email, $role, $uid_edit]);
                }
                flash('success', 'Utilisateur modifié.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                db()->prepare('INSERT INTO users (username,full_name,email,role,password) VALUES (?,?,?,?,?)')
                   ->execute([$username, $full_name, $email, $role, $hash]);
                flash('success', 'Utilisateur créé.');
            }
            redirect('/pages/users.php');
        }
        // Re-afficher le formulaire avec les valeurs saisies
        $edit_user = ['id' => $uid_edit, 'username' => $username, 'full_name' => $full_name, 'email' => $email, 'role' => $role];
    }
}

$all_users = db()->query('SELECT id, username, full_name, email, role, created_at FROM users ORDER BY created_at')->fetchAll();

$flash       = get_flash();
$page_title  = 'Gestion des utilisateurs';
$current_nav = 'users';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif ?>

<div class="page-header">
  <h1>Utilisateurs</h1>
  <a href="?new=1" class="btn btn-primary">+ Ajouter</a>
</div>

<div class="card" style="margin-bottom:2rem">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Identifiant</th><th>Nom</th><th>Email</th><th>R&ocirc;le</th><th>Cr&eacute;&eacute; le</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($all_users as $u): ?>
      <tr>
        <td><strong><?= e($u['username']) ?></strong></td>
        <td><?= e($u['full_name']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td>
          <?php if ($u['role'] === 'admin'): ?>
            <span style="color:var(--gold);font-weight:600">Admin</span>
          <?php else: ?>
            Utilisateur
          <?php endif ?>
        </td>
        <td><?= (new DateTime($u['created_at']))->format('d/m/Y') ?></td>
        <td style="white-space:nowrap">
          <a href="?edit=<?= $u['id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
          <?php if ((int)$u['id'] !== $user['id']): ?>
          <form method="post" style="display:inline"
                onsubmit="return confirm('Supprimer cet utilisateur et toutes ses données ?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
          </form>
          <?php endif ?>
        </td>
      </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Formulaire ajout/modification -->
<?php if ($edit_user || isset($_GET['new'])): ?>
<div class="card" style="max-width:500px">
  <h2 style="margin-bottom:1.5rem"><?= $edit_user && $edit_user['id'] ? "Modifier l'utilisateur" : 'Nouvel utilisateur' ?></h2>
  <?php if ($errors): ?>
  <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
  <?php endif ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?? 0 ?>">
    <div class="form-group" style="margin-bottom:1rem">
      <label for="eu_username">Identifiant *</label>
      <input type="text" id="eu_username" name="username" value="<?= e($edit_user['username'] ?? '') ?>" required>
    </div>
    <div class="form-group" style="margin-bottom:1rem">
      <label for="eu_full_name">Nom complet *</label>
      <input type="text" id="eu_full_name" name="full_name" value="<?= e($edit_user['full_name'] ?? '') ?>" required>
    </div>
    <div class="form-group" style="margin-bottom:1rem">
      <label for="eu_email">Email *</label>
      <input type="email" id="eu_email" name="email" value="<?= e($edit_user['email'] ?? '') ?>" required>
    </div>
    <div class="form-group" style="margin-bottom:1rem">
      <label for="eu_role">R&ocirc;le</label>
      <select id="eu_role" name="role">
        <option value="user"  <?= ($edit_user['role'] ?? 'user') === 'user'  ? 'selected' : '' ?>>Utilisateur</option>
        <option value="admin" <?= ($edit_user['role'] ?? '')     === 'admin' ? 'selected' : '' ?>>Admin</option>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:1.5rem">
      <label for="eu_password">
        Mot de passe <?= ($edit_user['id'] ?? 0) ? '(vide = inchangé)' : '*' ?>
      </label>
      <input type="password" id="eu_password" name="password"
             autocomplete="new-password" <?= ($edit_user['id'] ?? 0) ? '' : 'required' ?>>
      <span class="form-hint">Minimum 8 caractères</span>
    </div>
    <div style="display:flex;gap:.75rem">
      <button type="submit" class="btn btn-primary">Enregistrer</button>
      <a href="/pages/users.php" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>
<?php endif ?>

<?php require_once __DIR__ . '/../includes/footer.php' ?>
