<?php

declare(strict_types=1);

namespace WcoApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:scenario', description: 'Scenariusz grafu IVR (/graph/scenario)')]
final class ScenarioCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Surowy JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);
        $data = $client->getScenario();
        if ($input->getOption('json')) {
            $this->writeJson($io, $data);

            return Command::SUCCESS;
        }
        $io->definitionList(
            ['id' => (string) ($data['id'] ?? '')],
            ['name' => (string) ($data['name'] ?? '')],
            ['firstBlockId' => (string) ($data['firstBlockId'] ?? '')],
            ['version' => (string) ($data['version'] ?? '')],
        );

        return Command::SUCCESS;
    }
}
