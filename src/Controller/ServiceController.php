<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ServiceController extends AbstractController
{
    #[Route('/services', name: 'services')]
    public function index(
        ServiceRepository $serviceRepository,
        CategoryRepository $categoryRepository,
        Request $request
    ): Response {
        $search = trim($request->query->get('search', ''));
        $categoryId = $request->query->get('category');
        $categoryId = ($categoryId && $categoryId !== '') ? (int)$categoryId : null;

        // Sanitize search input to prevent XSS
        $search = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');

        // Get all active categories for filter dropdown
        $categories = $categoryRepository->findActiveCategories();
        
        // Get services based on filters
        $services = $serviceRepository->searchServices($search, $categoryId);

        // Group services by category for display
        $servicesByCategory = [];
        $uncategorizedServices = [];
        
        foreach ($services as $service) {
            if ($service->getCategory()) {
                $catId = $service->getCategory()->getId();
                if (!isset($servicesByCategory[$catId])) {
                    $servicesByCategory[$catId] = [
                        'category' => $service->getCategory(),
                        'services' => []
                    ];
                }
                $servicesByCategory[$catId]['services'][] = $service;
            } else {
                $uncategorizedServices[] = $service;
            }
        }
        
        // Count services per category for badge display
        $categoryCounts = [];
        foreach ($categories as $category) {
            $count = 0;
            foreach ($services as $service) {
                if ($service->getCategory() && $service->getCategory()->getId() === $category->getId()) {
                    $count++;
                }
            }
            $categoryCounts[$category->getId()] = $count;
        }

        return $this->render('service/index.html.twig', [
            'services' => $services,
            'servicesByCategory' => $servicesByCategory,
            'uncategorizedServices' => $uncategorizedServices,
            'categories' => $categories,
            'categoryCounts' => $categoryCounts,
            'search' => $search,
            'selectedCategory' => $categoryId,
        ]);
    }
}
