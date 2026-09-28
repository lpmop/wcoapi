<?php

declare(strict_types=1);

namespace WcoApi\Command;

use WcoApi\WcoConfig;
use WcoApi\WcoProfiles;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:profiles', description: 'Lista lokalnych profili kont WCO')]
final class ProfilesCommand extends Command
{
    public function __construct(
        private readonly WcoProfiles $profiles,
        private readonly WcoConfig $baseConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'JSON (bez haseł)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $data = $this->profiles->load();
        $default = $this->profiles->defaultName();

        if ($data['profiles'] === []) {
            $io->warning(sprintf(
                'Brak profili w %s — używane jest .env (user=%s). Dodaj: php bin/console wco:profiles:add',
                $this->profiles->filePath(),
                $this->baseConfig->username !== '' ? $this->baseConfig->username : '?'
            ));

            return Command::SUCCESS;
        }

        if ($input->getOption('json')) {
            $safe = [
                'default' => $default,
                'file' => $this->profiles->filePath(),
                'profiles' => [],
            ];
            foreach ($data['profiles'] as $name => $p) {
                $safe['profiles'][$name] = [
                    'vpbx' => $p['vpbx'],
                    'user' => $p['user'],
                    'label' => $p['label'] ?? '',
                    'has_pass' => $p['pass'] !== '',
                    'has_rec_pass' => ($p['rec_pass'] ?? '') !== '',
                ];
            }
            $io->writeln(json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($data['profiles'] as $name => $p) {
            $rows[] = [
                $name === $default ? '*' : '',
                $name,
                $p['label'] ?? '',
                $p['vpbx'],
                $p['user'],
            ];
        }

        $io->table(['def', 'name', 'label', 'vpbx', 'user'], $rows);
        $io->writeln(sprintf('<comment>Plik:</comment> %s', $this->profiles->filePath()));
        $io->writeln('<comment>Przełączanie:</comment> php bin/console wco:whoami -p NAZWA   |   wco:profiles:use NAZWA');

        return Command::SUCCESS;
    }
}
