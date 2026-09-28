<?php

declare(strict_types=1);

namespace WcoApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:additional-numbers', description: 'Dodatkowe numery główne')]
final class AdditionalNumbersCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Surowy JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);
        $data = $client->getAdditionalNumbers();
        if ($input->getOption('json')) {
            $this->writeJson($io, $data);

            return Command::SUCCESS;
        }
        $rows = [];
        foreach ($data as $i => $row) {
            $rows[] = [
                $i,
                (string) ($row['id'] ?? ''),
                (string) ($row['sdaId'] ?? ''),
                (string) ($row['number'] ?? ''),
                (string) ($row['mainNumberIndex'] ?? ''),
                !empty($row['bound']) ? 'yes' : 'no',
            ];
        }
        $io->table(['#', 'id', 'sdaId', 'number', 'index', 'bound'], $rows);

        return Command::SUCCESS;
    }
}
