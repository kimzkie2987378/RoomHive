<?php
/* =========================================================
   ROOMHIVE — MY ACCOUNT
   editprofile.php

   === AUTO-VERIFICATION ===
   ID uploaded + profile complete (name, phone, age,
   location) = AUTOMATICALLY Verified, instantly.

   === CAMERA (this version) ===
   - All modal buttons (Capture / Retake / Upload / Cancel)
     wired by DOCUMENT-LEVEL CAPTURE-PHASE delegation —
     fires before any other script, cannot be blocked.
   - .cam-stage and everything inside it are
     pointer-events:none — the video/flash/guide layers can
     never sit over the button row.
   - .cam-actions has explicit z-index above the stage.
   - 'id' target: Upload fills #uidFile + submits ID form.
   - 'personal' target: Upload POSTs to uploadpersonal.php
     (users.personal_photo — never touches avatar_path).
========================================================= */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/verification_gate.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

/* ---- Self-heal personal_photo BEFORE prepare()
        (EMULATE_PREPARES=false validates at prepare time) ---- */
try {
    $ppCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'personal_photo'")->fetch();
    if (!$ppCol) {
        $pdo->exec("ALTER TABLE users ADD COLUMN personal_photo VARCHAR(255) NULL");
    }
} catch (PDOException $e) {
    error_log('editprofile: personal_photo ensure failed: ' . $e->getMessage());
}

 $hasPersonalPhoto = true;
try {
    $stmt = $pdo->prepare(
        "SELECT id, name, email, phone, age, location, avatar_path, is_host, personal_photo
         FROM users WHERE id = :id LIMIT 1"
    );
} catch (PDOException $e) {
    $hasPersonalPhoto = false;
    try {
        $stmt = $pdo->prepare(
            "SELECT id, name, email, phone, age, location, avatar_path, is_host
             FROM users WHERE id = :id LIMIT 1"
        );
    } catch (PDOException $e2) {
        error_log('editprofile: users select prepare failed: ' . $e2->getMessage());
        die('Sorry, something went wrong. Please try again later.');
    }
}

try {
    $stmt->execute(['id' => $_SESSION['user_id']]);
} catch (PDOException $e) {
    error_log('editprofile: users select execute failed: ' . $e->getMessage());
    die('Sorry, something went wrong. Please try again later.');
}

 $dbUser = $stmt->fetch();

if (!$dbUser) {
    session_destroy();
    header("Location: /webprogg/auth/loginform.php");
    exit;
}

if ((int) $dbUser['is_host'] === 1) {
    header("Location: /webprogg/host/hostprofile.php");
    exit;
}

 $navAvatar = sync_user_session($dbUser);
 $notification_count = 0;

 $errors = [];
 $saved = false;
 $savedId = isset($_GET['saved']) && $_GET['saved'] === 'id';

if (!function_exists('ep_profile_complete')) {
    function ep_profile_complete(array $u) {
        return trim((string) ($u['name']     ?? '')) !== ''
            && trim((string) ($u['phone']    ?? '')) !== ''
            && (($u['age'] ?? null) !== null && $u['age'] !== '')
            && trim((string) ($u['location'] ?? '')) !== '';
    }
}

