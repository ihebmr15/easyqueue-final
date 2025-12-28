<?php

namespace App\Command;

use App\Entity\Ticket;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:list-tickets',
    description: 'List tickets, optionally filtered by user email',
)]
class ListTicketsCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user_email', InputArgument::OPTIONAL, 'Filter tickets by user email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('user_email');

        if ($email) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if (!$user) {
                $io->error("User with email '$email' not found.");
                return Command::FAILURE;
            }
            $tickets = $this->entityManager->getRepository(Ticket::class)->findBy(['user' => $user]);
            $io->title("Tickets for user: {$user->getFullname()}");
        } else {
            $tickets = $this->entityManager->getRepository(Ticket::class)->findAll();
            $io->title("All Tickets");
        }

        if (!$tickets) {
            $io->warning('No tickets found.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($tickets as $ticket) {
            $rows[] = [
                $ticket->getId(),
                $ticket->getUser()->getFullname(),
                $ticket->getServicename(),
                $ticket->getTicketnumber(),
                $ticket->getStatus(),
                $ticket->getCreatedat()->format('Y-m-d H:i:s'),
                $ticket->getEstimatedwait(),
            ];
        }

        $io->table(
            ['ID', 'User', 'Service', 'Ticket #', 'Status', 'Created At', 'Est. Wait'],
            $rows
        );

        return Command::SUCCESS;
    }
}
