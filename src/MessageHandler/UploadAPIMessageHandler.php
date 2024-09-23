<?php

namespace App\MessageHandler;

use App\Entity\Document;
use App\Entity\Product;
use App\Entity\User;
use App\Message\ScanApiMessage;
use App\Message\UploadAPIMessage;
use App\Service\DebrickedApiService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class UploadAPIMessageHandler implements MessageHandlerInterface{

    private $entityManager;
    private $emailService;
    private $apiService;
    private $bus;
    private $logger;
    public function __construct(LoggerInterface $logger, EntityManagerInterface $entityManager, EmailService $emailService, DebrickedApiService $apiService, MessageBusInterface $bus){
        $this->entityManager  = $entityManager;
        $this->emailService = $emailService;
        $this->apiService = $apiService;
        $this->bus = $bus;
        $this->logger = $logger;
    }
    public function __invoke(UploadAPIMessage $message){

        $this->logger->info('message: '. var_export($message, true));
        $this->logger->info('userId: ' . $message->getUserId() . ' docId: ' . $message->getDocId());

        $user = $this->entityManager->getRepository(User::class)->find($message->getUserId());
        $document = $this->entityManager->getRepository(\App\Entity\Document::class)->find($message->getDocId());
        $product= $document->getProduct();

        $response = $this->apiService->uploadFile($document);
        $this->logger->info('upload response: ' . var_export($response, true));

        if($response instanceof Response && $response->getStatusCode() === Response::HTTP_OK) {
            $responseData = $response->toArray();
            $this->logger->info('upload response: ' . var_export($responseData, true));


            $parameters = array();
            $parameters['file'] = $document->getName();
            $parameters['productName'] = $product->getName();
            $parameters['release'] = $product->getReleaseVersion();
            $this->emailService->sendEmailAsync($user->getEmail(), 'File Upload Successful!', 'upload_success', $parameters);

            $message = new ScanApiMessage($user->getId(), $document->getId(), $responseData['ciUploadId']);
            
            $this->bus->dispatch( $message );

        }else {
            $parameters = array();
            $parameters['file'] = $document->getName();
            $parameters['productName'] = $product->getName();
            $parameters['release'] = $product->getReleaseVersion();
            $this->emailService->sendEmailAsync($user->getEmail(), 'File Upload Failed!', 'upload_failed', $parameters);
        }
        return false;
    }
}