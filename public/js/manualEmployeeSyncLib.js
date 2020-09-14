/**
 * javascript file for SAPSF sync
 */
var SAPSFEmployeeSyncLib = {

	uidsToSync: [],
	addUids: function(inputid, canvasid)
	{
		var uidinput = $("#" + inputid).val();
		if (SAPSFEmployeeSyncLib._checkUid(uidinput))
		{
			var uids = uidinput.replace(/(\s|,)+/g, ',').split(',');

			for (var i = 0; i < uids.length; i++)
			{
				if (jQuery.inArray(uids[i], SAPSFEmployeeSyncLib.uidsToSync) < 0 && uids[i] !== '')
					SAPSFEmployeeSyncLib.uidsToSync.push(uids[i]);
			}
			SAPSFEmployeeSyncLib._refreshUids(canvasid);
		}
		else
		{
			FHC_DialogLib.alertError("Ungültiger uid input. Akzeptiert werden mit , oder Leerzeichen separierte Buchstaben. Keine Umlaute.");
		}
	},

	//------------ "private" methods -------------//
	_checkUid: function(uidstr)
	{
		return uidstr.match(/^([a-zA-Z0-9]+(\s|,)*)+$/);
	},
	_refreshUids: function(canvasid)
	{
		var uidstr = '';

		for (var i = 0; i < SAPSFEmployeeSyncLib.uidsToSync.length; i++)
		{
			var uid = SAPSFEmployeeSyncLib.uidsToSync[i];
			uidstr += uid;
			uidstr += '&nbsp;<span id="uid_' + uid + '" class="minusspan text-danger"><i class="fa fa-times" title="entfernen"></i></span><br />';
		}

		$("#" + canvasid).html(
			uidstr
		);

		$(".minusspan").click(
			function()
			{
				var spanuid = $(this).prop("id");
				spanuid = spanuid.substr(spanuid.indexOf("_") + 1);
				SAPSFEmployeeSyncLib.uidsToSync.splice(jQuery.inArray(spanuid, SAPSFEmployeeSyncLib.uidsToSync), 1);
				SAPSFEmployeeSyncLib._refreshUids(canvasid);
			}
		)
	},
	_writeSyncOutput: function(text, colorclass)
	{
		$("#syncoutput").append("<p class='" + colorclass + "'>" + text + "</p>");
	},
	_writeSyncSuccess: function(text)
	{
		SAPSFEmployeeSyncLib._writeSyncOutput(text, 'text-success');
	},
	_writeSyncError: function(text)
	{
		SAPSFEmployeeSyncLib._writeSyncOutput(text, 'text-danger');
	},
	_writeSyncInfo: function(text)
	{
		SAPSFEmployeeSyncLib._writeSyncOutput(text, 'text-info');
	},
	_clearSyncOutput: function()
	{
		$("#syncoutput").empty();
	}
};
