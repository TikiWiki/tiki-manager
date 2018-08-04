<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AccessInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:access')
            ->setDescription('Remote access to instance')
            ->setHelp('This command allows you to remotely access an instance');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = \Instance::getInstances();
        $instancesInfo = TrimHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $io->newLine();

            $renderResult = TrimHelper::renderInstancesTable($output, $instancesInfo);

            $io->newLine();
            $output->writeln('<comment>In case you want to access more than one instance, please use a comma (,) between the values</comment>');

            $helper = $this->getHelper('question');
            $question = TrimHelper::getQuestion(('Which instance(s) do you want to access?'));
            $question->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException(
                        'You must select an #ID'
                    );
                } else {
                    $instances = \Instance::getInstances();

                    $instancesId = array_filter(array_map('trim', explode(',', $answer)));
					$invalidInstancesId = array_diff($instancesId, array_keys($instances));

                    if ($invalidInstancesId) {
                        throw new \RuntimeException(
                            'Invalid instance(s) ID(s) #' . implode(',', $invalidInstancesId)
                        );
                    }
                }
                return $answer;
            });
            $answer = $helper->ask($input, $output, $question);

            $instancesId = array_filter(array_map('trim', explode(',', $answer)));
            foreach ($instancesId as $id) {
                $output->writeln('<fg=cyan>Connecting to ' . $instances[$id]->name.' at ' . $instances[$id]->webroot . ' directory ... (use "exit" to move to next the instance)</>');
                $access = $instances[$id]->getBestAccess('scripting');
                $access->openShell($instances[$id]->webroot);
            }
        } else {
            $output->writeln('<comment>No instances available to access.</comment>');
        }
    }
}