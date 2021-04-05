<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once('include/functions.inc.php');// needed for activation key generation
require_once 'SyncFromSAPSFLib.php';

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Success Factors
 */
class SyncEmployeesFromSAPSFLib extends SyncFromSAPSFLib
{
	const OBJTYPE = 'User';
	const HOURLY_RATE_OBJ = 'HourlyRate';
	const COST_CENTER_OBJ = 'CostCenter';
	const SAPSF_EMPLOYEES_FROM_SAPSF = 'SyncEmployeesFromSAPSF';
	const SAPSF_HOURLY_RATES_FROM_SAPSF = 'SyncHourlyRatesFromSAPSF';
	const SAPSF_COSTCENTERS_FROM_SAPSF = 'SyncCostcenterFromSAPSF';

	protected $_convertfunctions = array( // extraParams -> additional parameters coming from SF which are needed by function
		'mitarbeiter' =>array(
			'stundensatz' => array(
				'function' => '_selectStundensatzForFhc',
				'extraParams' => array( // property of SAPSF object with given "table" and name is passed as function param
					array('table' => 'sap_stundensatz_typ', 'name' => 'sap_stundensatz_typ'),
					array('table' => 'sap_stundensatz_typ', 'name' => 'sap_stundensatz_startdate')
				)
			)
		),
		'person' => array(
			'gebdatum' => array(
				'function' => '_convertDateToFhc',
				'extraParams' => null
			),
			'geburtsnation' => array(
				'function' => '_convertNationToFhc',
				'extraParams' => null
			),
			'staatsbuergerschaft' => array(
				'function' => '_convertNationToFhc',
				'extraParams' => null
			),
			'svnr' => array(
				'function' => '_selectKzForFhc',
				'extraParams' => array(
					array('table' => 'kztyp', 'name' => 'kztyp', 'fhcfield' => 'svnr')
				)
			),
			'ersatzkennzeichen' => array(
				'function' => '_selectKzForFhc',
				'extraParams' => array(
					array('table' => 'kztyp', 'name' => 'kztyp', 'fhcfield' => 'ersatzkennzeichen')
				)
			)
		),
		'benutzer' => array(
			'aktiv' => array(
				'function' => '_selectAktivForFhc',
				'extraParams' => array(
					array('table' => 'sapaktiv', 'name' => 'sapaktiv'),
					array('table' => 'sapaktiv', 'name' => 'sapstartdatum')
				)
			)
		),
		'kontaktmail' => array(
			'kontakt' => array(
				'function' => '_selectEmailForFhc',
				'extraParams' => array(
					array('table' => 'mailtyp', 'name' => 'emailtyp')
				)
			)
		),
		'kontakttelefon' => array(
			'kontakt' => array(
				'function' => '_selectPhoneForFhc',
				'extraParams' => array(
					array('table' => 'telefondaten', 'name' => 'telefontyp', 'fhcfield' => 'kontakttelprivate'),
					array('table' => 'telefondaten', 'name' => 'landesvorwahl'),
					array('table' => 'telefondaten', 'name' => 'ortsvorwahl'),
					array('table' => 'telefondaten', 'name' => 'telefonklappe')
				)
			)
		),
/*		'kontakttelmobile' => array(
			'kontakt' => array(
				'function' => '_selectPhoneForFhc',
				'extraParams' => array(
					array('table' => 'telefondaten', 'name' => 'telefontyp', 'fhcfield' => 'kontakttelmobile'),
					array('table' => 'telefondaten', 'name' => 'landesvorwahl'),
					array('table' => 'telefondaten', 'name' => 'ortsvorwahl'),
					array('table' => 'telefondaten', 'name' => 'telefonklappe')
				)
			)
		),*/
		'sap_kalkulatorischer_stundensatz' => array(
			'sap_kalkulatorischer_stundensatz' => array(
				'function' => '_selectKalkStundensatzForFhc',
				'extraParams' => array(
					array('table' => 'sap_stundensatz_typ', 'name' => 'sap_stundensatz_typ'),
					array('table' => 'sap_stundensatz_typ', 'name' => 'sap_stundensatz_startdate')
				)
			)
		),
		'benutzerfunktion' => array(
			'oe_kurzbz' => array(
				'function' => '_convertKostenstelleForFhc',
				'extraParams' => null
			),
			'datum_von' => array(
				'function' => '_convertDatesToFhc',
				'extraParams' => null
			),
			'datum_bis' => array(
				'function' => '_convertKostenstelleEndDateForFhc',
				'extraParams' => array(
					array('table' => 'sapaktiv', 'name' => 'sap_event_reason'),
					array('table' => 'benutzerfunktion', 'name' => 'datum_von')
				)
			)
		),
		'adresse' => array(), // filled in constructor
		'nebenadresse' => array()
	);

