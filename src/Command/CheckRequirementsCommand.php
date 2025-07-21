<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Config\App;
use TikiManager\Libs\Requirements\Requirements;

class CheckRequirementsCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('manager:check')
            ->setDescription('Check Tiki Manager requirements')
            ->setHelp('This command allows you to check if Tiki Manager requirements for server/client are met');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('Requirements');

        $osReq = Requirements::getInstance();
        $requirements = $osReq->getRequirements();
        foreach ($requirements as $requirementKey => $requirement) {
            if ($osReq->check($requirementKey)) {
                $this->io->block($requirement['name'] . ' (' . $osReq->getTags($requirementKey) . ')', 'OK');
            } else {
                $errorMessage = $requirement['errorMessage'] ?? $osReq->getRequirementMessage($requirementKey);
                $required = $requirement['required'] ?? true;
                $this->io->block($errorMessage, 'NOT OK', !$required ? 'fg=black;bg=yellow' : 'error');
            }
        }

        return 0;
    }
}
