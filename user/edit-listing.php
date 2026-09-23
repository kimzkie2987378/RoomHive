<?php
/* =========================================================
   ROOMHIVE — EDIT LISTING (HOST)
   edit-listing.php?id=<listing_id>

   FIELD LOCK ("someone on the space"):
   Any booking with status IN ('pending','confirmed') locks
   PRICE and STATUS — enforced by a FOR UPDATE re-check at
   save time, so even tampered submissions can't change them.
   Cosmetic fields stay editable. The lock lifts when the
   booking is rejected, cancelled, or completed.

   PHOTO MANAGER (all ops instant via fetch, independent of
   the field form):
     - Replace cover   -> op=replace_cover  (updates the
       listing_photos cover row, deletes the old file)
     - Remove cover    -> op=remove_cover   (row + file gone;
       public pages fall back to the placeholder)
     - Add gallery     -> op=add_photos     (multi-upload,
       appends after max(sort_order), capped at 20 total)
     - Delete gallery  -> delete_photo_id   (row + file)
   Uploads validated by finfo MIME sniff + getimagesize
   decode check (same hardening as uploadavatar.php). Files
   follow the host-step3 convention:
     disk: DOCUMENT_ROOT/webprogg/uploads/listing_photos/{cover|additional}/
     db:   uploads/listing_photos/{cover|additional}/<name>  (relative)

   Uses the shared host shell (host_init + navbar/sidebar/footer).
========================================================= */

require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/host/host_init.php';

/* -----------------------------------------------------
   HOST GUARD
----------------------------------------------------- */
if (!$dbUser['is_host']) {
    header("Location: /webprogg/user/userprofile.php");
    exit;
}