	/**
	 * SyncEmployeesFromSAPSFLib constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_setAdresseConvertFunctions();

		$this->ci->load->helper('extensions/FHC-Core-SAPSF/sync_helper');

		$this->ci->load->library('extensions/FHC-Core-SAPSF/SaveFromSAPSFLib');

		//load models
		$this->ci->load->model('extensions/FHC-Core-SAPSF/SAPSFQueries/QueryUserModel', 'QueryUserModel');
		$this->ci->load->model('extensions/FHC-Core-SAPSF/fhcomplete/SAPOrganisationsstruktur_model', 'SAPOrganisationsstrukturModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Gets employees to be synced.
	 * @param $objtype
	 * @param $newJobObj object contains information for sync (uids, fullsync)
	 * @param $lastDoneJobs object jobs executed last
	 * @return object the mitarbeiter to sync on success, error otherwise
	 */
	public function getEmployeesForSync($objtype, $newJobObj, $lastjobtime = null)
	{
		$lastModifiedDateTime = null;

		$lastModifiedDateTime = isEmptyString($lastjobtime) ? null : $this->convertDateToSAPSF($lastjobtime);

		// input uids if only certain users should be synced
		$uids = checkStringArray($newJobObj->uids) ? $newJobObj->uids : array();

		$selects = $this->getSelectsFromFieldMappings($objtype);
		$expands = $this->getExpandsFromFieldMappings($objtype);
		// properties to be filtered by lastmodified date
		$lastmodifiedprops = isset($lastModifiedDateTime) ? $this->_sapsflastmodifiedfields : null;
		// properties to be filtered by start date
		$startdateprops = isset($lastModifiedDateTime) ? $this->_sapsfstartdatefields: null;

		$uidsToSync = array();
		$maToSync = array();

		// full sync
		if (isset($newJobObj->syncAll) && $newJobObj->syncAll)
		{
			$employees = $this->ci->QueryUserModel->getAll($selects, $expands, $lastModifiedDateTime, $lastjobtime, $lastmodifiedprops,  $startdateprops);

			if (isError($employees))
			{
				return error(getError($employees));
			}

			if (hasData($employees))
			{
				$empData = getData($employees);

				foreach ($empData as $emp)
				{
					$maToSync[] = success($emp);
					$uidsToSync[] = $emp->userId;
				}
			}
		}

		// include additional, manually passed uids
		$uids = array_diff($uids, $uidsToSync);

		foreach ($uids as $uid)
		{
			$employee = $this->ci->QueryUserModel->getByUserId($uid, $selects, $expands);

			$maToSync[] = $employee;
		}

		return success($maToSync);
	}

	/**
	 * Gets synced employees uids, or errors from result.
	 * @param $results
	 * @return array
	 */
	public function getSyncedEmployees($results, $idtype = 'uid')
	{
		$syncedMitarbeiterRes = array();

		$errorno = 0;
		foreach ($results as $result)
		{
			if (isset($result) && isError($result))
			{
				$syncedMitarbeiterRes['error_'.$errorno] = error(getError($result));
				$errorno++;
			}
			elseif (hasData($result))
			{
				$syncid = getData($result);
				if (is_string($syncid) || is_numeric($syncid))
				{
					$employee = new stdClass();
					$employee->{$idtype} = $syncid;
					$syncedMitarbeiterRes[$syncid] = success($employee);
				}
			}
		}

		return $syncedMitarbeiterRes;
	}

