<?php

declare(strict_types=1);

namespace WcoApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:recording-filters', description: 'Opcje filtrów nagrań (/recordings/filter-options)')]
final class RecordingFiltersCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('vr-type', null, InputOption::VALUE_REQUIRED, 'vrType', 'NWI')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Surowy JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);
        $data = $client->getRecordingFilterOptions((string) $input->getOption('vr-type'));
        if ($input->getOption('json')) {
            $this->writeJson($io, $data);

            return Command::SUCCESS;
        }
        $io->section('previousOptions');
        $rows = [];
        foreach ($data['previousOptions'] ?? [] as $o) {
            $rows[] = [
                (string) ($o['name'] ?? ''),
                (string) ($o['blockType'] ?? ''),
                (string) ($o['blockTypeId'] ?? ''),
            ];
        }
        $io->table(['name', 'blockType', 'blockTypeId'], $rows);
        $io->writeln('<comment>bpartyOptions:</comment> ' . implode(', ', $data['bpartyOptions'] ?? []));
        $io->writeln('<comment>cpartyOptions:</comment> ' . implode(', ', $data['cpartyOptions'] ?? []));

        return Command::SUCCESS;
    }
}
