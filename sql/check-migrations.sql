-- ============================================================================
-- READ-ONLY: which sql/ migrations are installed on THIS database?
-- Paste into phpMyAdmin with the server's database selected (staging or
-- production). Changes nothing — it only reads information_schema.
-- Each row = one migration file; status OK or MISSING (+ what is missing).
-- Run the MISSING files in date order. A partly applied file stops at the
-- first "duplicate" error in phpMyAdmin: run only its missing statements.
-- ============================================================================
SELECT migration, IF(missing = '', 'OK', 'MISSING') AS status, missing
FROM (
  SELECT '2026-07-07-add-dashboard-access-right' AS migration,
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accessrights' AND COLUMN_NAME = 'dashboard') = 0, 'accessrights.dashboard', '') AS missing
  UNION ALL SELECT '2026-09-14-add-join-indexes',
         IF((SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME IN
            ('ix_employees_empid','ix_empdetails_empid','ix_empdetails_empisid','ix_attendancelog_emp_day','ix_attendancelog_day',
             'ix_workdays_emp_day','ix_workdays_efid','ix_hleavesbd_emp_range','ix_obshbd_emp_range','ix_hleaves_emp','ix_hleaves_status',
             'ix_obs_emp','ix_obs_status','ix_holidays_date_comp','ix_accessrights_empid')) < 15, 'some of the 15 join indexes', '')
  UNION ALL SELECT '2026-09-25-add-login-themes', CONCAT_WS(' ',
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login_themes') = 0, 'table login_themes', NULL),
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accessrights' AND COLUMN_NAME = 'logintheme') = 0, 'accessrights.logintheme', NULL))
  UNION ALL SELECT '2026-10-01-add-idcard', CONCAT_WS(' ',
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accessrights' AND COLUMN_NAME = 'idcard') = 0, 'accessrights.idcard', NULL),
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('idcard_cards','idcard_print_log','idcard_settings')) < 3, 'idcard tables', NULL))
  UNION ALL SELECT '2026-10-01-add-message-calls (+ fix-message-calls)',
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('msg_calls','msg_call_members','msg_call_signals')) < 3, 'msg_calls tables', '')
  UNION ALL SELECT '2026-10-01-add-message-groups', CONCAT_WS(' ',
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'Kind') = 0, 'messages.Kind', NULL),
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('msg_groups','msg_group_members')) < 2, 'msg_groups tables', NULL))
  UNION ALL SELECT '2026-10-01-add-message-mentions',
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'msg_mentions') = 0, 'table msg_mentions', '')
  UNION ALL SELECT '2026-10-01-add-message-presence',
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'msg_presence') = 0, 'table msg_presence', '')
  UNION ALL SELECT '2026-10-01-add-message-reactions',
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'msg_reactions') = 0, 'table msg_reactions', '')
  UNION ALL SELECT '2026-10-01-add-profile-change-requests',
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'profile_change_requests') = 0, 'table profile_change_requests', '')
  UNION ALL SELECT '2026-10-01-messages-utf8mb4',
         IF((SELECT CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'Message') <> 'utf8mb4', 'messages.Message is not utf8mb4', '')
  UNION ALL SELECT '2026-10-05-add-leave-credit-years', CONCAT_WS(' ',
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accessrights' AND COLUMN_NAME = 'lcreditedit') = 0, 'accessrights.lcreditedit', NULL),
         IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'credit' AND INDEX_NAME = 'ux_credit_emp') = 0, 'credit.ux_credit_emp', NULL),
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('credit_years','credit_log')) < 2, 'credit_years/credit_log', NULL))
  UNION ALL SELECT '2026-10-05-add-parental-family-access-right',
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accessrights' AND COLUMN_NAME = 'pfam') = 0, 'accessrights.pfam', '')
  UNION ALL SELECT '2026-10-06-add-message-delete-access-right',
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accessrights' AND COLUMN_NAME = 'msgdel') = 0, 'accessrights.msgdel', '')
  UNION ALL SELECT '2026-10-06-add-message-files-access-right',
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accessrights' AND COLUMN_NAME = 'msgfile') = 0, 'accessrights.msgfile', '')
  UNION ALL SELECT '2026-10-07-add-app-remember',
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_remember') = 0, 'table app_remember', '')
  UNION ALL SELECT '2026-10-07-add-message-clear',
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'msg_cleared') = 0, 'table msg_cleared', '')
  UNION ALL SELECT '2026-10-07-add-notif-seen',
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notif_seen') = 0, 'table notif_seen', '')
  UNION ALL SELECT '2026-10-07-add-push-devices',
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'push_devices') = 0, 'table push_devices', '')
  UNION ALL SELECT '2026-10-08-widen-fdetails',
         IF((SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fdetails' AND COLUMN_NAME = 'FName') < 150, 'fdetails columns still narrow', '')
  UNION ALL SELECT '2026-10-14-add-after-separation-access-right',
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accessrights' AND COLUMN_NAME = 'aftersep') = 0, 'accessrights.aftersep', '')
  UNION ALL SELECT '2026-10-14-add-message-review', CONCAT_WS(' ',
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accessrights' AND COLUMN_NAME = 'msgaudit') = 0, 'accessrights.msgaudit', NULL),
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('msg_deleted_archive','msg_review_log')) < 2, 'msg_deleted_archive/msg_review_log', NULL))
  UNION ALL SELECT '2026-10-14-add-employee-lifecycle', CONCAT_WS(' ',
         IF((SELECT COUNT(*) FROM estatus WHERE ID = 5) = 0, 'estatus 5 (AWOL)', NULL),
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accessrights' AND COLUMN_NAME = 'separation') = 0, 'accessrights.separation', NULL),
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accessrights' AND COLUMN_NAME = 'empverify') = 0, 'accessrights.empverify', NULL),
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'emp_lifecycle_events') = 0, 'emp_lifecycle_events', NULL),
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'emp_separations') = 0, 'emp_separations', NULL),
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'coe_requests') = 0, 'coe_requests', NULL),
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employment_verifications') = 0, 'employment_verifications', NULL))
  UNION ALL SELECT '2026-10-14-add-applicants', CONCAT_WS(' ',
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accessrights' AND COLUMN_NAME = 'applicants') = 0, 'accessrights.applicants', NULL),
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_openings') = 0, 'job_openings', NULL),
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants') = 0, 'applicants', NULL),
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicant_education') = 0, 'applicant_education', NULL),
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicant_family') = 0, 'applicant_family', NULL),
         IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicant_events') = 0, 'applicant_events', NULL))
  UNION ALL SELECT '2026-10-14-add-force-password-change',
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empdetails' AND COLUMN_NAME = 'must_change_pw') = 0, 'empdetails.must_change_pw', '')
  UNION ALL SELECT 'security fix (remember-me hash, see wedo-security-fixes)',
         IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empdetails' AND COLUMN_NAME = 'remember_hash') = 0, 'empdetails.remember_hash', '')
) AS m;
