<?php

declare(strict_types=1);

namespace WcoApi\Command;

use WcoApi\WcoClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'wco:recordings:delete',
    description: 'Usuwa nagrania (POST /admin_api/recordings/delete/)',
)]
final class RecordingsDeleteCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('ids', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'ID nagrań (pole id z listy)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Name nagrania (rozwiązane do id przez listę)', [])
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Zakres wyszukiwania name (dni wstecz)', '90')
            ->addOption('vr-type', null, InputOption::VALUE_REQUIRED, 'Typ nagrań przy lookup po name', 'NWI')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Bez potwierdzenia')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Tylko sprawdź delete-recordings-allowed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);

        $allowed = $client->isDeleteRecordingsAllowed();
        if ($input->getOption('check')) {
            $io->writeln($allowed
                ? '<info>delete-recordings-allowed=true</info>'
                : '<comment>delete-recordings-allowed=false</comment> (GUI czasem i tak pozwala — POST i tak warto spróbować)');

            return Command::SUCCESS;
        }

        if (!$allowed) {
            $io->warning('API zgłasza delete-recordings-allowed=false — próbuję usunięcia mimo to (jak w GUI).');
        }

        $idArgs = array_values(array_filter(
            array_map('strval', $input->getArgument('ids') ?? []),
            static fn (string $v): bool => $v !== ''
        ));
        $names = array_values(array_filter(
            array_map('strval', $input->getOption('name') ?? []),
            static fn (string $v): bool => $v !== ''
        ));

        if ($idArgs === [] && $names === []) {
            $io->error('Podaj ID (argumenty) lub --name=…');

            return Command::FAILURE;
        }

        $ids = [];
        foreach ($idArgs as $raw) {
            if (!ctype_digit($raw)) {
                $io->error('Niepoprawne ID: ' . $raw);

                return Command::FAILURE;
            }
            $ids[] = (int) $raw;
        }

        if ($names !== []) {
            $resolved = $this->resolveNamesToIds($client, $names, $input, $io);
            if ($resolved === null) {
                return Command::FAILURE;
            }
            $ids = array_merge($ids, $resolved);
        }

        $ids = array_values(array_unique($ids));
        $io->writeln('ID do usunięcia: ' . implode(', ', $ids));

        if (!$input->getOption('force') && !$io->confirm(sprintf('Usunąć %d nagranie/nagrań?', count($ids)), false)) {
            $io->warning('Anulowano');

            return Command::SUCCESS;
        }

        $client->deleteRecordings($ids);
        $io->success(sprintf('Usunięto %d nagranie/nagrań', count($ids)));

        return Command::SUCCESS;
    }

    private function resolveNamesToIds(
        WcoClient $client,
        array $names,
        InputInterface $input,
        SymfonyStyle $io,
    ): ?array {
        $days = max(1, (int) $input->getOption('days'));
        $tz = new \DateTimeZone('Europe/Warsaw');
        $now = new \DateTimeImmutable('now', $tz);
        $wanted = array_fill_keys($names, true);
        $found = [];

        $pageIdx = 0;
        $size = 100;
        while ($wanted !== [] && $pageIdx < 20) {
            $page = $client->listRecordings([
                'dateFrom' => $now->modify(sprintf('-%d days', $days))->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
                'dateTo' => $now->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
                'page' => $pageIdx,
                'size' => $size,
                'sort' => 'start,desc',
                'vrType' => (string) $input->getOption('vr-type'),
            ]);

            $content = array_values($page['content'] ?? []);
            if ($content === []) {
                break;
            }

            foreach ($content as $row) {
                $name = (string) ($row['name'] ?? '');
                if ($name !== '' && isset($wanted[$name]) && isset($row['id'])) {
                    $found[$name] = (int) $row['id'];
                    unset($wanted[$name]);
                }
            }

            $totalPages = (int) ($page['totalPages'] ?? 1);
            ++$pageIdx;
            if ($pageIdx >= $totalPages) {
                break;
            }
        }

        if ($wanted !== []) {
            $io->error('Nie znaleziono nagrań (name) w zakresie --days=' . $days . ': ' . implode(', ', array_keys($wanted)));

            return null;
        }

        return array_values($found);
    }
}
