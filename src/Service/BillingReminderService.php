<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\Product;
use Carbon\Carbon;

class BillingReminderService
{
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;
    private HttpClientInterface $httpClient;
    private string $businessEmail;
    private string $discordWebhookUrl;

    public function __construct(
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        HttpClientInterface $httpClient,
        string $businessEmail,
        string $discordWebhookUrl
    ) {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->httpClient = $httpClient;
        $this->businessEmail = $businessEmail;
        $this->discordWebhookUrl = $discordWebhookUrl;
    }

    public function sendReminders(): void
    {
        $oneMonthLater = Carbon::now()->addMonth()->startOfDay();
        $products = $this->entityManager->getRepository(Product::class)->findBy([
            'billingDate' => $oneMonthLater
        ]);

        foreach ($products as $product) {

            $accountEmail = $product->getAccount()->getEmail();
            $productName = $product->getName();
            $billingAmount = $product->getBillingAmount();
            $billingDate = $product->getBillingDate()->format('Y-m-d');

            // Send Email to Customer
//            $this->sendEmail($accountEmail, "Upcoming Payment: $productName",
//                "Your payment of €$billingAmount for $productName is due on $billingDate.");
//
//            // Send Email to Business
//            $this->sendEmail($this->businessEmail, "Upcoming Invoice: $productName",
//                "$accountEmail has an upcoming payment of €$billingAmount for $productName on $billingDate.");

            // Send Discord Notification
            $this->sendDiscordNotification($accountEmail, $productName, $billingAmount, $billingDate);
        }
    }

    private function sendEmail(string $to, string $subject, string $content): void
    {
        $email = (new Email())
            ->from('no-reply@yourbusiness.com')
            ->to($to)
            ->subject($subject)
            ->text($content);

        $this->mailer->send($email);
    }

    private function sendDiscordNotification(string $email, string $productName, float $amount, string $date): void
    {
        $this->httpClient->request('POST', $this->discordWebhookUrl, [
            'json' => [
                'content' => "**Reminder**: $email needs to pay €$amount for $productName by $date."
            ]
        ]);
    }
}
