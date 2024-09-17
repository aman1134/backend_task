<?php

// src/Service/ExternalApiService.php

namespace App\Service;

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
    public function __construct(EntityManagerInterface $entityManager, $client, string $apiUrl, string $bearerToken, EmailService $emailService)
    {
        $this->client = $client;
        $this->apiUrl = $apiUrl;
        $this->bearerToken = $bearerToken;
        $this->entityManager = $entityManager;
        $this->emailService = $emailService;
    }

    public function uploadFile(array $success, Product $product)
    {
        foreach ($success as $file) {
            $fileContent = fopen($file, 'r');

            // Prepare the form data for the request
            $formData = [
                'commitName' => '',
                'repositoryUrl' => '',
                'fileData' => [
                    'contents' => $fileContent,
                    'filename' => $file
                ],
                'fileRelativePath' => '',
                'branchName' => '',
                'defaultBranchName' => '',
                'releaseName' => $product->getReleaseVersion(),
                'repositoryName' => '',
                'productName' => $product->getName(),
            ];

            // Make the POST request
            try {
                $response = $this->client->request('POST', $this->apiUrl . '/api/1.0/open/uploads/dependencies/files', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->bearerToken,
                        'Content-Type' => 'multipart/form-data',
                    ],
                    'multipart' => $formData,
                ]);
            } catch (TransportExceptionInterface $e) {
                return false;
            }

            // Check the response status and content
            if ($response->getStatusCode() === Response::HTTP_OK) {
                $responseData = $response->toArray();
                return $this->scanFile($responseData['ciUploadId'], $file, $product);
            }
        }
        return false;
    }

    public function scanFile(string $ciUploadId, $file, $product)
    {
        $formData = [
            'ciUploadId' => $ciUploadId,
            'returnCommitData' => 'false',
            'versionHint' => 'false',
        ];

        // Make the POST request
        $response = $this->client->request('POST', $this->apiUrl . '/api/1.0/open/finishes/dependencies/files/uploads', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->bearerToken,
                'Accept' => 'application/json',
                'Content-Type' => 'multipart/form-data',
            ],
            'multipart' => $formData,
        ]);

        // Check the response status and content
        if ($response->getStatusCode() === Response::HTTP_OK) {
            $parameters = array();
            $parameters['files'] = array($file);
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
                'Authorization' => 'Bearer ' . $this->bearerToken,
                'accept' => '*/*',
            ],
            'query' => [
                'ciUploadId' => $ciUploadId,
            ],
        ]);

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
            $parameters['files'] = array($file);
            $parameters['productName'] = $product->getName();
            $parameters['release'] = $product->getReleaseVersion();
            $parameters['count'] = count($responseData['vulnerabilities']);

            if($parameters['count'] != null && $parameters['count'] > 6) {
                $this->emailService->sendEmailAsync($this->getUser()->getEmail(), 'File Scan is Completed!', 'scan_completed', $parameters);
            }
            return $responseData;

        }
        return false; // This will decode the JSON response to an array
    }
}
