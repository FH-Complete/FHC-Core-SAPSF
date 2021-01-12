<?php

// if not false, sync is not executed, only objects to sync are outputted
$config['FHC-Core-SAPSFSyncparams']['syncpreview'] = false;

// number of days to look in future for time-based data when syncing from SAPSF.
// chronologically last dataset is synced. Non-numeric value: start is today, no end. 0: only current dataset.
$config['FHC-Core-SAPSFSyncparams']['daysInFuture'] = null;

// number of days to look in past for updateamum of Mitarbeiter for data when syncing to SAPSF.
$config['FHC-Core-SAPSFSyncparams']['fhcMaHoursInPast'] = 24;

// number of days to look in past for updateamum of Mitarbeiter for data when syncing to SAPSF.
$config['FHC-Core-SAPSFSyncparams']['defaultFromDate'] = '2020-01-01';