/* ---- Guarded CSRF helpers (el- prefix) ---- */
if (!function_exists('el_csrf_token')) {
    function el_csrf_token() {
        if (empty($_SESSION['el_csrf'])) {
            $_SESSION['el_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['el_csrf'];
    }
    function el_csrf_field() {
        return '<input type="hidden" name="el_csrf" value="' . el_csrf_token() . '">';
    }
    function el_csrf_ok($token) {
        return is_string($token)
            && isset($_SESSION['el_csrf'])
            && hash_equals($_SESSION['el_csrf'], $token);
    }
}

/* ---- JSON responder for photo ops ---- */
if (!function_exists('el_json')) {
    function el_json($success, $message = '', $extra = []) {
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
        exit;
    }
}

 $listingId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;

/* -----------------------------------------------------
   LOAD LISTING (ownership enforced)
----------------------------------------------------- */
function el_load_listing(PDO $pdo, $listingId, $userId) {
    $stmt = $pdo->prepare(
        "SELECT l.id, l.title, l.category, l.property_type, l.location, l.exact_address,
                l.price, l.capacity, l.bedrooms, l.bathrooms, l.size_sqm, l.floor,
                l.parking, l.amenities, l.description, l.house_rules, l.status,
                (SELECT COUNT(*) FROM bookings b
                  WHERE b.listing_id = l.id
                    AND b.status IN ('pending','confirmed')) AS active_booking_count
         FROM listings l
         WHERE l.id = :id AND l.user_id = :uid
         LIMIT 1"
    );
    $stmt->execute(['id' => $listingId, 'uid' => $userId]);
    return $stmt->fetch();
}

 $listing = el_load_listing($pdo, $listingId, $_SESSION['user_id']);

if (!$listing) {
    header("Location: /webprogg/user/mylistings.php");
    exit;
}

 $hasActiveBooking = ((int) $listing['active_booking_count']) > 0;
 $lockedFields     = $hasActiveBooking;

 $errors = [];
 $saved  = false;

/* =========================================================
   PHOTO OPS (POST with op= / delete_photo_id) — JSON replies
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['op']) || isset($_POST['delete_photo_id']))) {

    if (!el_csrf_ok($_POST['el_csrf'] ?? '')) {
        el_json(false, 'Your session expired. Please try again.');
    }

    /* Re-verify ownership before any photo op */
    $own = el_load_listing($pdo, $listingId, $_SESSION['user_id']);
    if (!$own) {
        el_json(false, 'Listing not found.');
    }

    $coverDir      = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/listing_photos/cover/';
    $additionalDir = $_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/listing_photos/additional/';
    $allowedMimes  = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /* Validate + store one uploaded file. Returns [ok, dbPath, diskPath] */
    $el_store_upload = function ($file, $dir, $prefix) use ($allowedMimes) {
        if (!isset($file) || !is_uploaded_file($file['tmp_name'] ?? '')) {
            return [false, 'No photo was received.', null, null];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [false, 'Upload failed — please try again.', null, null];
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            return [false, 'That photo is over the 5MB limit.', null, null];
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!isset($allowedMimes[$mime])) {
            return [false, 'Photos must be JPG, PNG, or WEBP.', null, null];
        }
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return [false, "That file doesn't look like a valid image.", null, null];
        }

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return [false, 'Server could not create the upload folder.', null, null];
        }

        $name = $prefix . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMimes[$mime];
        $disk = $dir . $name;

        if (!move_uploaded_file($file['tmp_name'], $disk)) {
            return [false, 'Could not save the uploaded photo.', null, null];
        }

        /* Convention: relative path WITHOUT the /webprogg/ prefix */
        $dbPath = 'uploads/listing_photos/' . basename($dir) . '/' . $name;

        return [true, '', $dbPath, $disk];
    };

    /* Safely unlink a stored photo (realpath contained) */
    $el_unlink_photo = function ($dbPath) {
        if (empty($dbPath)) { return; }
        $full = realpath($_SERVER['DOCUMENT_ROOT'] . '/webprogg/' . ltrim((string) $dbPath, '/'));
        $base = realpath($_SERVER['DOCUMENT_ROOT'] . '/webprogg/uploads/listing_photos');
        if ($full !== false && $base !== false && strpos($full, $base . DIRECTORY_SEPARATOR) === 0) {
            @unlink($full);
        }
    };

    /* ---------------- REPLACE COVER ---------------- */
    if (($_POST['op'] ?? '') === 'replace_cover') {

        list($ok, $msg, $dbPath, $diskPath) = $el_store_upload(
            $_FILES['cover_photo'] ?? null, $coverDir, 'cover_'
        );
        if (!$ok) { el_json(false, $msg); }

        try {
            $oldStmt = $pdo->prepare(
                "SELECT id, photo_path FROM listing_photos
                 WHERE listing_id = :lid AND photo_type = 'cover'
                 LIMIT 1"
            );
            $oldStmt->execute(['lid' => $listingId]);
            $old = $oldStmt->fetch();

            if ($old) {
                $pdo->prepare(
                    "UPDATE listing_photos SET photo_path = :p WHERE id = :id"
                )->execute(['p' => $dbPath, 'id' => (int) $old['id']]);
                $el_unlink_photo($old['photo_path']);
            } else {
                $pdo->prepare(
                    "INSERT INTO listing_photos (listing_id, photo_path, photo_type, sort_order)
                     VALUES (:lid, :p, 'cover', 0)"
                )->execute(['lid' => $listingId, 'p' => $dbPath]);
            }

            el_json(true, 'Cover photo updated.', ['url' => '/webprogg/' . $dbPath]);

        } catch (Exception $e) {
            $el_unlink_photo($dbPath); /* don't orphan the new file */
            error_log('edit-listing replace_cover failed: ' . $e->getMessage());
            el_json(false, 'Could not update the cover photo.');
        }
    }

    /* ---------------- REMOVE COVER ---------------- */
    if (($_POST['op'] ?? '') === 'remove_cover') {
        try {
            $oldStmt = $pdo->prepare(
                "SELECT id, photo_path FROM listing_photos
                 WHERE listing_id = :lid AND photo_type = 'cover'
                 LIMIT 1"
            );
            $oldStmt->execute(['lid' => $listingId]);
            $old = $oldStmt->fetch();

            if ($old) {
                $pdo->prepare("DELETE FROM listing_photos WHERE id = :id")
                    ->execute(['id' => (int) $old['id']]);
                $el_unlink_photo($old['photo_path']);
            }

            el_json(true, 'Cover removed — the placeholder will show publicly.');

        } catch (Exception $e) {
            error_log('edit-listing remove_cover failed: ' . $e->getMessage());
            el_json(false, 'Could not remove the cover photo.');
        }
    }

    /* ---------------- ADD GALLERY PHOTOS ---------------- */
    if (($_POST['op'] ?? '') === 'add_photos') {

        try {
            /* Cap: 20 additional photos total (matches the wizard) */
            $cntStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM listing_photos
                 WHERE listing_id = :lid AND photo_type = 'additional'"
            );
            $cntStmt->execute(['lid' => $listingId]);
            $existingCount = (int) $cntStmt->fetchColumn();

            $maxSlot = $pdo->prepare(
                "SELECT COALESCE(MAX(sort_order), 0) FROM listing_photos
                 WHERE listing_id = :lid AND photo_type = 'additional'"
            );
            $maxSlot->execute(['lid' => $listingId]);
            $nextSort = (int) $maxSlot->fetchColumn() + 1;

            $files = $_FILES['additional_photos'] ?? null;
            $added = 0;
            $skipped = 0;
            $lastError = '';

            if (!$files || !is_array($files['name'] ?? null)) {
                el_json(false, 'No photos were received.');
            }

            $total = count($files['name']);

            for ($i = 0; $i < $total; $i++) {

                if ($existingCount + $added >= 20) {
                    $skipped++;
                    continue;
                }
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $one = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];

                list($ok, $msg, $dbPath, $diskPath) = $el_store_upload($one, $additionalDir, 'photo_');
                if (!$ok) {
                    $skipped++;
                    $lastError = $msg;
                    continue;
                }

                try {
                    $pdo->prepare(
                        "INSERT INTO listing_photos (listing_id, photo_path, photo_type, sort_order)
                         VALUES (:lid, :p, 'additional', :s)"
                    )->execute(['lid' => $listingId, 'p' => $dbPath, 's' => $nextSort]);
                    $nextSort++;
                    $added++;
                } catch (Exception $e) {
                    $el_unlink_photo($dbPath);
                    $skipped++;
                    $lastError = 'Could not save one of the photos.';
                }
            }

            if ($added === 0 && $skipped > 0) {
                el_json(false, $lastError !== '' ? $lastError : 'No photos could be added.');
            }

            $note = $added . ' photo' . ($added === 1 ? '' : 's') . ' added.';
            if ($skipped > 0) {
                $note .= " {$skipped} skipped (max 20 gallery photos, or invalid file).";
            }

            el_json(true, $note, ['added' => $added]);

        } catch (Exception $e) {
            error_log('edit-listing add_photos failed: ' . $e->getMessage());
            el_json(false, 'Could not add the photos. Please try again.');
        }
    }

    /* ---------------- DELETE GALLERY PHOTO ---------------- */
    if (isset($_POST['delete_photo_id'])) {

        $photoId = (int) $_POST['delete_photo_id'];
        if ($photoId <= 0) { el_json(false, 'Invalid photo.'); }

        try {
            /* Ownership: photo must belong to THIS host's listing */
            $getStmt = $pdo->prepare(
                "SELECT lp.id, lp.photo_path
                 FROM listing_photos lp
                 JOIN listings l ON l.id = lp.listing_id
                 WHERE lp.id = :pid
                   AND lp.listing_id = :lid
                   AND l.user_id = :uid
                   AND lp.photo_type = 'additional'
                 LIMIT 1"
            );
            $getStmt->execute([
                'pid' => $photoId,
                'lid' => $listingId,
                'uid' => $_SESSION['user_id'],
            ]);
            $photo = $getStmt->fetch();

            if (!$photo) {
                el_json(false, 'Photo not found.');
            }

            $pdo->prepare("DELETE FROM listing_photos WHERE id = :id")
                ->execute(['id' => (int) $photo['id']]);
            $el_unlink_photo($photo['photo_path']);

            el_json(true, 'Photo removed.');

        } catch (Exception $e) {
            error_log('edit-listing delete_photo failed: ' . $e->getMessage());
            el_json(false, 'Could not remove the photo.');
        }
    }

    /* Unknown op — bounce back to the page */
    header('Location: /webprogg/user/edit-listing.php?id=' . $listingId);
    exit;
}

