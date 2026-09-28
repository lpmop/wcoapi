<?php

declare(strict_types=1);

namespace WcoApi\Command;

use WcoApi\Exception\AuthenticationException;
use WcoApi\WcoProfiles;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wco:profiles:remove', description: 'Usuń lokalny profil konta')]
final class ProfilesRemoveCommand extends Command
{
    public function __construct(
        private readonly WcoProfiles $profiles,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Nazwa profilu');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');

        try {
            $this->profiles->remove($name);
        } catch (AuthenticationException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Usunięto profil "%s"', $name));

        return Command::SUCCESS;
    }
}
