<?php
/* =========================================================
   verification_gate.php
   AUTOMATIC verification — no admin review.

   A user is VERIFIED when BOTH are true:
     1. They uploaded at least one ID (user_id_documents,
        not rejected), AND
     2. Their profile is complete: name, phone, age,
        location all filled in (editprofile.php).

   Usage in becomeahost.php AND host-step2.php, right after
   the logged-in check:

       require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/verification_gate.php';
       require_verified_user($pdo, $_SESSION['user_id']);
========================================================= */

if (!function_exists('is_user_verified')) {
    /**
     * Auto-verification check: ID uploaded + profile complete.
     * Dynamic — no status column sync can go stale.
     */
    function is_user_verified($pdo, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) { return false; }

        try {
            /* 1) Has an uploaded ID (rejected docs don't count —
                  user re-uploads to fix) */
            $d = $pdo->prepare(
                "SELECT COUNT(*) FROM user_id_documents
                 WHERE user_id = :u AND status != 'rejected'"
            );
            $d->execute([':u' => $userId]);
            if ((int) $d->fetchColumn() === 0) {
                return false;
            }

            /* 2) Profile complete: name, phone, age, location */
            $p = $pdo->prepare(
                "SELECT name, phone, age, location
                 FROM users WHERE id = :u LIMIT 1"
            );
            $p->execute([':u' => $userId]);
            $u = $p->fetch();

            if (!$u) { return false; }

            return trim((string) $u['name'])     !== ''
                && trim((string) ($u['phone']    ?? '')) !== ''
                && ($u['age']      ?? null)      !== null
                && trim((string) ($u['location'] ?? '')) !== '';

        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('require_verified_user')) {
    /**
     * Gate for the listing flow. Verified = pass; otherwise
     * redirect to editprofile with an explanation banner.
     */
    function require_verified_user($pdo, $userId)
    {
        if (is_user_verified($pdo, $userId)) {
            return true;
        }

        $_SESSION['verification_redirect'] =
            'Before you can list a space you must: (1) fill in your '
            . 'name, phone, age, and location below, and (2) upload one '
            . 'government-issued ID. Your Verified badge is granted '
            . 'automatically once both are done.';

        header('Location: /webprogg/user/editprofile.php#verify-card');
        exit;
    }
}