/* =========================================================
   FIELD SAVE (regular form POST — no op field)
   The price/status lock re-verified under FOR UPDATE.
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!el_csrf_ok($_POST['el_csrf'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        try {
            $pdo->beginTransaction();

            $lockStmt = $pdo->prepare(
                "SELECT id,
                        (SELECT COUNT(*) FROM bookings b
                          WHERE b.listing_id = l.id
                            AND b.status IN ('pending','confirmed')) AS active_cnt
                 FROM listings l
                 WHERE l.id = :id AND l.user_id = :uid
                 LIMIT 1
                 FOR UPDATE"
            );
            $lockStmt->execute(['id' => $listingId, 'uid' => $_SESSION['user_id']]);
            $locked = $lockStmt->fetch();

            if (!$locked) {
                $pdo->rollBack();
                header("Location: /webprogg/user/mylistings.php");
                exit;
            }

            $lockedNow = ((int) $locked['active_cnt']) > 0;

            $title       = trim($_POST['title'] ?? '');
            $category    = trim($_POST['category'] ?? '');
            $propType    = trim($_POST['property_type'] ?? '');
            $location    = trim($_POST['location'] ?? '');
            $exactAddr   = trim($_POST['exact_address'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $houseRules  = trim($_POST['house_rules'] ?? '');
            $bedrooms    = (int) ($_POST['bedrooms'] ?? 1);
            $bathrooms   = (int) ($_POST['bathrooms'] ?? 1);
            $sizeSqm     = (float) ($_POST['size_sqm'] ?? 0);
            $floor       = trim($_POST['floor'] ?? '');
            $parking     = trim($_POST['parking'] ?? '');
            $capacity    = trim($_POST['capacity'] ?? '2');

            if ($title === '')                     { $errors[] = 'Listing title is required.'; }
            if ($location === '')                  { $errors[] = 'City / location is required.'; }
            if ($description === '')               { $errors[] = 'Description is required.'; }
            if ($bedrooms < 0 || $bedrooms > 20)   { $errors[] = 'Bedrooms must be between 0 and 20.'; }
            if ($bathrooms < 1 || $bathrooms > 20) { $errors[] = 'Bathrooms must be between 1 and 20.'; }
            if ($sizeSqm <= 0)                     { $errors[] = 'Size (sqm) must be a positive number.'; }

            $allowedCats = ['apartment','boardinghouse','privateroom','entirehouse','sharedbedroom','studioloft'];
            if (!in_array($category, $allowedCats, true)) { $errors[] = 'Invalid category.'; }

            $allowedAmenities = ['wifi','aircon','pet-friendly','free-water','free-electricity','security'];
            $amenitiesIn = $_POST['amenities'] ?? [];
            if (!is_array($amenitiesIn)) { $amenitiesIn = [$amenitiesIn]; }
            $amenities = array_values(array_intersect($amenitiesIn, $allowedAmenities));

            /* GATED FIELDS — current values forced back when locked */
            if ($lockedNow) {
                $price  = (float) $listing['price'];
                $status = (string) $listing['status'];
            } else {
                $price  = (float) ($_POST['price'] ?? 0);
                $status = trim($_POST['status'] ?? $listing['status']);

                if ($price <= 0)      { $errors[] = 'Monthly price must be a positive number.'; }
                if ($price > 1000000) { $errors[] = 'Monthly price looks invalid.'; }

                if (!in_array($status, ['approved', 'unlisted'], true)) {
                    $errors[] = 'You can set the listing to Active or Unlisted.';
                }
            }

            if (empty($errors)) {
                $upd = $pdo->prepare(
                    "UPDATE listings SET
                        title = :title,
                        category = :category,
                        property_type = :property_type,
                        location = :location,
                        exact_address = :exact_address,
                        price = :price,
                        capacity = :capacity,
                        bedrooms = :bedrooms,
                        bathrooms = :bathrooms,
                        size_sqm = :size_sqm,
                        floor = :floor,
                        parking = :parking,
                        amenities = :amenities,
                        description = :description,
                        house_rules = :house_rules,
                        status = :status,
                        updated_at = NOW()
                     WHERE id = :id AND user_id = :uid"
                );
                $upd->execute([
                    'title'         => $title,
                    'category'      => $category,
                    'property_type' => $propType,
                    'location'      => $location,
                    'exact_address' => $exactAddr,
                    'price'         => $price,
                    'capacity'      => $capacity,
                    'bedrooms'      => $bedrooms,
                    'bathrooms'     => $bathrooms,
                    'size_sqm'      => $sizeSqm,
                    'floor'         => $floor,
                    'parking'       => $parking,
                    'amenities'     => json_encode($amenities),
                    'description'   => $description,
                    'house_rules'   => $houseRules,
                    'status'        => $status,
                    'id'            => $listingId,
                    'uid'           => $_SESSION['user_id'],
                ]);

                $pdo->commit();
                $saved = true;

                /* Re-fetch so the form shows fresh values */
                $listing = el_load_listing($pdo, $listingId, $_SESSION['user_id']);
                $hasActiveBooking = ((int) $listing['active_booking_count']) > 0;
                $lockedFields     = $hasActiveBooking;

            } else {
                $pdo->rollBack();
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('edit-listing save failed: ' . $e->getMessage());
            $errors[] = 'Could not save your changes. Please try again.';
        }
    }
}

