-- 022_audit_log_immutability.sql
-- Enforce append-only audit_log at database level (Blueprint N-17, Section 454)
-- Prevents UPDATE and DELETE on governance.audit_log except by retention purge job

-- Revoke UPDATE and DELETE from all users except root (retention purge uses root)
-- Note: This uses a BEFORE UPDATE/DELETE trigger to reject modifications

DELIMITER //

-- Trigger: Block UPDATE on audit_log
CREATE TRIGGER governance.tr_audit_log_no_update
BEFORE UPDATE ON governance.audit_log
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'audit_log is append-only (N-17): UPDATE is not allowed';
END//

-- Trigger: Block DELETE on audit_log
-- Exception: allow DELETE when @audit_purge_mode = 1 (set by retention job)
CREATE TRIGGER governance.tr_audit_log_no_delete
BEFORE DELETE ON governance.audit_log
FOR EACH ROW
BEGIN
    IF @audit_purge_mode IS NULL OR @audit_purge_mode != 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'audit_log is append-only (N-17): DELETE is not allowed (use retention purge job)';
    END IF;
END//

DELIMITER ;
