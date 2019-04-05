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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Access\Access;
use TikiManager\Access\ShellPrompt;
use TikiManager\Application\Application;
use TikiManager\Application\Discovery;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Libs\Helpers\ApplicationHelper;

class CreateInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:create')
            ->setDescription('Creates a new instance')
            ->setHelp('This command allows you to create a new instance')
            ->addArgument('blank', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Create a new instance');

        $blank = $input->getArgument('blank') == 'blank' ? true : false;

        $output->writeln('<comment>Answer the following to add a new Tiki Manager instance.</comment>');

        $instance = new Instance();

        $instanceTypes = ApplicationHelper::isWindows() ? 'local' : Instance::TYPES;

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion('Connection type:', explode(',', $instanceTypes));
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
            if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
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

        $output->writeln('<info>Running on ' . $discovery->detectDistro() . '</info>');
        $output->writeln('<info>PHP exec: ' . $discovery->detectPHP() . '</info>');

        $folders = [
            'webroot' => [
                'question' => 'Webroot directory',
                'default' => $discovery->detectWebroot(),
            ],
            'tempdir' => [
                'question' => 'Working directory',
                'default' => TRIM_TEMP,
            ]
        ];

        $errors = [];

        foreach ($folders as $key => $folder) {
            $question = CommandHelper::getQuestion($folder['question'], $folder['default']);
            $path = $helper->ask($input, $output, $question);

            if ($access instanceof ShellPrompt) {
                $phpPath = $access->getInterpreterPath();

                $script = sprintf("echo is_dir('%s');", $path);
                $command = $access->createCommand($phpPath, ["-r {$script}"]);
                $command->run();

                if (empty($command->getStdoutContent())) {
                    $output->writeln('Directory [' . $path . '] does not exist.');

                    $helper = $this->getHelper('question');
                    $question = new ConfirmationQuestion('Create directory? [y]: ', true);

                    if (!$helper->ask($input, $output, $question)) {
                        $output->writeln('<error>Directory ['.$path.'] not created.</error>');
                        $errors[$key] = $path;
                        continue;
                    }

                    $script = sprintf("echo mkdir('%s', 0777, true);", $path);
                    $command = $access->createCommand($phpPath, ["-r {$script}"]);
                    $command->run();

                    if (empty($command->getStdoutContent())) {
                        $output->writeln('<error>Unable to create directory ['.$path.']</error>');
                        $errors[] = $path;
                        continue;
                    }
                }
            } else {
                $output->writeln('<error>Shell access is required to create '.strtolower($folder['question']).'. You will need to create it manually.</error>');
                $errors[] = $path;
                continue;
            }

            $instance->$key = $path;
        }

        list($backup_user, $backup_group, $backup_perm) = $discovery->detectBackupPerm();

        $question = CommandHelper::getQuestion('Backup owner', $backup_user);
        $backup_user = $helper->ask($input, $output, $question);

        $question = CommandHelper::getQuestion('Backup group', $backup_group);
        $backup_group = $helper->ask($input, $output, $question);

        $question = CommandHelper::getQuestion('Backup file permissions', decoct($backup_perm));
        $backup_perm = $helper->ask($input, $output, $question);

        $instance->backup_user = trim($backup_user);
        $instance->backup_group = trim($backup_group);
        $instance->backup_perm = octdec($backup_perm);

        $instance->phpexec = $discovery->detectPHP();
        $instance->phpversion = $discovery->detectPHPVersion();

        $instance->save();
        $output->writeln('<info>Instance information saved.</info>');

        if (array_key_exists('webroot', $errors) && $instance->type !== 'ftp') {
            $output->writeln('<error>Webroot directory is missing in filesystem. You need to create it manually.</error>');
            $output->writeln('<fg=blue>Instance configured as blank (empty).</>');
            return 0;
        }

        $countInstances = Instance::countNumInstances($access->host, $instance->webroot);
        $isInstalled = false;

        foreach (Application::getApplications($instance) as $app) {
            if ($app->isInstalled()) {
                $isInstalled = true;
            }
        }

        if ($isInstalled) {
            if ($countInstances == 1) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion('An application was detected in [' . $instance->webroot . '], do you want add it to the list? [y]: ', true);

                if (! $helper->ask($input, $output, $question)) {
                    $instance->delete();
                    return 1;
                }
                $result = $app->registerCurrentInstallation();
                $resultInstance = $result->getInstance();

                if ($instance->id === $resultInstance->id) {
                    $io->success('Please test your site at ' . $instance->weburl);
                    return 0;
                }
            } else {
                $instance->delete();
                $io->error('Unable to install. An application was detected in this instance.');
                return 1;
            }
        }

        if ($blank) {
            $output->writeln('<fg=blue>This is a blank (empty) instance. This is useful to restore a backup later.</>');
            return 0;
        }

        $result = CommandHelper::performInstall($instance, $input, $output);

        if ($result === false) {
            return 1;
        }

        return 0;
    }
}
