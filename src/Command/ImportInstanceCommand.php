<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Access\Access;
use TikiManager\Application\Application;
use TikiManager\Application\Discovery;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Libs\Helpers\ApplicationHelper;

class ImportInstanceCommand extends Command
{
    private static $nonInteractive;

    protected function configure()
    {
        $this
            ->setName('instance:import')
            ->setDescription('Import instance')
            ->setHelp('This command allows you to import instances not yet managed by Tiki Manager')
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Instance connection type'
            )
            ->addOption(
                'host',
                'rh',
                InputOption::VALUE_REQUIRED,
                'Remote host name'
            )
            ->addOption(
                'port',
                'rp',
                InputOption::VALUE_REQUIRED,
                'Remote port number'
            )
            ->addOption(
                'user',
                'ru',
                InputOption::VALUE_REQUIRED,
                'Remote User'
            )
            ->addOption(
                'pass',
                'rrp',
                InputOption::VALUE_REQUIRED,
                'Remote password'
            )
            ->addOption(
                'url',
                'u',
                InputOption::VALUE_REQUIRED,
                'Instance web url'
            )
            ->addOption(
                'name',
                'na',
                InputOption::VALUE_REQUIRED,
                'Instance name'
            )
            ->addOption(
                'email',
                'e',
                InputOption::VALUE_REQUIRED,
                'Instance contact email'
            )
            ->addOption(
                'webroot',
                'wr',
                InputOption::VALUE_REQUIRED,
                'Instance web root'
            )
            ->addOption(
                'tempdir',
                'td',
                InputOption::VALUE_REQUIRED,
                'Instance temporary directory'
            );

