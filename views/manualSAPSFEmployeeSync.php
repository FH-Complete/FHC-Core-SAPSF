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
		'navigationwidget' => true,
		'customJSs' => array('public/extensions/FHC-Core-SAPSF/js/manualSAPSFEmployeeSync.js'),
		'customCSSs' => array('public/extensions/FHC-Core-SAPSF/css/manualSAPSFEmployeeSync.css')
	)
);
?>

	<body>
	<div id="wrapper">

		<?php echo $this->widgetlib->widget('NavigationWidget'); ?>

		<div id="page-wrapper">
			<div class="container-fluid">
				<div class="row">
					<div class="col-xs-12">
						<h3 class="page-header">SAP Success Factors Mitarbeiter Synchronisation</h3>
					</div>
				</div>
                <div class="row">
                    <div class="col-xs-8 form-group">
                        <h4>Successfactors -> Fhcomplete</h4>
                        <div class="input-group">
                            <input class="form-control" type="text" id="addfromsapuidinput">
                            <span class="input-group-btn">
                            <button class="btn btn-default" id="addfromsapuidbtn"><i class="fa fa-plus"></i>&nbsp;uids hinzufügen</button>
                        </span>
                        </div>
                        <!--<h4>Zu:</h4>-->
                        <p></p>
                        <div class="well well-sm wellminheight">
                            <div id="enteredUidsFromSAP" class="panel panel-body">
                            </div>
                        </div>
                        <button class="btn btn-default" id="syncfromsapbtn">
                            <i class="fa fa-refresh"></i>&nbsp;Mitarbeiter synchronisieren
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