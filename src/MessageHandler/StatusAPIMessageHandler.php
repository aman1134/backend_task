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

class StatusAPIMessageHandler implements MessageHandlerInterface
{

    private $entityManager;
    private $emailService;
    private $apiService;
    private $logger;

    public function __construct(LoggerInterface $logger, EntityManagerInterface $entityManager, EmailService $emailService, DebrickedApiService $apiService, MessageBusInterface $bus)
    {
        $this->entityManager = $entityManager;
        $this->emailService = $emailService;
        $this->apiService = $apiService;
        $this->logger = $logger;
    }

    public function __invoke(ScanApiMessage $message)
    {

        $user = $this->entityManager->getRepository(User::class)->find($message->getUserId());
        $document = $this->entityManager->getRepository(\App\Entity\Document::class)->find($message->getDocumentId());
        $product= $document->getProduct();

        $response = $this->apiService->checkStatus($message->getCiUploadId());
        $this->logger->info('status response: ' . var_export($response, true));

        if ($response instanceof Response && $response->getStatusCode() === Response::HTTP_OK) {
            $responseData = $response->toArray();
            $this->logger->info('status $responseData: ' . var_export($responseData, true));


            $parameters = array();
            $parameters['file'] = $document->getName();
            $parameters['productName'] = $product->getName();
            $parameters['release'] = $product->getReleaseVersion();
            $parameters['count'] = $responseData['vulnerabilitiesFound'];
            if($parameters['count'] > 2) {
                foreach ($responseData['automationRules'] as $rule) {
                    foreach ($rule['triggerEvents'] as $event) {
                        $cve = array();
                        $cve['dependency'] = $event['dependency'];
                        $cve['id'] = $event['cve'];
                        $cve['link'] = $event['cveLink'];
                        $parameters['cve'][] = $cve;
                    }
                }
                $this->logger->info('status parameters: ' . var_export($parameters, true));
                $this->emailService->sendEmailAsync($user->getEmail(), 'File Scan Completed!', 'scan_completed', $parameters);
            }
        }
    }
}