/**
 * javascript file for SAPSF sync
 */

$(document).ready(function()
    {
        // add uids for sync
        $("#addfromsapuidbtn").click(
            function()
            {
                var uidinput = $("#addfromsapuidinput").val();
                if (SAPSFEmployeeSync._checkUid(uidinput))
                {
                    var uids = uidinput.replaceAll(/(\s|,)+/g,',').split(',');

                    for (var i = 0; i < uids.length; i++)
                    {
                        if (jQuery.inArray(uids[i], SAPSFEmployeeSync.uidsToSyncFromSAPSF) < 0)
                            SAPSFEmployeeSync.uidsToSyncFromSAPSF.push(uids[i]);
                    }
                    SAPSFEmployeeSync._refreshUids();
                }
                else
                {
                    FHC_DialogLib.alertError("Ungültiger uid input. Akzeptiert werden mit , oder Leerzeichen separierte Buchstaben.");
                }
            }
        );

        $("#syncfromsapbtn").click(
            function()
            {
                SAPSFEmployeeSync._clearSyncOutput();
                SAPSFEmployeeSync.getSyncEmployeesFromSAPSF(SAPSFEmployeeSync.uidsToSyncFromSAPSF);
            }
        );
    }
);

var SAPSFEmployeeSync = {

    uidsToSyncFromSAPSF: [],
    getSyncEmployeesFromSAPSF: function(uids)
    {
        FHC_AjaxClient.ajaxCallGet(
            FHC_JS_DATA_STORAGE_OBJECT.called_path+'/getSyncEmployeesFromSAPSF',
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
                                SAPSFEmployeeSync._writeSyncSuccess("Folgende uids erfolgreich gesynct: <br />" + uidstr);

                            if (errorstr !== '')
                                SAPSFEmployeeSync._writeSyncError("Folgende Fehler sind aufgetreten:<br />" + errorstr);
                        }
                        else
                            SAPSFEmployeeSync._writeSyncInfo("Keine Mitarbeiter für sync gefunden.");
                    }
                    else
                    {
                        SAPSFEmployeeSync._writeSyncError("Employees not synced, error:<br />" + FHC_AjaxClient.getError(data));
                    }
                },
                errorCallback: function(jqXHR, textStatus, errorThrown)
                {
                    SAPSFEmployeeSync._writeSyncError("error when syncing employees");
                }
            }
        );
    },

    //------------ "private" methods -------------//
    _checkUid: function(uidstr)
    {
        return uidstr.match(/^([a-zA-Z0-9]+(\s|,)*)+$/);
    },
    _refreshUids: function()
    {
        var uidstr = '';

        for (var i = 0; i < SAPSFEmployeeSync.uidsToSyncFromSAPSF.length; i++)
        {
            var uid = SAPSFEmployeeSync.uidsToSyncFromSAPSF[i];
            uidstr += uid;
            uidstr += '&nbsp;<span id="uid_' + uid + '" class="minusspan text-danger"><i class="fa fa-times" title="entfernen"></i></span><br />';
        }

        $("#enteredUidsFromSAP").html(
            uidstr
        )

        $(".minusspan").click(
            function()
            {
                var spanuid = $(this).prop("id");
                spanuid = spanuid.substr(spanuid.indexOf("_") + 1);
                SAPSFEmployeeSync.uidsToSyncFromSAPSF.splice(jQuery.inArray(spanuid, SAPSFEmployeeSync.uidsToSyncFromSAPSF), 1);
                SAPSFEmployeeSync._refreshUids();
            }
        )
    },
    _writeSyncOutput: function(text, colorclass)
    {
        $("#syncoutput").append("<p class='" + colorclass + "'>" + text + "</p>");
    },
    _writeSyncSuccess: function(text)
    {
        SAPSFEmployeeSync._writeSyncOutput(text, 'text-success');
    },
    _writeSyncError: function(text)
    {
        SAPSFEmployeeSync._writeSyncOutput(text, 'text-danger');
    },
    _writeSyncInfo(text)
    {
        SAPSFEmployeeSync._writeSyncOutput(text, 'text-info');
    },
    _clearSyncOutput()
    {
        $("#syncoutput").empty();
    }
};
