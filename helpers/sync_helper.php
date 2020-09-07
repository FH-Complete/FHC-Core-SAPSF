<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

// ------------------------------------------------------------------------
// Collection of utility functions for general purpose
// ------------------------------------------------------------------------

/**
 * Helper function for merging job employee input of multiple jobs
 */
function mergeEmployeesArray($jobs)
{
	$resultObj = new StdClass();
	$resultObj->syncAll = false;
	$resultObj->uids = array();

	if (count($jobs) == 0) return $resultObj;

	foreach ($jobs as $job)
	{
		$decodedInput = json_decode($job->input);
		if ($decodedInput === null)
		{
			// all uids must be synced
			$resultObj->syncAll = true;
		}
		else
		{
			foreach ($decodedInput as $el)
			{
				if (isset($el->uid) && !in_array($el->uid, $resultObj->uids))
					$resultObj->uids[] = $el->uid;
			}
		}
	}
	return $resultObj;
}

/**
 * Checks if given date exists and is valid.
 * @param $date
 * @param string $format
 * @return bool
 */
function validateDateFormat($date, $format = 'Y-m-d')
{
	$d = DateTime::createFromFormat($format, $date);
	return $d && $d->format($format) === $date;
}

/**
 * Descends into properties of an object using array.
 * @param $var object the object
 * @param $arr array with properties, hierarchical
 * @return mixed the property as indicated by array
 */
function getPropertyByArray($var, $arr)
{
	$result = $var;
	foreach ($arr as $item)
	{
		if (isset($result->{$item}))
			$result = $result->{$item};
		else
		{
			return null;
		}
	}
	return $result;
}

/**
 * Prints given data and aborts execution.
 * @param $obj
 */
function printAndDie($obj)
{
	print_r($obj);
	die();
}

function checkStringArray($arr)
{
	$valid = false;

	if (is_array($arr))
	{
		$valid = true;
		foreach ($arr as $str)
		{
			if (!is_string($str))
			{
				$valid = false;
				break;
			}
		}
	}

	return $valid;
}
