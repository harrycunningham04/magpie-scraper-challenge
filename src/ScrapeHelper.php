<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

final class ScrapeHelper
{
    private const DEFAULT_HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (compatible; MagpieChallenge/1.0)',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    ];

    public static function fetchDocument(string $url): Crawler
    {
        $client = new Client([
            'headers' => self::DEFAULT_HEADERS,
            'timeout' => 15,
            'allow_redirects' => true,
        ]);

        $response = $client->get($url);
        $html = (string) $response->getBody();

        return new Crawler($html, $url);
    }

    public static function getText(Crawler $node, string $selector): ?string
    {
        $matches = $node->filter($selector);

        if ($matches->count() === 0) {
            return null;
        }

        return self::normaliseWhitespace($matches->first()->text());
    }

    public static function getAttribute(Crawler $node, string $selector, string $attribute): ?string
    {
        $matches = $node->filter($selector);

        if ($matches->count() === 0) {
            return null;
        }

        $value = $matches->first()->attr($attribute);

        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    public static function normaliseWhitespace(string $text): string
    {
        $trimmedText = trim($text);
        $singleSpacedText = preg_replace('/\s+/', ' ', $trimmedText);

        return $singleSpacedText ?? $trimmedText;
    }

    public static function buildProductTitle(string $productName, string $capacityText): string
    {
        $normalisedName = self::normaliseWhitespace($productName);
        $normalisedCapacity = self::normaliseCapacityText($capacityText);

        return sprintf('%s %s', $normalisedName, $normalisedCapacity);
    }

    public static function normaliseCapacityText(string $capacityText): string
    {
        $normalisedCapacityText = strtoupper(self::normaliseWhitespace($capacityText));

        if (! preg_match('/^(\d+)\s*(GB|MB)$/', $normalisedCapacityText, $matches)) {
            throw new \RuntimeException(sprintf('Unable to normalise capacity text: "%s"', $capacityText));
        }

        return sprintf('%d%s', (int) $matches[1], $matches[2]);
    }

    public static function parsePrice(string $priceText): float
    {
        $normalisedPriceText = str_replace(',', '', self::normaliseWhitespace($priceText));

        if (! preg_match('/(\d+(?:\.\d{1,2})?)/', $normalisedPriceText, $matches)) {
            throw new \RuntimeException(sprintf('Unable to parse price from text: "%s"', $priceText));
        }

        return (float) $matches[1];
    }

    public static function parseCapacityMb(string $capacityText): int
    {
        $normalisedCapacityText = strtoupper(self::normaliseWhitespace($capacityText));

        if (! preg_match('/(\d+)\s*(GB|MB)\b/', $normalisedCapacityText, $matches)) {
            throw new \RuntimeException(sprintf('Unable to parse capacity from text: "%s"', $capacityText));
        }

        $amount = (int) $matches[1];
        $unit = $matches[2];

        if ($unit === 'GB') {
            return $amount * 1000;
        }

        return $amount;
    }

    public static function cleanAvailabilityText(string $availabilityText): string
    {
        $normalisedAvailabilityText = self::normaliseWhitespace($availabilityText);

        return preg_replace('/^Availability:\s*/i', '', $normalisedAvailabilityText) ?? $normalisedAvailabilityText;
    }

    public static function parseIsAvailable(string $availabilityText): bool
    {
        $normalisedAvailability = strtolower(self::normaliseWhitespace($availabilityText));

        return ! str_contains($normalisedAvailability, 'out of stock')
            && ! str_contains($normalisedAvailability, 'unavailable');
    }

    public static function cleanShippingText(?string $shippingText): ?string
    {
        if ($shippingText === null) {
            return null;
        }

        $normalisedShippingText = self::normaliseWhitespace($shippingText);

        return $normalisedShippingText === '' ? null : $normalisedShippingText;
    }

    public static function parseShippingDate(?string $shippingText): ?string
    {
        if ($shippingText === null || trim($shippingText) === '') {
            return null;
        }

        $normalisedShippingText = self::normaliseWhitespace($shippingText);

        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $normalisedShippingText, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\b(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{4})\b/', $normalisedShippingText, $matches)) {
            $dateString = sprintf('%s %s %s', $matches[1], $matches[2], $matches[3]);
            $timestamp = strtotime($dateString);

            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        if (preg_match('/\b(?:on|from|by)\s+(\d{1,2})(?:st|nd|rd|th)?\s+([A-Za-z]{3,9})\s+(\d{4})\b/i', $normalisedShippingText, $matches)) {
            $dateString = sprintf('%s %s %s', $matches[1], $matches[2], $matches[3]);
            $timestamp = strtotime($dateString);

            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        return null;
    }

    public static function extractColourOptions(Crawler $productNode): array
    {
        $colours = [];

        foreach ($productNode->filter('[data-colour]') as $colourNode) {
            $rawColour = $colourNode->getAttribute('data-colour');
            $colour = self::normaliseWhitespace($rawColour);

            if ($colour !== '') {
                $colours[] = $colour;
            }
        }

        $uniqueColours = array_values(array_unique($colours));

        if ($uniqueColours === []) {
            throw new \RuntimeException('No colour variants found for product.');
        }

        return $uniqueColours;
    }

    public static function extractInfoBlocks(Crawler $productNode): array
    {
        $infoBlocks = [];

        foreach ($productNode->filter('div.my-4.text-sm.block.text-center') as $infoNode) {
            $text = self::normaliseWhitespace($infoNode->textContent);

            if ($text !== '') {
                $infoBlocks[] = $text;
            }
        }

        return $infoBlocks;
    }

public static function extractPaginationUrls(Crawler $document): array
{
    $pageUrls = [];

    foreach ($document->filter('a[href*="smartphones?page="]') as $linkNode) {
        $href = $linkNode->getAttribute('href');

        if ($href === '') {
            continue;
        }

        $query = parse_url($href, PHP_URL_QUERY);

        if (!is_string($query)) {
            continue;
        }

        parse_str($query, $queryParams);

        if (!isset($queryParams['page'])) {
            continue;
        }

        $pageNumber = (int) $queryParams['page'];

        if ($pageNumber <= 1) {
            continue;
        }

        $pageUrls[] = sprintf(
            'https://www.magpiehq.com/developer-challenge/smartphones?page=%d',
            $pageNumber
        );
    }

    $pageUrls = array_values(array_unique($pageUrls));
    sort($pageUrls);

    return $pageUrls;
}

public static function buildAbsoluteUrl(string $baseUrl, string $url): string
{
    if ($url === '') {
        return $baseUrl;
    }

    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
        return $url;
    }

    $baseParts = parse_url($baseUrl);

    if ($baseParts === false || !isset($baseParts['scheme'], $baseParts['host'])) {
        throw new \RuntimeException(sprintf('Unable to build absolute URL from base URL: "%s"', $baseUrl));
    }

    $scheme = $baseParts['scheme'];
    $host = $baseParts['host'];
    $basePath = $baseParts['path'] ?? '/';

    if (str_starts_with($url, '/')) {
        return sprintf('%s://%s%s', $scheme, $host, $url);
    }

    $baseDirectory = preg_replace('#/[^/]*$#', '/', $basePath);
    $combinedPath = $baseDirectory . $url;

    $normalisedPath = self::normaliseUrlPath($combinedPath);

    return sprintf('%s://%s%s', $scheme, $host, $normalisedPath);
}

private static function normaliseUrlPath(string $path): string
{
    $segments = explode('/', $path);
    $resolvedSegments = [];

    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($resolvedSegments);
            continue;
        }

        $resolvedSegments[] = $segment;
    }

    return '/' . implode('/', $resolvedSegments);
}
}