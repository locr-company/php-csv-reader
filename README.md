![php](https://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg)
[![codecov](https://codecov.io/gh/locr-company/php-csv-reader/branch/main/graph/badge.svg?token=bhxfQglKff)](https://codecov.io/gh/locr-company/php-csv-reader)

# 1. Installation

```bash
composer require locr-company/csv-reader
```

# 2. How to use

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
cd php-csv-reader/.git/hooks && ln -s ../../git-hooks/* . && cd ../..
composer install
```