	/**
	 * Starts employee sync. Converts given employee data to fhc format and saves the employee.
	 * @param $employees
	 * @return object
	 */
	public function syncEmployeesWithFhc($employees)
	{
		$convEmployees = $this->_convertEmployeesForFhc($employees, self::OBJTYPE);

		if ($this->_syncpreview !== false)
			printAndDie($convEmployees);

		$results = array();

		if (is_array($convEmployees))
		{
			foreach ($convEmployees as $employee)
			{
				$result =  $this->ci->savefromsapsflib->saveMitarbeiter($employee);
				$results[] = $result;
			}
		}

		return success($results);
	}

	/**
	 * Starts hourly rates sync.
	 * Converts given hourly rate data to fhc format and saves the hourly rates object.
	 * @param $hourlyrates
	 * @return object
	 */
	public function syncHourlyRateWithFhc($hourlyrates)
	{
		$convEmployees = $this->_convertEmployeesForFhc($hourlyrates, self::HOURLY_RATE_OBJ);

		if ($this->_syncpreview !== false)
			printAndDie($convEmployees);

		$results = array();

		if (is_array($convEmployees))
		{
			foreach ($convEmployees as $employee)
			{
				$result = $this->ci->FhcDbModel->saveKalkStundensatz($employee);
				$results[] = $result;
			}
		}

		return success($results);
	}

	/**
	 * Starts costcenter sync.
	 * Converts given cost center data to fhc format and saves the cost center object.
	 * @param $costcenters
	 * @return object
	 */
	public function syncCostcentersWithFhc($costcenters)
	{
		$convEmployees = $this->_convertEmployeesForFhc($costcenters, self::COST_CENTER_OBJ);

		if ($this->_syncpreview !== false)
			printAndDie($convEmployees);

		$results = array();

		if (is_array($convEmployees))
		{
			foreach ($convEmployees as $employee)
			{
				$saveResult = $this->ci->savefromsapsflib->saveKostenstellenfunktionen($employee);

				$results[] = $saveResult;
			}
		}

		return success($results);
	}

	//------------------------------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Selects correct email string to be inserted in fhc.
	 * @param $mails array contains all mails present in sapsf
	 * @param $params array contain emailtyp information
	 * @return string the mail kontakt to insert in fhc
	 */
	protected function _selectEmailForFhc($mails, $params)
	{
		return $this->_selectFieldForFhc(
			$mails,
			$params,
			'emailtyp',
			$this->_sapsfvaluedefaults['kontaktmailprivate']['PerEmail']['emailType']
		);
	}

	/**
	 * Selects and builds correct phone string to be inserted in fhc.
	 * @param $phones contains all phones present in sapsf
	 * @param $params contains phone components and phone type
	 * @return string the phone kontakt to insert in fhc
	 */
	protected function _selectPhoneForFhc($phones, $params)
	{
		$phone = '';

		if (isset($phones) && isset($params['telefontyp']))
		{
			$phones = is_string($phones) ? array($phones) : $phones;
			$params['telefontyp'] = is_string($params['telefontyp']) ? array($params['telefontyp']) : $params['telefontyp'];
			$params['landesvorwahl'] = is_string($params['landesvorwahl']) ? array($params['landesvorwahl']) : $params['landesvorwahl'];
			$params['ortsvorwahl'] = is_string($params['ortsvorwahl']) ? array($params['ortsvorwahl']) : $params['ortsvorwahl'];
			if (isset($params['telefonklappe']))
				$params['telefonklappe'] = is_string($params['telefonklappe']) ? array($params['telefonklappe']) : $params['telefonklappe'];

			$fhcphonetype = $params['misc']['telefondaten']['fhcfield'];

			if (is_array($phones))
			{
				for ($i = 0; $i < count($phones); $i++)
				{
					if (isset($params['telefontyp'][$i]) && $params['telefontyp'][$i] == $this->_sapsfvaluedefaults[$fhcphonetype]['PerPhone']['phoneType'] && !isEmptyString($phones[$i]))
					{
						$phone = $params['landesvorwahl'][$i] . ' ' . $params['ortsvorwahl'][$i] . ' ' . $phones[$i] . (isset($params['telefonklappe'][$i]) ? '-' . $params['telefonklappe'][$i] : '');
						break;
					}
				}
			}
			else
				$phone = $params['landesvorwahl'] . ' ' . $params['ortsvorwahl'] . ' ' . $phones . (isset($params['telefonklappe']) ? '-' . $params['telefonklappe'] : '');
		}
		return $phone;
	}

