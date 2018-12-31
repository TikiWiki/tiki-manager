<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;
use TikiManager\Report\Manager as ReportManager;
use TikiManager\Command\Helper\CommandHelper;

class ReportManagerCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('manager:report')
            ->setDescription('Manage reports')
            ->setHelp('This command allows you perform actions related to reports (add, modify, remove and send');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $output->writeln('<comment>Note: Only Tiki instances can enable reports.</comment>');

        $io->newLine();
        CommandHelper::renderReportOptions($output);
        $io->newLine();

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'What do you want to do:',
            ['add', 'modify', 'remove', 'send'],
            null
        );
        $question->setErrorMessage('Option %s is invalid.');
        $option = $helper->ask($input, $output, $question);

        switch ($option) {
            case 'add':
                $this->add($helper, $input, $io, $output);
                break;
            case 'modify':
                $this->modify($helper, $input, $io, $output);
                break;
            case 'remove':
                $this->remove($helper, $input, $io, $output);
                break;
            case 'send':
                $this->send();
                break;
        }
    }

    /**
     * Add a Report Receiver
     *
     * @param $helper
     * @param $input
     * @param $io
     * @param $output
     */
    private function add($helper, $input, $io, $output)
    {
        $report = new ReportManager;
        $instances = $report->getAvailableInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        $allInstances = CommandHelper::getInstances();
        $allInstancesInfo = CommandHelper::getInstancesInfo($allInstances);

        if (isset($instancesInfo) && isset($allInstancesInfo)) {
            $io->newLine();
            $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

            $question = CommandHelper::getQuestion('Which instances do you want to report on', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedInstances = $helper->ask($input, $output, $question);
            foreach ($selectedInstances as $instance) {
                $io->newLine();
                $renderResult = CommandHelper::renderInstancesTable($output, $allInstancesInfo);

                $question = CommandHelper::getQuestion('Which instances do you want to include in the report', null, '?');
                $question->setValidator(function ($answer) use ($allInstances) {
                    return CommandHelper::validateInstanceSelection($answer, $allInstances);
                });

                $toAdd = $helper->ask($input, $output, $question);

                $report->reportOn($instance);
                $report->setInstances($instance, $toAdd);
            }
        } else {
            $output->writeln('<error>No instances available to add a Report Receiver.</error>');
        }
    }

    /**
     * Modify a Report Receiver
     *
     * @param $helper
     * @param $input
     * @param $io
     * @param $output
     */
    private function modify($helper, $input, $io, $output)
    {
        $report = new ReportManager;
        $instances = $report->getReportInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $io->newLine();
            $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

            $question = CommandHelper::getQuestion('Which reports do you want to modify', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedInstances = $helper->ask($input, $output, $question);
            foreach ($selectedInstances as $instance) {
                $allReportCandidates = $report->getReportCandidates($instance);
                $allReportCandidatesInfo = CommandHelper::getInstancesInfo($allReportCandidates);
                if (isset($allReportCandidatesInfo)) {
                    $io->newLine();
                    $renderResult = CommandHelper::renderInstancesTable($output, $allReportCandidatesInfo);

                    $question = CommandHelper::getQuestion('Which instances do you want to include in the report', null, '?');
                    $question->setValidator(function ($answer) use ($allReportCandidates) {
                        return CommandHelper::validateInstanceSelection($answer, $allReportCandidates);
                    });

                    $toAdd = $helper->ask($input, $output, $question);

                    $full = array_merge($report->getReportContent($instance), $toAdd);
                    $report->setInstances($instance, $full);
                }
            }
        } else {
            $output->writeln('<error>No instances available to modfiy a Report Receiver.</error>');
        }
    }

    /**
     * Remove a Report Receiver
     *
     * @param $helper
     * @param $input
     * @param $io
     * @param $output
     */
    private function remove($helper, $input, $io, $output)
    {
        $report = new ReportManager;
        $instances = $report->getReportInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $io->newLine();
            $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

            $question = CommandHelper::getQuestion('Which reports do you want to modify', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedInstances = $helper->ask($input, $output, $question);
            foreach ($selectedInstances as $instance) {
                $allReportCandidates = $report->getReportCandidates($instance);
                $allReportCandidatesInfo = CommandHelper::getInstancesInfo($allReportCandidates);
                if (isset($allReportCandidatesInfo)) {
                    $io->newLine();
                    $renderResult = CommandHelper::renderInstancesTable($output, $allReportCandidatesInfo);

                    $question = CommandHelper::getQuestion('Which instances do you want to remove from the report', null, '?');
                    $question->setValidator(function ($answer) use ($allReportCandidates) {
                        return CommandHelper::validateInstanceSelection($answer, $allReportCandidates);
                    });

                    $toRemove = $helper->ask($input, $output, $question);

                    $report->removeInstances($instance, $toRemove);
                }
            }
        } else {
            $output->writeln('<error>No instances available to modfiy a Report Receiver.</error>');
        }
    }

    /**
     * Send update reports
     */
    private function send()
    {
        $report = new ReportManager;
        $report->sendReports();
    }
}