        self::$nonInteractive = false;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \TikiManager\Application\Exception\ConfigException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $nonInteractive = $this->isNonInteractive($input, $output);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return 1;
        }

        if (!empty($nonInteractive)) {
            $instance = $nonInteractive['instance'];
            $discovery = $nonInteractive['discovery'];
            $access = $nonInteractive['access'];
            self::$nonInteractive = true;
        }

        if (!self::$nonInteractive) {
            $io->title('Import an instance');

            $output->writeln('<comment>Answer the following to import a new Tiki Manager instance.</comment>');

            $instance = new Instance();

            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion('Connection type:', CommandHelper::supportedInstanceTypes());
            $question->setErrorMessage('Connection type %s is invalid.');
            $instance->type = $type = $helper->ask($input, $output, $question);

            $access = Access::getClassFor($instance->type);
            $access = new $access($instance);
            $discovery = new Discovery($instance, $access);

            if ($type != 'local') {
                $question = CommandHelper::getQuestion('Host name');
                $access->host = $helper->ask($input, $output, $question);

                $question = CommandHelper::getQuestion('Port number', ($type == 'ssh') ? 22 : 21);
                $access->port = $helper->ask($input, $output, $question);

                $question = CommandHelper::getQuestion('User');
                $access->user = $helper->ask($input, $output, $question);

                $question = CommandHelper::getQuestion('Password');
                $question->setHidden(true);
                $question->setHiddenFallback(false);

                while ($type == 'ftp' && empty($access->password)) {
                    $access->password = $helper->ask($input, $output, $question);
                }
            } else {
                $access->host = 'localhost';
                $access->user = $discovery->detectUser();
            }

            $question = CommandHelper::getQuestion('Web URL', $discovery->detectWeburl());
            $instance->weburl = $helper->ask($input, $output, $question);

            $question = CommandHelper::getQuestion('Instance name', $discovery->detectName());
            $instance->name = $helper->ask($input, $output, $question);

            $question = CommandHelper::getQuestion('Contact email');
            $question->setValidator(function ($value) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Please insert a valid email address.');
                }
                return $value;
            });
            $instance->contact = $helper->ask($input, $output, $question);

            if (!$access->firstConnect()) {
                error('Failed to setup access');
            }

            $instance->save();
            $access->save();
            $output->writeln('<info>Instance information saved.</info>');
            $io->newLine();

            if ($output->getVerbosity() == OutputInterface::VERBOSITY_DEBUG || $_ENV['TRIM_DEBUG']) {
                $io->title('Tiki Manager Info');
                $mock_instance = new Instance();
                $mock_access = Access::getClassFor('local');
                $mock_access = new $mock_access($mock_instance);
                $mock_discovery = new Discovery($mock_instance, $mock_access);

                CommandHelper::displayInfo($mock_discovery, $io);
            }

            $folders = [
                'webroot' => [
                    'question' => 'Webroot directory',
                    'default' => $discovery->detectWebroot(),
                ],
                'tempdir' => [
                    'question' => 'Working directory',
                    'default' => $_ENV['TRIM_TEMP'],
                ]
            ];

            foreach ($folders as $key => $folder) {
                $question = CommandHelper::getQuestion($folder['question'], $folder['default']);
                $path = $helper->ask($input, $output, $question);
                $instance->$key = $path;
            }

            $phpVersion = $discovery->detectPHPVersion();
            $io->writeln('<info>Instance PHP Version: ' . CommandHelper::formatPhpVersion($phpVersion) . '</info>');

            list($backup_user, $backup_group, $backup_perm) = $discovery->detectBackupPerm();

            $instance->backup_user = trim($backup_user);
            $instance->backup_group = trim($backup_group);
            $instance->backup_perm = octdec($backup_perm);
        }

        $instance->vcs_type = $discovery->detectVcsType();
        $instance->phpexec = $discovery->detectPHP();
        $instance->phpversion = $discovery->detectPHPVersion();

        $instance->save();
        $access->save();

        $output->writeln('<info>Instance information saved.</info>');

        $countInstances = Instance::countNumInstances($instance);
        $isInstalled = false;

        foreach (Application::getApplications($instance) as $app) {
            if ($app->isInstalled()) {
                $isInstalled = true;
            }
        }

        if ($isInstalled) {
            if ($countInstances == 1) {
                $result = $app->registerCurrentInstallation();
                $resultInstance = $result->getInstance();

                if ($instance->id === $resultInstance->id) {
                    $io->success('Import completed, please test your site at ' . $instance->weburl);
                    return 0;
                }
            } else {
                $instance->delete();
                $io->error('Unable to import. An application was detected in this instance.');
                return 1;
            }
        } else {
            $instance->delete();
            $io->error('Unable to import. An application was not detected in this instance.');
            return 1;
        }
    }

    /**
     * Check non interactive instance creation mode
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array|bool|int
     * @throws \Exception
     */
    protected function isNonInteractive(InputInterface $input, OutputInterface $output)
    {
        $listInstanceTypes = CommandHelper::supportedInstanceTypes();
        $listInstanceTypeKeys = array_keys($listInstanceTypes);

        $type = $input->getOption('type');
        $weburl = $input->getOption('url');
        $name = $input->getOption('name');
        $contact = $input->getOption('email');
        $webroot = $input->getOption('webroot');
        $tempdir = $input->getOption('tempdir');

        if (!empty($type)
            && !empty($weburl)
            && !empty($name)
            && !empty($contact)
            && !empty($webroot)
            && !empty($tempdir)
        ) {
            if (!in_array($type, $listInstanceTypes) || !in_array($type, $listInstanceTypeKeys)) {
                throw new \InvalidArgumentException('Instance type invalid.');
            }

            if (filter_var($weburl, FILTER_VALIDATE_URL) === false) {
                throw new \InvalidArgumentException('Instance web url invalid.');
            }

            if (filter_var($contact, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException('Please insert a valid email address.');
            }

            if ($type != 'local') {
                $rhost = $input->getOption('host');
                $rport = $input->getOption('port');
                $ruser = $input->getOption('user');
                $rpass = $input->getOption('pass');

                if (empty($rhost) || !is_numeric($rport) || empty($ruser) || empty($rpass)) {
                    throw new \InvalidArgumentException('Remote server credentials are missing.');
                }
            }

            $instance = new Instance();

            $type = is_numeric($type) ? $listInstanceTypes[$type] : $type;
            $instance->type = $type;
            $instance->weburl = $weburl;
            $instance->name = $name;
            $instance->contact = $contact;
            $instance->webroot = $webroot;
            $instance->tempdir = $tempdir;

            $access = Access::getClassFor($type);
            $access = new $access($instance);
            $discovery = new Discovery($instance, $access);

            if ($type != 'local') {
                $access->host = $rhost;
                $access->port = $rport;
                $access->user = $ruser;
                $access->password = $rpass;
            }

            if (!$access->firstConnect()) {
                error('Failed to setup access');
                exit(1);
            }

            $instance->save();
            $access->save();

            $instance->phpexec = $discovery->detectPHP();
            $instance->phpversion = $discovery->detectPHPVersion();

            return [
                'instance' => $instance,
                'discovery' => $discovery,
                'access' => $access
            ];
        }

        return 0;
    }
}
