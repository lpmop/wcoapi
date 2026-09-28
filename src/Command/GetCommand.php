<?php

declare(strict_types=1);

namespace WcoApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'wco:get',
    description: 'Generyczny GET na /admin_api/{path} (np. footer/application-version)',
)]
final class GetCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Ścieżka względem admin_api (bez wiodącego /)')
            ->addOption('query', 'q', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Query key=value', [])
            ->addOption('json', null, InputOption::VALUE_NONE, 'Wymuś pretty JSON (domyślnie też JSON)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);

        $path = (string) $input->getArgument('path');
        $query = [];
        foreach ($input->getOption('query') as $pair) {
            if (!is_string($pair) || !str_contains($pair, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $pair, 2);
            $query[$k] = $v;
        }

        $data = $client->get($path, $query);
        $this->writeJson($io, $data);

        return Command::SUCCESS;
    }
}
