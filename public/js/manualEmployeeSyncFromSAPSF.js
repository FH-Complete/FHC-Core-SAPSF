/**
 * javascript file for sync from SAPSF
 */

$(document).ready(function()
	{
		// add uids for sync
		$("#addfromsapuidbtn").click(
			function()
			{
				SAPSFEmployeeSyncLib.addUids('addfromsapuidinput', 'enteredUidsFromSAP');

			}
		);

		$("#syncfromsapbtn").click(
			function()
			{
				SAPSFEmployeeSyncLib._clearSyncOutput();
				SAPSFEmployeeSync.getSyncEmployeesFromSAPSF(SAPSFEmployeeSyncLib.uidsToSync);
			}
		);
	}
);

var SAPSFEmployeeSync = {


	getSyncEmployeesFromSAPSF: function(uids)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path + '/getSyncEmployeesFromSAPSF',
			{"uids": uids},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.isSuccess(data))
					{
						if (FHC_AjaxClient.hasData(data))
						{
							var uidstr = '';
							var errorstr = '';

							var uids = FHC_AjaxClient.getData(data);
							var uidstrfirst = true;
							var errstrfirst = true;

							jQuery.each(uids, function(idx, uid)
							{
								if (uid.uid)
								{
									if (!uidstrfirst)
										uidstr += ', ';
									uidstr += uid.uid;
									uidstrfirst = false;
								}
								else
								{
									if (!errstrfirst)
										errorstr += ', ';
									errorstr += uid;
									errstrfirst = false;
								}
							});

							if (uidstr !== '')
								SAPSFEmployeeSyncLib._writeSyncSuccess("Folgende uids erfolgreich gesynct: <br />" + uidstr);

							if (errorstr !== '')
								SAPSFEmployeeSyncLib._writeSyncError("Folgende Fehler sind aufgetreten:<br />" + errorstr);
						}
						else
							SAPSFEmployeeSyncLib._writeSyncInfo("Es wurden keine Mitarbeiter gesynct.");
					}
					else
					{
						SAPSFEmployeeSyncLib._writeSyncError("Mitarbeiter nicht gesynct, Fehler:<br />" + FHC_AjaxClient.getError(data));
					}
				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					SAPSFEmployeeSyncLib._writeSyncError("Fehler bei Mitarbeitersynchronisierung");
				}
			}
		);
	}
};
