<?php
$this->load->view(
	'templates/FHC-Header',
	array(
		'title' => 'SAP Success Factors Mitarbeitersync',
		'jquery' => true,
		'jqueryui' => true,
		'bootstrap' => true,
		'fontawesome' => true,
		'sbadmintemplate' => true,
		'dialoglib' => true,
		'ajaxlib' => true,
		'customJSs' => array(
			'public/extensions/FHC-Core-SAPSF/js/manualEmployeeSyncLib.js',
			'public/extensions/FHC-Core-SAPSF/js/manualEmployeeSyncToSAPSF.js'
		),
		'customCSSs' => array(
			'public/extensions/FHC-Core-SAPSF/css/manualSAPSFEmployeeSync.css',
			'public/css/sbadmin2/admintemplate_contentonly.css'
		)
	)
);
?>

<body>
	<div id="wrapper">
		<div id="page-wrapper">
			<div class="container-fluid">
				<div class="row">
					<div class="col-xs-12">
						<h3 class="page-header">SAP Success Factors Mitarbeiter Synchronisation</h3>
					</div>
				</div>
                <div class="row">
                    <div class="col-xs-8 form-group">
                        <h4>Fhcomplete -> SuccessFactors</h4>
                        <div class="input-group">
                            <input class="form-control" type="text" id="addtosapuidinput">
                            <span class="input-group-btn">
                            <button class="btn btn-default" id="addtosapuidbtn"><i class="fa fa-plus"></i>&nbsp;uids hinzufügen</button>
                        </span>
                        </div>
                        <!--<h4>Zu:</h4>-->
                        <p></p>
                        <div class="well well-sm wellminheight">
                            <div id="enteredUidsFromFHC" class="panel panel-body">
                            </div>
                        </div>
                        <button class="btn btn-default" id="synctosapbtn">
                            <i class="fa fa-refresh"></i>&nbsp;Nach SAPSF  synchronisieren
                        </button>
                    </div>
                    <div class="col-xs-4">
                        <h4>Syncoutput</h4>
                        <p></p>
                        <p></p>
                        <div class="well well-sm wellminheight">
                            <div id="syncoutput" class="panel panel-body">
                            </div>
                        </div>
                    </div>
                </div>
			</div>
		</div>
	</div>
</body>

<?php $this->load->view('templates/FHC-Footer'); ?>