/* -----------------------------------------------------
   CURRENT PHOTOS (for the manager UI)
----------------------------------------------------- */
 $coverStmt = $pdo->prepare(
    "SELECT id, photo_path FROM listing_photos
     WHERE listing_id = :lid AND photo_type = 'cover'
     LIMIT 1"
);
 $coverStmt->execute(['lid' => $listingId]);
 $currentCover = $coverStmt->fetch();

 $galleryStmt = $pdo->prepare(
    "SELECT id, photo_path FROM listing_photos
     WHERE listing_id = :lid AND photo_type = 'additional'
     ORDER BY sort_order ASC, id ASC"
);
 $galleryStmt->execute(['lid' => $listingId]);
 $gallery = $galleryStmt->fetchAll();

/* -----------------------------------------------------
   OPTIONS
----------------------------------------------------- */
 $categories = [
    'apartment'     => 'Apartment',
    'boardinghouse' => 'Boarding House',
    'privateroom'   => 'Private Room',
    'entirehouse'   => 'Entire House',
    'sharedbedroom' => 'Shared Bedroom',
    'studioloft'    => 'Studio Loft',
];

 $amenityOptions = [
    'wifi'             => 'Wi-fi',
    'aircon'           => 'Aircon',
    'pet-friendly'     => 'Pet Friendly',
    'free-water'       => 'Free Water',
    'free-electricity' => 'Free Electricity',
    'security'         => '24/7 Security',
];

 $currentAmenities = json_decode($listing['amenities'] ?? '[]', true) ?? [];

 $activePage = 'listings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Listing — RoomHive</title>
