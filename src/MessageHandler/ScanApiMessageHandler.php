<?php


namespace App\MessageHandler;

use App\Entity\Document;
use App\Entity\Product;
use App\Entity\User;
use App\Message\ScanApiMessage;
use App\Message\StatusAPIMessage;
use App\Message\UploadAPIMessage;
use App\Service\DebrickedApiService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ScanApiMessageHandler implements MessageHandlerInterface
{

    private $entityManager;
    private $emailService;
    private $apiService;
    private $bus;
    private $logger;

    public function __construct(LoggerInterface $logger, EntityManagerInterface $entityManager, EmailService $emailService, DebrickedApiService $apiService, MessageBusInterface $bus)
    {
        $this->entityManager = $entityManager;
        $this->emailService = $emailService;
        $this->apiService = $apiService;
        $this->bus = $bus;
        $this->logger = $logger;
    }

    public function __invoke(ScanApiMessage $message)
    {

        $user = $this->entityManager->getRepository(User::class)->find($message->getUserId());
        $document = $this->entityManager->getRepository(\App\Entity\Document::class)->find($message->getDocumentId());
        $product= $document->getProduct();

        $parameters = array();
        $parameters['file'] = $document->getFileName();
        $parameters['productName'] = $product->getName();
        $parameters['release'] = $product->getReleaseVersion();
        $this->emailService->sendEmailAsync($user->getEmail(), 'File Scan is In Progress!', 'scan_in_progress', $parameters);

        $response = $this->apiService->scanFile($message->getCiUploadId());
        $this->logger->info('status response: ' . var_export($response, true));

        if ($response instanceof Response && $response->getStatusCode() === Response::HTTP_OK) {
            $responseData = $response->toArray();
            $this->logger->info('status $responseData: ' . var_export($responseData, true));

            $message = new StatusAPIMessage($message->getUserId(), $message->getCiUploadId(), $message->getDocumentId()) ;
            
            $this->bus->dispatch( $message );

        } else {
            $parameters = array();
            $parameters['file'] = $document->getName();
            $parameters['productName'] = $product->getName();
            $parameters['release'] = $product->getReleaseVersion();
            $this->emailService->sendEmailAsync($user->getEmail(), 'File Upload Failed!', 'upload_failed', $parameters);
        }
    }
}