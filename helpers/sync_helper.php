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
 * Prints given data and aborts execution.
 * @param $obj
 */
function printAndDie($obj)
{
	print_r($obj);
	die();
}
