<?php

namespace App\Controller;

use App\Entity\Complaint;
use App\Repository\ComplaintRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ComplaintController extends AbstractController
{
    #[Route('/complaint/new', name: 'complaint_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ServiceRepository $serviceRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        if ($request->isMethod('POST')) {
            $token = $request->request->get('_token');
            if (!$csrfTokenManager->isTokenValid(new CsrfToken('complaint_new', $token))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('complaint_new');
            }

            $serviceId = (int)$request->request->get('service_id');
            $message = trim($request->request->get('message', ''));

            if (empty($message)) {
                $this->addFlash('error', 'Please enter your complaint message.');
                $services = $serviceRepository->findAll();
                return $this->render('complaint/new.html.twig', [
                    'services' => $services,
                ]);
            }

            $service = $serviceRepository->find($serviceId);
            if (!$service) {
                $this->addFlash('error', 'Service not found.');
                $services = $serviceRepository->findAll();
                return $this->render('complaint/new.html.twig', [
                    'services' => $services,
                ]);
            }

            $complaint = new Complaint();
            $complaint->setUser($this->getUser());
            $complaint->setService($service);
            $complaint->setMessage(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

            $em->persist($complaint);
            $em->flush();

            $this->addFlash('success', 'Your complaint has been sent to the admin.');
            return $this->redirectToRoute('user_dashboard');
        }

        $services = $serviceRepository->findAll();
        return $this->render('complaint/new.html.twig', [
            'services' => $services,
        ]);
    }

    #[Route('/admin/complaints', name: 'admin_complaints')]
    #[IsGranted('ROLE_ADMIN')]
    public function list(ComplaintRepository $complaintRepository): Response
    {
        $complaints = $complaintRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/complaints.html.twig', [
            'complaints' => $complaints,
        ]);
    }
}




