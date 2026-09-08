<?php
// ========================================
// RoomHive - Forgot Password
// ========================================

$email = "";
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if (empty($email)) {

        $error = "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        /*
         * PASSWORD RESET LOGIC WILL GO HERE.
         *
         * Later, this will:
         * 1. Check if the email exists in MySQL.
         * 2. Generate a secure reset token.
         * 3. Send the reset link to the user's email.
         */

        $success = "If an account exists with this email, a reset link has been sent.";
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

    <title>RoomHive - Forgot Password</title>

    <!-- Poppins Font -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Main CSS -->
    <link rel="stylesheet" href="/webprogg/assets/style.css">

</head>

<body class="forgot-password-page">
 <!-- Close / Back to Home -->
        <a href="/webprogg/index.php" class="close-button" aria-label="Close">
            &times;
        </a>
    <div class="forgot-password-container">

        <div class="forgot-password-card">

            <!-- ==================================
                 ROOMHIVE LOGO
            =================================== -->

            <div class="forgot-logo">

                <img
                    src="/webprogg/images/RoomHiveLogos.png"
                    alt="RoomHive Logo"
                >

            </div>


            <!-- ==================================
                 FORGOT PASSWORD ICON
            =================================== -->

            <div class="forgot-icon">

                <img
                    src="/webprogg/images/ForgotPasswordIcon.png"
                    alt="Forgot Password"
                >

            </div>


            <!-- ==================================
                 TITLE
            =================================== -->

            <h1>Forgot Password?</h1>

            <p class="forgot-description">
                No worries! Enter your email address and we'll<br>
                send you a link to reset your password.
            </p>


            <!-- ==================================
                 ERROR MESSAGE
            =================================== -->

            <?php if (!empty($error)): ?>

                <div class="forgot-message error">

                    <?php echo htmlspecialchars($error); ?>

                </div>

            <?php endif; ?>


            <!-- ==================================
                 SUCCESS MESSAGE
            =================================== -->

            <?php if (!empty($success)): ?>

                <div class="forgot-message success">

                    <?php echo htmlspecialchars($success); ?>

                </div>

            <?php endif; ?>


            <!-- ==================================
                 EMAIL FORM
            =================================== -->

            <form method="POST" action="">

                <div class="forgot-form-group">

                    <label for="forgot-email">

                        <img
                            src="/webprogg/images/EmailIcon.jpg"
                            alt=""
                        >

                        Email Address

                    </label>


                    <input
                        type="email"
                        id="forgot-email"
                        name="email"
                        placeholder="Enter your email address"
                        value="<?php echo htmlspecialchars($email); ?>"
                        required
                    >

                </div>


                <!-- ==================================
                     SEND RESET LINK
                =================================== -->

                <button
                    type="submit"
                    class="reset-button"
                >
                    Send Reset Link
                </button>

            </form>


            <!-- ==================================
                 OR DIVIDER
            =================================== -->

            <div class="forgot-divider">

                <span></span>

                <p>OR</p>

                <span></span>

            </div>


            <!-- ==================================
                 BACK TO LOGIN
            =================================== -->

            <a
                href="/webprogg/auth/loginform.php"
                class="back-login-button"
            >

                <span class="back-arrow">←</span>

                <span>Back to Log in</span>

            </a>


            <!-- ==================================
                 HELP BOX
            =================================== -->

            <div class="forgot-help-box">

                <div class="help-icon">

                    <img
                        src="/webprogg/images/VerifiedListingsIcons.png"
                        alt="Verified"
                    >

                </div>


                <div class="help-content">

                    <strong>Still need help?</strong>

                    <p>
                        Contact our support team and we'll<br>
                        be happy to assist you.
                    </p>

                </div>


                <a
                    href="#"
                    class="contact-support"
                >
                    Contact Support →
                </a>

            </div>

        </div>

    </div>

</body>

</html>