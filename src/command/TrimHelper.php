<?php

namespace App\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
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
	 * @param string $question
	 * @param string $default
	 * @return Question
	 */
	public static function getQuestion($question, $default = null) {

		if ($default !== null) {
			$question = sprintf($question . " [%s]: ", $default);
		} else {
			$question = $question . ': ';
		}

		return new Question($question, $default);
	}
}