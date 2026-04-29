<?php
// ============================================
// ملف: message_handler.php
// معالج رسائل النجاح والخطأ الموحد
// ============================================

/**
 * عرض رسائل النجاح والخطأ المخزنة في الجلسة
 */
function displayMessages() {
    $output = '';
    
    if (isset($_SESSION['success'])) {
        $output .= '<div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> 
                        ' . htmlspecialchars($_SESSION['success']) . '
                    </div>';
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['error'])) {
        $output .= '<div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> 
                        ' . htmlspecialchars($_SESSION['error']) . '
                    </div>';
        unset($_SESSION['error']);
    }
    
    if (isset($_SESSION['warning'])) {
        $output .= '<div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        ' . htmlspecialchars($_SESSION['warning']) . '
                    </div>';
        unset($_SESSION['warning']);
    }
    
    return $output;
}

/**
 * تعيين رسالة نجاح
 */
function setSuccess($message) {
    $_SESSION['success'] = $message;
}

/**
 * تعيين رسالة خطأ
 */
function setError($message) {
    $_SESSION['error'] = $message;
}

/**
 * تعيين رسالة تحذير
 */
function setWarning($message) {
    $_SESSION['warning'] = $message;
}

/**
 * التوجيه مع رسالة
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION[$type] = $message;
    header("Location: $url");
    exit;
}
?>