<?php

namespace App\Controller;

use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserDashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'user_dashboard')]
    public function index(TicketRepository $ticketRepository): Response
    {
        $user = $this->getUser();
        $tickets = $ticketRepository->findBy(
            ['user' => $user],
            ['createdat' => 'DESC']
        );

        return $this->render('user/dashboard.html.twig', [
            'tickets' => $tickets,
        ]);
    }
}

