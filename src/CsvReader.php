<?php

declare(strict_types=1);

namespace Locr\Lib;

/**
 * @property-read string $BOMEncoding
 * @property-read int $BOMLength
 * @property-read array $FixedWidthFields
 * @property-read bool $IsLoaded
 * @property-read string $LineEnding
 * @property-read string $Separator
 * @property-read bool $StripTags
 */
class CsvReader extends BaseTableReader
{
    /**
     * @see https://de.wikipedia.org/wiki/Byte_Order_Mark
     */
    private const RAW_BOM_ENCODINGS = [
        'UTF-8'         => [0xEF, 0xBB, 0xBF],
        'UTF-16 (BE)'   => [0xFE, 0xFF],
        'UTF-16 (LE)'   => [0xFF, 0xFE],
        'UTF-32 (BE)'   => [0x00, 0x00, 0xFE, 0xFF],
        'UTF-32 (LE)'   => [0xFF, 0xFE, 0x00, 0x00],
        'UTF-7 (a)'     => [0x2B, 0x2F, 0x76, 0x38],
        'UTF-7 (b)'     => [0x2B, 0x2F, 0x76, 0x39],
        'UTF-7 (c)'     => [0x2B, 0x2F, 0x76, 0x2B],
        'UTF-7 (d)'     => [0x2B, 0x2F, 0x76, 0x2F],
        'UTF-1'         => [0xF7, 0x64, 0x4C],
        'UTF-EBCDIC'    => [0xDD, 0x73, 0x66, 0x73],
        'SCSU'          => [0x0E, 0xFE, 0xFF],
        'BOCU-1 (a)'    => [0xFB, 0xEE, 0x28],
        'BOCU-1 (b)'    => [0xFB, 0xEE, 0x28, 0xFF],
        'GB 18030'      => [0x84, 0x31, 0x95, 0x33]
    ];

    private int $bomLength = 0;
    private string $bomEncoding = '';
    /**
     * @var resource|null
     */
    private mixed $csvFile = null;
    /**
     * @var int[]
     * @see $FixedWidthFields
     */
    private array $fixedWidthFields = [];
    /**
     * @see $LineEnding
     */
    private string $lineEnding = '';
    /**
     * @see $Separator
     */
    private string $separator = ',';
    /**
     * @var array<string, int>
     */
    private array $separators = [
        ',' => 0,
        ';' => 0,
        '	' => 0,
        '|' => 0
    ];
    /**
     * @see $StripTags
     */
    private bool $stripTags = false;
    private string $tempFilename = '';

