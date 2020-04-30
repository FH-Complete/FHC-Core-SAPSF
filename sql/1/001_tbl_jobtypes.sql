INSERT INTO system.tbl_jobtypes
    (type, description)
SELECT 'SAPSFEmployeesCreate', 'Create new employees from SAP Success Factors'
WHERE
    NOT EXISTS (
        SELECT type FROM system.tbl_jobtypes WHERE type = 'SAPSFEmployeesCreate'
    );

INSERT INTO system.tbl_jobtypes
   (type, description)
SELECT 'SAPSFEmployeesAlias', 'Save Alias as email in SAP Success Factors'
WHERE
   NOT EXISTS (
       SELECT type FROM system.tbl_jobtypes WHERE type = 'SAPSFEmployeesAlias'
   );