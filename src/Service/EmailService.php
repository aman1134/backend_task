<?php

namespace App\Service;

use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Messenger\MessageBusInterface;
use Twig\Environment;

class EmailService
{
    private $mailer;
    private $bus;
    private $twig;

    public function __construct(MailerInterface $mailer,Environment $twig, MessageBusInterface $bus)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->bus = $bus;
    }

    public function sendEmailAsync(string $to, string $subject, string $templateName, array $parameters = array())
    {
        $content = $this->twig->render('email\\' . $templateName . '.html.twig', $parameters);
        $email = (new Email())
            ->from('aman.rastogi.2312@gmail.com')
            ->to($to)
            ->subject($subject)
            ->text($content);

        $this->bus->dispatch(new SendEmailMessage($email));
    }
}
