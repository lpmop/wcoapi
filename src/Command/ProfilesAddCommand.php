<?php

declare(strict_types=1);

namespace WcoApi\Command;

use WcoApi\Exception\AuthenticationException;
use WcoApi\WcoConfig;
use WcoApi\WcoProfiles;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:profiles:add', description: 'Dodaj / zaktualizuj lokalny profil konta WCO')]
final class ProfilesAddCommand extends Command
{
    public function __construct(
        private readonly WcoProfiles $profiles,
        private readonly WcoConfig $baseConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nazwa profilu (np. main, archiwista)')
            ->addOption('vpbx', null, InputOption::VALUE_REQUIRED, 'Numer VPBX')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Login')
            ->addOption('pass', null, InputOption::VALUE_REQUIRED, 'Hasło logowania')
            ->addOption('rec-pass', null, InputOption::VALUE_REQUIRED, 'Hasło nagrań (domyślnie = pass)')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Opis (opcjonalnie)')
            ->addOption('from-env', null, InputOption::VALUE_NONE, 'Skopiuj dane z aktualnego .env (WCO_*)')
            ->addOption('default', null, InputOption::VALUE_NONE, 'Ustaw jako domyślny');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = (string) ($input->getOption('name') ?? '');
        $fromEnv = (bool) $input->getOption('from-env');

        if ($fromEnv) {
            if ($name === '') {
                $name = 'main';
            }
            $vpbx = $this->baseConfig->vpbxNumber;
            $user = $this->baseConfig->username;
            $pass = $this->baseConfig->password;
            $recPass = $this->baseConfig->recordingPassword;
            $label = (string) ($input->getOption('label') ?? 'z .env');
        } else {
            $vpbx = (string) ($input->getOption('vpbx') ?? '');
            $user = (string) ($input->getOption('user') ?? '');
            $pass = (string) ($input->getOption('pass') ?? '');
            $recPass = (string) ($input->getOption('rec-pass') ?? '');
            $label = (string) ($input->getOption('label') ?? '');

            if ($name === '') {
                $name = (string) $io->ask('Nazwa profilu', $user !== '' ? $user : 'main');
            }
            if ($vpbx === '') {
                $vpbx = (string) $io->ask('VPBX', $this->baseConfig->vpbxNumber ?: null);
            }
            if ($user === '') {
                $user = (string) $io->ask('Login');
            }
            if ($pass === '') {
                $pass = (string) $io->askHidden('Hasło logowania');
            }
            if ($recPass === '') {
                $asked = $io->ask('Hasło nagrań (Enter = to samo co logowania)', '');
                $recPass = is_string($asked) && $asked !== '' ? $asked : $pass;
            }
        }

        if ($name === '' || $vpbx === '' || $user === '' || $pass === '') {
            $io->error('Wymagane: name, vpbx, user, pass (lub --from-env)');

            return Command::FAILURE;
        }

        try {
            $this->profiles->upsert($name, [
                'vpbx' => $vpbx,
                'user' => $user,
                'pass' => $pass,
                'rec_pass' => $recPass !== '' ? $recPass : $pass,
                'label' => $label,
            ], makeDefault: (bool) $input->getOption('default'));
        } catch (AuthenticationException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Zapisano profil "%s" → %s', $name, $this->profiles->filePath()));

        return Command::SUCCESS;
    }
}
