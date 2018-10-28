<?php

namespace App\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\Question;

class TrimHelper
{
	/**
	 * Get information from Instance Object
	 *
	 * @param $instances array of Instance objects
	 * @return array|null
	 */
	public static function getInstancesInfo($instances)
	{
		$instancesInfo = null;

		if (! empty($instances)) {
			foreach ($instances as $key => $instance) {
				$instancesInfo[] = array(
					$instance->id,
					$instance->type,
					$instance->name,
					$instance->weburl,
					$instance->contact,
					$instance->branch
				);
			}
		}

		return $instancesInfo;
	}

	/**
	 * Render a table with all Instances
	 *
	 * @param $output
	 * @param $rows
	 * @return bool
	 */
	public static function renderInstancesTable($output, $rows)
	{
		if (empty($rows)) {
			return false;
		}

		$instanceTableHeaders = array(
			'ID',
			'Type',
			'Name',
			'Web URL',
			'Contact',
			'Branch'
		);

		$table = new Table($output);
		$table
			->setHeaders($instanceTableHeaders)
			->setRows($rows);
		$table->render();

		return true;
	}

	/**
	 * Render a table with Options and Actions from "check" functionality
	 *
	 * @param $output
	 */
	public static function renderCheckOptionsAndActions($output)
	{
		$headers = array(
			'Option',
			'Action'
		);

		$options = array(
			array(
				'current',
				'Use the files currently online for checksum'
			),
			array(
				'source',
				'Get checksums from repository (best option)'
			),
			array(
				'skip',
				'Do nothing'
			)
		);

		$table = new Table($output);
		$table
			->setHeaders($headers)
			->setRows($options);
		$table->render();
	}

	/**
	 * Render a table with Report options
	 *
	 * @param $output
	 */
	public static function renderReportOptions($output)
	{
		$headers = array(
			'Option',
			'Description'
		);

		$options = array(
			array(
				'add',
				'Add a report receiver'
			),
			array(
				'modify',
				'Modify a report receiver'
			),
			array(
				'remove',
				'Remove a report receiver'
			),
			array(
				'send',
				'Send updated reports'
			)
		);

		$table = new Table($output);
		$table
			->setHeaders($headers)
			->setRows($options);
		$table->render();
	}

	/**
	 * Wrapper for standard console question
	 *
	 * @param $question
	 * @param null $default
	 * @param string $character
	 * @return Question
	 */
	public static function getQuestion($question, $default = null, $character = ':') {

		if ($default !== null) {
			$question = sprintf($question . " [%s]: ", $default);
		} else {
			$question = $question . $character . ' ';
		}

		return new Question($question, $default);
	}

	/**
	 * Get Instances based on type
	 *
	 * @param string $type
	 * @param bool $excludeBlank
	 * @return array
	 */
	public static function getInstances($type = 'all', $excludeBlank = false)
	{
		$result = array();

		switch ($type) {
			case 'tiki':
				$result = \Instance::getTikiInstances();
				break;
			case 'no-tiki':
				$result = \Instance::getNoTikiInstances();
				break;
			case 'update':
				$result = \Instance::getUpdatableInstances();
				break;
			case 'restore':
				$result = \Instance::getRestorableInstances();
	        	break;
			case 'all':
				$result = \Instance::getInstances($excludeBlank);
		}

		return $result;
	}

	/**
	 * Validate Instances Selection
	 *
	 * @param $answer
	 * @param $instances
	 * @return array
	 */
	public static function validateInstanceSelection($answer, $instances)
	{
		if (empty($answer)) {
			throw new \RuntimeException(
				'You must select an #ID'
			);
		} else {
			$instancesId = array_filter(array_map('trim', explode(',', $answer)));
			$invalidInstancesId = array_diff($instancesId, array_keys($instances));

			if ($invalidInstancesId) {
				throw new \RuntimeException(
					'Invalid instance(s) ID(s) #' . implode(',', $invalidInstancesId)
				);
			}

			$selectedInstances = array();
			foreach ($instancesId as $index) {
				if (array_key_exists($index, $instances))
					$selectedInstances[] = $instances[$index];
			}

		}
		return $selectedInstances;
	}
}