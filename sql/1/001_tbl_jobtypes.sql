INSERT INTO system.tbl_jobtypes
    (type, description)
SELECT 'SyncEmployeesFromSAPSF', 'Save employees from SAP Success Factors'
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

INSERT INTO system.tbl_jobtypes
   (type, description)
SELECT 'SyncHourlyRatesFromSAPSF', 'Save hourly rate data from SAP Success Factors'
WHERE
   NOT EXISTS (
       SELECT type FROM system.tbl_jobtypes WHERE type = 'SyncHourlyRatesFromSAPSF'
   );

INSERT INTO system.tbl_jobtypes
   (type, description)
SELECT 'SyncCostcenterFromSAPSF', 'Save cost center data from SAP Success Factors'
WHERE
   NOT EXISTS (
       SELECT type FROM system.tbl_jobtypes WHERE type = 'SyncCostcenterFromSAPSF'
   );
