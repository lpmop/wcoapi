<?php

declare(strict_types=1);

namespace WcoApi\Command;

use WcoApi\Persistence\DownloadTrackerInterface;
use WcoApi\Storage\LocalDirectoryStorage;
use WcoApi\Storage\RecordingStorageInterface;
use WcoApi\WcoConfig;
use WcoApi\WcoProfiles;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'wco:calls',
    description: 'Listuje rozmowy/nagrania WCO i opcjonalnie pobiera odszyfrowane MP3',
)]
final class CallsCommand extends AbstractWcoCommand
{
    public function __construct(
        WcoConfig $config,
        WcoProfiles $profiles,
        private readonly RecordingStorageInterface $storage,
        private readonly DownloadTrackerInterface $tracker,
    ) {
        parent::__construct($config, $profiles);
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Data od (Y-m-d H:i:s)', null)
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Data do (Y-m-d H:i:s)', null)
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Ile dni wstecz (domyślnie 60)', '60')
            ->addOption('page', null, InputOption::VALUE_REQUIRED, 'Strona (0-based)', '0')
            ->addOption('size', null, InputOption::VALUE_REQUIRED, 'Rozmiar strony', '20')
            ->addOption('vr-type', null, InputOption::VALUE_REQUIRED, 'Typ nagrań (np. NWI)', 'NWI')
            ->addOption('sort', null, InputOption::VALUE_REQUIRED, 'Sortowanie API', 'start,desc')
            ->addOption('archived-by', null, InputOption::VALUE_REQUIRED, 'Filtr API: NOT_ARCHIVED|BY_WWW|BY_SFTP|BY_WEBSERVICE (lub 0–3)')
            ->addOption('not-archived', null, InputOption::VALUE_NONE, 'Skrót: archivedBy=NOT_ARCHIVED (0) — filtr serwera')
            ->addOption('not-downloaded', null, InputOption::VALUE_NONE, 'Tylko niepobrane (storage/ledger/DB wg konfiguracji)')
            ->addOption('caller', null, InputOption::VALUE_REQUIRED, 'Filtr lokalny: numer A (nadawca)', null)
            ->addOption('callee', null, InputOption::VALUE_REQUIRED, 'Filtr lokalny: nazwa/numer B', null)
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Filtr lokalny: fragment name', null)
            ->addOption('download', 'd', InputOption::VALUE_REQUIRED, 'Pobierz po indeksie z listy (0..) lub po name', null)
            ->addOption('download-all', null, InputOption::VALUE_NONE, 'Pobierz wszystkie z listy (po filtrach)')
            ->addOption('encrypted', null, InputOption::VALUE_NONE, 'Pobierz zaszyfrowany ZIP zamiast MP3')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Nadpisz katalog lokalny (tylko driver=local)', null)
            ->addOption('json', null, InputOption::VALUE_NONE, 'Surowy JSON listy');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);

        $storage = $this->storage;
        $outOpt = $input->getOption('out');
        if (is_string($outOpt) && $outOpt !== '') {
            $dir = $outOpt;
            if (!str_starts_with($dir, '/') && !preg_match('#^[A-Za-z]:[\\\\/]#', $dir)) {
                $dir = getcwd() . DIRECTORY_SEPARATOR . $dir;
            }
            $storage = new LocalDirectoryStorage($dir);
        }

        $days = max(1, (int) $input->getOption('days'));
        $tz = new \DateTimeZone('Europe/Warsaw');
        $now = new \DateTimeImmutable('now', $tz);
        $dateFrom = $input->getOption('from')
            ?: $now->modify(sprintf('-%d days', $days))->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $dateTo = $input->getOption('to')
            ?: $now->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        $archivedBy = $input->getOption('archived-by');
        if ($input->getOption('not-archived')) {
            $archivedBy = 'NOT_ARCHIVED';
        }

        $page = $client->listRecordings([
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'page' => (int) $input->getOption('page'),
            'size' => (int) $input->getOption('size'),
            'sort' => (string) $input->getOption('sort'),
            'vrType' => (string) $input->getOption('vr-type'),
            'archivedBy' => is_string($archivedBy) && $archivedBy !== '' ? $archivedBy : null,
        ]);

        /** @var list<array<string, mixed>> $items */
        $items = array_values($page['content'] ?? []);
        $items = $this->applyLocalFilters($items, $input, $storage);

        if ($input->getOption('json')) {
            $this->writeJson($io, [
                'storage' => $storage->location(),
                'filters' => [
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                    'archivedBy' => $archivedBy,
                    'notDownloaded' => (bool) $input->getOption('not-downloaded'),
                ],
                'page' => $page,
                'content' => $items,
            ]);

            return Command::SUCCESS;
        }

