<?php

declare(strict_types=1);

namespace WcoApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:users', description: 'Lista użytkowników VPBX (/admin_api/users/)')]
final class UsersCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Filtr po nazwie/numerze/email')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Surowy JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);
        $users = $client->getUsers();

        $search = $input->getOption('search');
        if (is_string($search) && $search !== '') {
            $q = mb_strtolower($search);
            $users = array_values(array_filter($users, static function (array $u) use ($q): bool {
                $hay = mb_strtolower(implode(' ', [
                    (string) ($u['name'] ?? ''),
                    (string) ($u['firstName'] ?? ''),
                    (string) ($u['lastName'] ?? ''),
                    (string) ($u['number'] ?? ''),
                    (string) ($u['email'] ?? ''),
                    (string) ($u['login'] ?? ''),
                ]));

                return str_contains($hay, $q);
            }));
        }

        if ($input->getOption('json')) {
            $this->writeJson($io, $users);

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($users as $i => $u) {
            $roles = array_map(static fn ($r) => is_array($r) ? (string) ($r['name'] ?? '') : (string) $r, $u['roles'] ?? []);
            $rows[] = [
                $i,
                (string) ($u['userId'] ?? ''),
                trim((string) ($u['name'] ?? '')),
                (string) ($u['number'] ?? ''),
                (string) ($u['email'] ?? ''),
                (string) ($u['status'] ?? ''),
                implode(',', $roles),
                !empty($u['apiAccess']) ? 'yes' : 'no',
            ];
        }

        $io->table(['#', 'userId', 'Name', 'Number', 'Email', 'Status', 'Roles', 'API'], $rows);
        $io->writeln(sprintf('<comment>Razem:</comment> %d', count($users)));

        return Command::SUCCESS;
    }
}
