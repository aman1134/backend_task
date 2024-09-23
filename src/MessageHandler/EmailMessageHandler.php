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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class EmailMessageHandler implements MessageHandlerInterface{

    private $logger;
    private $mailer;
    public function __construct(LoggerInterface $logger, MailerInterface $mailer) {
        $this->logger = $logger;
        $this->mailer = $mailer;
    }
    public function __invoke(SendEmailMessage $message){
        $this->logger->info('status response: ' . var_export($message, true));

        $this->mailer->send($message->getMessage());
    }
}