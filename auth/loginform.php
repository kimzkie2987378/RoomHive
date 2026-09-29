<?php
/*loginform.php*/

require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';

/*
 * NOTE: session_start() is NOT called here. db_connect.php
 * starts the session (with the 30-day cookie params) and
 * sends no-store/no-cache headers, so Back-navigation always
 * produces a fresh request — which lets the guard below fire
 * instead of showing a cached copy.
 */

define('ADMIN_NAME', 'Admin User');
define('ADMIN_EMAIL', 'admin@roomhive.com');
define('ADMIN_PASSWORD_HASH', '$2b$10$hK5yT40UWo1rEvszXfutDuzUeCMo.RzqJKhZIte2YvnjOzBd.SCE2');
// ^ hash of "" — this IS the password, hashed. Never put the
// plain password itself here, or password_verify() will never match it.

 $error = "";
 $success = "";

/*
 * NEW — GOOGLE SIGN-IN ERROR CODES
 * google-callback.php bounces failures back here as
 * ?google_error=<code>; translate to friendly messages.
 */
 $googleErrorCodes = [
    'config'    => 'Google sign-in is not configured yet. Please use your email and password for now.',
    'cancelled' => 'Google sign-in was cancelled. You can try again anytime.',
    'state'     => 'Your Google sign-in session expired. Please try again.',
    'code'      => 'Google did not return an authorization code. Please try again.',
    'token'     => 'We could not verify your Google account. Please try again.',
    'profile'   => 'Your Google account email is not verified. Verify it at Google and try again.',
    'taken'     => 'This Google account is already linked to another RoomHive account.',
    'create'    => 'We could not create your account from Google. Please sign up with email instead.',
    'exception' => 'Something went wrong with Google sign-in. Please try again or use your email.',
];

if (isset($_GET['google_error']) && isset($googleErrorCodes[$_GET['google_error']])) {
    $error = $googleErrorCodes[$_GET['google_error']];
}

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
 *
 * (Placed ABOVE the guards so the guards can honor it too.)
 */
 $allowedRedirects = ['/webprogg/hiveclub.php', '/webprogg/user/membership.php'];

// Whichever page sent the person here (from the link's ?redirect=...)
 $redirectParam = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

/*
 * =========================================================
 * BACK-BUTTON GUARD — logged-in users never see this form
 * =========================================================
 * Runs on GET *and* POST (a stale tab submitting the form
 * also bounces here). Must run BEFORE any output.
 */
if (!empty($_SESSION['admin_logged_in'])) {
    header("Location: /webprogg/admin/admin.php");
    exit();
}

if (!empty($_SESSION['logged_in'])) {
    /* Honor the whitelist redirect if one was requested,
       otherwise send them to their home. */
    $alreadyRedirect = in_array($redirectParam, $allowedRedirects, true)
        ? $redirectParam
        : "/webprogg/user/usershome.php";
    header("Location: " . $alreadyRedirect);
    exit();
}

