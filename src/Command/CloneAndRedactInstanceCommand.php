<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use TikiManager\Application\Instance;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;

class CloneAndRedactInstanceCommand extends TikiManagerCommand
{
    protected function configure()
    {
        $this
            ->setName('instance:cloneandredact')
            ->setDescription('Make a clone of an instance and redact it')
            ->setHelp('This command allows you to clone an instance and redact the clone')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to be redacted, separated by comma (,)'
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (empty($input->getOption('instances'))) {
            $instances = CommandHelper::getInstances('upgrade');
            $instancesInfo = CommandHelper::getInstancesInfo($instances);

            if (empty($instancesInfo)) {
                // execute will output message
                return;
            }
            $this->io->note('To prevent data loss, Redact operations will create a clone of your instance and then redact it. Your instance will not be modified');
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $answer = $this->io->ask('Which instance do you want to redact', null, function ($answer) use ($instances) {
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                return implode(',', array_map(function ($elem) {
                    return $elem->getId();
                }, $selectedInstances));
            });
            $input->setOption('instances', $answer);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('upgrade');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        $tiki_namespace = true;

        if (empty($instancesInfo)) {
            $output->writeln('<comment>No Tiki instances available.</comment>');
            return;
        }
        $instancesOption = $input->getOption('instances');

        CommandHelper::validateInstanceSelection($instancesOption, $instances);
        $instancesOption = explode(',', $instancesOption);
        $selectedInstances = [];
        $instancesOption = explode(',', $instancesOption);
        $selectedInstances = [];
        foreach ($instancesOption as $key) { // keeping the same order as in $instancesOption
            if (array_key_exists($key, $instances)) {
                $selectedInstances[$key] = $instances[$key];
            }
        }
        foreach ($selectedInstances as $instance) {
            // first create a blank instance for the clone
            $output->writeln('Create a blank instance for the clone ...');
            $weburl = preg_replace('/\/$/', '', $instance->getWebUrl(''));
            $array = explode("/", $weburl);

            $name = $instance->name;
            $blankInstanceName = "redacted." . $name; // Ex.: example.org -> redacted.example.org
            $blankInstanceWeburl = preg_replace('/' . end($array) . '$/', '', $weburl) . $blankInstanceName;

            $webroot = preg_replace('/\/$/', '', $instance->webroot);
            $blankInstWebroot = preg_replace('/' . end($array) . '$/', '', $webroot) . $blankInstanceName;

            // check if we are on tiki namespace
            $command_name = 'manager:instance:create';
            if (! $this->getApplication()->has($command_name)) {
                $command_name = 'instance:create';
                $tiki_namespace = false;
            }

            $command = $this->getApplication()->find($command_name);
            $arguments = [
                '--blank' => true,
                '--type' => $instance->type,
                '--url' => $blankInstanceWeburl,
                '--name' => $blankInstanceName,
                '--email' => $instance->contact,
                '--webroot' => $blankInstWebroot,
                '--tempdir' => "/tmp/trim_temp." . $blankInstanceName,
                '--backup-user' => $instance->backup_user ? $instance->backup_user : 'www-data',
                '--backup-group' => $instance->backup_group ? $instance->backup_group : 'www-data',
                '--backup-permission' => $instance->backup_perm ? $instance->backup_perm : '750',
            ];
            $blankInstanceInput = new ArrayInput($arguments);
            $blankInstanceInput->setInteractive(false);
            $command->run($blankInstanceInput, $output);

            // then clone the source instance
            $output->writeln('Clone the source instance on the blank instance just created ...');
            $cloneInstance = Instance::getInstanceByName($blankInstanceName);

            $command_name = $tiki_namespace ? 'manager:instance:clone' : 'instance:clone';

            $command = $this->getApplication()->find($command_name);

            $arguments = [
                '--source' => $instance->getId(),
                '--target' => [$cloneInstance->getId()],
                '--db-prefix' => $blankInstanceName,
            ];
            $verifyInstanceInput = new ArrayInput($arguments);
            $verifyInstanceInput->setInteractive(true);

            $command->run($verifyInstanceInput, $output);

            // manual set up multitiki instance for redact
            $output->writeln('Setup the clone instance for the redact ...');
            $access = $cloneInstance->getBestAccess();

            $output->writeln('Create subdirectories ...');
            $dirs = [
                "db/redact",
                "dump/redact",
                "img/wiki/redact",
                "img/wiki_up/redact",
                "img/trackers/redact",
                "modules/cache/redact",
                "storage/redact",
                "storage/public/redact",
                "temp/redact",
                "temp/cache/redact",
                "temp/public/redact",
                "temp/templates_c/redact",
                "templates/redact",
                "themes/redact",
                "maps/redact",
                "whelp/redact",
                "mods/redact",
                "files/redact",
                "tiki_tests/tests/redact",
                "temp/unified-index/redact",
            ];
            foreach ($dirs as $dir) {
                $output->writeln($dir . ' ...');
                $access->shellExec("mkdir -p" . $dir);
                $output->write('ok.');
            }
            $output->writeln('Copy local.php ...');
            $access->shellExec("cp -r db/local.php db/redact/local.php");
            $output->write('ok.');

            // redact the clone
            $output->writeln('Redact the clone ...');
            $command_name = $tiki_namespace ? 'manager:instance:console' : 'instance:console';
            $command = $this->getApplication()->find($command_name);

            $arguments = [
                'command' => $command_name,
                '--instances' => $cloneInstance->getId(),
                '--command' => "database:redact --site='redact'",
            ];
            $redactInput = new ArrayInput($arguments);
            return $command->run($redactInput, $output);
        }

        return 0;
    }
}
