<?php

declare(strict_types=1);

namespace WcoApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:administrators', description: 'Lista administratorów VPBX (/admin_api/administrators/)')]
final class AdministratorsCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('roles', null, InputOption::VALUE_NONE, 'Pokaż konfigurowalne role zamiast listy')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Filtr po loginie/email/imieniu')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Surowy JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);

        if ($input->getOption('roles')) {
            $roles = $client->getAdministratorConfigurableRoles();
            if ($input->getOption('json')) {
                $this->writeJson($io, $roles);

                return Command::SUCCESS;
            }

            $rows = [];
            foreach ($roles as $r) {
                $rows[] = [
                    (string) ($r['id'] ?? ''),
                    (string) ($r['name'] ?? ''),
                    (string) ($r['description'] ?? ''),
                ];
            }
            $io->table(['id', 'name', 'description'], $rows);

            return Command::SUCCESS;
        }

        $admins = $client->getAdministrators();
        $search = $input->getOption('search');
        if (is_string($search) && $search !== '') {
            $q = mb_strtolower($search);
            $admins = array_values(array_filter($admins, static function (array $a) use ($q): bool {
                $hay = mb_strtolower(implode(' ', [
                    (string) ($a['login'] ?? ''),
                    (string) ($a['email'] ?? ''),
                    (string) ($a['firstName'] ?? ''),
                    (string) ($a['lastName'] ?? ''),
                    (string) ($a['telNumber'] ?? ''),
                ]));

                return str_contains($hay, $q);
            }));
        }

        if ($input->getOption('json')) {
            $this->writeJson($io, $admins);

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($admins as $i => $a) {
            $roles = array_map(
                static fn ($r) => is_array($r) ? (string) ($r['name'] ?? '') : (string) $r,
                $a['roles'] ?? []
            );
            $rows[] = [
                $i,
                (string) ($a['id'] ?? ''),
                (string) ($a['login'] ?? ''),
                trim((string) ($a['firstName'] ?? '') . ' ' . (string) ($a['lastName'] ?? '')),
                (string) ($a['email'] ?? ''),
                implode(',', $roles),
                !empty($a['accessToListenAndDownloadRecordings']) ? 'yes' : 'no',
            ];
        }

        $io->table(['#', 'id', 'login', 'Name', 'Email', 'Roles', 'Listen'], $rows);
        $io->writeln(sprintf('<comment>Razem:</comment> %d', count($admins)));
        $io->writeln(sprintf(
            '<comment>hasExternalRecordingKeys:</comment> %s',
            $client->hasExternalRecordingKeys() ? 'true' : 'false'
        ));

        return Command::SUCCESS;
    }
}
