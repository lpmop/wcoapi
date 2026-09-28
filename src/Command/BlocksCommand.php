<?php

declare(strict_types=1);

namespace WcoApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:blocks', description: 'Bloki schematu IVR (/schema/list-all-blocks)')]
final class BlocksCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('lite', null, InputOption::VALUE_NONE, 'Parametr isLite=true')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Filtr featureType (np. MENU)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Surowy JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);
        $data = $client->listAllBlocks((bool) $input->getOption('lite'));
        $type = $input->getOption('type');
        if (is_string($type) && $type !== '') {
            $data = array_values(array_filter(
                $data,
                static fn (array $g) => strcasecmp((string) ($g['featureType'] ?? ''), $type) === 0
            ));
        }
        if ($input->getOption('json')) {
            $this->writeJson($io, $data);

            return Command::SUCCESS;
        }
        $rows = [];
        foreach ($data as $group) {
            $ft = (string) ($group['featureType'] ?? '');
            $blocks = $group['blocks'] ?? [];
            if (!is_array($blocks) || $blocks === []) {
                $rows[] = [$ft, 0, '-', '-'];
                continue;
            }
            foreach ($blocks as $b) {
                $obj = is_array($b['object'] ?? null) ? $b['object'] : [];
                $rows[] = [
                    $ft,
                    (string) ($b['id'] ?? $obj['id'] ?? ''),
                    (string) ($obj['name'] ?? ''),
                    (string) ($obj['blockId'] ?? ''),
                ];
            }
        }
        $io->table(['featureType', 'id', 'name', 'blockId'], $rows);

        return Command::SUCCESS;
    }
}
