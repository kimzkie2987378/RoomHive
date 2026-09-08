-- =========================================================
-- ROOMHIVE — Foreign Key Migration (idempotent / re-runnable)
--
-- Unlike db_foreign_keys.sql, this version checks whether each
-- constraint already exists (via information_schema) and drops
-- it first if so, then re-adds it. Safe to run over and over —
-- won't error with "Duplicate FOREIGN KEY constraint name".
--
-- Run this in phpMyAdmin's SQL tab against the `roomhive` DB,
-- or: mysql -u root -p roomhive < db_foreign_keys_idempotent.sql
-- =========================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `roomhive_add_fk`$$
CREATE PROCEDURE `roomhive_add_fk`(
    IN p_table       VARCHAR(64),
    IN p_constraint  VARCHAR(64),
    IN p_column      VARCHAR(64),
    IN p_ref_table   VARCHAR(64),
    IN p_ref_column  VARCHAR(64)
)
BEGIN
    DECLARE existing INT DEFAULT 0;

    SELECT COUNT(*) INTO existing
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND CONSTRAINT_NAME = p_constraint
      AND CONSTRAINT_TYPE = 'FOREIGN KEY';

    IF existing > 0 THEN
        SET @drop_sql = CONCAT(
            'ALTER TABLE `', p_table, '` DROP FOREIGN KEY `', p_constraint, '`'
        );
        PREPARE stmt FROM @drop_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;

    SET @add_sql = CONCAT(
        'ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_constraint, '` ',
        'FOREIGN KEY (`', p_column, '`) REFERENCES `', p_ref_table, '`(`', p_ref_column, '`) ',
        'ON DELETE CASCADE ON UPDATE CASCADE'
    );
    PREPARE stmt FROM @add_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END$$

DELIMITER ;

CALL roomhive_add_fk('host_applications',    'fk_hostapp_user',    'user_id',            'users',             'id');
CALL roomhive_add_fk('listings',             'fk_listing_hostapp', 'host_application_id','host_applications', 'id');
CALL roomhive_add_fk('listings',             'fk_listing_user',    'user_id',            'users',             'id');
CALL roomhive_add_fk('listing_photos',       'fk_photo_listing',   'listing_id',         'listings',          'id');
CALL roomhive_add_fk('bookings',             'fk_booking_listing', 'listing_id',         'listings',          'id');
CALL roomhive_add_fk('bookings',             'fk_booking_user',    'user_id',            'users',             'id');
CALL roomhive_add_fk('reviews',              'fk_review_listing',  'listing_id',         'listings',          'id');
CALL roomhive_add_fk('reviews',              'fk_review_user',     'user_id',            'users',             'id');
CALL roomhive_add_fk('hive_members',         'fk_hivemember_user', 'user_id',            'users',             'id');
CALL roomhive_add_fk('hiveclub_transactions','fk_txn_user',        'user_id',            'users',             'id');
CALL roomhive_add_fk('hiveclub_transactions','fk_txn_hivemember',  'hive_member_id',     'hive_members',      'id');

DROP PROCEDURE IF EXISTS `roomhive_add_fk`;

-- ---------------------------------------------------------
-- After this finishes, refresh phpMyAdmin's Designer tab and
-- every relationship should show a connecting line.
--
-- If a CALL above errors with "Cannot add foreign key
-- constraint", that one specific relationship has an orphan
-- row blocking it (e.g. a listing whose user_id points at a
-- user that no longer exists). Find it with, e.g.:
--   SELECT * FROM listings WHERE user_id NOT IN (SELECT id FROM users);
-- fix/delete that row, then re-run this whole script — it's
-- safe to run again from the top.
-- ---------------------------------------------------------
