<?php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\Ticket;
use App\Repository\ServiceRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TicketController extends AbstractController
{
    #[Route('/ticket/book/{serviceId}', name: 'ticket_book')]
    public function book(int $serviceId, ServiceRepository $serviceRepository, TicketRepository $ticketRepository, EntityManagerInterface $em): Response
    {
        $service = $serviceRepository->find($serviceId);
        if (!$service || !$service->isActive()) {
            $this->addFlash('error', 'Service not found or inactive.');
            return $this->redirectToRoute('services');
        }

        // Get the next ticket number for this service
        $lastTicket = $ticketRepository->createQueryBuilder('t')
            ->where('t.servicename = :serviceName')
            ->setParameter('serviceName', $service->getName())
            ->orderBy('t.ticketnumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $nextTicketNumber = $lastTicket ? $lastTicket->getTicketnumber() + 1 : 1;

        // Calculate estimated wait time based on pending tickets
        $pendingTickets = $ticketRepository->createQueryBuilder('t')
            ->where('t.servicename = :serviceName')
            ->andWhere('t.status != :finished')
            ->setParameter('serviceName', $service->getName())
            ->setParameter('finished', 'finished')
            ->getQuery()
            ->getResult();

        $estimatedWait = count($pendingTickets) * ($service->getAverageWaitTime() ?? 5);

        $ticket = new Ticket();
        $ticket->setUser($this->getUser());
        $ticket->setService($service);
        $ticket->setTicketnumber($nextTicketNumber);
        $ticket->setStatus('pending');
        $ticket->setCreatedat(new \DateTime());
        $ticket->setEstimatedwait($estimatedWait);

        $em->persist($ticket);
        $em->flush();

        $this->addFlash('success', "Ticket #{$nextTicketNumber} booked successfully! Estimated wait: {$estimatedWait} minutes.");
        return $this->redirectToRoute('user_dashboard');
    }
}

