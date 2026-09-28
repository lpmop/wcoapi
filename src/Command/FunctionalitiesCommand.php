<?php

declare(strict_types=1);

namespace WcoApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:functionalities', description: 'Katalog funkcjonalności (/schema/list-functionalities-all)')]
final class FunctionalitiesCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('active', null, InputOption::VALUE_NONE, 'Tylko active=true')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Surowy JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);
        $data = $client->listFunctionalitiesAll();
        if ($input->getOption('active')) {
            $data = array_values(array_filter($data, static fn (array $f) => !empty($f['active'])));
        }
        if ($input->getOption('json')) {
            $this->writeJson($io, $data);

            return Command::SUCCESS;
        }
        $rows = [];
        foreach ($data as $f) {
            $rows[] = [
                (string) ($f['featureType'] ?? ''),
                (string) ($f['description'] ?? ''),
                !empty($f['active']) ? 'yes' : 'no',
                (string) ($f['maxQuantity'] ?? ''),
                (string) ($f['price'] ?? ''),
            ];
        }
        $io->table(['featureType', 'description', 'active', 'maxQty', 'price'], $rows);

        return Command::SUCCESS;
    }
}
