INSERT INTO system.tbl_jobtypes
    (type, description)
SELECT 'SyncEmployeesFromSAPSF', 'Create new employees from SAP Success Factors'
WHERE
    NOT EXISTS (
        SELECT type FROM system.tbl_jobtypes WHERE type = 'SyncEmployeesFromSAPSF'
    );

INSERT INTO system.tbl_jobtypes
   (type, description)
SELECT 'SyncEmployeesToSAPSF', 'Save employee data in SAP Success Factors'
WHERE
   NOT EXISTS (
       SELECT type FROM system.tbl_jobtypes WHERE type = 'SyncEmployeesToSAPSF'
   );