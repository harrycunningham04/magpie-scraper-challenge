# Magpie Developer Challenge Solution

This project scrapes smartphone product data from the Magpie developer challenge page and outputs a JSON file containing all product variants.

Each colour variant is treated as a separate product, and duplicate products are removed using a deduplication key.

## Requirements

- PHP 8.3+
- Composer

## Installation

Install dependencies:

composer install

## Run the scraper

php src/Scrape.php

This will generate:

output.json

## Notes

- All colour variants are captured as separate products
- Capacity is normalised to MB
- Shipping dates are parsed when possible
- Duplicate products are removed using a deterministic key
- Pagination is crawled automatically

## Author

Harry Cunningham
