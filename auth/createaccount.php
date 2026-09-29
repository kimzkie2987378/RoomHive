<?php
// ================================
// RoomHive - Create Account
// ================================
// NOTE: session_start() is NOT called here. db_connect.php
// owns session setup (30-day cookie params + no-cache
// headers) and starts the session for this page.

require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/*
 * ★ NEW — BACK-BUTTON GUARD: logged-in users never see this form
 * =========================================================
 * If someone creates an account, logs in, then presses the
 * browser Back arrow, this page would otherwise show the
 * signup form again (same problem loginform.php already
 * guards against). Bounce authenticated visitors to their
 * home page instead. Must run BEFORE any output.
 */
if (!empty($_SESSION['logged_in'])) {
    header("Location: /webprogg/user/usershome.php");
    exit();
}

if (!empty($_SESSION['admin_logged_in'])) {
    header("Location: /webprogg/admin/admin.php");
    exit();
}

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

            /*
             * =========================================================
             * ACCOUNT CREATED — GO TO LOGIN FORM
             * =========================================================
             * Do NOT log the user in here. Send them to the login
             * form so they sign in with the credentials they just
             * registered.
             *
             *   ?created=1  -> loginform.php shows a success message
             *   &email=...  -> loginform.php pre-fills their email
             */
            header(
                "Location: /webprogg/auth/loginform.php?created=1&email="
                . urlencode($email)
            );
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
                            &#9673;
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
                            &#9673;
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

    <!-- ★ NEW — BFCache Guard + Password Toggle -->
    <script>

        /* =========================================================
           BFCACHE GUARD — same as loginform.php
           Safari/Firefox can restore this page from the
           back-forward cache (Back/Forward buttons) with NO
           server request — bypassing the PHP guard above. If
           that happens, force a reload so the PHP guard re-runs
           and bounces any logged-in user to their home page.
        ========================================================= */
        window.addEventListener("pageshow", function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        function togglePassword(inputId, button) {

            const input = document.getElementById(inputId);
            const isHidden = input.type === "password";

            input.type = isHidden ? "text" : "password";

            // \u25C9 = filled circle (hidden), \u25CE = bullseye (visible)
            button.textContent = isHidden ? "\u25C9" : "\u25CE";
        }

    </script>

</body>

</html>