<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Service;
use App\Entity\Ticket;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ComplaintRepository;
use App\Repository\ServiceRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class AdminDashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(
        UserRepository $userRepository,
        TicketRepository $ticketRepository,
        ServiceRepository $serviceRepository,
        CategoryRepository $categoryRepository,
        ComplaintRepository $complaintRepository,
        Request $request
    ): Response {
        // Sanitize filter parameters to prevent XSS
        $serviceFilter = htmlspecialchars($request->query->get('service', ''), ENT_QUOTES, 'UTF-8');
        $statusFilter = htmlspecialchars($request->query->get('status', ''), ENT_QUOTES, 'UTF-8');

        // Get all data
        $users = $userRepository->findAll();
        $services = $serviceRepository->findAll();
        $categories = $categoryRepository->findAll();
        
        // Build ticket query with filters (using parameterized queries to prevent SQL injection)
        $qb = $ticketRepository->createQueryBuilder('t');
        if ($serviceFilter) {
            $qb->andWhere('t.servicename = :service')
               ->setParameter('service', $serviceFilter);
        }
        if ($statusFilter) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $statusFilter);
        }
        $tickets = $qb->orderBy('t.createdat', 'DESC')->getQuery()->getResult();

        // Enhanced Statistics
        $totalTickets = count($ticketRepository->findAll());
        $pendingTickets = count($ticketRepository->findBy(['status' => 'pending']));
        $activeTickets = count($ticketRepository->findBy(['status' => 'active']));
        $finishedTickets = count($ticketRepository->findBy(['status' => 'finished']));
        $totalUsers = count($users);
        $totalServices = count($services);
        $totalCategories = count($categories);
        $totalComplaints = count($complaintRepository->findAll());
        
        // Service usage statistics
        $serviceStats = [];
        foreach ($services as $service) {
            $serviceTickets = $ticketRepository->findBy(['servicename' => $service->getName()]);
            $serviceStats[$service->getName()] = [
                'total' => count($serviceTickets),
                'pending' => count(array_filter($serviceTickets, fn($t) => $t->getStatus() === 'pending')),
                'active' => count(array_filter($serviceTickets, fn($t) => $t->getStatus() === 'active')),
                'finished' => count(array_filter($serviceTickets, fn($t) => $t->getStatus() === 'finished')),
            ];
        }

        return $this->render('admin/dashboard.html.twig', [
            'users' => $users,
            'tickets' => $tickets,
            'services' => $services,
            'categories' => $categories,
            'serviceFilter' => $serviceFilter,
            'statusFilter' => $statusFilter,
            'totalTickets' => $totalTickets,
            'pendingTickets' => $pendingTickets,
            'activeTickets' => $activeTickets,
            'finishedTickets' => $finishedTickets,
            'totalUsers' => $totalUsers,
            'totalServices' => $totalServices,
            'totalCategories' => $totalCategories,
            'totalComplaints' => $totalComplaints,
            'serviceStats' => $serviceStats,
        ]);
    }

    #[Route('/admin/ticket/finish/{id}', name: 'admin_ticket_finish')]
    public function finishTicket(
        Ticket $ticket,
        EntityManagerInterface $em,
        TicketRepository $ticketRepository,
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        // CSRF Protection
        $token = $request->query->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('ticket_finish', $token))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_dashboard');
        }

        if ($ticket->getStatus() !== 'finished') {
            $ticket->setStatus('finished');
            $em->flush();
            $this->addFlash('success', 'Ticket #' . htmlspecialchars((string)$ticket->getTicketnumber(), ENT_QUOTES, 'UTF-8') . ' marked as finished!');
            
            // Automatically activate the next pending ticket for the same service
            $nextTicket = $ticketRepository->createQueryBuilder('t')
                ->where('t.servicename = :serviceName')
                ->andWhere('t.status = :status')
                ->setParameter('serviceName', $ticket->getServicename())
                ->setParameter('status', 'pending')
                ->orderBy('t.ticketnumber', 'ASC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            if ($nextTicket) {
                $nextTicket->setStatus('active');
                $em->flush();
                $this->addFlash('info', 'Ticket #' . htmlspecialchars((string)$nextTicket->getTicketnumber(), ENT_QUOTES, 'UTF-8') . ' automatically activated (next in queue)!');
            }
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admin/ticket/activate/{id}', name: 'admin_ticket_activate')]
    public function activateTicket(
        Ticket $ticket,
        EntityManagerInterface $em,
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        // CSRF Protection
        $token = $request->query->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('ticket_activate', $token))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_dashboard');
        }

        if ($ticket->getStatus() === 'pending') {
            $ticket->setStatus('active');
            $em->flush();
            $this->addFlash('success', 'Ticket #' . htmlspecialchars((string)$ticket->getTicketnumber(), ENT_QUOTES, 'UTF-8') . ' activated!');
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admin/service/add', name: 'admin_service_add', methods: ['GET', 'POST'])]
    public function addService(
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepository,
        CsrfTokenManagerInterface $csrfTokenManager,
        SluggerInterface $slugger,
        KernelInterface $kernel
    ): Response {
        if ($request->isMethod('POST')) {
            // CSRF Protection
            $token = $request->request->get('_token');
            if (!$csrfTokenManager->isTokenValid(new CsrfToken('service_add', $token))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('admin_dashboard');
            }

            $name = trim($request->request->get('name', ''));
            $description = trim($request->request->get('description', ''));
            $averageWaitTime = (int)$request->request->get('average_wait_time', 0);
            $categoryId = $request->request->get('category_id');

            // Input validation and XSS prevention
            if (empty($name) || $averageWaitTime <= 0) {
                $this->addFlash('error', 'Name and average wait time are required.');
                return $this->redirectToRoute('admin_dashboard');
            }

            // Sanitize input
            $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

            $service = new Service();
            $service->setName($name);
            $service->setDescription($description ?: null);
            $service->setAverageWaitTime($averageWaitTime);
            $service->setIsActive(true);

            // Handle category
            if ($categoryId) {
                $category = $categoryRepository->find((int)$categoryId);
                if ($category) {
                    $service->setCategory($category);
                }
            }

            // Handle image upload
            /** @var UploadedFile $imageFile */
            $imageFile = $request->files->get('image');
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($imageFile->getMimeType(), $allowedTypes)) {
                    $this->addFlash('error', 'Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.');
                    return $this->redirectToRoute('admin_dashboard');
                }

                // Validate file size (max 5MB)
                if ($imageFile->getSize() > 5 * 1024 * 1024) {
                    $this->addFlash('error', 'Image size must be less than 5MB.');
                    return $this->redirectToRoute('admin_dashboard');
                }

                try {
                    $projectDir = $kernel->getProjectDir();
                    $uploadDir = $projectDir . '/public/uploads/services';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $imageFile->move($uploadDir, $newFilename);
                    $service->setImage('uploads/services/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error uploading image.');
                    return $this->redirectToRoute('admin_dashboard');
                }
            }

            $em->persist($service);
            $em->flush();

            $this->addFlash('success', "Service '{$name}' added successfully!");
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admin/service/edit/{id}', name: 'admin_service_edit', methods: ['GET', 'POST'])]
    public function editService(
        Service $service,
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepository,
        CsrfTokenManagerInterface $csrfTokenManager,
        SluggerInterface $slugger,
        KernelInterface $kernel
    ): Response {
        if ($request->isMethod('POST')) {
            // CSRF Protection
            $token = $request->request->get('_token');
            if (!$csrfTokenManager->isTokenValid(new CsrfToken('service_edit', $token))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('admin_dashboard');
            }

            $name = trim($request->request->get('name', ''));
            $description = trim($request->request->get('description', ''));
            $averageWaitTime = (int)$request->request->get('average_wait_time', 0);
            $categoryId = $request->request->get('category_id');

            // Input validation
            if (empty($name) || $averageWaitTime <= 0) {
                $this->addFlash('error', 'Name and average wait time are required.');
                return $this->redirectToRoute('admin_service_edit', ['id' => $service->getId()]);
            }

            // Sanitize input
            $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

            $service->setName($name);
            $service->setDescription($description ?: null);
            $service->setAverageWaitTime($averageWaitTime);

            // Handle category
            if ($categoryId) {
                $category = $categoryRepository->find((int)$categoryId);
                $service->setCategory($category);
            } else {
                $service->setCategory(null);
            }

            // Handle image upload
            /** @var UploadedFile $imageFile */
            $imageFile = $request->files->get('image');
            if ($imageFile) {
                // Delete old image if exists
                if ($service->getImage()) {
                    $projectDir = $kernel->getProjectDir();
                    $oldImagePath = $projectDir . '/public/' . $service->getImage();
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                // Validate file type and size
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($imageFile->getMimeType(), $allowedTypes)) {
                    $this->addFlash('error', 'Invalid image type.');
                    return $this->redirectToRoute('admin_service_edit', ['id' => $service->getId()]);
                }

                if ($imageFile->getSize() > 5 * 1024 * 1024) {
                    $this->addFlash('error', 'Image size must be less than 5MB.');
                    return $this->redirectToRoute('admin_service_edit', ['id' => $service->getId()]);
                }

                try {
                    $projectDir = $kernel->getProjectDir();
                    $uploadDir = $projectDir . '/public/uploads/services';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $imageFile->move($uploadDir, $newFilename);
                    $service->setImage('uploads/services/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error uploading image.');
                    return $this->redirectToRoute('admin_service_edit', ['id' => $service->getId()]);
                }
            }

            $em->flush();

            $this->addFlash('success', "Service '{$name}' updated successfully!");
            return $this->redirectToRoute('admin_dashboard');
        }

        $categories = $categoryRepository->findAll();
        return $this->render('admin/service/edit.html.twig', [
            'service' => $service,
            'categories' => $categories,
        ]);
    }

    #[Route('/admin/service/toggle/{id}', name: 'admin_service_toggle')]
    public function toggleService(
        Service $service,
        EntityManagerInterface $em,
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        // CSRF Protection
        $token = $request->query->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('service_toggle', $token))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $service->setIsActive(!$service->isActive());
        $em->flush();

        $status = $service->isActive() ? 'activated' : 'deactivated';
        $this->addFlash('success', "Service '{$service->getName()}' {$status}!");

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admin/service/delete/{id}', name: 'admin_service_delete')]
    public function deleteService(
        Service $service,
        EntityManagerInterface $em,
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        KernelInterface $kernel
    ): Response {
        // CSRF Protection
        $token = $request->query->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('service_delete', $token))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_dashboard');
        }

        // Delete associated image
        if ($service->getImage()) {
            $projectDir = $kernel->getProjectDir();
            $imagePath = $projectDir . '/public/' . $service->getImage();
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $serviceName = $service->getName();
        $em->remove($service);
        $em->flush();

        $this->addFlash('success', "Service '{$serviceName}' deleted!");
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admin/user/delete/{id}', name: 'admin_user_delete')]
    public function deleteUser(
        User $user,
        EntityManagerInterface $em,
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        // CSRF Protection
        $token = $request->query->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('user_delete', $token))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_dashboard');
        }

        // Don't allow deleting users with tickets
        if (count($user->getTickets()) > 0) {
            $this->addFlash('error', 'Cannot delete user with existing tickets.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $userName = htmlspecialchars($user->getFullname(), ENT_QUOTES, 'UTF-8');
        $em->remove($user);
        $em->flush();

        $this->addFlash('success', "User '{$userName}' deleted!");
        return $this->redirectToRoute('admin_dashboard');
    }
}