    public function __destruct()
    {
        if (!is_null($this->csvFile)) {
            fclose($this->csvFile);
            $this->csvFile = null;
            $this->filename = '';
        }

        if ($this->tempFilename !== '') {
            @unlink($this->tempFilename);
        }
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'BOMEncoding' => $this->bomEncoding,
            'BOMLength' => $this->bomLength,
            'FixedWidthFields' => $this->fixedWidthFields,
            'IsLoaded' => !is_null($this->csvFile),
            'LineEnding' => $this->lineEnding,
            'Separator' => $this->separator,
            'StripTags' => $this->stripTags,
            default => parent::__get($name)
        };
    }

    public static function detectSeparator(string $line): string
    {
        $csvSeparator = ',';
        $separators = [
            ',' => 0,
            ';' => 0,
            '	' => 0,
            '|' => 0
        ];

        $lineStrlen = strlen($line);
        for ($i = 0; $i < $lineStrlen; $i++) {
            $char = $line[$i];
            if ($char === ',' || $char === ';' || $char === '	' || $char === '|') {
                $separators[$char]++;
            }
        }

        $maxValueChar = "\0";
        $maxValue = 0;
        foreach ($separators as $key => $value) {
            if ($value > $maxValue) {
                $maxValueChar = $key;
            }
        }

        if ($maxValueChar !== "\0") {
            $csvSeparator = $maxValueChar;
        }

        return $csvSeparator;
    }

    public function loadFile(string $filename): void
    {
        $cmd = sprintf("file %s", escapeshellarg($filename));
        $output = [];
        $returnVar = -1;
        exec($cmd, $output, $returnVar);
        if ($returnVar !== 0) {
            throw new \Exception(__METHOD__ . "(string \$filename): void => error reading the file ('{$filename}')");
        }

        $subRegexes = [
            'ASCII',
            'CSV',
            'UTF\-[0-9]+( Unicode)( \(with BOM\))?',
            'Unicode text, (UTF\-[0-9]+)( \(with BOM\))?',
            'ISO\-[0-9]+(\-[0-9]+)?'
        ];
        $validFileRegex = '/(' . implode('|', $subRegexes) . ')(\s[^\\s]+)? text/';
        $validLineFound = false;
        $withBOM = false; // with Byte Order Mark: https://de.wikipedia.org/wiki/Byte_Order_Mark
        foreach ($output as $line) {
            if (preg_match($validFileRegex, $line, $lineMatch)) {
                $validLineFound = true;
                if (
                    (
                        isset($lineMatch[3]) &&
                        trim($lineMatch[3]) === '(with BOM)'
                    ) ||
                    (
                        isset($lineMatch[5]) &&
                        trim($lineMatch[5]) === '(with BOM)'
                    )
                ) {
                    $withBOM = true;
                }
                break;
            }
        }
        if (!$validLineFound) {
            throw new \Exception(__METHOD__ . "(string \$filename): void => invalid file-content ('{$filename}').");
        }

        $csvFile = fopen($filename, 'r');
        if ($csvFile === false) {
            throw new \Exception(__METHOD__ . "(string \$filename): void => error opening the file ('{$filename}').");
        }

        $this->csvFile = $csvFile;
        $this->filename = $filename;
        $this->loadFileInternal($this->csvFile, $this->separator, $this->fieldsCount, $this->lineEnding, $withBOM);
    }

    /**
     * @param resource $csvFile
     */
    private function loadFileInternal(
        $csvFile,
        string &$csvSeparator,
        int &$fieldsCount,
        string &$lineEnding,
        bool $withBOM = false
    ): void {
        $c = '';
        $this->bomLength = 0;

        if ($withBOM) {
            $bomArray = [0x00, 0x00, 0x00, 0x00];
            for ($i = 0; $i < 4; $i++) {
                $c = fgetc($csvFile);
                if ($c === false) {
                    break;
                }
                $bomArray[$i] = ord($c);
            }

            $this->bomEncoding = '';
            foreach (self::RAW_BOM_ENCODINGS as $bomEncoding => $bomData) {
                $allBytesAreEqual = true;
                foreach ($bomData as $bomDataIndex => $bomByte) {
                    if ($bomArray[$bomDataIndex] !== $bomByte) {
                        $allBytesAreEqual = false;
                        break;
                    }
                }

                if ($allBytesAreEqual) {
                    // maybe its UTF-32 (LE) and not UTF-16 (LE)
                    if (count($bomData) > $this->bomLength) {
                        $this->bomLength = count($bomData);
                        $this->bomEncoding = $bomEncoding;
                    }
                }
            }

            fseek($csvFile, $this->bomLength);
        }

        while (($c = fgetc($csvFile)) !== false) {
            $ordC = ord($c);
            if ($ordC !== 10 && $ordC !== 13) {
                if ($c === ',' || $c === ';' || $c === '|' || $c === '	') {
                    $this->separators[$c]++;
                }
                continue;
            }

            $lineEnding[0] = $c;
            if ($ordC === 10) { // Unix / maxOS
                break;
            }

            $nextChar = '';
            if (($nextChar = fgetc($csvFile)) === false) {
                break;
            }

            if (ord($nextChar) === 10) { // Windows
                $lineEnding[1] = $nextChar;
            }

            break;
        }

        fseek($csvFile, $this->bomLength);

        $maxValueChar = "\0";
        $maxValue = 0;
        foreach ($this->separators as $key => $value) {
            if ($value > $maxValue) {
                $maxValueChar = $key;
            }
        }

        if ($maxValueChar != "\0") {
            $csvSeparator = $maxValueChar;
        }

        $fieldsCount++;
        while (($c = fgetc($csvFile)) !== false) {
            if ($c === $csvSeparator) {
                $fieldsCount++;
            }
            if (isset($lineEnding[0]) && $c === $lineEnding[0]) {
                break;
            }
        }

        fseek($csvFile, $this->bomLength);
    }

    public function loadFormatString(string $content, bool $detectAndSetHeaderFields = false): void
    {
        $tempFilename = tempnam(sys_get_temp_dir(), 'csv');
        if ($tempFilename === false) {
            throw new \Exception(
                __METHOD__ . '(string $content, bool $detectAndSetHeaderFields = false): void' .
                    ' => temporary file could not been created.'
            );
        }
        $fd = fopen($tempFilename, 'w');
        if ($fd === false) {
            throw new \Exception(
                __METHOD__ . '(string $content, bool $detectAndSetHeaderFields = false): void' .
                    ' => could not load csv-string.'
            );
        }

        if (fwrite($fd, $content) === false) {
            fclose($fd);
            throw new \Exception(
                __METHOD__ . '(string $content, bool $detectAndSetHeaderFields = false): void' .
                    ' => could not write temporary csv-file.'
            );
        }
        fclose($fd);

        $this->loadFormatFile($tempFilename, $detectAndSetHeaderFields);

        @unlink($tempFilename);
    }

    public function loadFormatFile(string $filename, bool $detectAndSetHeaderFields = false): void
    {
        $csvFile = fopen($filename, 'r');
        if ($csvFile === false) {
            throw new \Exception(
                __METHOD__ . "(string \$filename, bool \$detectAndSetHeaderFields = false): void" .
                    " => error opening the format-file ('{$filename}')."
            );
        }

        $csvSeparator = ',';
        $fieldsCount = 0;
        $lineEnding = '';
        $fields = [];

        $this->loadFileInternal($csvFile, $csvSeparator, $fieldsCount, $lineEnding);
        $this->readNextLineInternal($fields, $csvFile, $csvSeparator, $lineEnding);

        if (count($fields) === 0) {
            throw new \Exception(
                __METHOD__ . "(string \$filename, bool \$detectAndSetHeaderFields = false): void" .
                    " => error parsing the format-file ('{$filename}')."
            );
        }

        // check if first line is header with length, start, stop, field...
        $lengthHeaderIndex = -1;
        $startHeaderIndex = -1;
        $stopHeaderIndex = -1;
        $fieldNameHeaderIndex = -1;
        $headerIndex = -1;
        foreach ($fields as $field) {
            $field = strtolower($field);
            $headerIndex++;
            if ($field === 'length') {
                $lengthHeaderIndex = $headerIndex;
            } elseif ($field === 'start') {
                $startHeaderIndex = $headerIndex;
            } elseif ($field === 'stop') {
                $stopHeaderIndex = $headerIndex;
            } elseif ($field === 'fieldname') {
                $fieldNameHeaderIndex = $headerIndex;
            }
        }

        $intValue = 0;

        if ($lengthHeaderIndex >= 0 || ($startHeaderIndex >= 0 && $stopHeaderIndex >= 0)) {
            $headerFields = [];
            $fixedWidthFields = [];
            $startPosition = 0;
            $stopPosition = 0;
            while ($this->readNextLineInternal($fields, $csvFile, $csvSeparator, $lineEnding)) {
                $field = '';
                if ($lengthHeaderIndex >= 0) {
                    $field = trim($fields[$lengthHeaderIndex]);
                    if (!is_numeric($field)) {
                        throw new \Exception(
                            __METHOD__ . "(string \$filename, bool \$detectAndSetHeaderFields = false): void" .
                                " => error parsing a value ({$field}) to integer in format-file ('{$filename}')."
                        );
                    }
                    $intValue = (int)$field;
                } elseif ($startHeaderIndex >= 0 && $stopHeaderIndex >= 0) {
                    $field = trim($fields[$startHeaderIndex]);
                    if (!is_numeric($field)) {
                        throw new \Exception(
                            __METHOD__ . "(string \$filename, bool \$detectAndSetHeaderFields = false): void" .
                                " => error parsing a value ({$field}) to integer in format-file ('{$filename}')."
                        );
                    }
                    $startPosition = (int)$field;

                    $field = trim($fields[$stopHeaderIndex]);
                    if (!is_numeric($field)) {
                        throw new \Exception(
                            __METHOD__ . "(string \$filename, bool \$detectAndSetHeaderFields = false): void" .
                                " => error parsing a value ({$field}) to integer in format-file ('{$filename}')."
                        );
                    }
                    $stopPosition = (int)$stopPosition;
                    $intValue = $stopPosition - ($startPosition - 1);
                }
                if ($detectAndSetHeaderFields && $fieldNameHeaderIndex >= 0) {
                    $headerFields[] = trim($fields[$fieldNameHeaderIndex]);
                }

                $fixedWidthFields[] = $intValue;
            }
            foreach ($fixedWidthFields as $it) {
                $this->fixedWidthFields[] = $it;
            }

            if ($detectAndSetHeaderFields && $fieldNameHeaderIndex >= 0) {
                foreach ($headerFields as $it) {
                    $this->headerFields[] = $it;
                }
            }
        } else {
            foreach ($fields as $it) {
                if (!is_numeric($it)) {
                    throw new \Exception(
                        __METHOD__ . "(string \$filename, bool \$detectAndSetHeaderFields = false): void" .
                            " => error parsing a value ({$it}) to integer in format-file ('{$filename}')."
                    );
                }
                $intValue = (int)$it;
                $this->fixedWidthFields[] = $intValue;
            }
        }

        $this->fieldsCount = count($this->fixedWidthFields);
    }

    public function loadString(string $content): void
    {
        $tempFilename = tempnam(sys_get_temp_dir(), 'csv');
        if ($tempFilename === false) {
            throw new \Exception(__METHOD__ . '(string $content): void => temporary file could not been created.');
        }
        $fd = fopen($tempFilename, 'w');
        if ($fd === false) {
            throw new \Exception(__METHOD__ . '(string $content): void => could not load csv-string.');
        }

        $this->tempFilename = $tempFilename;

        if (fwrite($fd, $content) === false) {
            fclose($fd);
            throw new \Exception(__METHOD__ . '(string $content): void => could not write temporary csv-file.');
        }
        fclose($fd);

        $this->loadFile($this->tempFilename);
    }

    protected function readDatasetsCallbackInternal(callable $callback, int $limit = -1, int $offset = -1): int
    {
        if (is_null($this->csvFile)) {
            return 0;
        }

        fseek($this->csvFile, $this->bomLength);

        if ($this->firstLineIsHeader) {
            if (count($this->headerFields) === 0) {
                $this->readHeaderFields();
            } else {
                $fields = [];
                $this->readNextLine($fields);
            }
        }

        $fields = [];
        $linesCallbacked = 0;
        $lineCounter = 0;
        while ($this->readNextLine($fields)) {
            if ($limit >= 0 && $linesCallbacked >= $limit) {
                break;
            }

            $lineCounter++;

            if ($offset >= 0 && $lineCounter - 1 < $offset) {
                continue;
            }

            $rowVector = [];
            for ($i = 0; $i < $this->fieldsCount; $i++) {
                if (count($fields) < $i + 1) {
                    $rowVector[] = '';
                    continue;
                }

                $rowVector[] = $fields[$i];
            }

            $callback($rowVector, $lineCounter);

            $linesCallbacked++;
        }

        return $linesCallbacked;
    }

    private function readHeaderFields(): void
    {
        $fields = [];
        if ($this->readNextLine($fields)) {
            $this->headerFields = $fields;
        }
    }

    /**
     * @param string[] $fields
     */
    private function readNextLine(array &$fields): bool
    {
        $readResult = false;
        if (is_null($this->csvFile)) {
            return $readResult;
        }
        do { // this is for empty lines!
            $readResult = $this->readNextLineInternal($fields, $this->csvFile, $this->separator, $this->lineEnding);
        } while (!$readResult && !feof($this->csvFile));

        return $readResult;
    }

    /**
     * @param string[] $fields
     * @param resource $csvFile
     */
    private function readNextLineInternal(array &$fields, $csvFile, string $csvSeparator, string $lineEnding): bool
    {
        $fields = [];
        $c = '';
        $lineEndingReached = false;

        if (count($this->fixedWidthFields) > 0) {
            $line = '';
            while (($c = fgetc($csvFile)) !== false) {
                $lineEndingReached = false;
                if (isset($lineEnding[0]) && $c === $lineEnding[0]) {
                    if (isset($lineEnding[1])) {
                        $nextChar = '';
                        if (($nextChar = fgetc($csvFile)) !== false) {
                            if ($nextChar === $lineEnding[1]) {
                                $lineEndingReached = true;
                            }
                        }
                        if (!$lineEndingReached) {
                            fseek($csvFile, -1, SEEK_CUR);
                        }
                    } else {
                        $lineEndingReached = true;
                    }
                }

                if ($lineEndingReached) {
                    break;
                }

                $line .= $c;
            }

            if ($line === '') {
                return false;
            }

            if (!mb_check_encoding($line, 'UTF-8')) {
                $convertedLine = iconv('ISO-8859-1', 'UTF-8', $line);
                if ($convertedLine !== false) {
                    $line = $convertedLine;
                }
            }

            $start = 0;
            foreach ($this->fixedWidthFields as $it) {
                if ($start >= mb_strlen($line)) {
                    break;
                }

                $field = mb_substr($line, $start, $it);
                $fields[] = $field;
                $start += $it;
            }
        } else {
            $field = '';
            $isQuoted = false;
            while (($c = fgetc($csvFile)) !== false) {
                if (strlen($field) === 0 && $c === '"' && !$isQuoted) {
                    $isQuoted = true;
                    continue;
                }

                if (!$isQuoted) {
                    $lineEndingReached = false;
                    if (isset($lineEnding[0]) && $c === $lineEnding[0]) {
                        if (isset($lineEnding[1])) {
                            $nextChar = '';
                            if (($nextChar = fgetc($csvFile)) !== false) {
                                if ($nextChar === $lineEnding[1]) {
                                    $lineEndingReached = true;
                                }
                            }
                            if (!$lineEndingReached) {
                                fseek($csvFile, -1, SEEK_CUR);
                            }
                        } else {
                            $lineEndingReached = true;
                        }
                    }

                    if ($c === $csvSeparator || $lineEndingReached) {
                        if (!mb_check_encoding($field, 'UTF-8')) {
                            $field = iconv('ISO-8859-1', 'UTF-8', $field);
                        }
                        $fields[] = $field;
                        $field = '';
                        if ($c === $csvSeparator) {
                            continue;
                        } elseif ($lineEndingReached) {
                            break;
                        }
                    }
                } else {
                    if ($c === '"') {
                        $nextChar = '';
                        if (($nextChar = fgetc($csvFile)) !== false) {
                            if ($nextChar === '"') {
                                $field .= $c;
                                continue;
                            } else {
                                if (!mb_check_encoding($field, 'UTF-8')) {
                                    $field = iconv('ISO-8859-1', 'UTF-8', $field);
                                }
                                $fields[] = $field;
                                $field = '';
                                $isQuoted = false;

                                $lineEndingReached = false;
                                if (isset($lineEnding[0]) && $nextChar === $lineEnding[0]) {
                                    if (isset($lineEnding[1])) {
                                        if (($nextChar = fgetc($csvFile)) !== false) {
                                            if ($nextChar === $lineEnding[1]) {
                                                $lineEndingReached = true;
                                            }
                                        }
                                        if (!$lineEndingReached) {
                                            fseek($csvFile, -1, SEEK_CUR);
                                        }
                                    } else {
                                        $lineEndingReached = true;
                                    }
                                }
                                if ($lineEndingReached) {
                                    break;
                                }
                                continue;
                            }
                        } else {
                            break;
                        }
                    }
                }

                $field .= $c;
            }

            if (count($fields) === 1 && isset($fields[0])) {
                $firstField = (string)$fields[0];
                if (strlen($firstField) === 0) {
                    if ($this->ignoreEmptyLines) {
                        return false;
                    } else {
                        return true;
                    }
                }
            }

            if ($field !== '') { // last line, last field
                if (!mb_check_encoding($field, 'UTF-8')) {
                    $field = iconv('ISO-8859-1', 'UTF-8', $field);
                }
                $fields[] = $field;
                $field = '';
            }
        }

        if (count($fields) === 0) {
            return false;
        }

        if ($this->stripTags) {
            foreach ($fields as $key => $value) {
                if (is_string($value)) {
                    $fields[$key] = strip_tags($value);
                }
            }
        }

        return true;
    }

    public function setStripTags(bool $stripTags): self
    {
        $this->stripTags = $stripTags;

        return $this;
    }
}