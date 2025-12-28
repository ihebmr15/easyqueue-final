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
    name: 'app:add-ticket',
    description: 'Add a new ticket for a user',
)]
class AddTicketCommand extends Command
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
            ->addArgument('user_email', InputArgument::REQUIRED, 'Email of the user for this ticket')
            ->addArgument('servicename', InputArgument::REQUIRED, 'Service name')
            ->addArgument('ticketnumber', InputArgument::REQUIRED, 'Ticket number (integer)')
            ->addArgument('status', InputArgument::REQUIRED, 'Status')
            ->addArgument('estimatedwait', InputArgument::REQUIRED, 'Estimated wait (minutes)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('user_email');
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error("User with email '$email' not found.");
            return Command::FAILURE;
        }

        $ticket = new Ticket();
        $ticket->setUser($user);
        $ticket->setServicename($input->getArgument('servicename'));
        $ticket->setTicketnumber((int)$input->getArgument('ticketnumber'));
        $ticket->setStatus($input->getArgument('status'));
        $ticket->setCreatedat(new \DateTime());
        $ticket->setEstimatedwait((int)$input->getArgument('estimatedwait'));

        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        $io->success("Ticket #{$ticket->getTicketnumber()} for user '{$user->getFullname()}' created successfully!");

        return Command::SUCCESS;
    }
}