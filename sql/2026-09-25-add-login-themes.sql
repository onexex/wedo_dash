-- ============================================================================
-- Migration: seasonal login themes (Maintenance > Login Theme, logintheme.php)
-- Run ONCE per environment, BEFORE deploying the code that uses it.
-- Safe if the code ships first: login.php falls back to the normal look and the
-- Login Theme page simply isn't reachable until the access right exists.
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- The .sql extension is blocked from HTTP by .htaccess, so this file is safe in-repo.
-- ============================================================================

-- 1) One row per scheduled look. Colours and effects are NOT stored here — they
--    come from the preset catalog in includes/login-theme.php. With
--    repeats_yearly = 1 only the month/day of the dates matter, and a window may
--    wrap the year end (Dec 31 -> Jan 6).
CREATE TABLE IF NOT EXISTS login_themes (
  id              INT(11)      NOT NULL AUTO_INCREMENT,
  name            VARCHAR(120) NOT NULL,
  preset          VARCHAR(40)  NOT NULL,
  season_label    VARCHAR(60)  NULL,
  headline        VARCHAR(80)  NULL,
  headline_accent VARCHAR(60)  NULL,
  message         VARCHAR(300) NULL,
  announcement    VARCHAR(300) NULL,
  show_effects    TINYINT(1)   NOT NULL DEFAULT 1,
  banner_path     VARCHAR(255) NULL,
  starts_on       DATE         NOT NULL,
  ends_on         DATE         NOT NULL,
  repeats_yearly  TINYINT(1)   NOT NULL DEFAULT 1,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  updated_by      VARCHAR(50)  NULL,
  created_at      DATETIME     NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_themes_active (is_active, starts_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) The Philippine calendar as a starting point, all switched OFF, so running
--    this never changes the sign-in page until someone turns a season on.
--    Chinese New Year is not seeded: it follows the lunar calendar.
INSERT INTO login_themes (name, preset, show_effects, starts_on, ends_on, repeats_yearly, is_active)
SELECT * FROM (
  SELECT 'Christmas' AS name,        'christmas' AS preset,    1 AS fx, DATE('2026-12-01') AS s, DATE('2026-12-30') AS e, 1 AS y, 0 AS a UNION ALL
  SELECT 'New Year',                 'newyear',                1,       DATE('2026-12-31'),      DATE('2027-01-06'),      1,      0      UNION ALL
  SELECT 'Valentine''s Day',         'valentines',             1,       DATE('2026-02-10'),      DATE('2026-02-14'),      1,      0      UNION ALL
  SELECT 'Independence Day',         'independence',           1,       DATE('2026-06-10'),      DATE('2026-06-12'),      1,      0      UNION ALL
  SELECT 'Undas',                    'halloween',              1,       DATE('2026-10-29'),      DATE('2026-11-02'),      1,      0
) seed
WHERE NOT EXISTS (SELECT 1 FROM login_themes);

-- 3) Access right for the Login Theme page (default OFF so nobody gains access
--    implicitly), granted out of the box to Super Users / HR (EmpRoleID = 1),
--    the same audience as the Maintenance screens.
ALTER TABLE accessrights
  ADD COLUMN logintheme INT(11) NOT NULL DEFAULT 1 AFTER dashboard;

UPDATE accessrights a
  JOIN empdetails d ON a.EmpID = d.EmpID
  SET a.logintheme = 2
  WHERE d.EmpRoleID = 1;

-- Verify:
-- SELECT name, preset, starts_on, ends_on, is_active FROM login_themes;
-- SELECT logintheme, COUNT(*) FROM accessrights GROUP BY logintheme;
