<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CategoryController extends AbstractController
{
    #[Route('/admin/category', name: 'admin_category_list')]
    public function list(CategoryRepository $categoryRepository, Request $request): Response
    {
        $search = $request->query->get('search', '');
        $categories = empty($search) 
            ? $categoryRepository->findAll() 
            : $categoryRepository->searchByName($search);

        return $this->render('admin/category/list.html.twig', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    #[Route('/admin/category/add', name: 'admin_category_add', methods: ['GET', 'POST'])]
    public function add(
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        if ($request->isMethod('POST')) {
            // CSRF Protection
            $token = $request->request->get('_token');
            if (!$csrfTokenManager->isTokenValid(new CsrfToken('category_add', $token))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('admin_category_list');
            }

            $name = trim($request->request->get('name', ''));
            $description = trim($request->request->get('description', ''));

            // Input validation and XSS prevention
            if (empty($name)) {
                $this->addFlash('error', 'Category name is required.');
                return $this->redirectToRoute('admin_category_list');
            }

            // Sanitize input to prevent XSS
            $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

            // Check if category already exists
            $existing = $em->getRepository(Category::class)->findOneBy(['name' => $name]);
            if ($existing) {
                $this->addFlash('error', 'Category with this name already exists.');
                return $this->redirectToRoute('admin_category_list');
            }

            $category = new Category();
            $category->setName($name);
            $category->setDescription($description ?: null);
            $category->setIsActive(true);

            $em->persist($category);
            $em->flush();

            $this->addFlash('success', "Category '{$name}' added successfully!");
            return $this->redirectToRoute('admin_category_list');
        }

        return $this->render('admin/category/add.html.twig');
    }

    #[Route('/admin/category/edit/{id}', name: 'admin_category_edit', methods: ['GET', 'POST'])]
    public function edit(
        Category $category,
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        if ($request->isMethod('POST')) {
            // CSRF Protection
            $token = $request->request->get('_token');
            if (!$csrfTokenManager->isTokenValid(new CsrfToken('category_edit', $token))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('admin_category_list');
            }

            $name = trim($request->request->get('name', ''));
            $description = trim($request->request->get('description', ''));

            // Input validation
            if (empty($name)) {
                $this->addFlash('error', 'Category name is required.');
                return $this->redirectToRoute('admin_category_edit', ['id' => $category->getId()]);
            }

            // Sanitize input
            $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

            // Check if another category with same name exists
            $existing = $em->getRepository(Category::class)->findOneBy(['name' => $name]);
            if ($existing && $existing->getId() !== $category->getId()) {
                $this->addFlash('error', 'Category with this name already exists.');
                return $this->redirectToRoute('admin_category_edit', ['id' => $category->getId()]);
            }

            $category->setName($name);
            $category->setDescription($description ?: null);

            $em->flush();

            $this->addFlash('success', "Category '{$name}' updated successfully!");
            return $this->redirectToRoute('admin_category_list');
        }

        return $this->render('admin/category/edit.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/admin/category/toggle/{id}', name: 'admin_category_toggle')]
    public function toggle(
        Category $category,
        EntityManagerInterface $em,
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        // CSRF Protection
        $token = $request->query->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('category_toggle', $token))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_category_list');
        }

        $category->setIsActive(!$category->isActive());
        $em->flush();

        $status = $category->isActive() ? 'activated' : 'deactivated';
        $this->addFlash('success', "Category '{$category->getName()}' {$status}!");

        return $this->redirectToRoute('admin_category_list');
    }

    #[Route('/admin/category/delete/{id}', name: 'admin_category_delete')]
    public function delete(
        Category $category,
        EntityManagerInterface $em,
        ServiceRepository $serviceRepository,
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        // CSRF Protection
        $token = $request->query->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('category_delete', $token))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_category_list');
        }

        // Check if category has services
        $services = $serviceRepository->findByCategory($category->getId());
        if (count($services) > 0) {
            $this->addFlash('error', 'Cannot delete category with existing services. Please remove or reassign services first.');
            return $this->redirectToRoute('admin_category_list');
        }

        $categoryName = $category->getName();
        $em->remove($category);
        $em->flush();

        $this->addFlash('success', "Category '{$categoryName}' deleted!");
        return $this->redirectToRoute('admin_category_list');
    }
}




