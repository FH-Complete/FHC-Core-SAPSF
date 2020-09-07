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
                    FHC_DialogLib.alertError("Ungültiger uid input. Akzeptiert werden Buchstaben, separiert mit , oder Leerzeichen.");
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
                        console.log(data);
                    }
                    else
                    {
                        console.log('error');
                    }
                },
                errorCallback: function(jqXHR, textStatus, errorThrown)
                {
                    FHC_DialogLib.alertError("error when getting employees");
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
