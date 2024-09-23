<?php

// src/Controller/ProductController.php

namespace App\Controller;

use App\Entity\User;
use App\Message\UploadAPIMessage;
use Psr\Log\LoggerInterface;
use App\Entity\Product;
use App\Entity\Document;
use App\Service\DebrickedApiService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use function Symfony\Component\String\b;

class ScanDocumentController extends AbstractController
{
    private $entityManager;
    private $validator;
    private $emailService;
    private $apiService;
    private $logger;
    private $bus;

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, EmailService $emailService, DebrickedApiService $apiService, LoggerInterface $logger, MessageBusInterface $bus)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->emailService = $emailService;
        $this->apiService = $apiService;
        $this->logger = $logger;
        $this->bus = $bus;
    }

    /**
     * @Route("/api/product/document/scan", name="upload_product_document", methods={"POST"})
     */
    public function scanProductDocument(Request $request): JsonResponse{

        $name = $request->get('productName');
        $release = $request->get('productRelease');
        $files = $request->files->get('documents');

        if (!$name ) {
            return new JsonResponse(['error' => 'Missing name'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if (!$release ) {
            return new JsonResponse(['error' => 'Missing release'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if (!$files ) {
            return new JsonResponse(['error' => 'Missing files'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['name' => $name, 'releaseVersion' => $release]);
        if (!$product) {
            return new JsonResponse(['error' => 'Invalid Product details'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->logger->info("files count: " . (count($files)));
        $success = array();

         foreach ($files as $file) {
            /** @var UploadedFile $file */
            if ($file instanceof UploadedFile) {
                $fileName = time().'.'.$file->getClientOriginalExtension();
                $this->logger->info("processing file : " . $fileName);
                try {
                    $file->move($this->getParameter('documents_directory'), $fileName);

                    $this->logger->info("filename: " . $fileName);

                    $document = $this->entityManager->getRepository(Document::class)->findOneBy(['name' => $file->getClientOriginalName(), 'product' => $product]);
                    if ( !$document ) {
                        $document = new Document();
                        $document->setName($file->getClientOriginalName());
                        $document->setFileName($fileName);
                        $document->setProduct($product);
                        $this->entityManager->persist($document);
                        $this->entityManager->flush();
                    }

                    $success[] = $document;
                } catch (FileException $e) {

                    $parameters = array();
                    $parameters['file'] = $file->getClientOriginalName();
                    $parameters['productName'] = $product->getName();
                    $parameters['release'] = $product->getReleaseVersion();
                    $this->emailService->sendEmailAsync($this->getUser()->getEmail(), 'File Upload Failed!', 'upload_failed', $parameters);
                    return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
                }
            }
         }

        $this->logger->error("success: " . count($success));

        foreach($success as $document){
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->getUser()->getUserIdentifier()]);

            $message =  new UploadAPIMessage( $user->getId(), $document->getProduct()->getId(), $document->getId() ) ;
            $this->logger->info('Dispatching message: '. var_export($message, true));
            $this->bus->dispatch( $message );

        }

        return new JsonResponse(['message' => 'Product documents are uploaded successfully'], JsonResponse::HTTP_CREATED);
    }
}
