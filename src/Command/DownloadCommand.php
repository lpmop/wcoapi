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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'wco:download',
    description: 'Pobiera nagrania po name i zapisuje MP3 (domyślnie z rozpakowaniem)',
)]
final class DownloadCommand extends AbstractWcoCommand
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
            ->addArgument('names', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Name nagrań (pole name z listy)')
            ->addOption('no-extract', null, InputOption::VALUE_NONE, 'Zapisz ZIP bez rozpakowywania do MP3')
            ->addOption('encrypted', null, InputOption::VALUE_NONE, 'Pobierz zaszyfrowany ZIP (implikuje --no-extract)')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Katalog docelowy (domyślnie WCO_STORAGE_PATH)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client] = $this->loginClient($io);

        /** @var list<string> $names */
        $names = array_values(array_filter(
            array_map('strval', $input->getArgument('names') ?? []),
            static fn (string $v): bool => $v !== ''
        ));
        if ($names === []) {
            $io->error('Podaj co najmniej jedno name nagrania');

            return Command::FAILURE;
        }

        $storage = $this->storage;
        $outOpt = $input->getOption('out');
        if (is_string($outOpt) && $outOpt !== '') {
            $dir = $outOpt;
            if (!str_starts_with($dir, '/') && !preg_match('#^[A-Za-z]:[\\\\/]#', $dir)) {
                $dir = getcwd() . DIRECTORY_SEPARATOR . $dir;
            }
            $storage = new LocalDirectoryStorage($dir);
        }

        if (!$storage instanceof LocalDirectoryStorage && $storage->location() === '(disabled)') {
            $io->error('Zapis plików wyłączony (WCO_STORAGE_DRIVER=null). Ustaw local lub podaj --out.');

            return Command::FAILURE;
        }

        $encrypted = (bool) $input->getOption('encrypted');
        $noExtract = $encrypted || (bool) $input->getOption('no-extract');

        $io->section(match (true) {
            $encrypted => 'Pobieranie zaszyfrowanego ZIP',
            $noExtract => 'Pobieranie ZIP (bez rozpakowania)',
            default => 'Pobieranie odszyfrowanych MP3',
        });

        if (!$encrypted && !$client->unlockRecordingsWithPassword($this->config->recordingPassword)) {
            $io->error('Odblokowanie nagrań (encryption/by-key-pwd) nie powiodło się');

            return Command::FAILURE;
        }

        $targetDir = $storage instanceof LocalDirectoryStorage
            ? $storage->location()
            : sys_get_temp_dir() . '/wco-dl';

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $io->error('Nie można utworzyć katalogu: ' . $targetDir);

            return Command::FAILURE;
        }

        foreach ($names as $name) {
            $io->writeln('→ ' . $name);

            if ($noExtract) {
                $zipBinary = $client->downloadRecordingsZip([$name], $encrypted);
                $prefix = $encrypted ? 'wco-encrypted-' : 'wco-recordings-';
                $file = $targetDir . DIRECTORY_SEPARATOR . $prefix . $name . '.zip';
                if (file_put_contents($file, $zipBinary) === false) {
                    $io->error('Nie zapisano ZIP: ' . $file);

                    return Command::FAILURE;
                }
                $files = [$file];
            } else {
                $files = $client->downloadAndExtractMp3([$name], $targetDir, false);
            }

            foreach ($files as $file) {
                $bytes = is_file($file) ? (int) filesize($file) : 0;
                if ($storage instanceof LocalDirectoryStorage && dirname($file) !== $storage->location()) {
                    $binary = (string) file_get_contents($file);
                    $ext = pathinfo($file, PATHINFO_EXTENSION) ?: ($noExtract ? 'zip' : 'mp3');
                    $file = $storage->write($name, $binary, $ext);
                }
                $io->writeln('  Zapisano: <info>' . $file . '</info>');
                $this->tracker->mark($name, $file, $bytes, [
                    'encrypted' => $encrypted,
                    'noExtract' => $noExtract,
                ]);
            }
        }

        $io->success(sprintf('Pobrano %d nagranie/nagrań', count($names)));

        return Command::SUCCESS;
    }
}