<link rel="stylesheet" href="/webprogg/assets/style.css">
<link rel="stylesheet" href="/webprogg/assets/hostprofile.css?v=5">
<style>
    .el-wrap { max-width: 860px; margin: 0 auto; }
    .el-banner-lock {
        display: flex; align-items: center; gap: 12px;
        padding: 16px 18px; margin-bottom: 20px;
        background: #FFF6E9; border: 1px solid #F5C77E;
        border-radius: 14px; font-size: 13px; color: #8A5A10; line-height: 1.6;
    }
    .el-banner-lock strong { display: block; color: #8A5A10; font-size: 13.5px; }
    .el-banner-open {
        display: flex; align-items: center; gap: 12px;
        padding: 16px 18px; margin-bottom: 20px;
        background: #E9F7EF; border: 1px solid #BFE8CF;
        border-radius: 14px; font-size: 13px; color: #1e7a3d;
    }
    .el-field { margin-bottom: 16px; }
    .el-field label {
        display: block; margin-bottom: 6px;
        font-size: 12.5px; font-weight: 700; color: #1c2a38;
    }
    .el-field input[type="text"], .el-field input[type="number"],
    .el-field select, .el-field textarea {
        width: 100%; box-sizing: border-box;
        padding: 11px 13px;
        border: 1px solid #d9dee4; border-radius: 10px;
        font-family: inherit; font-size: 13.5px; color: #1c2a38;
        outline: none;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .el-field input:focus, .el-field select:focus, .el-field textarea:focus {
        border-color: #eda423;
        box-shadow: 0 0 0 3px rgba(237, 164, 35, 0.15);
    }
    .el-field input:disabled, .el-field select:disabled {
        background: #f5f5f5; color: #999; cursor: not-allowed;
    }
    .el-field .el-locked-note {
        display: block; margin-top: 4px;
        font-size: 11px; font-weight: 700; color: #C77800;
    }
    .el-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 640px) { .el-grid-2 { grid-template-columns: 1fr; } }
    .el-amenities { display: flex; flex-wrap: wrap; gap: 10px; }
    .el-amenity {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 14px;
        background: #F6F7FB; border: 1px solid #EEF1F6;
        border-radius: 999px; font-size: 12.5px; cursor: pointer;
        color: #1c2a38; font-weight: 600;
    }
    .el-amenity input { accent-color: #eda423; }
    .el-error {
        background: #fdecea; border: 1px solid #f5c6c2;
        border-radius: 10px; padding: 10px 14px; margin-bottom: 16px;
        font-size: 13px;
    }
    .el-error p { margin: 0 0 4px; color: #b3261e; }
    .el-success {
        background: #E9F7EF; border: 1px solid #BFE8CF;
        border-radius: 10px; padding: 12px 16px; margin-bottom: 16px;
        font-size: 13.5px; color: #1e7a3d; font-weight: 600;
    }
    .el-actions {
        display: flex; gap: 12px; margin-top: 24px;
        padding-top: 20px; border-top: 1px solid rgba(28,42,56,0.08);
    }

    /* ---- Photo manager (elp-) ---- */
    .elp-section { margin-bottom: 14px; }
    .elp-label {
        display: block; margin-bottom: 8px;
        font-size: 12px; font-weight: 800; letter-spacing: 0.6px;
        text-transform: uppercase; color: #8B93A6;
    }
    .elp-cover {
        position: relative;
        width: 100%; max-width: 420px;
        aspect-ratio: 16 / 10;
        border-radius: 14px; overflow: hidden;
        border: 1px solid #EEF1F6;
        background: #F0EEE6;
    }
    .elp-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .elp-cover-empty {
        width: 100%; height: 100%;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: 6px;
        color: #8B93A6; font-size: 12.5px;
    }
    .elp-cover-empty span.big { font-size: 28px; }
    .elp-cover-badge {
        position: absolute; top: 10px; left: 10px;
        padding: 4px 12px; border-radius: 999px;
        background: rgba(28,42,56,0.85); color: #fff;
        font-size: 10.5px; font-weight: 800;
    }
    .elp-cover-actions { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
    .elp-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 9px 16px; border-radius: 10px;
        border: 1px solid #EEF1F6; background: #fff;
        color: #1c2a38; font-size: 12.5px; font-weight: 700;
        cursor: pointer; font-family: inherit;
        transition: background .15s ease, border-color .15s ease, color .15s ease;
    }
    .elp-btn:hover { background: #F6F7FB; border-color: #eda423; color: #b07708; }
    .elp-btn.danger { color: #E14B4B; border-color: #F5B5B5; }
    .elp-btn.danger:hover { background: #FCEAEA; border-color: #E14B4B; color: #E14B4B; }
    .elp-btn:disabled { opacity: 0.6; cursor: wait; }

    .elp-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: 12px;
    }
    .elp-item {
        position: relative;
        aspect-ratio: 1 / 1;
        border-radius: 12px; overflow: hidden;
        border: 1px solid #EEF1F6;
        background: #F0EEE6;
    }
    .elp-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .elp-item .elp-del {
        position: absolute; top: 6px; right: 6px;
        width: 26px; height: 26px;
        border: none; border-radius: 50%;
        background: rgba(0,0,0,0.6); color: #fff;
        font-size: 15px; line-height: 1; cursor: pointer;
        transition: background .15s ease, transform .15s ease;
    }
    .elp-item .elp-del:hover { background: #E14B4B; transform: scale(1.1); }

    .elp-empty-note {
        padding: 22px; text-align: center;
        border: 1px dashed #E2D4B6; border-radius: 12px;
        color: #8B93A6; font-size: 12.5px;
        background: #FFFDF6;
    }
    .elp-toast {
        display: none;
        margin-bottom: 14px; padding: 11px 14px;
        border-radius: 10px; font-size: 13px; font-weight: 600;
    }
    .elp-toast.ok { display: block; background: #E9F7EF; color: #1e7a3d; border: 1px solid #BFE8CF; }
    .elp-toast.err { display: block; background: #fdecea; color: #a4302f; border: 1px solid #f3b9b9; }
</style>
</head>
<body>

<?php include __DIR__ . '/../host/host_navbar.php'; ?>

<main class="hp-dashboard hp-dashboard--flush-top">

  <?php include __DIR__ . '/../host/host_sidebar.php'; ?>

  <div class="hp-content">

    <div class="el-wrap">

      <div class="hp-page-header">
        <div>
          <h1 class="hp-page-title">Edit: <?php echo h($listing['title']); ?></h1>
          <p class="hp-page-subtitle">Details save with the button below; photo changes apply instantly.</p>
        </div>
        <a href="/webprogg/user/mylistings.php" class="hp-btn-outline" style="text-decoration:none;">&larr; Back to My Listings</a>
      </div>

      <?php if ($saved): ?>
        <div class="el-success">&#10003; Changes saved successfully.</div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="el-error">
          <?php foreach ($errors as $error): ?>
            <p><?php echo h($error); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- ============ PHOTO MANAGER (instant ops) ============ -->
      <section class="hp-card" style="margin-bottom:20px;">
        <div class="hp-card-header">
          <h3>Photos</h3>
        </div>

        <div class="elp-toast" id="elpToast"></div>

        <div class="elp-section">
          <span class="elp-label">Cover Photo</span>

          <div class="elp-cover">
            <?php if ($currentCover): ?>
              <img src="<?php echo h(resolve_photo($currentCover['photo_path'], '/webprogg/images/ListingPlaceholder.png')); ?>" alt="Cover photo" id="elpCoverImg">
              <span class="elp-cover-badge">Cover</span>
            <?php else: ?>
              <div class="elp-cover-empty">
                <span class="big">&#128247;</span>
                No cover photo yet — the placeholder shows publicly
              </div>
            <?php endif; ?>
          </div>

          <div class="elp-cover-actions">
            <button type="button" class="elp-btn" id="elpCoverBtn">&#128465; <?php echo $currentCover ? 'Replace Cover' : 'Upload Cover'; ?></button>
            <?php if ($currentCover): ?>
              <button type="button" class="elp-btn danger" id="elpCoverRemove">Remove</button>
            <?php endif; ?>
          </div>
          <input type="file" id="elpCoverInput" accept="image/jpeg,image/png,image/webp" style="display:none">
        </div>

        <div class="elp-section">
          <span class="elp-label">Gallery Photos (<?php echo count($gallery); ?>/20)</span>

          <?php if (empty($gallery)): ?>
            <div class="elp-empty-note">No gallery photos yet — add interior and detail shots below.</div>
          <?php else: ?>
            <div class="elp-grid" id="elpGrid">
              <?php foreach ($gallery as $ph): ?>
                <div class="elp-item" data-photo-id="<?php echo (int) $ph['id']; ?>">
                  <img src="<?php echo h(resolve_photo($ph['photo_path'], '/webprogg/images/ListingPlaceholder.png')); ?>" alt="Gallery photo">
                  <button type="button" class="elp-del js-elp-del" data-photo-id="<?php echo (int) $ph['id']; ?>" aria-label="Remove photo">&times;</button>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="elp-cover-actions" style="margin-top:12px;">
            <button type="button" class="elp-btn" id="elpAddBtn">&#10133; Add Photos</button>
          </div>
          <input type="file" id="elpAddInput" accept="image/jpeg,image/png,image/webp" multiple style="display:none">
        </div>

        <p style="margin:14px 0 0; font-size:11.5px; color:#8B93A6; line-height:1.6;">
          Photo changes apply instantly and are independent of the details form below.
          JPG, PNG, or WEBP &middot; max 5MB each &middot; up to 20 gallery photos.
          <?php if ($lockedFields): ?>
            Photos stay editable even while the price is locked &mdash; they can't affect an active booking.
          <?php endif; ?>
        </p>
      </section>

      <!-- ============ DETAILS FORM ============ -->
      <section class="hp-card">

        <?php if ($lockedFields): ?>
          <div class="el-banner-lock">
            <span style="font-size:22px;">&#128274;</span>
            <div>
              <strong>Price and availability are locked</strong>
              Someone has an active booking or 50% reserve on this space.
              The <?php echo h(number_format((float) $listing['price'], 0)); ?>/month rate and the listing
              status stay exactly as the guest agreed to. The lock lifts when the booking
              is rejected, cancelled, or completed.
            </div>
          </div>
        <?php else: ?>
          <div class="el-banner-open">
            <span style="font-size:20px;">&#9989;</span>
            <div>No active bookings — all fields including price and availability are editable.</div>
          </div>
        <?php endif; ?>

        <form method="POST" action="/webprogg/user/edit-listing.php?id=<?php echo (int) $listingId; ?>">
          <?php echo el_csrf_field(); ?>

          <div class="el-field">
            <label for="elTitle">Listing Title *</label>
            <input type="text" id="elTitle" name="title" required maxlength="120"
                   value="<?php echo h($listing['title']); ?>">
          </div>

          <div class="el-grid-2">
            <div class="el-field">
              <label for="elCategory">Category *</label>
              <select name="category" id="elCategory" required>
                <?php foreach ($categories as $key => $label): ?>
                  <option value="<?php echo h($key); ?>" <?php echo $listing['category'] === $key ? 'selected' : ''; ?>>
                    <?php echo h($label); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="el-field">
              <label for="elPropType">Property Type</label>
              <input type="text" id="elPropType" name="property_type" maxlength="80"
                     value="<?php echo h($listing['property_type'] ?? ''); ?>">
            </div>
          </div>

          <div class="el-grid-2">
            <div class="el-field">
              <label for="elLocation">City / Municipality *</label>
              <input type="text" id="elLocation" name="location" required maxlength="120"
                     value="<?php echo h($listing['location']); ?>">
            </div>

            <div class="el-field">
              <label for="elExact">Exact Address</label>
              <input type="text" id="elExact" name="exact_address" maxlength="255"
                     value="<?php echo h($listing['exact_address'] ?? ''); ?>">
            </div>
          </div>

          <div class="el-field">
            <label for="elPrice">Monthly Price (₱) *</label>
            <input type="number" id="elPrice" name="price" min="1" step="0.01" required
                   value="<?php echo h($listing['price']); ?>"
                   <?php echo $lockedFields ? 'disabled' : ''; ?>>
            <?php if ($lockedFields): ?>
              <span class="el-locked-note">&#128274; Locked — a guest has reserved this rate.</span>
            <?php endif; ?>
          </div>

          <div class="el-field">
            <label for="elStatus">Availability</label>
            <select name="status" id="elStatus" <?php echo $lockedFields ? 'disabled' : ''; ?>>
              <option value="approved" <?php echo $listing['status'] === 'approved' ? 'selected' : ''; ?>>Active (visible to renters)</option>
              <option value="unlisted" <?php echo $listing['status'] === 'unlisted' ? 'selected' : ''; ?>>Unlisted (hidden from search)</option>
            </select>
            <?php if ($lockedFields): ?>
              <span class="el-locked-note">&#128274; Locked — can't change while a booking is active.</span>
            <?php endif; ?>
          </div>

          <div class="el-grid-2">
            <div class="el-field">
              <label for="elBedrooms">Bedrooms</label>
              <input type="number" id="elBedrooms" name="bedrooms" min="0" max="20"
                     value="<?php echo h($listing['bedrooms']); ?>">
            </div>
            <div class="el-field">
              <label for="elBathrooms">Bathrooms</label>
              <input type="number" id="elBathrooms" name="bathrooms" min="1" max="20"
                     value="<?php echo h($listing['bathrooms']); ?>">
            </div>
          </div>

          <div class="el-grid-2">
            <div class="el-field">
              <label for="elSize">Size (sqm)</label>
              <input type="number" id="elSize" name="size_sqm" min="1" step="0.1"
                     value="<?php echo h($listing['size_sqm']); ?>">
            </div>
            <div class="el-field">
              <label for="elCapacity">Max Capacity</label>
              <input type="text" id="elCapacity" name="capacity" maxlength="10"
                     value="<?php echo h($listing['capacity'] ?? '2'); ?>">
            </div>
          </div>

          <div class="el-grid-2">
            <div class="el-field">
              <label for="elFloor">Floor</label>
              <input type="text" id="elFloor" name="floor" maxlength="60"
                     value="<?php echo h($listing['floor'] ?? ''); ?>">
            </div>
            <div class="el-field">
              <label for="elParking">Parking</label>
              <input type="text" id="elParking" name="parking" maxlength="60"
                     value="<?php echo h($listing['parking'] ?? ''); ?>">
            </div>
          </div>

          <div class="el-field">
            <label>Amenities</label>
            <div class="el-amenities">
              <?php foreach ($amenityOptions as $key => $label): ?>
                <label class="el-amenity">
                  <input type="checkbox" name="amenities[]" value="<?php echo h($key); ?>"
                         <?php echo in_array($key, $currentAmenities, true) ? 'checked' : ''; ?>>
                  <?php echo h($label); ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="el-field">
            <label for="elDesc">Description *</label>
            <textarea id="elDesc" name="description" rows="5" required
                      placeholder="Describe the space, the neighborhood, what makes it special..."><?php echo h($listing['description']); ?></textarea>
          </div>

          <div class="el-field">
            <label for="elRules">House Rules</label>
            <textarea id="elRules" name="house_rules" rows="3"
                      placeholder="e.g. No smoking. Quiet hours after 10 PM."><?php echo h($listing['house_rules'] ?? ''); ?></textarea>
          </div>

          <div class="el-actions">
            <button type="submit" class="hp-btn-primary" style="border:none; border-radius:10px; padding:12px 28px; font-weight:700; cursor:pointer; background:linear-gradient(135deg,#f6b93b,#eda423); color:#1c2a38;">
              SAVE CHANGES
            </button>
            <a href="/webprogg/user/mylistings.php" class="hp-btn-outline" style="text-decoration:none; display:inline-flex; align-items:center;">Cancel</a>
          </div>

        </form>
      </section>

    </div>

  </div>
</main>

<?php include __DIR__ . '/../host/host_footer.php'; ?>

<script>
/* =====================================================
   PHOTO MANAGER — instant ops via fetch to this page
====================================================== */
(function () {
    "use strict";

    var CSRF = "<?php echo el_csrf_token(); ?>";
    var pageUrl = "/webprogg/user/edit-listing.php?id=<?php echo (int) $listingId; ?>";

    var toast = document.getElementById('elpToast');

    function showToast(ok, msg) {
        toast.className = 'elp-toast ' + (ok ? 'ok' : 'err');
        toast.textContent = (ok ? '\u2713 ' : '') + msg;
        toast.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function postOp(formData, onDone) {
        formData.append('el_csrf', CSRF);
        return fetch(pageUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            onDone(data);
        })
        .catch(function () {
            onDone({ success: false, message: "Couldn't reach the server. Please try again." });
        });
    }

    /* ---- Replace / upload cover ---- */
    var coverBtn   = document.getElementById('elpCoverBtn');
    var coverInput = document.getElementById('elpCoverInput');

    if (coverBtn && coverInput) {
        coverBtn.addEventListener('click', function () { coverInput.click(); });

        coverInput.addEventListener('change', function () {
            var file = coverInput.files[0];
            if (!file) return;

            coverBtn.disabled = true;
            var fd = new FormData();
            fd.append('op', 'replace_cover');
            fd.append('cover_photo', file);

            postOp(fd, function (data) {
                coverBtn.disabled = false;
                coverInput.value = '';
                if (data.success) {
                    showToast(true, data.message);
                    window.setTimeout(function () { window.location.reload(); }, 700);
                } else {
                    showToast(false, data.message || 'Could not update the cover.');
                }
            });
        });
    }

    /* ---- Remove cover ---- */
    var coverRemove = document.getElementById('elpCoverRemove');

    if (coverRemove) {
        coverRemove.addEventListener('click', function () {
            if (!confirm('Remove the cover photo? The placeholder will show on the public listing.')) return;

            coverRemove.disabled = true;
            var fd = new FormData();
            fd.append('op', 'remove_cover');

            postOp(fd, function (data) {
                coverRemove.disabled = false;
                if (data.success) {
                    showToast(true, data.message);
                    window.setTimeout(function () { window.location.reload(); }, 700);
                } else {
                    showToast(false, data.message || 'Could not remove the cover.');
                }
            });
        });
    }

    /* ---- Add gallery photos ---- */
    var addBtn   = document.getElementById('elpAddBtn');
    var addInput = document.getElementById('elpAddInput');

    if (addBtn && addInput) {
        addBtn.addEventListener('click', function () { addInput.click(); });

        addInput.addEventListener('change', function () {
            if (!addInput.files.length) return;

            addBtn.disabled = true;
            addBtn.textContent = 'Uploading\u2026';

            var fd = new FormData();
            fd.append('op', 'add_photos');
            for (var i = 0; i < addInput.files.length; i++) {
                fd.append('additional_photos[]', addInput.files[i]);
            }

            postOp(fd, function (data) {
                addBtn.disabled = false;
                addBtn.textContent = '\u2795 Add Photos';
                addInput.value = '';
                if (data.success) {
                    showToast(true, data.message);
                    window.setTimeout(function () { window.location.reload(); }, 700);
                } else {
                    showToast(false, data.message || 'Could not add the photos.');
                }
            });
        });
    }

    /* ---- Delete gallery photo (event delegation) ---- */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.js-elp-del') : null;
        if (!btn) return;

        e.preventDefault();

        if (!confirm('Remove this photo from the gallery?')) return;

        var item = btn.closest('.elp-item');
        btn.disabled = true;

        var fd = new FormData();
        fd.append('delete_photo_id', btn.getAttribute('data-photo-id'));

        postOp(fd, function (data) {
            if (data.success) {
                if (item) { item.remove(); }
                showToast(true, data.message);
            } else {
                btn.disabled = false;
                showToast(false, data.message || 'Could not remove the photo.');
            }
        });
    });
})();
</script>

</body>
</html>