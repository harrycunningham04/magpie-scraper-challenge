<?php

declare(strict_types=1);

namespace App;

final class Product
{

    public function __construct(
        private string $title,
        private float $price,
        private string $imageUrl,
        private int $capacityMB,
        private string $colour,
        private string $availabilityText,
        private bool $isAvailable,
        private ?string $shippingText,
        private ?string $shippingDate,
    ) {

    }

    public function dedupeKey(): string
    {
        return strtolower(sprintf(
            '%s-%s-%s',
            trim($this->title),
            trim($this->colour),
            $this->capacityMB
        ));
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'price' => $this->price,
            'imageUrl' => $this->imageUrl,
            'capacityMB' => $this->capacityMB,
            'colour' => $this->colour,
            'availabilityText' => $this->availabilityText,
            'isAvailable' => $this->isAvailable,
            'shippingText' => $this->shippingText,
            'shippingDate' => $this->shippingDate,
        ];
    }

}
