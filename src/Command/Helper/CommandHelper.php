<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command\Helper;

use Cron\CronExpression;
use Gitonomy\Git\Repository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Application\Discovery;
use TikiManager\Application\Exception\VcsException;
use TikiManager\Application\Tiki;
use TikiManager\Application\Tiki\Versions\Fetcher\YamlFetcher;
use TikiManager\Application\Tiki\Versions\TikiRequirementsHelper;
use TikiManager\Application\Instance;
use TikiManager\Command\Exception\InvalidCronTimeException;
use TikiManager\Config\App;
use TikiManager\Libs\Helpers\ApplicationHelper;
use TikiManager\Hooks\TikiCommandHook;
use TikiManager\Command\Helper\InstanceValidator;

class CommandHelper
{
    const INSTANCE_SELECTION_SINGLE = 0x1;
    const INSTANCE_SELECTION_ALLOW_EMPTY = 0x2;
    const INSTANCE_SELECTION_IGNORE_INVALID = 0x4;

    /**
     * Get information from Instance Object
     *
     * @param array $instances An array of Instance objects
     * @param bool $all_infos set to true to get all infos about the instance
     * @return array|null
     */
    public static function getInstancesInfo(array $instances, bool $all_infos = false): ?array
    {
        $instancesInfo = null;

        foreach ($instances as $instance) {
            $extra = [];
            $instance_initial_infos = [
                'id' => $instance->id,
                'type' => $instance->type,
                'name' => $instance->name,
                'url' => $instance->weburl,
                'email' => $instance->contact,
                'php_version' => self::formatPhpVersion($instance->phpversion),
                'branch' => $instance->branch,
                'revision' => $instance->revision,
                'last_action' => $instance->last_action,
                'state' => $instance->state,
                'last_action_date' => $instance->last_action_date,
                'last_revision_date' => $instance->last_revision_date
            ];
            if ($all_infos) {
                $requirements = (new \TikiManager\Application\Tiki\Versions\Fetcher\YamlFetcher)->getParsedRequirements();
                $filter = array_filter($requirements, function ($result) use ($instance) {
                    $branch = str_replace(['tags/','.x'], '', $instance->branch);
                    $pattern = '/' . preg_quote((string)$result['version'], '/') . '/';
                    if (! empty($instance->branch) && preg_match($pattern, $branch)) {
                        return $result;
                    }
                });
                $default_php_version = "7.4";
                $access = $instance->getBestAccess();
                $output = $access->shellExec("$instance->phpexec -v");
                if ($output) {
                    preg_match('/PHP (\d+\.\d+\.\d+)/', $output, $matches);
                    $default_php_version = $matches[1];
                }
                $filter = array_values($filter);
                $extra = [
                    'webroot' => $instance->webroot,
                    'tempdir' => $instance->tempdir,
                    'phpexec' => $instance->phpexec,
                    'php_min' => $filter[0]['php']['min'] ?? $default_php_version,
                    'php_max' => $filter[0]['php']['max'] ?? $default_php_version,
                    'user' => $access ? $access->user : null,
                    'backup_user' => $instance->getProp('backup_user'),
                    'backup_group' => $instance->getProp('backup_group'),
                    'backup_perm' => DecOct($instance->getProp('backup_perm')),
                ];
            }
            $instancesInfo[] = array_merge($instance_initial_infos, $extra);
        }

        return $instancesInfo;
    }

    /**
     * Render a table with tag(s) of an instance
     *
     * @param $output
     * @param $rows
     * @return bool
     */
    public static function renderInstanceTags($output, $rows, $multiple = false)
    {
        $table = new Table($output);
        $header = ['Tag Name', 'Tag Value'];

        if ($multiple) {
            array_unshift($header, 'No.');
        }

        $table
            ->setHeaders($header)
            ->setRows($rows);
        $table->render();

        return true;
    }

