<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once 'vendor/nategood/httpful/bootstrap.php';

/**
 * Handles REST calls to SAPSF client API
 */
class SAPSFClientLib
{
	// Configs parameters names
	const ACTIVE_CONNECTION = 'fhc_sapsf_active_connection';
	const CONNECTIONS = 'fhc_sapsf_connections';
	const AUTH_TEMPLATE = 'login@companyid:password';
	const AUTH_PREFIX = 'Basic ';

    const HTTP_GET_METHOD = 'GET'; // http get method name
    const HTTP_POST_METHOD = 'POST'; // http post method name
    //const HTTP_DELETE_METHOD = 'DELETE'; // http deletemethod name
    const URI_TEMPLATE = '%s://%s/%s/%s'; // URI format

	// Blocking errors
	const CONNECTION_ERROR = 'CONNECTION_ERROR';
	const PARSE_ERROR = 'PARSE_ERROR';
	const WRONG_WS_PARAMETERS = 'WRONG_WS_PARAMETERS';
	const INVALID_HTTP_RESPONSE = 'INVALID_RESPONSE';

	const ERROR_STR = '%s: %s'; // Error message format

    private $_configArray;		// contains the connection parameters configuration array

	private $_authorisationString;		// http header value string for authorisation

    private $_httpMethod;			// http method used to call this server
    private $_callParametersArray;	// contains parameters for POST request
    private $_odataUriPart;			// odata-specific stringpart appended to the url, containing properties and getparameters

	private $_sessionCookie;
	private $_xcsrfToken;

	private $_error;				// true if an error occurred
	private $_errorMessage;			// contains the error message

	private $_hasData;				// indicates if there are data in the response or not

	private $_ci; // Code igniter instance

    /**
     * Object initialization
     */
    public function __construct()
    {
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-SAPSF/SAPSFClient'); // Loads FHC-SAPSF configuration

		$this->_setPropertiesDefault(); // properties initialization
        $this->_setConnection(); // sets the connection parameters
    }

    // --------------------------------------------------------------------------------------------
    // Public methods

    /**
     * Performs a call to a remote web service
     */
    public function call($odataUriPart, $httpMethod = SAPSFClientLib::HTTP_GET_METHOD, $callParametersArray = array())
    {
        if ($odataUriPart != null && trim($odataUriPart) != '')
        {
            $this->_odataUriPart = $odataUriPart;
        }
        else
        {
            $this->_error('MISSING_REQUIRED_PARAMETERS');
        }
        if ($httpMethod != null
            && ($httpMethod == SAPSFClientLib::HTTP_GET_METHOD || $httpMethod == SAPSFClientLib::HTTP_POST_METHOD /*|| $httpMethod == SAPSFClientLib::HTTP_DELETE_METHOD*/))
        {
            $this->_httpMethod = $httpMethod;
        }
        else
        {
            $this->_error(self::WRONG_WS_PARAMETERS, 'Wrong http method');
        }

		// Checks that the SOAP webservice parameters are present in an array
        if (is_array($callParametersArray))
            $this->_callParametersArray = $callParametersArray;
        else
            $this->_error(self::WRONG_WS_PARAMETERS, 'Are those parameters?');

		if ($this->isError()) return null; // If an error was raised then return a null value

        return $this->_callRemoteService($this->_generateURI()); // perform a remote SOAP call with the given uri
    }

	/**
	 * Returns the error message stored in property _errorMessage
	 */
	public function getError()
	{
		return $this->_errorMessage;
	}

	/**
	 * Returns true if an error occurred, otherwise false
	 */
	public function isError()
	{
		return $this->_error;
	}

	/**
	 * Returns false if an error occurred, otherwise true
	 */
	public function isSuccess()
	{
		return !$this->isError();
	}

	/**
	 * Returns true if the response contains data, otherwise false
	 */
	public function hasData()
	{
		return $this->_hasData;
	}

	/**
	 * Reset the library properties to default values
	 */
	public function resetToDefault()
	{
        $this->_error = false;
		$this->_errorMessage = '';
		$this->_hasData = false;
	}

    // --------------------------------------------------------------------------------------------
    // Private methods

	/**
     * Initialization of the properties of this object
     */
	private function _setPropertiesDefault()
	{
        $this->_configArray = $this->_ci->config->item('FHC-Core-SAPSF');
		$this->_authorisationString = '';
		$this->_httpMethod = '';
		$this->_callParametersArray = array();
        $this->_odataUriPart = '';
        $this->_xcsrfToken = '';
        $this->_sessionCookie = '';
		$this->_error = false;
		$this->_errorMessage = '';
		$this->_hasData = false;
	}

    /**
     * Sets the connection
     */
    private function _setConnection()
    {
		$activeConnectionName = $this->_ci->config->item(self::ACTIVE_CONNECTION);
		$connectionsArray = $this->_ci->config->item(self::CONNECTIONS);
		$credentials = $connectionsArray[$activeConnectionName];
		$credstr = self::AUTH_TEMPLATE;
        foreach ($credentials as $credkey => $credval)
        {
            $credstr = str_replace($credkey, $credval, $credstr);
		}
		$this->_authorisationString = self::AUTH_PREFIX . base64_encode($credstr);
    }

