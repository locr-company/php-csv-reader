![php](https://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg)
[![codecov](https://codecov.io/bb/locr/php-csv-reader/branch/main/graph/badge.svg?token=OLLLKN9C6B)](https://codecov.io/bb/locr/php-csv-reader)

# 1. Installation

## 1.1. edit composer.json

Add the following lines into your composer.json file of your project!

```json
{
    ...
    "require": {
        ...
        "locr/csv-reader": "^1.0"
    },
    ...
    "repositories": [
        ...
        {
            "type": "vcs",
            "url": "git@bitbucket.org:locr/php-base-table-reader.git"
        },
        {
            "type": "vcs",
            "url": "git@bitbucket.org:locr/php-csv-reader.git"
        }
    ]
}
```

## 1.2. install composer dependency

```bash
composer install
```

# 2. Development

Clone the repository

```bash
git clone git@bitbucket.org:locr/php-csv-reader.git
cd php-csv-reader/.git/hooks && ln -s ../../git-hooks/* . && cd ../..
```

Install [Composer](https://getcomposer.org/)
