<?php

// src/Controller/ProductController.php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProductController extends AbstractController
{
    private $entityManager;
    private $validator;

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
    }

    /**
     * @Route("/api/products/register", name="register_product", methods={"POST"})
     */
    public function registerProduct(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['name']) || !isset($data['release'])) {
            return new JsonResponse(['error' => 'Missing required fields'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Create a new Product instance
        $product = new Product();
        $product->setName($data['name']);
        $product->setReleaseVersion($data['release']); // Assuming release is a date in string format

        // Validate the Product entity
        $errors = $this->validator->validate($product);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Persist and save the product to the database
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Product registered successfully'], JsonResponse::HTTP_CREATED);
    }
}