        if ($items === []) {
            $io->warning(sprintf('Brak rozmów dla zakresu %s → %s', $dateFrom, $dateTo));

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($items as $i => $row) {
            $start = isset($row['start']) ? $this->formatMs((int) $row['start']) : '-';
            $name = (string) ($row['name'] ?? '');
            $local = $this->tracker->has($name) || $storage->exists($name) ? 'tak' : 'nie';
            $rows[] = [
                $i,
                (string) ($row['id'] ?? ''),
                $start,
                (string) ($row['seconds'] ?? ''),
                (string) ($row['aPartyNumber'] ?? ''),
                $this->bPartyNumberFromName($name),
                trim((string) ($row['bPartyUserFullName'] ?? '')),
                (string) ($row['archivedBy'] ?? ''),
                $local,
                $name,
            ];
        }

        $io->title(sprintf('Rozmowy (%d)', count($items)));
        $io->table(
            ['#', 'Id', 'Start', 'Sek', 'A', 'B nr', 'Odbiorca', 'Arch', 'Pobrane', 'Name'],
            $rows
        );

        $io->writeln(sprintf(
            '<comment>Zakres:</comment> %s → %s | <comment>storage:</comment> %s',
            $dateFrom,
            $dateTo,
            $storage->location()
        ));
        $io->writeln('Pobrane = tracker (DB/ledger) lub plik w storage. API nie ma per-user download.');
        $io->writeln('Usuwanie: <info>php bin/console wco:recordings:delete ID</info> (kolumna Id).');

        $namesToDownload = [];
        if ($input->getOption('download-all')) {
            foreach ($items as $row) {
                if (!empty($row['name'])) {
                    $namesToDownload[] = (string) $row['name'];
                }
            }
        } elseif (($download = $input->getOption('download')) !== null && $download !== '') {
            $name = $this->resolveDownloadName((string) $download, $items);
            if ($name === null) {
                $io->error('Nie znaleziono nagrania dla --download=' . $download);

                return Command::FAILURE;
            }
            $namesToDownload[] = $name;
        }

        if ($namesToDownload === []) {
            return Command::SUCCESS;
        }

        if (!$storage instanceof LocalDirectoryStorage && $storage->location() === '(disabled)') {
            $io->error('Zapis plików wyłączony (WCO_STORAGE_DRIVER=null). Ustaw local.');

            return Command::FAILURE;
        }

        $encrypted = (bool) $input->getOption('encrypted');
        $io->section($encrypted ? 'Pobieranie zaszyfrowanego ZIP' : 'Pobieranie odszyfrowanych MP3');

        if (!$encrypted && !$client->unlockRecordingsWithPassword($this->config->recordingPassword)) {
            $io->error('Odblokowanie nagrań (encryption/by-key-pwd) nie powiodło się');

            return Command::FAILURE;
        }

        $targetDir = $storage instanceof LocalDirectoryStorage
            ? $storage->location()
            : sys_get_temp_dir() . '/wco-dl';

        foreach ($namesToDownload as $name) {
            $io->writeln('→ ' . $name);
            $metaRow = null;
            foreach ($items as $row) {
                if (($row['name'] ?? null) === $name) {
                    $metaRow = $row;
                    break;
                }
            }

            $files = $client->downloadAndExtractMp3([$name], $targetDir, $encrypted);
            foreach ($files as $file) {
                $bytes = is_file($file) ? (int) filesize($file) : 0;
                // gdy storage nie jest tym samym katalogiem co temp — skopiuj
                if ($storage instanceof LocalDirectoryStorage && dirname($file) !== $storage->location()) {
                    $binary = (string) file_get_contents($file);
                    $ext = pathinfo($file, PATHINFO_EXTENSION) ?: ($encrypted ? 'zip' : 'mp3');
                    $file = $storage->write($name, $binary, $ext);
                }
                $io->writeln('  Zapisano: <info>' . $file . '</info>');
                $this->tracker->mark($name, $file, $bytes, [
                    'archivedBy' => $metaRow['archivedBy'] ?? null,
                    'encrypted' => $encrypted,
                    'aPartyNumber' => $metaRow['aPartyNumber'] ?? null,
                    'bPartyUserFullName' => $metaRow['bPartyUserFullName'] ?? null,
                ]);
            }
        }

        return Command::SUCCESS;
    }

    private function bPartyNumberFromName(string $name): string
    {
        $parts = explode('_', $name);
        $last = end($parts);

        return is_string($last) && $last !== '' ? $last : '-';
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function applyLocalFilters(array $items, InputInterface $input, RecordingStorageInterface $storage): array
    {
        $caller = $input->getOption('caller');
        $callee = $input->getOption('callee');
        $name = $input->getOption('name');
        $notDownloaded = (bool) $input->getOption('not-downloaded');

        return array_values(array_filter($items, function (array $row) use ($caller, $callee, $name, $notDownloaded, $storage): bool {
            $rowName = (string) ($row['name'] ?? '');
            if ($notDownloaded && ($this->tracker->has($rowName) || $storage->exists($rowName))) {
                return false;
            }
            if ($caller !== null && $caller !== '') {
                if (!str_contains((string) ($row['aPartyNumber'] ?? ''), (string) $caller)) {
                    return false;
                }
            }
            if ($callee !== null && $callee !== '') {
                $hay = ((string) ($row['bPartyUserFullName'] ?? '')) . ' ' . $rowName;
                if (!str_contains(mb_strtolower($hay), mb_strtolower((string) $callee))) {
                    return false;
                }
            }
            if ($name !== null && $name !== '') {
                if (!str_contains($rowName, (string) $name)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function resolveDownloadName(string $download, array $items): ?string
    {
        if (ctype_digit($download)) {
            $idx = (int) $download;

            return isset($items[$idx]['name']) ? (string) $items[$idx]['name'] : null;
        }

        foreach ($items as $row) {
            if (($row['name'] ?? null) === $download) {
                return (string) $row['name'];
            }
        }

        return $download;
    }

    private function formatMs(int $ms): string
    {
        try {
            return (new \DateTimeImmutable('@' . intdiv($ms, 1000)))
                ->setTimezone(new \DateTimeZone('Europe/Warsaw'))
                ->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return (string) $ms;
        }
    }
}
