<?php

namespace App\Controller;

use App\Entity\Product;
use Carbon\Carbon;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;


final class HomeController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;

    public function __construct(EntityManagerInterface $entityManager, HttpClientInterface $httpClient)
    {
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
    }

    #[Route('/home', name: 'app_home')]
    public function index(): Response
    {
        $today = new DateTime(); // Get the current date (Sunday if the cron runs then)
        $products = $this->entityManager->getRepository(Product::class)->getAllAccounts($today);

        $list = [];


        $this->httpClient->request('POST', "https://discord.com/api/webhooks/1337129298926108712/6cG75iAcT5f4NJTXmE-eTd43Kpsk6RQjbMwvLyooq6-sodyoeazpIhCtQqgHPcBK3LsR", [
            "json" => [
                "content" => "Alle betalingen van {$today->format('d-m-Y')} - {$today->modify('+1 week')->format('d-m-Y')}",
            ]
        ]);

        foreach ($products as $product) {
            if (!array_key_exists($product->getAccount()->getId(), $list)) {
                $list[$product->getAccount()->getId()]["account"] = $product->getAccount();
                $list[$product->getAccount()->getId()]["total"] = 0;
            }

            $list[$product->getAccount()->getId()]["products"][] = $product;
            $list[$product->getAccount()->getId()]["total"] = $list[$product->getAccount()->getId()]["total"] + $product->getBillingAmount();
        }

        foreach ($list as $account) {
            $invoicedProducts = [];

            foreach ($account["products"] as $product) {
                $invoicedProducts[] = [
                    "name" => "Product",
                    "value" => "{$product->getName()}"
                ];
            }

            $invoicedProducts[] = [
                "name" => "Totaal",
                "value" => "€{$account["total"]}",
            ];

            $structure = [
                [
                    "name" => "#",
                    "value" => "{$account["account"]->getId()}",
                    "inline" => true
                ],
                [
                    "name" => "Email",
                    "value" => "{$account["account"]->getEmail()}",
                    "inline" => true
                ],
            ];

            $structure = array_merge($structure, $invoicedProducts);

            $this->httpClient->request('POST', "https://discord.com/api/webhooks/1337129298926108712/6cG75iAcT5f4NJTXmE-eTd43Kpsk6RQjbMwvLyooq6-sodyoeazpIhCtQqgHPcBK3LsR", [
                "json" => [
                    "embeds" => [
                        [
                            "fields" => $structure
                        ]
                    ]
                ]
            ]);
        }


        dd($products);

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }
}
