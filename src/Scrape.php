<?php

declare(strict_types=1);

namespace App;

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;

final class Scrape
{
    private const START_URL = 'https://www.magpiehq.com/developer-challenge/smartphones';

    /**
     * @var array<string, Product>
     */
    private array $products = [];

    /**
     * @var array<string, bool>
     */
    private array $visitedPageUrls = [];

    public function run(): void
    {
        $this->scrapeAllPages();
        $this->writeOutput();
    }

    private function scrapeAllPages(): void
    {
        $pendingPageUrls = [self::START_URL];

        while ($pendingPageUrls !== []) {
            $pageUrl = array_shift($pendingPageUrls);

            if ($pageUrl === null || isset($this->visitedPageUrls[$pageUrl])) {
                continue;
            }

            $this->visitedPageUrls[$pageUrl] = true;

            $document = ScrapeHelper::fetchDocument($pageUrl);

            $this->extractProductsFromDocument($document);

            foreach (ScrapeHelper::extractPaginationUrls($document) as $discoveredPageUrl) {
                if (! isset($this->visitedPageUrls[$discoveredPageUrl])) {
                    $pendingPageUrls[] = $discoveredPageUrl;
                }
            }
        }
    }

    private function extractProductsFromDocument(Crawler $document): void
    {
        foreach ($document->filter('.product') as $productNode) {
            $this->extractProductsFromNode(new Crawler($productNode, $document->getUri()));
        }
    }

    private function extractProductsFromNode(Crawler $productNode): void
    {
        $productName = ScrapeHelper::getText($productNode, '.product-name');
        $capacityText = ScrapeHelper::getText($productNode, '.product-capacity');
        $imagePath = ScrapeHelper::getAttribute($productNode, 'img', 'src');
        $priceText = ScrapeHelper::getText($productNode, 'div.my-8.block.text-center.text-lg');

        if ($productName === null) {
            throw new \RuntimeException('Missing product name.');
        }

        if ($capacityText === null) {
            throw new \RuntimeException(sprintf('Missing capacity for product "%s".', $productName));
        }

        if ($imagePath === null) {
            throw new \RuntimeException(sprintf('Missing image for product "%s".', $productName));
        }

        if ($priceText === null) {
            throw new \RuntimeException(sprintf('Missing price for product "%s".', $productName));
        }

        $title = ScrapeHelper::buildProductTitle($productName, $capacityText);
        $capacityMB = ScrapeHelper::parseCapacityMb($capacityText);
        $imageUrl = ScrapeHelper::buildAbsoluteUrl($productNode->getUri(), $imagePath);
        $price = ScrapeHelper::parsePrice($priceText);
        $colourOptions = ScrapeHelper::extractColourOptions($productNode);

        $infoBlocks = ScrapeHelper::extractInfoBlocks($productNode);

        if ($infoBlocks === []) {
            throw new \RuntimeException(sprintf('Missing availability information for product "%s".', $title));
        }

        $availabilityText = ScrapeHelper::cleanAvailabilityText($infoBlocks[0]);
        $isAvailable = ScrapeHelper::parseIsAvailable($availabilityText);

        $shippingText = ScrapeHelper::cleanShippingText($infoBlocks[1] ?? null);
        $shippingDate = ScrapeHelper::parseShippingDate($shippingText);

        foreach ($colourOptions as $colour) {
            $product = new Product(
                title: $title,
                price: $price,
                imageUrl: $imageUrl,
                capacityMB: $capacityMB,
                colour: $colour,
                availabilityText: $availabilityText,
                isAvailable: $isAvailable,
                shippingText: $shippingText,
                shippingDate: $shippingDate,
            );

            $this->storeProduct($product);
        }
    }

    private function storeProduct(Product $product): void
    {
        $this->products[$product->dedupeKey()] = $product;
    }

    private function writeOutput(): void
    {
        $output = array_map(
            static fn(Product $product): array => $product->toArray(),
            array_values($this->products)
        );

        usort(
            $output,
            static function (array $leftProduct, array $rightProduct): int {
                return [$leftProduct['title'], $leftProduct['colour']]
                    <=> [$rightProduct['title'], $rightProduct['colour']];
            }
        );

        $json = json_encode(
            $output,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new \RuntimeException('Failed to encode output JSON.');
        }

        file_put_contents(__DIR__ . '/../output.json', $json);
    }
}

$scrape = new Scrape();
$scrape->run();