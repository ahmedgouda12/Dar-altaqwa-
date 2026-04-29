<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'تغيير كلمة المرور';
require_once 'includes/header.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = '❌ جميع الحقول مطلوبة';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = '❌ كلمة المرور الجديدة وتأكيدها غير متطابقين';
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = '❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل';
        $message_type = 'error';
    } else {
        // التحقق من كلمة المرور الحالية حسب نوع المستخدم
        $user_id = $_SESSION['user_id'];
        $user_type = $_SESSION['user_type'];

        $table = '';
        $id_field = 'id';
        $password_field = 'password';

        switch ($user_type) {
            case 'admin':
                $table = 'admins';
                $username_field = 'username';
                break;
            case 'teacher':
                $table = 'teachers';
                $username_field = 'email';
                break;
            case 'guardian':
                $table = 'guardians';
                $username_field = 'email';
                break;
            case 'student':
                $table = 'students';
                $username_field = 'username';
                break;
            default:
                $message = '❌ نوع المستخدم غير معروف';
                $message_type = 'error';
                break;
        }

        if (empty($message)) {
            // جلب كلمة المرور الحالية
            $stmt = $pdo->prepare("SELECT $password_field FROM $table WHERE $id_field = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if ($user && password_verify($current_password, $user[$password_field])) {
                // تحديث كلمة المرور
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE $table SET $password_field = ? WHERE $id_field = ?");
                $update->execute([$hashed, $user_id]);

                $message = '✅ تم تغيير كلمة المرور بنجاح';
                $message_type = 'success';
            } else {
                $message = '❌ كلمة المرور الحالية غير صحيحة';
                $message_type = 'error';
            }
        }
    }
}
?>

<style>
.change-password-container {
    max-width: 500px;
    margin: 0 auto;
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #1e3c3f;
}
.form-group input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 1rem;
    transition: 0.3s;
}
.form-group input:focus {
    border-color: #1e3c3f;
    outline: none;
}
.message {
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center;
}
.message.success {
    background: #d4edda;
    color: #155724;
    border-right: 5px solid #28a745;
}
.message.error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid #dc3545;
}
.btn-save {
    background: #28a745;
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 30px;
    font-size: 1.1rem;
    cursor: pointer;
    width: 100%;
    transition: 0.3s;
}
.btn-save:hover {
    background: #218838;
    transform: translateY(-2px);
}
.info-box {
    background: #e7f3ff;
    border-right: 5px solid #17a2b8;
    padding: 15px;
    border-radius: 10px;
    margin-top: 20px;
    font-size: 0.9rem;
}
</style>

<section class="change-password-container">
    <h2 class="card-title" style="text-align:center; margin-bottom:30px;">
        <i class="fas fa-key"></i> تغيير كلمة المرور
    </h2>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label>كلمة المرور الحالية</label>
            <input type="password" name="current_password" required>
        </div>
        <div class="form-group">
            <label>كلمة المرور الجديدة (6 أحرف على الأقل)</label>
            <input type="password" name="new_password" required>
        </div>
        <div class="form-group">
            <label>تأكيد كلمة المرور الجديدة</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn-save">
            <i class="fas fa-save"></i> تغيير كلمة المرور
        </button>
    </form>

    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <strong>نصيحة:</strong> اختر كلمة مرور قوية وسهلة التذكر. تجنب استخدام أرقام متتالية أو كلمات شائعة.
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>