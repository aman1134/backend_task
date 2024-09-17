<?php

// src/Controller/ApiLoginController.php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class AuthenticationController extends AbstractController
{
    private $entityManager;
    private $jwtManager;

    public function __construct(EntityManagerInterface $entityManager, JWTTokenManagerInterface $jwtManager)
    {
        $this->entityManager = $entityManager;
        $this->jwtManager = $jwtManager;
    }

    /**
     * @Route("/login", name="api_login", methods={"POST"})
     */
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse(['error' => 'Invalid data.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $email = $data['email'];
        $password = $data['password'];

        // Fetch user by email
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user || !password_verify($password, $user->getPassword())) {
            return new JsonResponse(['error' => 'Invalid credentials.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Generate JWT token
        $token = $this->jwtManager->create($user);

        return new JsonResponse(['token' => $token]);
    }
}

