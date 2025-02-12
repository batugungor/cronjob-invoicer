<?php

namespace App\Command;

use App\Entity\Product;
use App\Repository\ProductRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:check-billing-dates',
    description: 'Checks for products with billing dates in the upcoming week.',
)]
class SendBillingRemindersCommand extends Command
{
    private ProductRepository $productRepository;
    private HttpClientInterface $httpClient;

    public function __construct(ProductRepository $productRepository, HttpClientInterface $httpClient)
    {
        parent::__construct();
        $this->productRepository = $productRepository;
        $this->httpClient = $httpClient;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new DateTime(); // Get the current date (Sunday if the cron runs then)
        $products = $this->productRepository->getAllAccounts($today);

        if (empty($products)) {
            $output->writeln('<info>No products found for the upcoming week.</info>');
        } else {
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
        }

        return Command::SUCCESS;
    }
}
