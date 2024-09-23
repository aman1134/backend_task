<?php

namespace App\Service;

use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpClientService{

    private $client;
    private string $apiUrl;
    private string $bearerToken;
    private $logger;

    public function __construct(string $apiUrl, string $bearerToken, HttpClientInterface $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->apiUrl = $apiUrl;
        $this->bearerToken = $bearerToken;
        $this->logger = $logger;
    }

    public function doPost($endPoint, $formData)
    {
        try{
            return $this->client->request('POST', $this->apiUrl . $endPoint, [
                'headers' => [
                    'accept' => '*/*',
                    'Authorization' => $this->bearerToken,
                    'Content-Type' => 'application/json',
                ],
                'body' => $formData,
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error( "Exception caught while make API call to debricked: " . $e->getMessage());
            return $e->getMessage();
        }
    }
}