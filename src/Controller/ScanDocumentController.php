<?php

// src/Controller/ProductController.php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use App\Entity\Product;
use App\Entity\Document;
use App\Service\DebrickedApiService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ScanDocumentController extends AbstractController
{
    private $entityManager;
    private $validator;
    private $emailService;
    private $apiService;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, EmailService $emailService, DebrickedApiService $apiService, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->emailService = $emailService;
        $this->apiService = $apiService;
        $this->logger = $logger;
    }

    /**
     * @Route("/api/product/document/scan", name="upload_product_document", methods={"POST"})
     */
    public function scanProductDocument(Request $request): JsonResponse
    {
        $name = $request->get('productName');
        $release = $request->get('productRelease');
        $file = $request->files->get('documents');

        // Validate required fields
        if (!$name ) {
            return new JsonResponse(['error' => 'Missing name'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if (!$release ) {
            return new JsonResponse(['error' => 'Missing release'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if (!$file ) {
            return new JsonResponse(['error' => 'Missing files'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['name' => $name, 'releaseVersion' => $release]);
        if (!$product) {
            return new JsonResponse(['error' => 'Invalid Product details'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // $this->logger->info("files count: " . (count($files)));
        $success = array();
        // Handle file uploads
        // foreach ($files as $file) {
            $this->logger->error( "inside loop");
            $this->logger->error( "bool: " . $file instanceof UploadedFile);
            /** @var UploadedFile $file */
            if ($file instanceof UploadedFile) {
                $fileName = uniqid().'.'.$file->guessExtension();
                try {
                    // Move the file to the directory where documents are stored
                    $file->move($this->getParameter('documents_directory'), $fileName);

                    $this->logger->info("filename: " . $fileName);
                    // Create a new Document entity for each file
                    $document = new Document();
                    $document->setName($file->getClientOriginalName());
                    $document->setFileName($fileName);
                    $document->setProduct($product);

                    // Persist document entity
                    $this->entityManager->persist($document);

                    $success[] = $document;
                } catch (FileException $e) {

                    $parameters = array();
                    $parameters['files'] = array($file->getClientOriginalName());
                    $parameters['productName'] = $product->getName();
                    $parameters['release'] = $product->getReleaseVersion();
                    $this->emailService->sendEmailAsync($this->getUser()->getEmail(), 'File Upload Failed!', 'upload_failed', $parameters);
                    return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
                }
            }
        // }

        $this->logger->error("success: " . count($success));
        
        $fileNames = array();
        foreach($success as $document){
            $fileNames = $document->getName();
        }

        $response = $this->apiService->uploadFile($success, $product);

        if(!$response || $response->getStatusCode() !== Response::HTTP_OK){
            $parameters = array();
            $parameters['files'] = $fileNames;
            $parameters['productName'] = $product->getName();
            $parameters['release'] = $product->getReleaseVersion();
            $this->emailService->sendEmailAsync($this->getUser()->getEmail(), 'File Upload Failed!', 'upload_failed', $parameters);

            $this->logger->error("response: " . $response);
            return new JsonResponse(['error' => 'Failed to upload document'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        $parameters = array();
        $parameters['files'] = $fileNames;
        $parameters['productName'] = $product->getName();
        $parameters['release'] = $product->getReleaseVersion();

        $this->logger->error("response: " + $response);
        $this->emailService->sendEmailAsync($this->getUser()->getEmail(), 'File Upload Successful!', 'upload_success', $parameters);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Product and documents uploaded successfully'], JsonResponse::HTTP_CREATED);
    }
}
