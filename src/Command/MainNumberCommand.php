<?php

declare(strict_types=1);

namespace WcoApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:main-number', description: 'Numer główny VPBX')]
final class MainNumberCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Surowy JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);
        $data = $client->getMainNumber();
        if ($input->getOption('json')) {
            $this->writeJson($io, $data);

            return Command::SUCCESS;
        }
        $io->definitionList(
            ['ID' => (string) ($data['id'] ?? '')],
            ['SDA' => (string) ($data['sdaId'] ?? '')],
            ['Number' => (string) ($data['number'] ?? '')],
            ['firstBlockId' => (string) ($data['firstBlockId'] ?? '')],
            ['bound' => !empty($data['bound']) ? 'yes' : 'no'],
        );

        return Command::SUCCESS;
    }
}
