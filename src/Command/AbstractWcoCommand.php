<?php

declare(strict_types=1);

namespace WcoApi\Command;

use WcoApi\WcoClient;
use WcoApi\WcoConfig;
use WcoApi\WcoProfiles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractWcoCommand extends Command
{
    protected WcoConfig $config;
    protected ?string $activeProfileName = null;

    public function __construct(
        protected readonly WcoConfig $baseConfig,
        protected readonly WcoProfiles $profiles,
    ) {
        parent::__construct();
        $this->config = $baseConfig;
        $this->addOption(
            'account',
            'p',
            InputOption::VALUE_REQUIRED,
            'Lokalny profil konta'
        );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $requested = $input->getOption('account');
        $requested = is_string($requested) && $requested !== '' ? $requested : null;

        $this->activeProfileName = $requested ?? $this->profiles->defaultName();
        $this->config = $this->profiles->resolve($requested, $this->baseConfig);
    }

    protected function createClient(): WcoClient
    {
        $this->config->assertCredentials();

        return new WcoClient($this->config->baseUrl, 60, $this->config->verifySsl);
    }

    protected function loginClient(SymfonyStyle $io): array
    {
        $client = $this->createClient();
        $session = $client->login(
            $this->config->vpbxNumber,
            $this->config->username,
            $this->config->password,
        );

        $profilePart = $this->activeProfileName !== null
            ? $this->profiles->activeLabel($this->activeProfileName)
            : 'env';

        $io->success(sprintf(
            'Zalogowano: %s (vpbxId=%s, profil=%s)',
            $session['userLogin'] ?? $this->config->username,
            (string) ($session['vpbxId'] ?? '?'),
            $profilePart
        ));

        return [$client, $session];
    }

    protected function writeJson(SymfonyStyle $io, mixed $data): void
    {
        $io->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
