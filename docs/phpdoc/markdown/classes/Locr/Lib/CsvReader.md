***

# CsvReader





* Full name: `\Locr\Lib\CsvReader`
* Parent class: [`BaseTableReader`](./BaseTableReader.md)




## Methods


### detectSeparator

This method detects the used separator of the given line, by counting the character
(one of ",", ";", "\t" or "|") that is mostly used.

```php
public static detectSeparator(string $line): string
```

```php
<?php

use Locr\Lib\CsvReader;

$separator = CsvReader::detectSeparator('foo;bar;baz');
print $separator; // ;
```

* This method is **static**.




**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$line` | **string** |  |





***

### loadFile

This loads a .csv file.

```php
public loadFile(string $filename): void
```

```php
<?php

use Locr\Lib\CsvReader;

$csvReader = new CsvReader();
$csvReader->loadFile('file.csv');
```






**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$filename` | **string** |  |





***

### loadFormatFile

This loads a fixed-width definition of the .csv-file.

```php
public loadFormatFile(string $filename, bool $detectAndSetHeaderFields = false): void
```

```php
<?php

use Locr\Lib\CsvReader;

$csvReader = new CsvReader();
$csvReader->loadFile('file.csv');
$csvReader->loadFormatFile('file_format.csv');
```






**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$filename` | **string** |  |
| `$detectAndSetHeaderFields` | **bool** |  |





***

### loadFormatString

This loads a fixed-width definition of the .csv-file.

```php
public loadFormatString(string $content, bool $detectAndSetHeaderFields = false): void
```

```php
<?php

use Locr\Lib\CsvReader;

$csvReader = new CsvReader();
$csvReader->loadFile('file.csv');
$csvReader->loadFormatString('5|10|10|5|3');

// alternative format
$csvFormat = "Fieldname|Length|Start|Stop\n";
$csvFormat .= "id|3|1|3\n";
$csvFormat .= "country|8|4|11\n";
$csvFormat .= "city|13|12|24\n";
$csvFormat .= "postal|7|25|31\n";
$csvFormat .= "street|10|32|41\n";
$csvFormat .= "house|5|42|46";
$csvReader->loadFormatString($csvFormat, true);
```






**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$content` | **string** |  |
| `$detectAndSetHeaderFields` | **bool** |  |





***

### loadString

This loads a csv by it's content.

```php
public loadString(string $content): void
```

```php
<?php

use Locr\Lib\CsvReader;

$csvReader = new CsvReader();
$csvReader->loadString('foo|bar|baz');
$rows = $csvReader->readDatasets();
print $rows[0][0]; // foo
print $rows[0][1]; // bar
print $rows[0][2]; // baz
```






**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$content` | **string** |  |





***

### setStripTags

If set to true, html-tags were stripped away from the csv-content

```php
public setStripTags(bool $stripTags): self
```

```php
<?php

use Locr\Lib\CsvReader;

$csvReader = new CsvReader();
$csvReader->setStripTags(true);
$csvReader->loadString('foo|bar|<hello>world<hello>');
$rows = $csvReader->readDatasets();
print $rows[0][2]; // world
```






**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$stripTags` | **bool** |  |





***


***
> Automatically generated on 2024-06-17
