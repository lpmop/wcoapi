<?php

declare(strict_types=1);

namespace WcoApi\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:whoami', description: 'Sesja po logowaniu + wersja aplikacji')]
final class WhoamiCommand extends AbstractWcoCommand
{
    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Surowy JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        [$client, $session] = $this->loginClient($io);
        $version = $client->getApplicationVersion();
        $authMsg = $client->isAuthorizedToOpenMessage();
        $payload = [
            'session' => $session,
            'applicationVersion' => $version,
            'isAuthorizedToOpenMessage' => $authMsg,
            'encryptionByKey' => $client->isAuthenticationByKey(),
            'deleteRecordingsAllowed' => $client->isDeleteRecordingsAllowed(),
        ];

        if ($input->getOption('json')) {
            $this->writeJson($io, $payload);

            return Command::SUCCESS;
        }

        $io->definitionList(
            ['Login' => (string) ($session['userLogin'] ?? '')],
            ['Main number' => (string) ($session['mainNumber'] ?? '')],
            ['vpbxId' => (string) ($session['vpbxId'] ?? '')],
            ['Roles' => implode(', ', $session['roles'] ?? [])],
            ['App version' => (string) ($version['applicationVersion'] ?? '')],
            ['Encryption by key' => $payload['encryptionByKey'] ? 'yes' : 'no'],
            ['Delete recordings' => $payload['deleteRecordingsAllowed'] ? 'yes' : 'no'],
        );

        return Command::SUCCESS;
    }
}