if (!function_exists('ep_notify')) {
    function ep_notify($pdo, $userId, $message, $link) {
        try {
            $pdo->prepare(
                "INSERT INTO notifications (user_id, message, link, is_read)
                 VALUES (:u, :m, :l, 0)"
            )->execute([
                ':u' => (int) $userId,
                ':m' => mb_substr($message, 0, 240),
                ':l' => $link,
            ]);
        } catch (PDOException $e) { /* best-effort */ }
    }
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_id_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            id_type VARCHAR(60) NOT NULL,
            id_number VARCHAR(80) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
            admin_note VARCHAR(255) NULL,
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL,
            INDEX idx_uid (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    error_log('editprofile: could not ensure user_id_documents table: ' . $e->getMessage());
}

 $idTypes = [
    'passport'         => 'Passport',
    'drivers-license'  => "Driver's License",
    'philsys'          => 'PhilSys / National ID',
    'umid'             => 'UMID',
    'voters-id'        => "Voter's ID",
    'postal-id'        => 'Postal ID',
    'prc-id'           => 'PRC ID',
    'student-id'       => 'Student ID',
    'sss-id'           => 'SSS ID',
    'tin-id'           => 'TIN ID',
];

/* =========================================================
   ID UPLOAD
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_id'])) {

    $idType   = trim($_POST['id_type'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    $file     = $_FILES['id_file'] ?? null;

    if (!csrf_verify()) {

        $errors[] = 'Your session expired. Please try uploading again.';

    } elseif (!isset($idTypes[$idType])) {

        $errors[] = 'Please choose a valid ID type.';

    } elseif ($idNumber === '' || mb_strlen($idNumber) > 80) {

        $errors[] = 'Please enter the ID number (max 80 characters).';

    } elseif (!$file || !is_array($file) || $file['error'] !== UPLOAD_ERR_OK) {

        $errors[] = 'Please choose a file or take a photo'
            . ($file && (int) $file['error'] === UPLOAD_ERR_INI_SIZE ? ' (file is too large).' : '.');

    } elseif ((int) $file['size'] > 5 * 1024 * 1024) {

        $errors[] = 'File is too large. Maximum size is 5MB.';

    } else {

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

        if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {

            $errors[] = 'Invalid file type. Allowed: JPG, PNG, WEBP, or PDF.';

        } else {

            $dir = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/id_documents';

            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $newName  = 'uid' . (int) $_SESSION['user_id']
                      . '_' . bin2hex(random_bytes(8))
                      . '.' . $ext;
            $destPath = $dir . '/' . $newName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {

                $errors[] = 'Could not save the file. Please try again.';

            } else {

                $profileComplete = ep_profile_complete($dbUser);
                $docStatus       = $profileComplete ? 'verified' : 'pending';

                try {
                    $ins = $pdo->prepare(
                        "INSERT INTO user_id_documents
                            (user_id, id_type, id_number, file_path, status, uploaded_at)
                         VALUES
                            (:u, :t, :n, :f, :s, NOW())"
                    );
                    $ins->execute([
                        ':u' => (int) $_SESSION['user_id'],
                        ':t' => $idType,
                        ':n' => $idNumber,
                        ':f' => '/webprogg/uploads/id_documents/' . $newName,
                        ':s' => $docStatus,
                    ]);

                    if ($profileComplete) {
                        ep_notify(
                            $pdo,
                            $_SESSION['user_id'],
                            "You're verified! Your Verified badge is now active and you can list a space.",
                            '/webprogg/user/userprofile.php'
                        );
                    }

                    header('Location: /webprogg/user/editprofile.php?saved=id#verify-card');
                    exit;

                } catch (PDOException $e) {
                    error_log('editprofile: ID insert failed: ' . $e->getMessage());
                    $errors[] = 'Could not save your ID. Please try again.';
                    @unlink($destPath);
                }
            }
        }
    }
}

/* ---- Profile save + auto-verify promotion ---- */
 $justVerified = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['upload_id'])) {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $age      = trim($_POST['age'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if ($name === '')     { $errors[] = 'Full name is required.'; }
        if ($phone === '')    { $errors[] = 'Phone number is required to get verified.'; }
        if ($age === '')      { $errors[] = 'Age is required to get verified.'; }
        if ($location === '') { $errors[] = 'Location is required to get verified.'; }
        if ($age !== '' && (!ctype_digit($age) || (int) $age < 18 || (int) $age > 120)) {
            $errors[] = 'Age must be a number between 18 and 120.';
        }

        if (empty($errors)) {
            $updateStmt = $pdo->prepare(
                "UPDATE users SET name = :name, phone = :phone, age = :age, location = :location WHERE id = :id"
            );
            $updateStmt->execute([
                'name'     => $name,
                'phone'    => $phone,
                'age'      => (int) $age,
                'location' => $location,
                'id'       => $_SESSION['user_id'],
            ]);

            $dbUser['name']     = $name;
            $dbUser['phone']    = $phone;
            $dbUser['age']      = $age;
            $dbUser['location'] = $location;

            $saved = true;

            try {
                $hasDoc = $pdo->prepare(
                    "SELECT COUNT(*) FROM user_id_documents
                     WHERE user_id = :u AND status != 'rejected'"
                );
                $hasDoc->execute([':u' => (int) $_SESSION['user_id']]);

                if ((int) $hasDoc->fetchColumn() > 0 && ep_profile_complete($dbUser)) {

                    $promote = $pdo->prepare(
                        "UPDATE user_id_documents
                         SET status = 'verified', reviewed_at = NOW()
                         WHERE user_id = :u AND status = 'pending'"
                    );
                    $promote->execute([':u' => (int) $_SESSION['user_id']]);

                    if ($promote->rowCount() > 0) {
                        $justVerified = true;
                        ep_notify(
                            $pdo,
                            $_SESSION['user_id'],
                            "You're verified! Your Verified badge is now active and you can list a space.",
                            '/webprogg/user/userprofile.php'
                        );
                    }
                }
            } catch (PDOException $e) {
                error_log('editprofile: auto-verify promotion failed: ' . $e->getMessage());
            }
        }
    }
}

 $idDocs = [];
try {
    $idStmt = $pdo->prepare(
        "SELECT id, id_type, id_number, file_path, status, admin_note, uploaded_at
         FROM user_id_documents
         WHERE user_id = :u
         ORDER BY uploaded_at DESC
         LIMIT 5"
    );
    $idStmt->execute(['u' => (int) $_SESSION['user_id']]);
    $idDocs = $idStmt->fetchAll();
} catch (PDOException $e) {
    $idDocs = [];
}

 $isVerified     = is_user_verified($pdo, $_SESSION['user_id']);
 $hasIdNoProfile = !$isVerified && !empty($idDocs);

if (!function_exists('uid_status_badge')) {
    function uid_status_badge($status) {
        $map = [
            'pending'  => ['Needs complete profile', 'uid-badge-pending'],
            'verified' => ['Verified',               'uid-badge-verified'],
            'rejected' => ['Rejected',               'uid-badge-rejected'],
        ];
        $s = $map[$status] ?? $map['pending'];
        return '<span class="uid-badge ' . $s[1] . '">' . htmlspecialchars($s[0], ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

 $user = [
    'name'     => $dbUser['name'],
    'avatar'   => !empty($dbUser['avatar_path']) ? $dbUser['avatar_path'] : '/webprogg/images/default-avatar.png',
    'personal' => ($hasPersonalPhoto && !empty($dbUser['personal_photo']))
        ? $dbUser['personal_photo']
        : '',
    'email'    => $dbUser['email'],
    'phone'    => $dbUser['phone'] ?? '',
    'age'      => $dbUser['age'] ?? '',
    'location' => $dbUser['location'] ?? '',
];

 $activeSidebar = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile &amp; Account — RoomHive</title>
<script>try{if(localStorage.getItem("rhTheme")==="dark"){document.documentElement.setAttribute("data-theme-preview","1");}}catch(e){}</script>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/myaccount.css">
<script>document.documentElement.classList.add("js");</script>
<style>
.uid-badge{display:inline-block;font-size:10.5px;font-weight:800;letter-spacing:.04em;border-radius:999px;padding:3px 10px;white-space:nowrap}
.uid-badge-pending{background:#FFF1DC;color:#B07708;border:1px solid rgba(237,164,35,.5)}
.uid-badge-verified{background:#E8F8F1;color:#178A50;border:1px solid rgba(23,138,80,.35)}
.uid-badge-rejected{background:#FDECEC;color:#A4302F;border:1px solid #F3B9B9}

.uid-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #ECECEC;border-radius:10px;margin-bottom:8px;background:#FBFBF9}
.uid-item-thumb{width:40px;height:40px;border-radius:8px;object-fit:cover;background:#F0EEE6;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:18px;overflow:hidden}
.uid-item-body{flex:1;min-width:0}
.uid-item-body strong{display:block;font-size:12.5px;color:#1c2a38}
.uid-item-body span{display:block;font-size:11px;color:#8d99a5;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.uid-item-note{font-size:11px;color:#A4302F;margin-top:2px;white-space:normal}

.uid-upload-box{border:1.5px dashed #D9D9D9;border-radius:10px;background:#FBFCFD;padding:16px 14px;text-align:center;cursor:pointer;transition:border-color .15s ease,background .15s ease;display:block}
.uid-upload-box:hover{border-color:#eda423;background:#FFFAF0}
.uid-upload-box input[type="file"]{display:none}
.uid-upload-ico{font-size:26px;line-height:1;color:#293541}
.uid-upload-text strong{display:block;font-size:13px;color:#39444e;font-weight:600;margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.uid-upload-text span{display:block;font-size:11.5px;color:#888f95;margin-top:2px}
.uid-upload-box.has-file{border-color:#eda423;background:#FDF4E3}
.uid-upload-box.has-file .uid-upload-text strong{color:#B07708}

.uid-verified-banner{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:10px;background:#E8F8F1;border:1px solid rgba(23,138,80,.35);color:#178A50;font-size:13px;font-weight:700;margin-bottom:12px}
.uid-steps{font-size:12.5px;color:#777777;margin:0 0 12px;line-height:1.6;padding-left:18px}

.uid-cam-btn{display:inline-flex;align-items:center;gap:7px;border:1.5px solid #eda423;background:#FDF4E3;color:#B07708;font-family:inherit;font-size:12.5px;font-weight:700;border-radius:10px;padding:10px 16px;cursor:pointer;transition:background .15s ease,transform .15s ease;position:relative;z-index:5;pointer-events:auto;-webkit-tap-highlight-color:transparent;user-select:none}
.uid-cam-btn:hover{background:#eda423;color:#fff;transform:translateY(-1px)}
.uid-cam-row{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;position:relative;z-index:5}

.cam-overlay{position:fixed;inset:0;z-index:9000;background:rgba(15,25,20,.85);display:none;align-items:center;justify-content:center;padding:16px}
.cam-overlay.open{display:flex}
.cam-card{background:#fff;border-radius:18px;width:100%;max-width:520px;max-height:calc(100vh - 32px);overflow-y:auto;box-shadow:0 30px 70px rgba(0,0,0,.4)}
.cam-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #eee4d3}
.cam-head strong{font-size:14px;color:#1c2a38}
.cam-close{width:32px;height:32px;border:none;border-radius:50%;background:#f4f1e7;color:#62705f;cursor:pointer;font-size:16px;line-height:1}
.cam-close:hover{background:#dd930f;color:#fff}

/* THE CLICK FIX — the stage and EVERYTHING inside it can
   never intercept a click; the button row sits above. */
.cam-stage{position:relative;width:100%;aspect-ratio:4/3;background:#111;overflow:hidden;pointer-events:none !important}
.cam-stage *{pointer-events:none !important}
.cam-stage video,.cam-stage img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;display:block}
.cam-stage video{transform:scaleX(-1)}
.cam-stage img{visibility:hidden}
.cam-stage.captured video{visibility:hidden}
.cam-stage.captured img{visibility:visible}
.cam-flash-dot{position:absolute;inset:0;background:#fff;opacity:0}
.cam-flash-dot.go{animation:camFlash .35s ease}
@keyframes camFlash{0%{opacity:.85}100%{opacity:0}}
.cam-guide{position:absolute;left:50%;bottom:10px;transform:translateX(-50%);background:rgba(0,0,0,.55);color:#fff;font-size:11.5px;padding:6px 12px;border-radius:999px}
.cam-err{margin:12px 18px 0;padding:10px 12px;border-radius:10px;background:#FDECEC;color:#A4302F;font-size:12.5px;border:1px solid #F3B9B9;display:none}

/* Button row — own stacking context above everything */
.cam-actions{position:relative;z-index:9100;display:flex;gap:8px;padding:14px 18px 18px;flex-wrap:wrap}
.cam-btn{flex:1;min-width:110px;padding:12px;border:none;border-radius:10px;font-family:inherit;font-size:13px;font-weight:800;cursor:pointer;pointer-events:auto !important;transition:transform .15s ease}
.cam-btn:hover{transform:translateY(-1px)}
.cam-shutter{background:linear-gradient(135deg,#f6b93b,#eda423);color:#1c2a38}
.cam-upload{background:#178A50;color:#fff}
.cam-cancel{background:#F6F7FB;color:#1c2a38;flex:0 0 auto;padding:12px 18px}
[hidden]{display:none !important}
.cam-note{padding:0 18px 12px;font-size:11.5px;color:#8d99a5;text-align:center}

/* PERSONAL PHOTO — full-width box preview */
.ep-photo-section{border:1.5px dashed #e3e7ec;border-radius:12px;padding:16px;background:#fbfcfd;text-align:center;overflow:hidden}
.ep-photo-preview{width:100% !important;max-width:100% !important;height:220px !important;object-fit:cover;object-position:center;border-radius:14px;border:1px solid rgba(28,42,56,.12);background:#F0EEE6;display:block;margin:0 0 10px 0;box-shadow:0 4px 14px rgba(28,42,56,.12)}
.ep-photo-preview.is-empty{background:repeating-linear-gradient(45deg,#F0EEE6,#F0EEE6 12px,#EAE5D6 12px,#EAE5D6 24px)}
.ep-photo-btns{display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:10px}
.ep-photo-status{display:block;font-size:12px;font-weight:600;margin-top:8px;min-height:16px}
.ep-photo-status.ok{color:#178A50}
.ep-photo-status.err{color:#A4302F}
.ep-photo-file{display:none}
.uid-hidden-file{display:none}

/* Profile-picture avatar (top of form) */
.up-profile-photo img{border-radius:12px;object-fit:cover}
</style>
</head>
<body>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/usernav.php'; ?>

<header class="ub-page-head">
    <span class="ub-eyebrow">Profile &amp; Account</span>
    <h1>Keep your details up to date</h1>
    <p class="ub-lead">Hosts and support use this info to reach you about your bookings.</p>
</header>

<main class="up-dashboard">

  <?php require $_SERVER['DOCUMENT_ROOT'] . '/webprogg/includes/sidebar.php'; ?>

  <div class="up-content">

    <?php if (!empty($_SESSION['verification_redirect'])): ?>
      <section class="up-alert up-alert-error">
        <p><?php echo h($_SESSION['verification_redirect']); unset($_SESSION['verification_redirect']); ?></p>
      </section>
    <?php endif; ?>

    <?php if ($savedId && $isVerified): ?>
      <section class="up-alert up-alert-success">
        <p>&#10003; You're verified! Your Verified badge is now active and you can list a space.</p>
      </section>
    <?php elseif ($savedId): ?>
      <section class="up-alert up-alert-success">
        <p>&#10003; ID uploaded. Fill in your name, phone, age, and location below to be verified automatically.</p>
      </section>
    <?php elseif ($saved && $justVerified): ?>
      <section class="up-alert up-alert-success">
        <p>&#10003; Profile saved — and you're now VERIFIED! Your badge is active and listing is unlocked.</p>
      </section>
    <?php elseif ($saved): ?>
      <section class="up-alert up-alert-success">
        <p>&#10003; Your profile has been updated.</p>
      </section>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <section class="up-alert up-alert-error">
        <?php foreach ($errors as $error): ?>
          <p><?php echo h($error); ?></p>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <div class="up-two-col">

      <!-- ============ LEFT: PROFILE FORM ============ -->
      <form method="POST" action="/webprogg/user/editprofile.php" class="up-card up-reveal" style="display:block;">
        <?php echo csrf_field(); ?>

        <div class="up-profile-photo" style="margin-bottom:20px;">
          <img src="<?php echo h($user['avatar']); ?>" alt="<?php echo h($user['name']); ?>" id="epAvatarImg">
        </div>

        <div style="display:flex; flex-direction:column; gap:16px;">

          <div class="up-field">
            <label for="epName">Full Name</label>
            <input type="text" id="epName" name="name" value="<?php echo h($user['name']); ?>" required>
          </div>

          <div class="up-field">
            <label for="epEmail">Email</label>
            <input type="email" id="epEmail" value="<?php echo h($user['email']); ?>" disabled>
            <span class="up-field-hint">Contact support to change the email on your account.</span>
          </div>

          <div class="up-field">
            <label for="epPhone">Phone Number <span style="color:#B07708;">*</span></label>
            <input type="tel" id="epPhone" name="phone" value="<?php echo h($user['phone']); ?>" placeholder="09XX XXX XXXX" required>
          </div>

          <div class="up-field">
            <label for="epAge">Age <span style="color:#B07708;">*</span></label>
            <input type="number" id="epAge" name="age" value="<?php echo h($user['age']); ?>" min="18" max="120" required>
          </div>

          <div class="up-field">
            <label for="epLocation">Location <span style="color:#B07708;">*</span></label>
            <input type="text" id="epLocation" name="location" value="<?php echo h($user['location']); ?>" placeholder="City, Province" required>
          </div>

          <!-- PERSONAL PHOTO (below Location) — separate from
               the profile picture, saves to personal_photo -->
          <div class="up-field">
            <label>Personal Photo</label>

            <div class="ep-photo-section">

              <?php if ($user['personal'] !== ''): ?>
                <img class="ep-photo-preview" id="epPhotoPreview"
                     src="<?php echo h($user['personal']); ?>" alt="Personal photo"
                     onerror="this.onerror=null;this.style.display='none';">
              <?php else: ?>
                <img class="ep-photo-preview is-empty" id="epPhotoPreview"
                     src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
                     alt="No personal photo yet">
              <?php endif; ?>

              <p style="font-size:11.5px;color:#8d99a5;margin:0 0 6px;">
                This is separate from your profile picture — hosts use it to
                recognize you when you meet in person.
              </p>

              <div class="ep-photo-btns">
                <button type="button" class="uid-cam-btn" data-cam-open="personal">
                  &#128247; Take Photo
                </button>
                <button type="button" class="uid-cam-btn" id="epPhotoFileBtn" style="background:#fff;">
                  &#128193; Choose File
                </button>
              </div>

              <input type="file" id="epPhotoFile" class="ep-photo-file"
                     accept="image/jpeg,image/png,image/webp">

              <span class="ep-photo-status" id="epPhotoStatus"></span>
            </div>
          </div>

        </div>

        <button type="submit" class="up-btn-solid" style="margin-top:20px;">SAVE CHANGES</button>
      </form>

      <!-- ============ RIGHT COLUMN ============ -->
      <div class="up-right-col">

        <div class="up-card up-account-security up-reveal" id="verify-card" style="--i: 0; scroll-margin-top: 120px;">
          <div class="up-card-header">
            <h3>Verify Your Identity</h3>
            <?php if ($isVerified): ?>
              <span class="uid-badge uid-badge-verified">Verified</span>
            <?php endif; ?>
          </div>

          <?php if ($isVerified): ?>

            <div class="uid-verified-banner">
              &#10003; You're verified! Your badge is active and
              you can list a space.
            </div>

          <?php else: ?>

            <p style="font-size:13px; color:#777777; margin:0 0 10px;">
              Get verified <strong>automatically</strong> — no waiting, no review.
              Complete both steps:
            </p>

            <ol class="uid-steps">
              <li>Fill in your <strong>name, phone, age, and location</strong> (left form) and save.</li>
              <li>Upload <strong>one government-issued ID</strong> below — file or live camera.</li>
            </ol>

            <?php if ($hasIdNoProfile): ?>
              <div class="uid-verified-banner" style="background:#FFF1DC;border-color:rgba(237,164,35,.5);color:#B07708;">
                &#8987; Your ID is received — just complete the four
                profile fields on the left to be verified instantly.
              </div>
            <?php endif; ?>

          <?php endif; ?>

          <?php if (!empty($idDocs)): ?>
            <div style="margin-bottom:14px;">
              <?php foreach ($idDocs as $doc): ?>
                <div class="uid-item">
                  <?php $isPdf = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION)) === 'pdf'; ?>
                  <?php if ($isPdf): ?>
                    <span class="uid-item-thumb" title="PDF document">&#128196;</span>
                  <?php else: ?>
                    <img class="uid-item-thumb" src="<?php echo h($doc['file_path']); ?>" alt="ID document">
                  <?php endif; ?>
                  <div class="uid-item-body">
                    <strong>
                      <?php echo h($idTypes[$doc['id_type']] ?? ucwords(str_replace('-', ' ', $doc['id_type']))); ?>
                      <?php echo uid_status_badge($doc['status']); ?>
                    </strong>
                    <span>
                      &bull;&bull;&bull;&bull;<?php echo h(substr($doc['id_number'], -4)); ?>
                      &middot; uploaded <?php echo h(date('M j, Y', strtotime($doc['uploaded_at']))); ?>
                    </span>
                    <?php if ($doc['status'] === 'rejected' && !empty($doc['admin_note'])): ?>
                      <span class="uid-item-note">Reason: <?php echo h($doc['admin_note']); ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="/webprogg/user/editprofile.php" enctype="multipart/form-data" id="uidUploadForm" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="upload_id" value="1">

            <div class="up-field">
              <label for="uidType">ID Type</label>
              <select id="uidType" name="id_type" required>
                <option value="" disabled selected>Select ID type</option>
                <?php foreach ($idTypes as $val => $label): ?>
                  <option value="<?php echo h($val); ?>"><?php echo h($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="up-field">
              <label for="uidNumber">ID Number</label>
              <input type="text" id="uidNumber" name="id_number" maxlength="80" placeholder="e.g. N12-345-678-901" required>
            </div>

            <div class="up-field">
              <label>ID Document — file or live camera</label>

              <label class="uid-upload-box" id="uidUploadBox" for="uidFile">
                <span class="uid-upload-ico">&#128193;</span>
                <span class="uid-upload-text">
                  <strong id="uidFileName">Choose a file</strong>
                  <span>JPG, PNG, WEBP or PDF &middot; max 5MB</span>
                </span>
              </label>

              <input type="file" id="uidFile" name="id_file" class="uid-hidden-file"
                     accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">

              <div class="uid-cam-row">
                <button type="button" class="uid-cam-btn" data-cam-open="id">
                  &#128247; Take ID photo with camera
                </button>
              </div>
            </div>

            <button type="submit" class="up-btn-solid" style="width:100%;">UPLOAD ID</button>
          </form>
        </div>

        <div class="up-card up-account-security up-reveal" style="--i: 1;">
          <div class="up-card-header">
            <h3>Change Password</h3>
          </div>
          <p style="font-size:13px; color:#777777; margin:0 0 12px;">Update the password you use to sign in.</p>
          <a href="/webprogg/user/security.php" class="up-btn-outline up-manage-security">Manage Security</a>
        </div>

        <div class="up-card up-account-security up-reveal" style="--i: 2;">
          <div class="up-card-header">
            <h3>Profile Picture</h3>
          </div>
          <p style="font-size:13px; color:#777777; margin:0 0 12px;">
            Change your profile picture from the Overview page. Your Personal
            Photo (left) is separate and used for in-person recognition.
          </p>
          <a href="/webprogg/user/userprofile.php" class="up-btn-outline">GO TO OVERVIEW</a>
        </div>

        <div class="up-need-help up-reveal" style="--i: 3;">
          <div class="up-need-help-text">
            <h3>Need Help?</h3>
            <p>Questions about your account? We're here 24/7.</p>
            <a href="/webprogg/user/helpcenter.php" class="up-btn-solid">CONTACT SUPPORT</a>
          </div>
          <img src="/webprogg/images/needhelpicon-userprofile.png" alt="" class="up-need-help-image">
        </div>

      </div>

    </div>
  </div>
</main>

<!-- LIVE CAMERA MODAL -->
<div class="cam-overlay" id="camOverlay" aria-hidden="true">
  <div class="cam-card" role="dialog" aria-modal="true" aria-label="Camera">

    <div class="cam-head">
      <strong id="camTitle">Camera</strong>
      <button type="button" class="cam-close" id="camCloseBtn" aria-label="Close camera">&times;</button>
    </div>

    <div class="cam-stage" id="camStage">
      <video id="camVideo" autoplay playsinline muted></video>
      <img id="camShot" alt="Captured photo">
      <span class="cam-flash-dot" id="camFlash"></span>
      <span class="cam-guide" id="camGuide">Center your photo, then tap Capture</span>
    </div>

    <div class="cam-err" id="camErr"></div>

    <div class="cam-actions">
      <button type="button" class="cam-btn cam-retake" id="camRetakeBtn" hidden>Retake</button>
      <button type="button" class="cam-btn cam-shutter" id="camShutterBtn">&#128247; Capture</button>
      <button type="button" class="cam-btn cam-upload" id="camUploadBtn" hidden>&#11014; Upload</button>
      <button type="button" class="cam-btn cam-cancel" id="camCancelBtn">Cancel</button>
    </div>

    <p class="cam-note" id="camNote">
      The photo is saved exactly as framed in the box above.
    </p>

  </div>
</div>

<footer class="site-footer">
    <div class="footer-top">
        <div class="footer-brand">
            <a href="/webprogg/user/usershome.php">
                <img src="/webprogg/images/RoomHiveLogos.png" alt="RoomHive Logo" class="footer-logo">
            </a>
            <p class="footer-tagline">Find, stay, relax, at home. RoomHive helps you discover comfortable stays across Negros Oriental.</p>
            <div class="footer-contact-line"><img src="/webprogg/images/PhoneIcon.jpg" alt=""><span>0927 569 3574</span></div>
            <div class="footer-contact-line"><img src="/webprogg/images/EmailIcon.jpg" alt=""><span>kimdivino55@gmail.com</span></div>
            <div class="footer-contact-line"><img src="/webprogg/images/GPSIcon.png" alt=""><span>Dumaguete City, Negros Oriental, Philippines</span></div>
        </div>
        <div class="footer-links">
            <span class="footer-heading">LISTINGS</span>
            <a href="/webprogg/Listings/listing.php?category=studio-loft">Studios</a>
            <a href="/webprogg/Listings/listing.php?category=shared-bedroom">Shared Rooms</a>
            <a href="/webprogg/Listings/listing.php?category=entire-house">Entire House</a>
            <a href="/webprogg/Listings/listing.php">Featured Stays</a>
        </div>
        <div class="footer-links">
            <span class="footer-heading">QUICK LINKS</span>
            <a href="/webprogg/index.php">About Us</a>
            <a href="/webprogg/misc/contacts.php">Contact</a>
            <a href="/webprogg/host/becomeahost.php">Become a Host</a>
            <a href="/webprogg/hiveclub.php">Hive Club</a>
        </div>
        <div class="footer-contact">
            <span class="footer-heading">GET THE APP</span>
            <div class="footer-app-badges">
                <img src="/webprogg/images/GooglePlay.jpg" alt="Get it on Google Play">
                <img src="/webprogg/images/AppStore.jpg" alt="Download on the App Store">
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> RoomHive. All rights reserved.</p>
    </div>
</footer>

<script src="/webprogg/assets/javaScript.js"></script>

<script>
(function () {
    "use strict";

    /* =========================================
       CAMERA MODULE
       ALL modal buttons are wired by CAPTURE-PHASE
       document delegation below — nothing can block them.
       Modes: 'id' (submit ID form) | 'personal' (uploadpersonal.php)
    ========================================== */
    var overlay  = document.getElementById('camOverlay');
    var video    = document.getElementById('camVideo');
    var shotImg  = document.getElementById('camShot');
    var stage    = document.getElementById('camStage');
    var flash    = document.getElementById('camFlash');
    var guide    = document.getElementById('camGuide');
    var errBox   = document.getElementById('camErr');
    var titleEl  = document.getElementById('camTitle');
    var noteEl   = document.getElementById('camNote');

    var stream   = null;
    var mode     = null;
    var captured = null;
    var busy     = false;

    if (!overlay || !video) {
        console.error('[camera] Modal markup incomplete — check the camOverlay block.');
        return;
    }

    function showErr(msg) {
        if (!errBox) { alert(msg); return; }
        errBox.textContent = msg;
        errBox.style.display = 'block';
    }

    function stopStream() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
    }

    function setBtn(id, show) {
        var b = document.getElementById(id);
        if (b) { b.hidden = !show; }
        return b;
    }

    function resetStage() {
        captured = null;
        busy = false;
        if (stage) { stage.classList.remove('captured'); }
        setBtn('camShutterBtn', true);
        setBtn('camRetakeBtn', false);
        setBtn('camUploadBtn', false);
        setBtn('camCancelBtn', true);
        var up = document.getElementById('camUploadBtn');
        if (up) { up.disabled = false; }
        if (guide) { guide.style.display = ''; }
    }

    function openCamera(target) {
        mode = target;
        captured = null;
        busy = false;
        if (errBox) { errBox.style.display = 'none'; }
        resetStage();

        if (titleEl) {
            titleEl.textContent = mode === 'id'
                ? 'Take your ID photo'
                : 'Take your personal photo';
        }
        if (noteEl) {
            noteEl.textContent = mode === 'id'
                ? 'Capture, then tap Upload — the photo is sent with your ID form.'
                : 'Hosts use this photo to recognize you in person. Tap Upload to save it.';
        }

        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showErr('Your browser does not support camera access. Please choose a file instead.');
            return;
        }

        navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } },
            audio: false
        })
        .then(function (s) {
            stream = s;
            video.srcObject = s;
            return video.play();
        })
        .catch(function (err) {
            console.error('[camera]', err);
            showErr('Camera access was blocked or is unavailable. '
                + 'Allow camera permission for this page (localhost/HTTPS required), '
                + 'or choose a file instead.');
        });
    }

    function closeCamera() {
        stopStream();
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
    }

    /* CAPTURE — center-crop to the box's exact aspect ratio */
    function capture() {
        if (!stream || !video || busy) { return; }

        var vw = video.videoWidth  || 1280;
        var vh = video.videoHeight || 960;

        var boxW = (stage && stage.clientWidth)  ? stage.clientWidth  : 520;
        var boxH = (stage && stage.clientHeight) ? stage.clientHeight : 390;
        var boxAspect = boxW / boxH;
        var srcAspect = vw / vh;

        var sw, sh, sx, sy;
        if (srcAspect > boxAspect) {
            sh = vh;
            sw = vh * boxAspect;
            sx = (vw - sw) / 2;
            sy = 0;
        } else {
            sw = vw;
            sh = vw / boxAspect;
            sx = 0;
            sy = (vh - sh) / 2;
        }

        var OUT_W = Math.min(1200, Math.round(sw));
        var OUT_H = Math.round(OUT_W / boxAspect);

        var canvas = document.createElement('canvas');
        canvas.width  = OUT_W;
        canvas.height = OUT_H;

        var ctx = canvas.getContext('2d');
        ctx.translate(OUT_W, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, sx, sy, sw, sh, 0, 0, OUT_W, OUT_H);

        if (flash) {
            flash.classList.remove('go');
            void flash.offsetWidth;
            flash.classList.add('go');
        }

        canvas.toBlob(function (blob) {
            if (!blob) { showErr('Could not capture the photo. Please try again.'); return; }
            captured = blob;

            if (shotImg) { shotImg.src = URL.createObjectURL(blob); }
            if (stage) { stage.classList.add('captured'); }

            setBtn('camShutterBtn', false);
            setBtn('camRetakeBtn', true);
            setBtn('camUploadBtn', true);
            setBtn('camCancelBtn', true);
            if (guide) { guide.style.display = 'none'; }
        }, 'image/jpeg', 0.92);
    }

    /* =========================================
       UPLOAD — 'id' submits the ID form;
       'personal' POSTs to uploadpersonal.php
    ========================================== */
    function uploadCaptured() {
        if (!captured || !mode || busy) { return; }

        if (mode === 'id') {

            var input = document.getElementById('uidFile');
            var t     = document.getElementById('uidType');
            var n     = document.getElementById('uidNumber');

            if (!t || !t.value) {
                alert('Please select the ID type first.');
                if (t) { t.focus(); }
                closeCamera();
                return;
            }
            if (!n || !n.value.trim()) {
                alert('Please enter the ID number first.');
                if (n) { n.focus(); }
                closeCamera();
                return;
            }

            var file = new File([captured], 'camera-id.jpg', { type: 'image/jpeg' });
            var dt   = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            input.dispatchEvent(new Event('change'));

            closeCamera();

            var form = document.getElementById('uidUploadForm');
            if (form) { form.submit(); }

        } else if (mode === 'personal') {

            busy = true;
            var up = document.getElementById('camUploadBtn');
            if (up) { up.disabled = true; }

            var fd = new FormData();
            fd.append('personal_photo', captured, 'camera-personal.jpg');

            fetch('/webprogg/user/uploadpersonal.php', { method: 'POST', body: fd })
                .then(function (res) {
                    if (!res.ok) {
                        return res.text().then(function (t) {
                            throw new Error('HTTP ' + res.status + ': ' + t.substring(0, 200));
                        });
                    }
                    return res.json();
                })
                .then(function (data) {
                    busy = false;
                    if (up) { up.disabled = false; }
                    if (data && data.success) {
                        var prev = document.getElementById('epPhotoPreview');
                        if (prev) {
                            prev.classList.remove('is-empty');
                            prev.style.display = '';
                            prev.src = data.personal_url;
                        }
                        setPersonalStatus('Personal photo saved! (Your profile picture is unchanged.)', true);
                        closeCamera();
                    } else {
                        showErr((data && data.error) || 'Could not save your personal photo.');
                    }
                })
                .catch(function (err) {
                    busy = false;
                    if (up) { up.disabled = false; }
                    console.error('[personal photo upload]', err);
                    showErr('Upload failed: ' + (err && err.message ? err.message : 'network error'));
                });
        }
    }

    /* =========================================
       THE CLICK FIX — capture-phase document delegation
       for every modal button. Capture phase fires BEFORE
       any bubble-phase handler (incl. javaScript.js), and
       the stage itself is pointer-events:none, so these
       clicks can never be swallowed.
    ========================================== */
    document.addEventListener('click', function (e) {
        if (!e.target || !e.target.closest) { return; }
        var btn = e.target.closest('.cam-actions .cam-btn, .cam-close');
        if (!btn) { return; }

        if (btn.id === 'camShutterBtn' && !btn.hidden) {
            e.preventDefault(); e.stopPropagation(); capture();
        } else if (btn.id === 'camRetakeBtn' && !btn.hidden) {
            e.preventDefault(); e.stopPropagation(); resetStage();
        } else if (btn.id === 'camUploadBtn' && !btn.hidden && !btn.disabled) {
            e.preventDefault(); e.stopPropagation(); uploadCaptured();
        } else if (btn.id === 'camCancelBtn' || btn.id === 'camCloseBtn') {
            e.preventDefault(); e.stopPropagation(); closeCamera();
        }
    }, true);

    /* Openers — same delegation pattern (Take Photo buttons) */
    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-cam-open]');
        if (opener) {
            e.preventDefault();
            e.stopPropagation();
            openCamera(opener.getAttribute('data-cam-open'));
        }
    }, true);

    /* Backdrop + Escape close (bubble phase — backdrop isn't a button) */
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) { closeCamera(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('open')) { closeCamera(); }
    });
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { stopStream(); }
    });

    /* =========================================
       PERSONAL PHOTO — file picker
    ========================================== */
    var photoStatus = document.getElementById('epPhotoStatus');

    function setPersonalStatus(msg, ok) {
        if (!photoStatus) { return; }
        photoStatus.textContent = msg;
        photoStatus.className = 'ep-photo-status ' + (ok ? 'ok' : 'err');
    }

    var epFile    = document.getElementById('epPhotoFile');
    var epFileBtn = document.getElementById('epPhotoFileBtn');

    if (epFileBtn && epFile) {
        epFileBtn.addEventListener('click', function (e) {
            e.preventDefault();
            epFile.click();
        });

        epFile.addEventListener('change', function () {
            var f = epFile.files && epFile.files[0];
            if (!f) { return; }

            var okTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (okTypes.indexOf(f.type) === -1) {
                setPersonalStatus('Please choose a JPG, PNG, or WEBP image.', false);
                epFile.value = '';
                return;
            }
            if (f.size > 5 * 1024 * 1024) {
                setPersonalStatus('That image is too large (max 5MB).', false);
                epFile.value = '';
                return;
            }

            setPersonalStatus('Uploading...', true);

            var fd = new FormData();
            fd.append('personal_photo', f, f.name);

            fetch('/webprogg/user/uploadpersonal.php', { method: 'POST', body: fd })
                .then(function (res) {
                    if (!res.ok) {
                        return res.text().then(function (t) {
                            throw new Error('HTTP ' + res.status + ': ' + t.substring(0, 200));
                        });
                    }
                    return res.json();
                })
                .then(function (data) {
                    if (data && data.success) {
                        var prev = document.getElementById('epPhotoPreview');
                        if (prev) {
                            prev.classList.remove('is-empty');
                            prev.style.display = '';
                            prev.src = data.personal_url;
                        }
                        setPersonalStatus('Personal photo saved! (Your profile picture is unchanged.)', true);
                    } else {
                        setPersonalStatus((data && data.error) || 'Upload failed.', false);
                    }
                })
                .catch(function (err) {
                    console.error('[personal photo upload]', err);
                    setPersonalStatus('Upload failed: ' + (err && err.message ? err.message : 'network error'), false);
                });

            epFile.value = '';
        });
    }

    /* =========================================
       ID FILE INPUT + FORM GUARD + SCROLL + REVEAL
    ========================================== */
    var fileInput = document.getElementById('uidFile');
    var uploadBox = document.getElementById('uidUploadBox');
    var fileName  = document.getElementById('uidFileName');

    if (fileInput && uploadBox && fileName) {
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files.length > 0) {
                fileName.textContent = fileInput.files[0].name;
                uploadBox.classList.add('has-file');
            } else {
                fileName.textContent = 'Choose a file';
                uploadBox.classList.remove('has-file');
            }
        });
    }

    if (window.location.hash === '#verify-card') {
        var card = document.getElementById('verify-card');
        if (card) {
            window.setTimeout(function () {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 150);
        }
    }

    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var revealEls = Array.prototype.slice.call(document.querySelectorAll(".up-reveal"));
    if (reduced || !("IntersectionObserver" in window)) {
        revealEls.forEach(function (el) { el.classList.add("in-view"); });
    } else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                io.unobserve(el);
                el.classList.add("in-view");
                window.setTimeout(function () { el.style.setProperty("--i", "0"); }, 1200);
            });
        }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
        revealEls.forEach(function (el) { io.observe(el); });
    }
})();
</script>

</body>
</html>