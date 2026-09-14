<?php
// ================================
// RoomHive - Create Account
// ================================
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

// Form variables
$fullName = "";
$email = "";
$error = "";
$success = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $terms = isset($_POST["terms"]);

    // Validation
    if (empty($fullName) || empty($email) || empty($password) || empty($confirmPassword)) {

        $error = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirmPassword) {

        $error = "Passwords do not match.";

    } elseif (!$terms) {

        $error = "Please agree to the Terms of Service and Privacy Policy.";

    } else {

        /*
         * =========================================================
         * DATABASE REGISTRATION
         * =========================================================
         */

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);

        if ($stmt->fetch()) {

            $error = "An account with that email already exists.";

        } else {

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $insert = $pdo->prepare(
                "INSERT INTO users (name, email, password) VALUES (:name, :email, :password)"
            );
            $insert->execute([
                'name' => $fullName,
                'email' => $email,
                'password' => $passwordHash,
            ]);

            $userId = $pdo->lastInsertId();

            /*
             * Log the new user in immediately, same as loginform.php,
             * and send them to their home page.
             */
            session_regenerate_id(true);

            $_SESSION["user_id"] = $userId;
            $_SESSION["user_name"] = $fullName;
            $_SESSION["user_email"] = $email;
            $_SESSION["logged_in"] = true;

            header("Location: /webprogg/user/usershome.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>RoomHive - Create Your Account</title>

    <!-- Poppins Font -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Your CSS -->
    <link rel="stylesheet" href="/webprogg/assets/style.css">
    <link rel="stylesheet" href="/webprogg/assets/loginform.css">
</head>

<body class="create-account-page">
<!-- Close / Back to Home -->
        <a href="/webprogg/index.php" class="close-button" aria-label="Close">
            &times;
        </a>
    <div class="create-account-container">

        <div class="create-account-card">

            <!-- RoomHive Logo -->
            <div class="create-logo">
                <img
                    src="/webprogg/images/RoomHiveLogos.png"
                    alt="RoomHive Logo"
                >
            </div>

            <!-- Title -->
            <h1>Create Your Account</h1>

            <p class="create-subtitle">
                Join RoomHive and find your perfect stay.
            </p>

            <!-- Error Message -->
            <?php if (!empty($error)): ?>
                <div class="form-message error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (!empty($success)): ?>
                <div class="form-message success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>


            <!-- Create Account Form -->
            <form method="POST" action="">

                <!-- Full Name -->
                <div class="form-group">

                    <label for="full_name">
                        <img
                            src="/webprogg/images/FullNameIcon-CreateAccount.png"
                            alt=""
                        >
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?php echo htmlspecialchars($fullName); ?>"
                        required
                    >

                </div>


                <!-- Email -->
                <div class="form-group">

                    <label for="email">
                        <img
                            src="/webprogg/images/EmailIcon.jpg"
                            alt=""
                        >
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php echo htmlspecialchars($email); ?>"
                        required
                    >

                </div>


                <!-- Password -->
                <div class="form-group">

                    <label for="password">
                        <img
                            src="/webprogg/images/LockIcon.png"
                            alt=""
                        >
                        Password
                    </label>

                    <div class="password-container">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            minlength="8"
                        >

                        <button
                            type="button"
                            class="show-password"
                            onclick="togglePassword('password', this)"
                            aria-label="Show password"
                        >
                            ◉
                        </button>

                    </div>

                    <p class="password-hint">
                        Password must be at least 8 characters.
                    </p>

                </div>


                <!-- Confirm Password -->
                <div class="form-group">

                    <label for="confirm_password">
                        <img
                            src="/webprogg/images/LockIcon.png"
                            alt=""
                        >
                        Confirm Password
                    </label>

                    <div class="password-container">

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            required
                        >

                        <button
                            type="button"
                            class="show-password"
                            onclick="togglePassword('confirm_password', this)"
                            aria-label="Show password"
                        >
                            ◉
                        </button>

                    </div>

                </div>


                <!-- Terms -->
                <div class="terms-container">

                    <input
                        type="checkbox"
                        id="terms"
                        name="terms"
                        required
                    >

                    <label for="terms">
                        I agree to the
                        <a href="#">Terms of Service</a>
                        and
                        <a href="#">Privacy Policy</a>.
                    </label>

                </div>


                <!-- Create Account Button -->
                <button
                    type="submit"
                    class="create-account-button"
                >
                    Create Account
                </button>

            </form>


            <!-- OR Divider -->
            <div class="or-divider">
                <span></span>
                <p>OR</p>
                <span></span>
            </div>


                     <!-- Google -->
            <button
                type="button"
                class="social-login google-login"
                onclick="window.location.href='google-login.php'"
            >

                <img
                    src="/webprogg/images/Googlecons.png"
                    alt="Google"
                >

                <span>Continue with Google</span>

            </button>


            <!-- Apple -->
            <button
                type="button"
                class="social-login apple-login"
                onclick="window.location.href='apple-login.php'"
            >

                <img
                    src="/webprogg/images/AppleIcons.png"
                    alt="Apple"
                >

                <span>Continue with Apple</span>

            </button>


            <!-- Login -->
            <p class="login-text">
                Already have an account?
                <a href="/webprogg/auth/loginform.php">Log in</a>
            </p>

        </div>

    </div>


    <!-- Password Toggle -->
    <script>

        function togglePassword(inputId, button) {

            const input = document.getElementById(inputId);

            if (input.type === "password") {

                input.type = "text";
                button.textContent = "◉";

            } else {

                input.type = "password";
                button.textContent = "◉";

            }
        }

    </script>

</body>

</html>