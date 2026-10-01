-- Profile change requests: employees propose edits to their own 201 record
-- (general info, education, IDs, family); a user with "Update 201 Files"
-- approves or rejects them. See includes/profile-change.php.
--
-- Additive and safe to re-run. Apply on staging/production BEFORE (or right
-- after) deploying the code that uses it; until then the request button
-- reports that the feature is not set up, and nothing else is affected.

CREATE TABLE IF NOT EXISTS `profile_change_requests` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `EmpID`       VARCHAR(30)  NOT NULL,                     -- whose record
  `RequestedBy` VARCHAR(30)  NOT NULL,                     -- always the employee themself
  `Changes`     LONGTEXT     NOT NULL,                     -- JSON {fields:[{field,section,label,old,new}], family:{old,new}|null}
  `Status`      VARCHAR(10)  NOT NULL DEFAULT 'pending',   -- pending | approved | rejected | cancelled
  `ReviewedBy`  VARCHAR(30)  NULL,
  `ReviewedAt`  DATETIME     NULL,
  `Remarks`     VARCHAR(500) NULL,
  `CreatedAt`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pcr_status` (`Status`, `CreatedAt`),
  KEY `idx_pcr_emp` (`EmpID`, `Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
