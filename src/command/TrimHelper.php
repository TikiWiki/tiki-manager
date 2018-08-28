<?php

namespace App\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\Question;

class TrimHelper
{
	// getInstances
	// getTikiInstances
	// getUpdatableInstances
	// getRestorableInstances
	// getReportInstances

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
				$result = query(SQL_SELECT_ACCESS, array(':id' => $instance->id));
				$instanceType = $result->fetch()['type'];

				$instancesInfo[] = array(
					$instance->id,
					$instanceType,
					$instance->name,
					$instance->weburl,
					$instance->contact
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
			'Contact'
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
	 * Validate Instances Selection
	 *
	 * @param $answer
	 * @param string $type
	 * @return array
	 */
	public static function validateInstanceSelection($answer, $type = 'all')
	{
		if (empty($answer)) {
			throw new \RuntimeException(
				'You must select an #ID'
			);
		} else {
			$instances = $type == 'all' ? \Instance::getInstances() : \Instance::getTikiInstances();

			$instancesId = array_filter(array_map('trim', explode(',', $answer)));
			$invalidInstancesId = array_diff($instancesId, array_keys($instances));

			if ($invalidInstancesId) {
				throw new \RuntimeException(
					'Invalid instance(s) ID(s) #' . implode(',', $invalidInstancesId)
				);
			}
		}
		return $instancesId;
	}
}