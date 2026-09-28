<?php

declare(strict_types=1);

namespace WcoApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:blocks-settings', description: 'Ustawienia bloków (/schema/list-all-blocks-settings)')]
final class BlocksSettingsCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Surowy JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);
        $data = $client->listAllBlocksSettings();
        if ($input->getOption('json')) {
            $this->writeJson($io, $data);

            return Command::SUCCESS;
        }
        $rows = [];
        foreach ($data as $group) {
            $ft = (string) ($group['featureType'] ?? '');
            $count = is_array($group['blocks'] ?? null) ? count($group['blocks']) : 0;
            $rows[] = [$ft, (string) $count];
        }
        $io->table(['featureType', 'blocks'], $rows);
        $io->writeln('Pełne dane: <info>--json</info>');

        return Command::SUCCESS;
    }
}
