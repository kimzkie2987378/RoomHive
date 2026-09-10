<?php
/*loginform.php*/
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';


define('ADMIN_NAME', 'Admin User');
define('ADMIN_EMAIL', 'admin@roomhive.com');
define('ADMIN_PASSWORD_HASH', '$2b$10$hK5yT40UWo1rEvszXfutDuzUeCMo.RzqJKhZIte2YvnjOzBd.SCE2');
// ^ hash of "" — this IS the password, hashed. Never put the
// plain password itself here, or password_verify() will never match it.

$error = "";
$success = "";

/*
 * =========================================================
 * REDIRECT-AFTER-LOGIN
 * =========================================================
 * Pages can send people here as loginform.php?redirect=hiveclub.php
 * so that once they log in, they land back where they started
 * instead of always going to usershome.php.
 *
 * Only local pages on this whitelist are allowed — never trust
 * $_GET/$_POST['redirect'] directly as a Location header, or it
 * becomes an open-redirect hole.
 */
$allowedRedirects = ['/webprogg/hiveclub.php', '/webprogg/user/membership.php'];

// Whichever page sent the person here (from the link's ?redirect=...)
$redirectParam = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {

        $error = "Please enter your email address and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        /*
         * =========================================================
         * FIXED ADMIN CHECK (comes before the regular DB login)
         * =========================================================
         * If the typed email + password match the hardcoded admin
         * account, log in as admin and skip the users table lookup
         * entirely.
         */
        $isAdminEmail = hash_equals(strtolower(ADMIN_EMAIL), strtolower($email));

        if ($isAdminEmail && password_verify($password, ADMIN_PASSWORD_HASH)) {

            session_regenerate_id(true);

            $_SESSION["admin_name"] = ADMIN_NAME;
            $_SESSION["admin_email"] = ADMIN_EMAIL;
            $_SESSION["admin_logged_in"] = true;

            header("Location: /webprogg/admin/admin.php");
            exit();
        }

        /*
         * =========================================================
         * DATABASE LOGIN
         * =========================================================
         */

        $stmt = $pdo->prepare(
            "SELECT id, name, email, password FROM users WHERE email = :email LIMIT 1"
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            /*
             * Regenerate session ID after authentication.
             * This helps prevent session fixation.
             */
            session_regenerate_id(true);

            /*
             * Store authenticated user information.
             */
            $_SESSION["user_id"] = $user['id'];
            $_SESSION["user_name"] = $user['name'];
            $_SESSION["user_email"] = $user['email'];
            $_SESSION["logged_in"] = true;

            /*
             * IMPORTANT:
             * Successful login goes to usershome.php by default,
             * unless a whitelisted ?redirect= was passed in (e.g.
             * JOIN HIVE CLUB sends people to
             * loginform.php?redirect=hiveclub.php).
             */
            $redirectTo = in_array($redirectParam, $allowedRedirects, true)
                ? $redirectParam
                : "/webprogg/user/usershome.php";

            header("Location: $redirectTo");
            exit();

        } else {

            $error = "Incorrect email address or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>RoomHive - Log In</title>

    <!-- Poppins Font -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Main CSS -->
    <link
        rel="stylesheet"
        href="/webprogg/assets/style.css"
    >

</head>

<body>

    <main class="login-page">
 <!-- Close / Back to Home -->
        <a href="/webprogg/index.php" class="close-button" aria-label="Close">
            &times;
        </a>
        <div class="login-card">

            <!-- RoomHive Logo -->
            <div class="login-logo">

                <img
                    src="/webprogg/images/RoomHiveLogos.png"
                    alt="RoomHive Logo"
                >

            </div>


            <!-- Login Title -->
            <div class="login-header">

                <h1>Welcome Back!</h1>

            </div>


            <!-- Error Message -->
            <?php if (!empty($error)): ?>

                <div class="login-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>


            <!-- Success Message -->
            <?php if (!empty($success)): ?>

                <div class="login-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>

            <?php endif; ?>


            <!-- Login Form -->
            <form
                action="/webprogg/auth/loginform.php"
                method="POST"
            >

                <!-- Carries the ?redirect= target through the POST,
                     so it's still known once the form is submitted. -->
                <input
                    type="hidden"
                    name="redirect"
                    value="<?php echo htmlspecialchars($redirectParam); ?>"
                >

                <!-- Email -->
                <div class="login-input-group">

                    <label for="email">

                        <img
                            src="/webprogg/images/EmailIcon.jpg"
                            alt="Email"
                        >

                        <span>Email Address</span>

                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
                        autocomplete="email"
                        required
                    >

                </div>


                <!-- Password -->
                <div class="login-input-group">

                    <label for="password">

                        <img
                            src="/webprogg/images/LockIcon.png"
                            alt="Password"
                        >

                        <span>Password</span>

                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <!-- Forgot Password -->
                <div class="forgot-password">

                    <a href="/webprogg/auth/forgotpassword.php">
                        Forgot Password?
                    </a>

                </div>


                <!-- Login Button -->
                <button
                    type="submit"
                    class="login-button"
                >
                    Log in
                </button>

            </form>

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


            <!-- Create Account -->
            <div class="create-account">

                <span>Not registered yet?</span>

                <a href="/webprogg/auth/createaccount.php">
                    Create Account Here
                </a>

            </div>

        </div>

    </main>

</body>

</html>