    /**
     * Render a table with all Instances
     *
     * @param $output
     * @param $rows
     * @param bool $all_infos set to true to get all infos about the instance
     * @return bool
     */
    public static function renderInstancesTable($output, $rows, bool $all_infos = false)
    {
        if (empty($rows)) {
            return false;
        }

        $instanceTableHeaders = [
            'ID',
            'Type',
            'Name',
            'Web URL',
            'Contact',
            'PHP Version',
            'Branch',
            'Revision',
            'Last Action',
            'Result',
            'Action Date',
            'Revision Date'
        ];

        if ($all_infos) {
            $instanceTableHeaders[]='Web ROOT';
            $instanceTableHeaders[]='Temp Dir';
            $instanceTableHeaders[]='PHPExec';
            $instanceTableHeaders[]='PHP Min';
            $instanceTableHeaders[]='PHP Max';
            $instanceTableHeaders[]='User';
            $instanceTableHeaders[]='Backup user';
            $instanceTableHeaders[]='Backup group';
            $instanceTableHeaders[]='Backup permission';
        }

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
        $headers = [
            'Option',
            'Action'
        ];

        $options = [
            [
                'current',
                'Use the files currently online for checksum'
            ],
            [
                'source',
                'Get new checksums from repository (best option)'
            ],
            [
                'skip',
                'Do nothing'
            ]
        ];

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
        $headers = [
            'Option',
            'Description'
        ];

        $options = [
            [
                'add',
                'Add a report receiver'
            ],
            [
                'modify',
                'Modify a report receiver'
            ],
            [
                'remove',
                'Remove a report receiver'
            ],
            [
                'send',
                'Send updated reports'
            ]
        ];

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
    public static function getQuestion($question, $default = null, $character = ':')
    {
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
        $result = [];

        switch ($type) {
            case 'tiki':
                $result = Instance::getTikiInstances();
                break;
            case 'no-tiki':
                $result = Instance::getNoTikiInstances();
                break;
            case 'update':
                $result = Instance::getUpdatableInstances();
                break;
            case 'upgrade':
                $result = Instance::getUpgradableInstances();
                break;
            case 'restore':
                $result = Instance::getRestorableInstances();
                break;
            case 'all':
                $result = Instance::getInstances($excludeBlank);
        }

        return $result;
    }

    /**
     * Validate Instances Selection.
     * Instances selection allows selecting instances per ID or per Name or with the "all" keyword.
     *
     * @param $answer
     * @param $allInstances
     * @param $flags a combinaison of flags concerning the validation.
     * @return array selected Instances. The array order is the same as in $anwser (or $allInstances when you use "all"). The index ARE NOT the instance IDs.
     */
    public static function validateInstanceSelection($answer, $allInstances, $flags = 0x0)
    {
        $selectedInstances = [];
        if (empty($answer)) {
            if ($flags & CommandHelper::INSTANCE_SELECTION_ALLOW_EMPTY) {
                return $selectedInstances;
            }
            throw new \RuntimeException('You must select an instance');
        } elseif (strtolower($answer) == "all") {
            $selectedInstances = $allInstances;
        } else {
            $instances = [];
            foreach ($allInstances as $instance) {
                $instances[ $instance->getId() ] = $instance;
                $instances[ $instance->name ] = $instance;
            }

            $answerInstances = array_filter(array_map('trim', explode(',', $answer)));
            $invalidIdentifiers = [];

            foreach ($answerInstances as $answerInstance) {
                if (! isset($instances[$answerInstance])) {
                    $invalidIdentifiers[] = $answerInstance;
                    continue;
                }

                $selectedInstances[] = $instances[$answerInstance];
            }

            if (! ($flags & CommandHelper::INSTANCE_SELECTION_IGNORE_INVALID) && count($invalidIdentifiers) > 0) {
                throw new \RuntimeException(
                    'Invalid instance(s) name(s) or ID(s): ' . implode(',', $invalidIdentifiers)
                );
            }
        }

        if (! ($flags & CommandHelper::INSTANCE_SELECTION_ALLOW_EMPTY) && count($selectedInstances) === 0) {
            throw new \RuntimeException('You must select at least one instance');
        }
        if (($flags & CommandHelper::INSTANCE_SELECTION_SINGLE) && count($selectedInstances) > 1) {
            throw new \RuntimeException('You can only select one instance');
        }

        return $selectedInstances;
    }

    /**
     * Validator for empty inputs
     * @param $answer
     * @param $exceptionMessage
     * @return mixed
     */
    public static function validateEmptyInput($answer, $exceptionMessage)
    {
        if (empty($answer)) {
            throw new \RuntimeException(
                $exceptionMessage
            );
        }

        return $answer;
    }

    /**
     * Validator to check for email format
     * @param $answer
     * @return mixed
     */
    public static function validateEmail($answer)
    {
        if (!filter_var($answer, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException(
                'You must insert a valid email'
            );
        }

        return $answer;
    }

    /**
     * Validator to check for an integer input
     * @param $answer
     * @param $exceptionMessage
     * @return mixed
     */
    public static function validateInteger($answer, $exceptionMessage)
    {
        if (!is_int($answer)) {
            throw new \RuntimeException(
                $exceptionMessage
            );
        }

        return $answer;
    }

    /**
     * Validate time input in the format "<hours>:<minutes>"
     *
     * @param $answer
     * @return array
     */
    public static function validateTimeInput($answer)
    {
        if (empty($answer)) {
            throw new \RuntimeException(
                'You must provide a valid time'
            );
        }

        if (!preg_match('/\d{1,2}:\d{1,2}/', $answer)) {
            throw new \RuntimeException(
                'Invalid time format. Please provide a value in the format <hours>:<minutes>'
            );
        }

        list($hour, $minutes) = explode(':', $answer);

        if (!in_array($hour, range(0, 23))) {
            throw new \RuntimeException(
                'Invalid hour.'
            );
        }

        if (!in_array($minutes, range(0, 59))) {
            throw new \RuntimeException(
                'Invalid minutes.'
            );
        }

        return [$hour, $minutes];
    }

    /**
     * Retrieve instance IDs given an array with multiple instances
     *
     * @param $instances
     * @return array
     */
    public static function getInstanceIds($instances)
    {
        $payload = [];

        foreach ($instances as $instance) {
            $payload[] = is_object($instance)
                ? ( method_exists($instance, 'getId') ? $instance->getId() : $instance->id )
                : $instance['id'];
        }

        return $payload;
    }

    /**
     * Remove folder contents
     *
     * @param array|string $dirs
     * @param LoggerInterface $logger
     * @return bool
     */
    public static function clearFolderContents($dirs, LoggerInterface $logger)
    {
        if (!is_array($dirs)) {
            $dirs = [$dirs];
        }

        try {
            $fileSystem = new Filesystem();
            foreach ($dirs as $dir) {
                if (!$fileSystem->exists($dir)) {
                    continue;
                }

                $iterator = new \FilesystemIterator($dir);
                foreach ($iterator as $file) {
                    $fileSystem->remove($file->getPathName());
                }
            }
        } catch (IOException $e) {
            $message = sprintf("An error occurred while removing folder contents:\n%s", $e->getMessage());
            $logger->error($message);
            return false;
        }

        return true;
    }

    /**
     * Remove one or more files from filesystem
     *
     * @param string|array $files A string or array with files full path to remove
     * @param LoggerInterface $logger
     * @return bool
     */
    public static function removeFiles($files, LoggerInterface $logger)
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        try {
            $fileSystem = new Filesystem();
            foreach ($files as $file) {
                if (!$fileSystem->exists($file)) {
                    continue;
                }

                $fileSystem->remove($file);
            }
        } catch (IOException $e) {
            $message = sprintf("An error occurred while removing file:\n%s", $e->getMessage());
            $logger->error($message);
            return false;
        }

        return true;
    }

    /**
     * Get VCS Versions (GIT)
     *
     * @param string $vcsType
     * @return array
     */
    public static function getVersions($vcsType = '')
    {
        $instance = new Instance();
        $instance->type = 'local';
        if (!empty($vcsType)) {
            $instance->vcs_type = $vcsType;
        }
        $instance->phpversion = 50500;
        $tikiApplication = new Tiki($instance);

        return $tikiApplication->getVersions();
    }

    /**
     * Get information from Version Object
     *
     * @param $versions
     * @return array|null
     */
    public static function getVersionsInfo($versions)
    {
        $versionsInfo = null;

        if (!empty($versions)) {
            foreach ($versions as $version) {
                $versionsInfo[] = [
                    $version->type,
                    $version->branch
                ];
            }
        }

        return $versionsInfo;
    }

    /**
     * Render a table with Versions information
     *
     * @param $output
     * @param $rows
     * @param string $viewFormat
     * @param string|null $phpVersionStr
     * @param bool $showSupportColumn
     */
    public static function renderVersionsByFormat(
        $output,
        $rows,
        $viewFormat = 'table',
        $phpVersionStr = null,
        $showSupportColumn = false
    ) {
        if (empty($rows)) {
            return;
        }

        if ($viewFormat === 'simple') {
            foreach ($rows as $row) {
                $detectedEncoding = mb_detect_encoding($row[1], "UTF-8, ISO-8859-1, ASCII", true);
                $encodingToUse = $detectedEncoding ?: 'ISO-8859-1';
                $encodedValue = mb_convert_encoding($row[1], 'UTF-8', $encodingToUse);
                $output->writeln('<info>' . $encodedValue . '</info>');
            }
        } else {
            if ($showSupportColumn && !empty($phpVersionStr)) {
                $output->writeln("List of all versions, available for install, they may not be installable depending on the target instance PHP version. Tagged below the ones that would work for PHP {$phpVersionStr}\n");
            }

            $headers = ['Type', 'Name'];
            if ($showSupportColumn && !empty($phpVersionStr)) {
                $headers[] = "Supported in PHP {$phpVersionStr}";
            }

            $formattedRows = [];

            foreach ($rows as $row) {
                if ($showSupportColumn && !empty($phpVersionStr)) {
                    $supported = self::isTikiVersionSupportedInPhp($row[1], $phpVersionStr) ? 'Y' : 'N';
                    $row[] = $supported;
                }
                $formattedRows[] = $row;
            }

            $table = new Table($output);
            $table->setHeaders($headers)->setRows($formattedRows);
            $table->render();
        }
    }

    /**
     * Check if a Tiki version is supported in a given PHP version
     *
     * @param string $versionName
     * @param string $phpVersionStr (e.g 8.1.32)
     * @return bool
     */
    public static function isTikiVersionSupportedInPhp(string $versionName, string $phpVersionStr): bool
    {
        $helper = new TikiRequirementsHelper(new YamlFetcher());
        $requirements = $helper->findByBranchName($versionName);
        $phpVersionId = self::phpVersionStringToId($phpVersionStr);

        if (($phpVersionId >= 50300 && !$requirements) || ($requirements && $requirements->getPhpVersion()->isValidVersion($phpVersionStr))) {
            return true;
        }

        return false;
    }

    /**
     * Convert PHP version string (e.g. 8.1.32) to ID (e.g. 80132)
     *
     * @param string $phpVersion
     * @return int
     * @throws \RuntimeException
     */
    public static function phpVersionStringToId(string $phpVersion): int
    {
        $parts = explode('.', $phpVersion);
        if (count($parts) < 2) {
            throw new \RuntimeException('Invalid PHP version format. Expected format: x.y.z or x.y');
        }
        $major = (int) ($parts[0] ?? 0);
        $minor = (int) ($parts[1] ?? 0);
        $patch = (int) ($parts[2] ?? 0);

        return $major * 10000 + $minor * 100 + $patch;
    }

    /**
     * Display Info
     * @param Discovery $discovery
     */
    public static function displayInfo(Discovery $discovery)
    {
        $io = App::get('io');
        $io->writeln('<info>Running on ' . $discovery->detectDistro() . '</info>');
        $io->writeln('<info>PHP Version: ' . phpversion() . '</info>');
        $io->writeln('<info>PHP exec: ' . PHP_BINARY . '</info>');

        try {
            $repository = new Repository($_ENV['TRIM_ROOT']);

            $io->writeln('<info>Branch: ' . $repository->getHead()->getRevision() . '</info>');
            $io->writeln('<info>Commit Hash: ' . $repository->getHead()->getCommit()->getShortHash() . '</info>');
            $io->writeln('<info>Commit Date: ' . $repository->getHead()->getCommit()->getCommitterDate()->format('Y-m-d H:i:s') . '</info>');
        } catch (\Exception $e) {
            $io->writeln('<error>It was not possible to git related information.</error>');
        }
    }

    /**
     * Format PHP version to display
     *
     * @param $phpVersion
     * @return string
     */
    public static function formatPhpVersion($phpVersion, $format = '%d.%d.%d')
    {
        if (preg_match('/(\d+)(\d{2})(\d{2})$/', $phpVersion, $matches)) {
            $phpVersion = sprintf($format, $matches[1], $matches[2], $matches[3]);
        }

        return $phpVersion;
    }

    public static function validateEmailInput($value)
    {
        if (!empty($value)) {
            $emailList = explode(',', $value);
            array_walk($emailList, function ($emailAddr) {
                if (!filter_var($emailAddr, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException(sprintf("Email address '%s' is not valid!", $emailAddr));
                }
            });
        }

        return $value;
    }

    /**
     * Build error message to fix instance if setup fails
     *
     * @param $instanceId
     * @param \Exception|null $e
     * @param string $action
     * @throws \Exception
     */
    public static function setInstanceSetupError($instanceId, \Exception $e = null, $action = 'install')
    {
        $errors = [];
        $io = App::get('io');

        if ($e instanceof VcsException) {
            $errors[] = 'Tiki Manager detected a problem with your instance´s VCS.';
            $errors[] = $e->getMessage();
            $errors[] = 'You can also use the "stash" option to save your local modifications, and try to apply them after update/upgrade.';
            $errors[] = 'Below is an example of how to update an instance using the "stash" option to avoid vcs conflicts:';
            $errors[] = '- If you are using Tiki Manager in standalone (CLI) or via Virtualmin, access your CLI and go to the Tiki Manager root folder and run the update command with the stash option as following:';
            $errors[] = 'php tiki-manager.php instance:update --stash';
            $errors[] = '- If the command is executed via cron job or Tiki scheduler, edit your job and add "--stash" to the console command.';
            $errors[] = '- If you are using Tiki Manager via the web interface implemented in Tiki, make sure the stash select option is set to "Yes/true"';
        } elseif (! empty($instanceId) && is_numeric($instanceId)) {
            $errors[] = 'Failed to install instance. Please follow these steps to continue the process manually.';
            $errors[] = '- php tiki-manager.php instance:access --instances=' . $instanceId;
            $errors[] = '- bash setup.sh -n fix';

            switch ($action) {
                case 'update':
                case 'upgrade':
                    $errors[] = '- php -q -d memory_limit=256M console.php database:update';
                    break;
                default:
                    $errors[] = '- php -q -d memory_limit=256M console.php database:install';
                    break;
            }

            if ($e && $_ENV['TRIM_DEBUG']) {
                $errors[] = $e->getMessage();
            }
        }
        $io->error($errors);

        if ($e) {
            static::logException($e, $instanceId);
        }
    }

    /**
     * Get instance types
     *
     * @return array
     */
    public static function supportedInstanceTypes()
    {
        $instanceTypes = ApplicationHelper::isWindows() ? 'local' : Instance::TYPES;
        return explode(',', $instanceTypes);
    }

    /**
     * @param \Exception $e
     * @param $instance
     */
    public static function logException($e, $instance)
    {
        if (!$instance instanceof Instance) {
            $instance = Instance::getInstance($instance);
        }

        $log = [];
        $log[] = sprintf('## Error in %s (id: %s)', $instance->name, $instance->id);
        $log[] = $e->getMessage();
        $log[] = $e->getTraceAsString();

        trim_output(implode(PHP_EOL, $log));
    }

    /**
     * Validate CronTab input value
     *
     * @param $answer
     * @return string
     * @throws InvalidCronTimeException
     */
    public static function validateCrontabInput($answer): string
    {
        if (!CronExpression::isValidExpression($answer)) {
            throw new InvalidCronTimeException();
        }

        return $answer;
    }

    /**
     * Reads the .version file and returns JSON data.
     */
    public static function getVersionFileData($path, $onlyComments = false)
    {
        $versionFile = ! empty($path) ? $path : $_ENV['TRIM_ROOT'] . '/.version';

        if (!file_exists($versionFile)) {
            return false;
        }

        // Read the .version file and ignore empty lines
        $versionContent = file($versionFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (empty($versionContent)) {
            return false;
        }

        if ($onlyComments) {
            // Get the comments only
            return array_filter($versionContent, function ($line) {
                return strpos($line, '#') === 0;
            });
        }

        // Get the JSON part
        $filteredJsonContent = array_filter($versionContent, function ($line) {
            return strpos($line, '#') !== 0;
        });

        if (!empty($filteredJsonContent)) {
            $content = implode("\n", $filteredJsonContent);
            return json_decode($content, true);
        }

        return false;
    }

    /**
     * Check Tiki-Manager instance was installed using operation system packages
     *
     * @return bool
     */
    public static function isInstalledFromPackage()
    {
        $versionFile = $_ENV['TRIM_ROOT'] . '/.version';

        if (!file_exists($versionFile)) {
            return false;
        }

        $versionData = self::getVersionFileData($versionFile);

        if (is_array($versionData) && !empty($versionData) && !empty($versionData['version'])) {
            return true;
        }

        return false;
    }

    public static function renderBackupIgnoreListTable($output, $rows)
    {
        $table = new Table($output);
        $header = ['ID', 'Exclude Path'];

        $table
            ->setHeaders($header)
            ->setRows($rows);
        $table->render();

        return true;
    }

    /**
     * Validate instances if the "validate" option is enabled.
     * @param array $instances Array of instances to validate.
     * @param TikiCommandHook $hook The command hook.
     * @return void
     */
    public static function validateInstances(array $instances, TikiCommandHook $hook): void
    {
        $httpClient = HttpClient::create();
        $logger = App::get('Logger');
        $io = App::get('io');
        $instanceValidator = new InstanceValidator($httpClient, $logger);

        foreach ($instances as $instance) {
            $io->newLine();
            $io->section('Validating ' . $instance->name);
            $isValid = $instanceValidator->validate($instance);
            if ($isValid) {
                $io->writeln('<info>Instance validated successfully.</info>');
                return;
            }
            $invalidInstanceErrorMessage = 'Instance validation failed for ' . $instance->name;
            $logger->error($invalidInstanceErrorMessage);
            $hook->registerFailHookVars([
                'error_message' => $invalidInstanceErrorMessage,
                'error_code' => 'FAIL_OPERATION_VALIDATION_INSTANCE',
                'instance' => $instance,
            ]);
        }
    }
}
