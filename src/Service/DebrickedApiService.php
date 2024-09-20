<?php

// src/Service/ExternalApiService.php

namespace App\Service;

use Psr\Log\LoggerInterface;
use App\Service\EmailService;
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
    private $client;
    private string $apiUrl;
    private string $bearerToken;
    private $entityManager;
    private $emailService;
    private $logger;
    private string $documentsDirectory;

    public function __construct(string $apiUrl, string $bearerToken, string $documentsDirectory, EntityManagerInterface $entityManager,HttpClientInterface $client, EmailService $emailService, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->apiUrl = $apiUrl;
        $this->bearerToken = $bearerToken;
        $this->entityManager = $entityManager;
        $this->emailService = $emailService;
        $this->logger = $logger;
        $this->documentsDirectory = $documentsDirectory;
    }

    public function uploadFile(array $success, Product $product)
    {
        foreach ($success as $document) {
            $filePath = $this->documentsDirectory . '/' . $document->getFileName();

            if (!file_exists($filePath)) {
                $this->logger->error("File not found: " . $filePath);
            }

            $fileContent = fopen($filePath, 'r');

            $this->logger->info( "file:");
            $this->logger->info(print_r($fileContent));

            // Prepare the form data for the request
            $formData = [
                [
                    'name'     => 'fileData',
                    'contents' => $fileContent,
                    'filename' => $document->getName()
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
            $this->logger->info( print_r($formData));
            // Make the POST request
            try {
                $response = $this->client->request('POST', $this->apiUrl . '/api/1.0/open/uploads/dependencies/files', [
                    'headers' => [
                        'Authorization' => $this->bearerToken,
                        'Content-Type' => 'multipart/form-data',
                    ],
                    'body' => $formData,
                ]);
            } catch (TransportExceptionInterface $e) {
                $this->logger->error( "Exception cosught in DebrickedApiService: " . $e->getMessage());
                return false;
            }

            // Check the response status and content
            if ($response->getStatusCode() === Response::HTTP_OK) {
                $this->logger->info( "reponse:");
                $this->logger->info( print_r($response));
                $responseData = $response->toArray();
                $this->logger->info( "responseData:");
                $this->logger->info( print_r($responseData));
                return $this->scanFile($responseData['ciUploadId'], $file, $product);
            }else {
                // code...
                $this->logger->error("Upload failed: ". print_r($response));
            }
        }
        return false;
    }

    public function scanFile(string $ciUploadId, $file, $product)
    {
        $this->logger->info( "ciUploadId: " . ciUploadId);
        $formData = [
            'ciUploadId' => $ciUploadId,
            'returnCommitData' => 'false',
            'versionHint' => 'false',
        ];

        // Make the POST request
        $response = $this->client->request('POST', $this->apiUrl . '/api/1.0/open/finishes/dependencies/files/uploads', [
            'headers' => [
                'Authorization' => $this->bearerToken,
                'Accept' => 'application/json',
                'Content-Type' => 'multipart/form-data',
            ],
            'multipart' => $formData,
        ]);

        $this->logger->info( "response: ");
        $this->logger->info( print_r($response));

        // Check the response status and content
        if ($response->getStatusCode() === Response::HTTP_OK) {
            $parameters = array();
            $parameters['files'] = array($file->getName());
            $parameters['productName'] = $product->getName();
            $parameters['release'] = $product->getReleaseVersion();
            $this->emailService->sendEmailAsync($this->getUser()->getEmail(), 'File Scan is In Progress!', 'scan_in_progress', $parameters);
            return $this->getScanStatus($ciUploadId, $file, $product);
        }

        return $response->getContent(false);
    }

    public function getScanStatus(string $ciUploadId, $file, $product)
    {
        $response = $this->client->request('GET', $this->apiUrl . '/api/1.0/open/ci/upload/status', [
            'headers' => [
                'Authorization' => $this->bearerToken,
                'accept' => '*/*',
            ],
            'query' => [
                'ciUploadId' => $ciUploadId,
            ],
        ]);
        $this->logger->info("response:");
        $this->logger->info(print_r($response));

        if ($response->getStatusCode() === Response::HTTP_OK) {
            $responseData = $response->getContent()->toArray();

            $document = $this->entityManager->getRepository(Document::class)->findOneBy(['fileName' => $file]);

            $scan = new Scan();
            $scan->setProgress($responseData['progress']);
            $scan->setStatus($responseData['progress'] == '100' ? 'completed' : 'in progress');
            $scan->setDocument($document);

            $this->entityManager->persist($scan);
            $this->entityManager->flush();

            $parameters = array();
            $parameters['files'] = array($file->getName());
            $parameters['productName'] = $product->getName();
            $parameters['release'] = $product->getReleaseVersion();
            $parameters['count'] = count($responseData['vulnerabilities']);

            $this->logger->info(print_r($parameters));

            if($parameters['count'] != null && $parameters['count'] > 6) {
                $this->emailService->sendEmailAsync($this->getUser()->getEmail(), 'File Scan is Completed!', 'scan_completed', $parameters);
            }
            return $responseData;

        }
        return false; // This will decode the JSON response to an array
    }
}