    /**
     * Generate the URI to call the remote web service
     */
    private function _generateURI()
    {
        $uri = sprintf(
            SAPSFClientLib::URI_TEMPLATE,
            $this->_configArray['protocol'],
            $this->_configArray['host'],
            $this->_configArray['path'],
            $this->_odataUriPart
        );

        return $uri;
    }

	/**
	 * Performs a remote REST web service call with the given name and parameters
	 */
	private function _callRemoteService($uri)
	{
        $response = null;

        try
        {
            if ($this->_isGET()) // if the call was performed using a HTTP GET...
            {
                $response = $this->_callGET($uri); // ...calls the remote web service with the HTTP GET method
            }
/*            elseif ($this->_isDELETE())
            {
                $response = $this->_callDELETE($uri); // ...calls the remote web service with the HTTP DELETE method
            }*/
            else // else if the call was performed using a HTTP POST...
            {
                $response = $this->_callPOST($uri); // ...calls the remote web service with the HTTP GET method
            }

            // Checks the response of the remote web service and handles possible errors
            $response = $this->_checkResponse($response);
        }
        catch (\Httpful\Exception\ConnectionErrorException $cee) // connection error
        {
            $response = null;
            $this->_error(self::CONNECTION_ERROR, sprintf(self::ERROR_STR, $cee->getCode(), $cee->getMessage()));
        }
            // otherwise another error has occurred, most likely the result of the
            // remote web service is not correct format so a parse error is raised
        catch (Exception $e)
        {
            $response = null;
            $this->_error(self::PARSE_ERROR, sprintf(self::ERROR_STR, $e->getCode(), $e->getMessage()));
        }

        // set session header params for session reuse
        $this->_setSessionReuse($response);
        return $response;
	}

	/**
	 * Performs a remote call using the GET HTTP method
	 * NOTE: parameters in a HTTP GET call are placed into the URI
	 */
    private function _callGET($uri)
    {
    	$request = \Httpful\Request::get($uri);
    	$this->_addHeaders($request);
        return $request->send();
    }

	/**
	 * Adds HTTP headers to a request
	 */
    private function _addHeaders(&$request)
	{
		$request->addHeader('Authorization', $this->_authorisationString);
		if (!isEmptyString($this->_sessionCookie) && !isEmptyString($this->_xcsrfToken))
		{
			$request->addHeader('Cookie', $this->_sessionCookie);
			$request->addHeader('X-CSRF-Token', $this->_xcsrfToken);
		}
	}

	/**
	 * Performs a remote call using the POST HTTP method
	 */
    private function _callPOST($uri)
    {
		$request = \Httpful\Request::post($uri);
		$this->_addHeaders($request);
        return $request->body(http_build_query($this->_callParametersArray))
            ->sendsType(\Httpful\Mime::FORM)
            ->send();
    }

	/**
	 * Sets cookie and X-CSRF token for session reuse
	 */
    private function _setSessionReuse($response)
	{
		if (isEmptyString($this->_sessionCookie) && isset($response->headers['set-cookie']) &&
			isEmptyString($this->_xcsrfToken) && isset($response->headers['x-csrf-token']))
		{
			$this->_sessionCookie = substr($response->headers['set-cookie'], 0, strpos($response->headers['set-cookie'], '; Path'));
			$this->_xcsrfToken = $response->headers['x-csrf-token'];
		}
	}

    /**
     * Returns true if the HTTP method used to call this server is GET
     */
    private function _isGET()
    {
        return $this->_httpMethod == SAPSFClientLib::HTTP_GET_METHOD;
    }

    /**
     * Returns true if the HTTP method used to call this server is POST
     */
    private function _isPOST()
    {
        return $this->_httpMethod == SAPSFClientLib::HTTP_POST_METHOD;
    }

    /**
     * Returns true if the HTTP method used to call this server is DELETE
     */
/*    private function _isDELETE()
    {
        return $this->_httpMethod == SAPSFClientLib::HTTP_DELETE_METHOD;
    }*/

    /**
     * Checks the response from the remote web service
     */
    private function _checkResponse($response)
    {
		$checkResponse = null;
		$this->_hasData = false;
		$genericErrorText = 'Error occured';

        // If a valid response
        if (is_object($response) && isset($response->headers) && isset($response->body))
        {
            $responsebody = $response->body;
            if (isset($response->body->error))
            {
                $responseerror = $responsebody->error;
                $errorcode = isEmptyString($responseerror->code) ? 'ERROR:' : $responseerror->code;
                $errormsg = isEmptyString($responseerror->message->value) ? $genericErrorText : $responseerror->message->value;
                $this->_error($errorcode, $errormsg);
            }
            elseif (isset($response->headers->error_code))
            {
                $errormsg = is_string($responsebody) && !isEmptyString($responsebody) ? $responsebody : $genericErrorText;
                $this->_error($response->headers->error_code, $errormsg);
            }
            else
            {
                // If data are present set property
                if (isset($responsebody->d) && count($responsebody->d) > 0)
                    $this->_hasData = true;

                $checkResponse = $response; // returns a success
            }
        }
        else
            $this->_error(self::INVALID_HTTP_RESPONSE, 'Invalid HTTP response');

		return $checkResponse;
    }

	/**
	 * Sets property _error to true and stores an error message in property _errorMessage
	 */
	private function _error($code, $message = 'Generic error')
	{
		$this->_error = true;
		$this->_errorMessage = $code.': '.$message;
	}
}
