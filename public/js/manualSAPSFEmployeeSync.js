/**
 * javascript file for Mobility Online courses sync
 */
const FULL_URL = FHC_JS_DATA_STORAGE_OBJECT.app_root + FHC_JS_DATA_STORAGE_OBJECT.ci_router + "/"+FHC_JS_DATA_STORAGE_OBJECT.called_path;

$(document).ready(function()
    {

        // add uids for sync
        $("#addfromsapuidbtn").click(
            function()
            {
                var uidinput = $("#addfromsapuidinput").val();
                if (SAPSFEmployeeSync._checkUid(uidinput))
                {
                    //uidinput = 'bla , blu';
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
                SAPSFEmployeeSync.getSyncEmployeesFromSAPSF(SAPSFEmployeeSync.uidsToSyncFromSAPSF);
            }
        );

        //init sync
        $("#lvsyncbtn").click(
            function()
            {
                SAPSFEmployeeSync.syncLvs($("#studiensemester").val());
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

                            var successtext = uidstr === '' ? uidstr : "Folgende uids erfolgreich gesynct: " + uidstr;

                            if (errorstr === '')
                                FHC_DialogLib.alertSuccess(successtext);
                            else
                                FHC_DialogLib.alertWarning(successtext + "<br /> Folgende Fehler sind beim Syncen aufgetreten:<br />" + errorstr);
                        }
                        else
                            FHC_DialogLib.alertInfo("Keine Mitarbeiter für sync gefunden.");
                    }
                    else
                    {
                        FHC_DialogLib.alertError("Employees not synced, error:<br />" + FHC_AjaxClient.getError(data));
                    }
                },
                errorCallback: function(jqXHR, textStatus, errorThrown)
                {
                    FHC_DialogLib.alertError("error when syncing employees");
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
        var uidstr = SAPSFEmployeeSync.uidsToSyncFromSAPSF.join('<br />');

        $("#enteredUidsFromSAP").html(
            uidstr
        )
    }
};
