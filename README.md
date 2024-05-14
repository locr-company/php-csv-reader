![php](https://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg)
[![codecov](https://codecov.io/gh/locr-company/php-csv-reader/branch/main/graph/badge.svg?token=bhxfQglKff)](https://codecov.io/gh/locr-company/php-csv-reader)
![github_workflow_status](https://img.shields.io/github/actions/workflow/status/locr-company/php-csv-reader/php-8.1.yml)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=locr-company_php-csv-reader&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=locr-company_php-csv-reader)
![github_tag](https://img.shields.io/github/v/tag/locr-company/php-csv-reader)
![packagist](https://img.shields.io/packagist/v/locr-company/csv-reader)

# 1. Installation

```bash
composer require locr-company/csv-reader
```

# 2. How to use

Here are the [Docs](https://locr-company.github.io/php-csv-reader/).

```php
<?php

use Locr\Lib\CsvReader;

$csvReader = new CsvReader();
$csvReader->loadFile('file.csv');
$csvReader->setFirstLineIsHeader(true); // if the first line of the csv-file has column informations

// read all rows at once
$rows = $csvReader->readDataset();
foreach ($rows as $row) {
    print $row['column1'] . '|' . $row['column2'] . "\n";
}

// read rows one by one, if you expect a very large csv-file
$csvReader->readDatasetsCallback(function (array $row, int $lineNumber) {
    print $row['column1'] . '|' . $row['column2'] . "\n";
});
```

# 3. Development

Clone the repository

```bash
git clone git@github.com:locr-company/php-csv-reader.git
cd php-csv-reader && composer install
```