	/**
	 * Selects correct Kennzeichen (svnr, ersatzkennzeichen) to be inserted in fhc.
	 * @param $kzval array contains Kennzeichen
	 * @param $params array contain kztyp information
	 * @return string the kz to insert in fhc
	 */
	protected function _selectKzForFhc($kzval, $params)
	{
		$kz = null;

		if (isset($kzval) && isset($params['kztyp']))
		{
			if (is_array($kzval))
			{
				for ($i = 0; $i < count($kzval); $i++)
				{
					if (isset($params['kztyp'][$i]) && $params['kztyp'][$i] == $this->_confvaluedefaults['User']['kztyp'][$params['misc']['kztyp']['fhcfield']])
					{
						$kz = str_replace(' ', '', $kzval[$i]);
						break;
					}
				}
			}
			elseif (isset($params['kztyp']) && $params['kztyp'] == $this->_confvaluedefaults['User']['kztyp'][$params['misc']['kztyp']['fhcfield']])
			{
				$kz = str_replace(' ', '', $kzval);
			}
		}

		return $kz;
	}

	/**
	 * Selects correct Lektorenstundensatz to be inserted in fhc.
	 * @param $stundensaetze array
	 * @param $params array contains Stundensatz type
	 * @return string the kalkulatorischer Stundensatz to insert in fhc
	 */
	protected function _selectStundensatzForFhc($stundensaetze, $params)
	{
		return $this->_selectFieldForFhc(
			$stundensaetze,
			$params,
			'sap_stundensatz_typ',
			$this->_sapsfvaluedefaults['sap_lekt_stundensatz']['HourlyRates']['hourlyRatesType']
		);
	}

	/**
	 * Selects correct kalkulatorischer Stundensatz to be inserted in fhc.
	 * @param $stundensaetze array
	 * @param $params array contains Stundensatz type
	 * @return string the kalkulatorischer Stundensatz to insert in fhc
	 */
	protected function _selectKalkStundensatzForFhc($stundensaetze, $params)
	{
		return $this->_selectFieldForFhc(
			$stundensaetze,
			$params,
			'sap_stundensatz_typ',
			$this->_sapsfvaluedefaults['sap_kalkulatorischer_stundensatz']['HourlyRates']['hourlyRatesType']
		);
	}

	/**
	 * Selects correct adresse field to be inserted in fhc.
	 * @param $adressen array the adresse fields
	 * @param $params array contains adresse type
	 * @return string the adresse field to insert in fhc
	 */
	protected function _selectAdresseForFhc($adressen, $params)
	{
		return $this->_selectFieldForFhc(
			$adressen,
			$params,
			'typ',
			isset($params['misc']['adressedaten']['adressetyp']) ? $params['misc']['adressedaten']['adressetyp'] : null
		);
	}

	/**
	 * Selects correct adresse nation to be inserted in fhc.
	 * @param $adressen array the adresse nation fields
	 * @param $params array contains adresse type
	 * @return string the adresse nation field to insert in fhc
	 */
	protected function _selectAdresseNationForFhc($adressen, $params)
	{
		$nation = $this->_selectFieldForFhc(
			$adressen,
			$params,
			'typ',
			isset($params['misc']['adressedaten']['adressetyp']) ? $params['misc']['adressedaten']['adressetyp'] : null
		);

		return $this->_convertNationToFhc($nation);
	}

