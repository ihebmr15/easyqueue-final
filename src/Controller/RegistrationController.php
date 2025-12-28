<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'register')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));
            $password = $request->request->get('password', '');
            $fullname = trim($request->request->get('fullname', ''));

            // Input validation
            if (empty($email) || empty($password) || empty($fullname)) {
                $this->addFlash('error', 'All fields are required.');
                return $this->render('security/register.html.twig');
            }

            // Email validation
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Invalid email format.');
                return $this->render('security/register.html.twig');
            }

            // Password strength validation
            if (strlen($password) < 6) {
                $this->addFlash('error', 'Password must be at least 6 characters long.');
                return $this->render('security/register.html.twig');
            }

            // Sanitize input to prevent XSS
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            $fullname = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');

            // Check for existing user using parameterized query (prevent SQL injection)
            $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $this->addFlash('error', 'Email already registered.');
                return $this->render('security/register.html.twig');
            }

            $user = new User();
            $user->setEmail($email);
            $user->setFullname($fullname);
            $user->setCreatedat(new \DateTime());
            $user->setRoles([]);
            $user->setPassword($passwordHasher->hashPassword($user, $password));

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Registration successful! Please login.');
            return $this->redirectToRoute('login');
        }

        return $this->render('security/register.html.twig');
    }
}
