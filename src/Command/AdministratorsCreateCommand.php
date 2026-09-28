<?php

declare(strict_types=1);

namespace WcoApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'wco:administrators:create',
    description: 'Dodaje administratora (POST /admin_api/administrators/)'
)]
final class AdministratorsCreateCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this
            ->addOption('login', null, InputOption::VALUE_REQUIRED, 'Login nowego admina')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Hasło logowania nowego admina')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'Imię (domyślnie = email)')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Nazwisko')
            ->addOption('roles', null, InputOption::VALUE_REQUIRED, 'Role po przecinku (np. ROLE_FAX_USER,ROLE_REPORT_USER)')
            ->addOption('recording-password', null, InputOption::VALUE_REQUIRED, 'Aktualne hasło nagrań VPBX (domyślnie WCO_REC_PASS)')
            ->addOption('keys-password', null, InputOption::VALUE_REQUIRED, 'Hasło kluczy nagrań (domyślnie = --password)')
            ->addOption('pwd-days', null, InputOption::VALUE_REQUIRED, 'Ważność hasła w dniach', '100')
            ->addOption('force-pwd-change', null, InputOption::VALUE_NONE, 'Wymuś zmianę hasła przy logowaniu')
            ->addOption('listen', null, InputOption::VALUE_NEGATABLE, 'Dostęp do odsłuchu/pobierania nagrań', true)
            ->addOption('generate-keys', null, InputOption::VALUE_NEGATABLE, 'Generuj klucze nagrań', true)
            ->addOption('json', null, InputOption::VALUE_NONE, 'Surowy JSON listy po utworzeniu');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $login = (string) $input->getOption('login');
        $email = (string) $input->getOption('email');
        $password = (string) $input->getOption('password');
        $rolesOpt = (string) ($input->getOption('roles') ?? '');

        if ($login === '' || $email === '' || $password === '' || $rolesOpt === '') {
            $io->error('Wymagane: --login, --email, --password, --roles');

            return Command::FAILURE;
        }

        [$client] = $this->loginClient($io);

        $available = $client->getAdministratorConfigurableRoles();
        $byName = [];
        foreach ($available as $role) {
            $name = (string) ($role['name'] ?? '');
            if ($name !== '') {
                $byName[$name] = $role;
            }
        }

        $wanted = array_values(array_filter(array_map('trim', explode(',', $rolesOpt))));
        $roles = [];
        foreach ($wanted as $name) {
            if (!isset($byName[$name])) {
                $io->error(sprintf(
                    'Nieznana rola %s. Dostępne: %s',
                    $name,
                    implode(', ', array_keys($byName))
                ));

                return Command::FAILURE;
            }
            $roles[] = $byName[$name];
        }

        $recordingPassword = (string) ($input->getOption('recording-password') ?? '');
        if ($recordingPassword === '') {
            $recordingPassword = $this->config->recordingPassword;
        }
        if ($recordingPassword === '') {
            $io->error('Podaj --recording-password lub ustaw WCO_REC_PASS (aktualne hasło nagrań VPBX).');

            return Command::FAILURE;
        }

        $keysPassword = (string) ($input->getOption('keys-password') ?? '');
        if ($keysPassword === '') {
            $keysPassword = $password;
        }

        $firstName = (string) ($input->getOption('first-name') ?? '');
        if ($firstName === '') {
            $firstName = $email;
        }
        $lastName = (string) ($input->getOption('last-name') ?? '');
        if ($lastName === '') {
            $lastName = $login;
        }

        $days = max(1, (int) $input->getOption('pwd-days'));
        $pwdExpDate = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify(sprintf('+%d days', $days))
            ->format('Y-m-d\TH:i:s.v\Z');

        $payload = [
            'email' => $email,
            'lastName' => $lastName,
            'firstName' => $firstName,
            'login' => $login,
            'id' => null,
            'roles' => $roles,
            'pwdExpDate' => $pwdExpDate,
            'generateKeys' => (bool) $input->getOption('generate-keys'),
            'forcePwdChange' => (bool) $input->getOption('force-pwd-change'),
            'accessToListenAndDownloadRecordings' => (bool) $input->getOption('listen'),
            'changePassword' => true,
            'notifyAboutRecordingFailure' => false,
            'notifyAboutArchivePackageExpiration' => false,
            'lastPasswordKeyModification' => null,
            'oldPassword' => null,
            'password' => $password,
            'recordingPassword' => $recordingPassword,
            'recordingKeysPassword' => $keysPassword,
            'version' => -1,
            'number' => null,
            'mainAdminPassword' => null,
            'userList' => null,
            'allEmployees' => false,
            'deleteRecordingsAllowed' => false,
            'removeReports' => false,
        ];

        $admins = $client->createAdministrator($payload);

        if ($input->getOption('json')) {
            $this->writeJson($io, $admins);

            return Command::SUCCESS;
        }

        $created = null;
        foreach ($admins as $a) {
            if (($a['login'] ?? '') === $login) {
                $created = $a;
                break;
            }
        }

        if ($created === null) {
            $io->warning('Utworzono, ale nie znaleziono nowego loginu na liście odpowiedzi.');
            $this->writeJson($io, $admins);

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Dodano administratora %s (id=%s)',
            (string) $created['login'],
            (string) ($created['id'] ?? '?')
        ));
        $roleNames = array_map(
            static fn ($r) => is_array($r) ? (string) ($r['name'] ?? '') : (string) $r,
            $created['roles'] ?? []
        );
        $io->listing([
            'email: ' . (string) ($created['email'] ?? ''),
            'roles: ' . implode(', ', $roleNames),
        ]);

        return Command::SUCCESS;
    }
}
