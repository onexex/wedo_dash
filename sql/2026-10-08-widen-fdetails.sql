-- ============================================================================
-- Family details (fdetails): room for real names, addresses and phone numbers.
--
-- The columns were FName 50, FAdd 50, FRel 20 and FContact 12 characters, so a
-- number like "+63 917 123 4567" or a full address did not fit: approving a
-- profile change request with such a family row failed (strict MySQL) or the
-- value was cut off. Used by UpdateEmployeeInfo, newemployee and
-- includes/profile-change.php (pcr_apply).
--
-- Only widens columns; existing data is kept. Safe to run more than once.
-- Needed on local, staging and production.
-- ============================================================================

ALTER TABLE `fdetails`
  MODIFY `FName`    VARCHAR(150) NOT NULL,
  MODIFY `FAdd`     VARCHAR(255) NOT NULL,
  MODIFY `FRel`     VARCHAR(50)  NOT NULL,
  MODIFY `FContact` VARCHAR(50)  NOT NULL;
