<?php
/*loginform.php*/
session_start();
require_once 'db_connect.php';

$error = "";
$success = "";

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
             * Successful login goes to usershome.php,
             * NOT index.php.
             */
            header("Location: usershome.php");
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
        href="style.css"
    >

</head>

<body>

    <main class="login-page">
 <!-- Close / Back to Home -->
        <a href="index.php" class="close-button" aria-label="Close">
            &times;
        </a>
        <div class="login-card">

            <!-- RoomHive Logo -->
            <div class="login-logo">

                <img
                    src="images/RoomHiveLogos.png"
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
                action="loginform.php"
                method="POST"
            >

                <!-- Email -->
                <div class="login-input-group">

                    <label for="email">

                        <img
                            src="images/EmailIcon.jpg"
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
                            src="images/LockIcon.png"
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

                    <a href="forgotpassword.php">
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
                    src="images/Googlecons.png"
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
                    src="images/AppleIcons.png"
                    alt="Apple"
                >

                <span>Continue with Apple</span>

            </button>


            <!-- Create Account -->
            <div class="create-account">

                <span>Not registered yet?</span>

                <a href="createaccount.php">
                    Create Account Here
                </a>

            </div>

        </div>

    </main>

</body>

</html>