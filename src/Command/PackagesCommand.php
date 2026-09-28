<?php

declare(strict_types=1);

namespace WcoApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:packages', description: 'Pakiet sklepu / store (/store/packages)')]
final class PackagesCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Surowy JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);
        $data = $client->getStorePackages();
        if ($input->getOption('json')) {
            $this->writeJson($io, $data);

            return Command::SUCCESS;
        }
        $io->definitionList(
            ['name' => (string) ($data['name'] ?? '')],
            ['type' => (string) ($data['type'] ?? '')],
            ['amount' => (string) ($data['amount'] ?? '')],
            ['configurable' => !empty($data['configurable']) ? 'yes' : 'no'],
        );
        $rows = [];
        foreach ($data['quantities'] ?? [] as $q) {
            $rows[] = [
                (string) ($q['featureType'] ?? ''),
                (string) ($q['limit'] ?? ''),
                (string) ($q['active'] ?? ''),
            ];
        }
        if ($rows !== []) {
            $io->table(['featureType', 'limit', 'active'], $rows);
        }

        return Command::SUCCESS;
    }
}