	/**
	 * Selects correct adresse strasse to be inserted in fhc.
	 * @param $adressen array the adresse strasse fields
	 * @param $params array contains adresse type
	 * @return string the adresse strasse field to insert in fhc
	 */
	protected function _selectStrasseForFhc($adressen, $params)
	{
		$adresse = $this->_selectFieldForFhc(
			$adressen,
			$params,
			'typ',
			isset($params['misc']['adressedaten']['adressetyp']) ? $params['misc']['adressedaten']['adressetyp'] : null
		);

		if (isset($params['hausnr']) && !isEmptyString($params['hausnr']))
		{
			$hausnr = $this->_selectFieldForFhc(
				$params['hausnr'],
				$params,
				'typ',
				isset($params['misc']['adressedaten']['adressetyp']) ? $params['misc']['adressedaten']['adressetyp'] : null
			);

			$adresse .= ' ' . $hausnr;
		}

		return $adresse;
	}

	/**
	 * Sets correct aktiv boolean for fhc.
	 * If FAS-aktiv in SAPSF is false, set to false.
	 * Otherwise use SAPSF-aktiv. If there is a future start date in SAPSF, it still counts as active.
	 * @param $aktiv fas-aktiv in SAPSF
	 * @param $params contains other necessary parameter, like sapaktiv and start date of employment
	 * @return bool the value to be saved in FAS aktiv field
	 */
	protected function _selectAktivForFhc($aktiv, $params)
	{
		$fasaktiv = isset($params['sapaktiv']) && is_bool($params['sapaktiv']) ? $params['sapaktiv'] : true;

		if ($aktiv === false)
			$fasaktiv = false;
		elseif (isset($params['sapstartdatum']))
		{
			$sapstartdate = $this->_convertDateToFhc($params['sapstartdatum']);

			if ($sapstartdate > date('Y-m-d')) // if there is a future start date, it counts as aktiv
				$fasaktiv = true;
		}

		return $fasaktiv;
	}

	/**
	 * Converts SAPSF cost center to FHC const center using a matching table.
	 * @param string |  array costcenter(s) from SAPSF, string or array of strings
	 * @return array contains fhc cost centers
	 */
	protected function _convertKostenstelleForFhc($costcenter)
	{
		$kostenstellen = array();
		$fhc_kostenstellen = array();

		if (is_string($costcenter))
		{
			$kostenstellen[] = $costcenter;
		}
		elseif (is_array($costcenter))
		{
			$kostenstellen = array_merge($kostenstellen, $costcenter);
		}

		foreach ($kostenstellen as $kostenstelle)
		{
			$kstOeResult = $this->ci->SAPOrganisationsstrukturModel->loadWhere(array('oe_kurzbz_sap' => $kostenstelle));

			if (hasData($kstOeResult))
				$fhc_kostenstellen[] = getData($kstOeResult)[0]->oe_kurzbz;
			else
				$fhc_kostenstellen[] = $kostenstelle;
		}

		return $fhc_kostenstellen;
	}

	/**
	 * Kündigung end date of last Kündigung is set to year 9999 in SAPSF.
	 * In such case, it should not be saved as current Funktion with end date = null in FHC, but with end date = start date.
	 * @param array | string $costcenterEndDates
	 * @param array $params
	 * @return array with possibly modified end dates
	 */
	protected function _convertKostenstelleEndDateForFhc($costcenterEndDates, $params)
	{
		$sapEventReasons = is_array($params['sap_event_reason']) ? $params['sap_event_reason'] : array($params['sap_event_reason']);
		$sapDatumVon = is_array($params['datum_von']) ? $params['datum_von'] : array($params['datum_von']);
		$fhcCostcenterEndDates = $this->_convertDatesToFhc($costcenterEndDates);

		foreach ($fhcCostcenterEndDates as $idx => $fhcCostcenterEndDate)
		{
			if (in_array($sapEventReasons[$idx], $this->_synccostcentereventtypeexceptions))
			{
				$fhcCostcenterEndDates[$idx] = $sapDatumVon[$idx];
			}
		}

		return $fhcCostcenterEndDates;
	}

