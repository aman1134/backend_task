<?php

// src/Service/ExternalApiService.php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use App\Service\HttpClientService;
use App\Entity\Product;
use App\Entity\Scan;
use Doctrine\ORM\EntityManagerInterface;
use MongoDB\BSON\Document;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Exception\ExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

class DebrickedApiService
{
    private $entityManager;
    private $emailService;
    private $logger;
    private string $documentsDirectory;
    private $clientService;

    public function __construct(string $documentsDirectory, EntityManagerInterface $entityManager,HttpClientService $clientService, EmailService $emailService, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->emailService = $emailService;
        $this->logger = $logger;
        $this->documentsDirectory = $documentsDirectory;
        $this->clientService = $clientService;
    }

    public function uploadFile($document){

        $product = $document->getProduct();
        $filePath = $this->documentsDirectory . '/' . $document->getFileName();

        if (!file_exists($filePath)) {
            $this->logger->error("File not found: " . $filePath);
        }

        $fileContent = fopen($filePath, 'r');

        $this->logger->info( "file:");
        $this->logger->info(var_export($fileContent, true));

        $formData = [ 
            [
                'name'     => 'fileRelativePath',
                'contents' => $filePath
            ],
            [
                'name' => 'releaseName',
                'contents' => $product->getReleaseVersion(),
            ],
            [
                'name' => 'productName',
                'contents' => $product->getName(),
            ]
        ];

        $this->logger->info( "formData:");
        $this->logger->info( var_export($formData, true));

        $response = $this->clientService->doPost('/api/1.0/open/uploads/dependencies/files', $formData);

        return $response;
    }

    public function scanFile(string $ciUploadId)
    {
        $this->logger->info( "ciUploadId: " . $ciUploadId);
        $formData = [
            'ciUploadId' => $ciUploadId
        ];

        return $this->clientService->doPost('/api/1.0/open/uploads/dependencies/files', $formData);
    }

    public function checkStatus(string $ciUploadId)
    {
        $this->logger->info( "ciUploadId: " . $ciUploadId);
        $formData = [
            'ciUploadId' => $ciUploadId
        ];

        return $this->clientService->doPost('/api/1.0/open/uploads/dependencies/files', $formData);
    }
}
