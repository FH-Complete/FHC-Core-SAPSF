<?php

// if not false, sync is not executed, only objects to sync are outputted
$config['FHC-Core-SAPSFSyncparams']['syncpreview'] = false;

// number of days to look in future for data when syncing from SAPSF.
// chronologically first dataset is synced.
$config['FHC-Core-SAPSFSyncparams']['daysInFuture'] = 30;

// number of days to look in past for updateamum of Mitarbeiterfor data when syncing to SAPSF.
$config['FHC-Core-SAPSFSyncparams']['fhcMaHoursInPast'] = 24;