/*
 * =========================================================
 * AJAX DETECTION
 * =========================================================
 * The login page itself now submits via fetch() so the
 * success/loading animation can play before the redirect.
 * The X-Requested-With header marks those requests; direct
 * visits (bookmarks, no-JS) keep the classic form POST path.
 */
 $isAjax = (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
);

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
         * ADMIN CHECK (comes before the regular DB login)
         * =========================================================
         */
        $isAdminEmail = hash_equals(strtolower(ADMIN_EMAIL), strtolower($email));

        if ($isAdminEmail && password_verify($password, ADMIN_PASSWORD_HASH)) {

            session_regenerate_id(true);

            $_SESSION["admin_name"] = ADMIN_NAME;
            $_SESSION["admin_email"] = ADMIN_EMAIL;
            $_SESSION["admin_logged_in"] = true;

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success'  => true,
                    'redirect' => '/webprogg/admin/admin.php',
                    'name'     => ADMIN_NAME,
                    'admin'    => true,
                ]);
                exit();
            }

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

            session_regenerate_id(true);

            $_SESSION["user_id"] = $user['id'];
            $_SESSION["user_name"] = $user['name'];
            $_SESSION["user_email"] = $user['email'];
            $_SESSION["logged_in"] = true;

            $redirectTo = in_array($redirectParam, $allowedRedirects, true)
                ? $redirectParam
                : "/webprogg/user/usershome.php";

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success'  => true,
                    'redirect' => $redirectTo,
                    'name'     => $user['name'],
                    'admin'    => false,
                ]);
                exit();
            }

            header("Location: $redirectTo");
            exit();

        } else {

            $error = "Incorrect email address or password.";
        }
    }

    if ($isAjax && !empty($error)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => $error,
        ]);
        exit();
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

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="/webprogg/assets/style.css">
    <link rel="stylesheet" href="/webprogg/assets/loginform.css?v=2">

    <script>document.documentElement.classList.add("js");</script>

    <style>

        /* =============================================
           TOKENS — dark "night hive" palette
        ============================================== */
        .li-veil {
            --li-honey: #eda423;
            --li-honey-light: #f6c04e;
            --li-honey-dark: #d99218;
            --li-ink: #eef2f6;          /* light text on dark bg */
            --li-bg-1: #10161d;         /* deep navy base */
            --li-bg-2: #1a232e;         /* mid navy */
            --li-bg-3: #0c1116;         /* near-black base */
        }

        /* =============================================
           OVERLAY SHELL — dark, calm, moody
        ============================================== */
        .li-veil {
            position: fixed;
            inset: 0;
            z-index: 9999;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 24px;

            visibility: hidden;
            pointer-events: none;

            background:
                radial-gradient(900px 460px at 82% -12%, rgba(237, 164, 35, 0.12), transparent 62%),
                radial-gradient(700px 420px at 8% 112%, rgba(237, 164, 35, 0.08), transparent 60%),
                linear-gradient(180deg, var(--li-bg-1) 0%, var(--li-bg-2) 55%, var(--li-bg-3) 100%);

            opacity: 0;
            transition: opacity 0.35s ease;
        }

        .li-veil.open {
            visibility: visible;
            pointer-events: auto;
            opacity: 1;
        }

        /* honeycomb texture — dim gold on navy, subtle */
        .li-veil::before {
            content: "";
            position: absolute;
            inset: 0;

            background-image: url("data:image/svg+xml,%3Csvg width='28' height='49' viewBox='0 0 28 49' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23eda423' fill-opacity='0.07' fill-rule='nonzero'%3E%3Cpath d='M13.99 9.25l13 7.5v15l-13 7.5L1 31.75v-15l12.99-7.5zM3 17.9v12.7l10.99 6.34 11-6.35V17.9l-11-6.34L3 17.9zM0 15l12.98-7.5V0h-2v6.35L0 12.69v2.3zm0 18.5L12.98 41v8h-2v-6.85L0 35.81v-2.3zM15 0v7.5L27.99 15H28v-2.31h-.01L17 6.35V0h-2zm0 49v-8l12.99-7.5H28v2.31h-.01L17 42.15V49h-2z'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 28px 49px;

            -webkit-mask-image: linear-gradient(180deg, rgba(0,0,0,.9), transparent 82%);
            mask-image: linear-gradient(180deg, rgba(0,0,0,.9), transparent 82%);

            pointer-events: none;
        }

        /* glow blobs — deep amber embers, not bright highlights */
        .li-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            opacity: 0.55;
        }

        .li-blob-1 {
            width: 380px;
            height: 380px;
            top: -140px;
            right: -110px;
            background: radial-gradient(circle at 30% 30%, rgba(217, 146, 24, 0.5), rgba(237, 164, 35, 0.14) 60%, transparent 75%);
            animation: liDrift 12s ease-in-out infinite alternate;
        }

        .li-blob-2 {
            width: 300px;
            height: 300px;
            bottom: -130px;
            left: -100px;
            background: radial-gradient(circle at 60% 40%, rgba(217, 146, 24, 0.4), rgba(237, 164, 35, 0.12) 60%, transparent 75%);
            animation: liDrift 16s ease-in-out infinite alternate-reverse;
        }

        @keyframes liDrift {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(26px, -22px) scale(1.08); }
        }

        /* floating hexes — faint gold outlines on the dark */
        .li-hex {
            position: absolute;
            width: 26px;
            height: 30px;
            pointer-events: none;

            background: rgba(237, 164, 35, 0.10);
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);

            animation: liHexFloat 9s ease-in-out infinite;
        }

        .li-hex-1 { top: 16%; left: 12%;  animation-delay: 0s;   }
        .li-hex-2 { top: 68%; left: 18%;  animation-delay: 1.4s; width: 18px; height: 21px; }
        .li-hex-3 { top: 24%; right: 14%; animation-delay: 2.2s; width: 34px; height: 39px; }
        .li-hex-4 { top: 74%; right: 20%; animation-delay: 0.8s; }
        .li-hex-5 { top: 46%; left: 6%;   animation-delay: 3s;   width: 14px; height: 16px; }
        .li-hex-6 { top: 40%; right: 7%;  animation-delay: 1.9s; width: 20px; height: 23px; }

        @keyframes liHexFloat {
            0%, 100% { transform: translateY(0) rotate(0deg);   opacity: .4; }
            50%      { transform: translateY(-26px) rotate(8deg); opacity: .8; }
        }

        .li-stage {
            position: relative;
            z-index: 1;

            display: flex;
            flex-direction: column;
            align-items: center;

            max-width: 420px;
            width: 100%;

            text-align: center;
        }

        .li-badge {
            position: relative;
            width: 132px;
            height: 132px;
            margin-bottom: 30px;
        }

        /* pulsing rings — soft gold on dark */
        .li-badge::before,
        .li-badge::after {
            content: "";
            position: absolute;
            inset: 0;

            border: 2px solid rgba(237, 164, 35, 0.28);
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);

            animation: liRing 2.4s cubic-bezier(0, 0, 0.2, 1) infinite;
        }

        .li-badge::after {
            animation-delay: 1.2s;
        }

        @keyframes liRing {
            0%   { transform: scale(1);    opacity: .7; }
            100% { transform: scale(1.85); opacity: 0; }
        }

        .li-badge svg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            overflow: visible;
        }

        .li-hex-outline {
            fill: rgba(237, 164, 35, 0.08);
            stroke: var(--li-honey);
            stroke-width: 3;
            stroke-linejoin: round;

            stroke-dasharray: 320;
            stroke-dashoffset: 320;

            animation: liDraw 0.9s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        @keyframes liDraw {
            to { stroke-dashoffset: 0; }
        }

        /* the spinning core — deeper amber, glows on the dark */
        .li-hex-core {
            fill: var(--li-honey-dark);
            transform-origin: 50% 50%;
            animation: liSpin 1.1s cubic-bezier(0.65, 0, 0.35, 1) infinite;
            filter: drop-shadow(0 0 14px rgba(237, 164, 35, 0.35));
        }

        .li-veil.success .li-hex-core {
            animation: liCoreAway 0.45s ease forwards;
        }

        @keyframes liSpin {
            to { transform: rotate(360deg); }
        }

        @keyframes liCoreAway {
            to { transform: scale(0) rotate(180deg); opacity: 0; }
        }

        .li-orbit {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 10px;
            height: 12px;

            background: var(--li-honey-dark);
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);

            transform-origin: 0 0;

            animation: liOrbit 1.6s linear infinite;
        }

        .li-orbit-2 { animation-delay: -0.8s; width: 7px; height: 8px; opacity: .7; }

        .li-veil.success .li-orbit {
            animation: liFadeOut 0.3s ease forwards;
        }

        @keyframes liOrbit {
            from { transform: rotate(0deg)   translateX(84px) rotate(0deg); }
            to   { transform: rotate(360deg) translateX(84px) rotate(-360deg); }
        }

        @keyframes liFadeOut {
            to { opacity: 0; }
        }

        .li-check {
            position: absolute;
            inset: 0;
            margin: auto;

            width: 56px;
            height: 56px;

            opacity: 0;
        }

        .li-check path {
            fill: none;
            stroke: #ffffff;
            stroke-width: 6;
            stroke-linecap: round;
            stroke-linejoin: round;

            stroke-dasharray: 60;
            stroke-dashoffset: 60;
        }

        .li-veil.success .li-check {
            opacity: 1;
        }

        .li-veil.success .li-check path {
            animation: liDraw 0.55s cubic-bezier(0.22, 1, 0.36, 1) 0.35s forwards;
        }

        /* the check disc — warm amber against the navy, glowing */
        .li-check-disc {
            position: absolute;
            inset: 0;
            margin: auto;

            width: 72px;
            height: 72px;

            background: linear-gradient(135deg, var(--li-honey-dark), #b97f10);

            border-radius: 50%;

            box-shadow: 0 12px 34px rgba(0, 0, 0, 0.45), 0 0 24px rgba(237, 164, 35, 0.3);

            transform: scale(0);
        }

        .li-veil.success .li-check-disc {
            animation: liPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s forwards;
        }

        @keyframes liPop {
            0%   { transform: scale(0); }
            70%  { transform: scale(1.15); }
            100% { transform: scale(1); }
        }

        /* =============================================
           TEXT — light on dark
        ============================================== */
        .li-loading-text {
            margin: 0;
            color: var(--li-ink);
            font-size: 17px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .li-loading-dots::after {
            content: "";
            animation: liDots 1.4s steps(4, end) infinite;
        }

        @keyframes liDots {
            0%  { content: ""; }
            25% { content: "."; }
            50% { content: ".."; }
            75% { content: "..."; }
            100% { content: ""; }
        }

        .li-sub-text {
            margin: 8px 0 0;

            color: rgba(238, 242, 246, 0.55);
            font-size: 12.5px;
            font-weight: 500;
        }

        .li-success-text {
            display: none;
        }

        .li-veil.success .li-loading-text,
        .li-veil.success .li-sub-text {
            display: none;
        }

        .li-veil.success .li-success-text {
            display: block;
        }

        /* eyebrow pill — translucent dark chip with gold text */
        .li-success-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            padding: 6px 15px;

            background: rgba(237, 164, 35, 0.14);
            border: 1px solid rgba(237, 164, 35, 0.4);
            border-radius: 999px;

            color: var(--li-honey-light);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;

            opacity: 0;
            transform: translateY(10px);
        }

        .li-veil.success .li-success-eyebrow {
            animation: liRise 0.5s cubic-bezier(0.22, 1, 0.36, 1) 0.75s forwards;
        }

        .li-welcome {
            margin: 14px 0 0;

            font-size: clamp(24px, 4.5vw, 34px);
            font-weight: 800;
            letter-spacing: -0.6px;
            line-height: 1.15;

            color: var(--li-ink);

            opacity: 0;
            transform: translateY(12px);
        }

        .li-veil.success .li-welcome {
            animation: liRise 0.55s cubic-bezier(0.22, 1, 0.36, 1) 0.9s forwards;
        }

        /* shimmering name — warm gold reads great on navy */
        .li-shimmer {
            background: linear-gradient(92deg, #eda423 0%, #f6c04e 45%, #eda423 90%);
            background-size: 200% auto;

            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;

            animation: liShimmer 2.6s linear infinite;
        }

        @keyframes liShimmer {
            to { background-position: 200% center; }
        }

        .li-success-sub {
            margin: 10px 0 0;

            color: rgba(238, 242, 246, 0.55);
            font-size: 13px;

            opacity: 0;
            transform: translateY(10px);
        }

        .li-veil.success .li-success-sub {
            animation: liRise 0.5s cubic-bezier(0.22, 1, 0.36, 1) 1.05s forwards;
        }

        @keyframes liRise {
            to { opacity: 1; transform: translateY(0); }
        }

        /* progress bar — dark track, honey fill */
        .li-progress {
            width: 240px;
            height: 8px;
            margin-top: 30px;

            background: rgba(238, 242, 246, 0.12);
            border-radius: 999px;
            overflow: hidden;

            opacity: 0;
        }

        .li-veil.success .li-progress {
            animation: liFadeIn 0.4s ease 1.1s forwards;
        }

        .li-progress span {
            display: block;
            width: 0%;
            height: 100%;

            background: linear-gradient(90deg, var(--li-honey-dark), var(--li-honey-light));
            border-radius: 999px;

            box-shadow: 0 0 12px rgba(237, 164, 35, 0.4);
        }

        .li-veil.success .li-progress span {
            animation: liFill 1.4s cubic-bezier(0.22, 1, 0.36, 1) 1.15s forwards;
        }

        @keyframes liFill {
            to { width: 100%; }
        }

        @keyframes liFadeIn {
            to { opacity: 1; }
        }

        .li-particle {
            position: fixed;
            z-index: 10000;

            width: 12px;
            height: 14px;

            background: var(--li-honey);
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);

            pointer-events: none;
        }

        @media (prefers-reduced-motion: reduce) {
            .li-veil::before,
            .li-blob,
            .li-hex,
            .li-badge::before,
            .li-badge::after {
                animation: none !important;
            }

            .li-hex-outline {
                animation: none;
                stroke-dashoffset: 0;
            }

            .li-hex-core,
            .li-orbit {
                animation-duration: 2.4s;
            }

            .li-veil.success .li-check path {
                animation-duration: 0.01s;
                animation-delay: 0.1s;
            }

            .li-veil.success .li-check-disc {
                animation-duration: 0.01s;
                animation-delay: 0.05s;
            }

            .li-veil.success .li-success-eyebrow,
            .li-veil.success .li-welcome,
            .li-veil.success .li-success-sub {
                animation-duration: 0.01s;
            }

            .li-veil.success .li-progress span {
                animation-duration: 0.5s;
            }
        }

        @media (max-width: 480px) {
            .li-badge {
                width: 110px;
                height: 110px;
            }

            .li-progress {
                width: 200px;
            }
        }
    </style>

</head>

<body>

    <main class="login-page">
        <div class="login-card">

            <!-- Close / Back to Home — INSIDE the card so it pins
                 to the card's top-right edge (see .close-button in
                 loginform.css; the card is the position anchor). -->
            <a href="/webprogg/index.php" class="close-button" aria-label="Close">
                &times;
            </a>

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

                <div class="login-error" id="loginServerError">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php else: ?>

                <div class="login-error" id="loginServerError" style="display:none;"></div>

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
                id="loginPageForm"
            >

                <input
                    type="hidden"
                    name="redirect"
                    value="<?php echo htmlspecialchars($redirectParam); ?>"
                >

                <!-- Email -->
                <div class="login-input-group">

                    <label for="email">

                        <img
                            src="/webprogg/images/EmailIcon.png"
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
                    id="loginPageSubmit"
                >
                    Log in
                </button>

            </form>

            <!-- Google — now a working OAuth flow -->
            <button
                type="button"
                class="social-login google-login"
                onclick="window.location.href='/webprogg/auth/google-login.php<?php echo $redirectParam !== '' ? '?redirect=' . urlencode($redirectParam) : ''; ?>'"
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

    <!-- =====================================================
         LOGIN VEIL — loading + success animation.
         Dark "night hive" edition: deep navy backdrop, dim
         amber embers, light text — honey stays as the accent.
    ====================================================== -->
    <div
        class="li-veil"
        id="liVeil"
        aria-hidden="true"
        role="status"
        aria-live="polite"
    >

        <span class="li-blob li-blob-1" aria-hidden="true"></span>
        <span class="li-blob li-blob-2" aria-hidden="true"></span>

        <span class="li-hex li-hex-1" aria-hidden="true"></span>
        <span class="li-hex li-hex-2" aria-hidden="true"></span>
        <span class="li-hex li-hex-3" aria-hidden="true"></span>
        <span class="li-hex li-hex-4" aria-hidden="true"></span>
        <span class="li-hex li-hex-5" aria-hidden="true"></span>
        <span class="li-hex li-hex-6" aria-hidden="true"></span>

        <div class="li-stage">

            <div class="li-badge" aria-hidden="true">

                <svg viewBox="0 0 120 132">
                    <polygon
                        class="li-hex-outline"
                        points="60,4 114,35 114,97 60,128 6,97 6,35"
                    ></polygon>
                </svg>

                <svg viewBox="0 0 120 132" style="pointer-events:none;">
                    <polygon
                        class="li-hex-core"
                        points="60,32 96,53 96,95 60,116 24,95 24,53"
                    ></polygon>
                </svg>

                <span class="li-orbit"></span>
                <span class="li-orbit li-orbit-2"></span>

                <span class="li-check-disc"></span>

                <svg class="li-check" viewBox="0 0 56 56">
                    <path d="M14 29 L24 39 L42 19"></path>
                </svg>

            </div>

            <!-- LOADING state copy -->
            <p class="li-loading-text">
                <span id="liLoadingLabel">Signing you in</span><span class="li-loading-dots"></span>
            </p>
            <p class="li-sub-text">
                Checking your credentials securely…
            </p>

            <!-- SUCCESS state copy -->
            <div class="li-success-text">

                <span class="li-success-eyebrow">
                    &#10003; Login Successful
                </span>

                <h2 class="li-welcome" id="liWelcome">
                    Welcome back,
                    <span class="li-shimmer" id="liWelcomeName">Friend</span>!
                </h2>

                <p class="li-success-sub" id="liSuccessSub">
                    Taking you to your home…
                </p>

            </div>

            <!-- honey progress bar -->
            <div class="li-progress" aria-hidden="true">
                <span></span>
            </div>

        </div>

    </div>

    <script>
    (function () {
        "use strict";

        /* =========================================================
           BFCACHE GUARD
           Safari/Firefox can restore this page from the
           back-forward cache (Back/Forward buttons) with NO
           server request — bypassing even no-store headers.
           If that happens, force a reload so the PHP guard at
           the top of this file re-runs and bounces any
           logged-in user back to their home page.
        ========================================================= */
        window.addEventListener("pageshow", function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        var veil      = document.getElementById("liVeil");
        var form      = document.getElementById("loginPageForm");
        var errorBox  = document.getElementById("loginServerError");
        var submitBtn = document.getElementById("loginPageSubmit");

        if (!veil || !form) { return; }

        var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

        var REDIRECT_DELAY   = reduced ? 900 : 2300;
        var FETCH_TIMEOUT_MS = 8000;

        var busy = false;

        function openVeil() {
            veil.classList.remove("success");
            veil.classList.add("open");
            veil.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";
        }

        function closeVeil() {
            veil.classList.remove("open");
            veil.classList.remove("success");
            veil.setAttribute("aria-hidden", "true");
            document.body.style.overflow = "";
        }

        function successVeil(displayName, isAdmin) {

            veil.classList.add("success");

            var nameEl = document.getElementById("liWelcomeName");
            if (nameEl) {
                nameEl.textContent = (displayName || (isAdmin ? "Admin" : "Friend")).trim();
            }

            var subEl = document.getElementById("liSuccessSub");
            if (subEl) {
                subEl.textContent = isAdmin
                    ? "Taking you to the admin dashboard…"
                    : "Taking you to your home…";
            }

            var label = document.getElementById("liLoadingLabel");
            if (label) {
                label.textContent = "Signing you in";
            }

            burstParticles();
        }

        function burstParticles() {
            if (reduced) { return; }

            var cx = window.innerWidth / 2;
            var cy = window.innerHeight / 2 - 40;
            var colors = ["#eda423", "#f6c04e", "#d99218", "#eef2f6", "#1fa971"];

            for (var i = 0; i < 26; i++) {

                var p = document.createElement("span");
                p.className = "li-particle";

                var size = 7 + Math.random() * 12;
                p.style.width = size + "px";
                p.style.height = (size * 1.2) + "px";
                p.style.left = (cx - size / 2) + "px";
                p.style.top = (cy - size / 2) + "px";
                p.style.background = colors[i % colors.length];

                document.body.appendChild(p);

                var angle = (Math.PI * 2 * i) / 26 + (Math.random() * 0.5 - 0.25);
                var dist = 90 + Math.random() * 190;
                var dx = Math.cos(angle) * dist;
                var dy = Math.sin(angle) * dist - 40;

                p.animate(
                    [
                        { transform: "translate(0,0) rotate(0deg) scale(1)", opacity: 1 },
                        {
                            transform: "translate(" + dx + "px," + (dy + 140) + "px) rotate(" +
                                (Math.random() > 0.5 ? 360 : -360) + "deg) scale(0.2)",
                            opacity: 0
                        }
                    ],
                    {
                        duration: 900 + Math.random() * 600,
                        easing: "cubic-bezier(.22,1,.36,1)",
                        fill: "forwards"
                    }
                ).onfinish = (function (el) {
                    return function () { el.remove(); };
                })(p);
            }
        }

        function showInlineError(message) {
            closeVeil();

            if (errorBox) {
                errorBox.textContent = message;
                errorBox.style.display = "block";
            }

            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = "Log in";
            }

            busy = false;
        }

        form.addEventListener("submit", function (event) {

            if (typeof form.reportValidity === "function" && !form.reportValidity()) {
                return;
            }

            if (busy) {
                event.preventDefault();
                return;
            }

            busy = true;
            event.preventDefault();

            openVeil();

            var formData = new FormData(form);

            var fetchPromise = fetch("/webprogg/auth/loginform.php", {
                method: "POST",
                headers: { "X-Requested-With": "XMLHttpRequest" },
                body: formData,
                credentials: "same-origin"
            })
            .then(function (res) { return res.json(); });

            var timeoutPromise = new Promise(function (_resolve, reject) {
                window.setTimeout(function () {
                    reject(new Error("timeout"));
                }, FETCH_TIMEOUT_MS);
            });

            Promise.race([fetchPromise, timeoutPromise])
                .then(function (data) {

                    if (data && data.success) {

                        successVeil(data.name, !!data.admin);

                        /* location.replace() removes the login page
                           from history — Back from the next page can't
                           land here again. */
                        window.setTimeout(function () {
                            window.location.replace(
                                data.redirect || "/webprogg/user/usershome.php"
                            );
                        }, REDIRECT_DELAY);

                        return;
                    }

                    showInlineError(
                        (data && data.error) || "Incorrect email address or password."
                    );
                })
                .catch(function () {

                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = "Logging in...";
                    }

                    var label = document.getElementById("liLoadingLabel");
                    if (label) { label.textContent = "Still working"; }

                    form.submit();
                });
        });
    })();
    </script>

</body>

</html>