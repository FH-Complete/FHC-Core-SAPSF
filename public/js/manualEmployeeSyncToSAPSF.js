/**
 * javascript file for sync to SAPSF
 */

$(document).ready(function()
	{
		// add uids for sync
		$("#addtosapuidbtn").click(
			function()
			{
				SAPSFEmployeeSyncLib.addUids('addtosapuidinput', 'enteredUidsFromFHC');
			}
		);

		$("#synctosapbtn").click(
			function()
			{
				SAPSFEmployeeSyncLib._clearSyncOutput();
				SAPSFEmployeeSync.postSyncEmployeesToSAPSF(SAPSFEmployeeSyncLib.uidsToSync);
			}
		);
	}
);

var SAPSFEmployeeSync = {

	postSyncEmployeesToSAPSF: function(uids)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path + '/postSyncEmployeesToSAPSF',
			{"uids": uids},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.isSuccess(data))
					{
						if (FHC_AjaxClient.hasData(data))
						{
							var respdata = FHC_AjaxClient.getData(data);
							var successstr = '';
							var errorstr = '';

							for (var i = 0; i < respdata.length; i++)
							{
								var responseobj = respdata[i];

								if (responseobj.key)
								{
									successstr += "<br /><br />" + responseobj.key;
								}
								else if (responseobj.error)
								{
									errorstr += "<br /><br />" + responseobj.error;
								}
							}

							if (successstr !== '')
							{
								SAPSFEmployeeSyncLib._writeSyncSuccess("<b>Folgende Daten wurden erfolgreich gesynct:</b>" + successstr);
							}

							if (errorstr !== '')
							{
								var separator = successstr !== '' ? "<br />" : "";
								SAPSFEmployeeSyncLib._writeSyncError(separator + "<b>Folgende Fehler sind aufgetreten:</b>" + errorstr);
							}
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