	//------------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Sets the convert functions for Hauptadresse and Nebenadresse.
	 */
	private function _setAdresseConvertFunctions()
	{
		$adresseConvFunctions =
		array(
			'nation' => array(
				'function' => '_selectAdresseNationForFhc',
				'extraParams' => array(
					array('table' => 'adressedaten', 'name' => 'typ', 'adressetyp' => 'h')
				)
			),
			'plz' => array(
				'function' => '_selectAdresseForFhc',
				'extraParams' => array(
					array('table' => 'adressedaten', 'name' => 'typ', 'adressetyp' => 'h')
				)
			),
			'ort' => array(
				'function' => '_selectAdresseForFhc',
				'extraParams' => array(
					array('table' => 'adressedaten', 'name' => 'typ', 'adressetyp' => 'h')
				)
			),
			'strasse' => array(
				'function' => '_selectStrasseForFhc',
				'extraParams' => array(
					array('table' => 'adressedaten', 'name' => 'typ', 'adressetyp' => 'h'),
					array('table' => 'adressedaten', 'name' => 'hausnr')
				)
			),
			'gemeinde' => array(
				'function' => '_selectAdresseForFhc',
				'extraParams' => array(
					array('table' => 'adressedaten', 'name' => 'typ', 'adressetyp' => 'h')
				)
			),
			'name' => array(
				'function' => '_selectAdresseForFhc',
				'extraParams' => array(
					array('table' => 'adressedaten', 'name' => 'typ', 'adressetyp' => 'h')
				)
			)
		);

		// nebenadresse and hauptadresse have almost same structure, so they are set separately
		$this->_convertfunctions['adresse'] = $adresseConvFunctions;

		foreach ($adresseConvFunctions as $field => $function)
		{
			foreach ($adresseConvFunctions[$field]['extraParams'] as $idx => $param)
			{
				if ($param['name'] == 'typ')
					$adresseConvFunctions[$field]['extraParams'][$idx]['adressetyp'] = 'n';

			}
		}

		$this->_convertfunctions['nebenadresse'] = $adresseConvFunctions;
	}

	/**
	 * Converts given employee data to fhc format.
	 * @param $sapsfemployees
	 * @param $objtype
	 * @return array converted employees
	 */
	private function _convertEmployeesForFhc($sapsfemployees, $objtype)
	{
		$mas = array();

		foreach ($sapsfemployees as $employee)
		{
			if (hasData($employee))
			{
				$ma = $this->_convertSapsfObjToFhc(getData($employee), $objtype);
				$mas[] = $ma;
			}
		}

		return $mas;
	}

	/**
	 * Selects a value out of a data array based on extraparameters.
	 * @param $data
	 * @param $params extraparameters
	 * @param $fieldname of the extraparameters
	 * @param $sffieldtype type to select
	 * @return object|null
	 */
	private function _selectFieldForFhc($data, $params, $fieldname, $sffieldtype)
	{
		$returnvalue = null;

		if (isset($data) && isset($params[$fieldname]))
		{
			$data = is_string($data) ? array($data) : $data;
			$params[$fieldname] = is_string($params[$fieldname]) ?
				array($params[$fieldname]) : $params[$fieldname];

			if (is_array($data))
			{
				// get correct value according to fielddtype
				for ($i = 0; $i < count($data); $i++)
				{
					if (isset($params[$fieldname][$i]) &&
						$params[$fieldname][$i] == $sffieldtype)
					{
						$returnvalue = $data[$i];
						break;
					}
				}
			}
			else
				$returnvalue = $data;
		}

		return $returnvalue;
	}
}
