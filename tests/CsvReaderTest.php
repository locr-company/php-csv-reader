<?php

declare(strict_types=1);

namespace UnitTests;

use Locr\Lib\CsvReader;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Locr\Lib\CsvReader
 * @covers \Locr\Lib\CsvReader
 */
final class CsvReaderTest extends TestCase
{
    private $csvLines = [
        'id;country;city;postal;street;house',
        '1;DEU;Braunschweig;38106;Bültenweg;73',
        '2;DEU;Braunschweig;"38106";Rebenring;"31"',
        '3;DEU;incomplete;38100',
        '',
        '4;DEU;overloaded;38100;a;b;c;d;e'
    ];
    private $csvLinesWithBOM = [
        "\xEF\xBB\xBFid;country;city;postal;street;house",
        '1;DEU;Braunschweig;38106;Bültenweg;73',
        '2;DEU;Braunschweig;"38106";Rebenring;"31"',
    ];
    private $csvLinesWithTags = [
        'id;country;city;postal;street;house',
        '1;DEU;Braunschweig;38106;Bültenweg;<house>73</house>',
        '2;DEU;Braunschweig;"38106";Rebenring;"<house>31</house>"'
    ];
    private $prnLines = [
        'id country city         postal street    house',
        '1  DEU     Braunschweig 38106  Bültenweg 73',
        '2  DEU     Braunschweig 38106  Rebenring 31',
        '3  DEU     incomplete   38100',
        '',
        '4  DEU     overloaded   38100  a         b     c d e'
    ];
    private $prnFormat = '3|8|13|7|10|5';
    private $prnNewFormat = "Fieldname|Length|Start|Stop\n" .
                             "id|3|1|3\n" .
                             "country|8|4|11\n" .
                             "city|13|12|24\n" .
                             "postal|7|25|31\n" .
                             "street|10|32|41\n" .
                             "house|5|42|46";

    /**
     * @covers \Locr\Lib\BaseTableReader::__get
     * @covers ::__destruct
     * @covers ::__get
     */
    public function testNewCsvReader()
    {
        $csvReader = new CsvReader();
        $this->assertInstanceOf(
            CsvReader::class,
            $csvReader
        );

        $this->assertEquals(',', $csvReader->Separator);
        $this->assertEquals(false, $csvReader->FirstLineIsHeader);
    }

    /**
     * @covers ::__get
     * @covers ::loadString
     */
    public function testIsLoaded()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines);

        $this->assertEquals(false, $csvReader->IsLoaded);
        $csvReader->loadString($csvContent);
        $this->assertEquals(true, $csvReader->IsLoaded);
    }

    /**
     * @covers ::__get
     * @covers ::loadString
     */
    public function testFilenameProperty()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines);

        $this->assertEquals('', $csvReader->Filename);
        $csvReader->loadString($csvContent);
        $this->assertTrue(strlen($csvReader->Filename) > 0);
    }

    /**
     * @covers ::__get
     * @covers ::loadString
     */
    public function testLoadStringWithUnixLineEnding()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines);
        $csvReader->loadString($csvContent);

        $this->assertEquals(';', $csvReader->Separator);
        $this->assertEquals("\n", $csvReader->LineEnding);
        $this->assertEquals(6, $csvReader->FieldsCount);
    }

    /**
     * @covers ::__get
     * @covers ::loadString
     */
    public function testLoadStringWithWindowsLineEnding()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\r\n", $this->csvLines);
        $csvReader->loadString($csvContent);

        $this->assertEquals(';', $csvReader->Separator);
        $this->assertEquals("\r\n", $csvReader->LineEnding);
        $this->assertEquals(6, $csvReader->FieldsCount);
    }

    /**
     * @covers ::__get
     * @covers ::loadString
     */
    public function testLoadStringWithMacOSLineEnding()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\r", $this->csvLines);
        $csvReader->loadString($csvContent);

        $this->assertEquals(';', $csvReader->Separator);
        $this->assertEquals("\r", $csvReader->LineEnding);
        $this->assertEquals(6, $csvReader->FieldsCount);
    }

    /**
     * @covers ::loadString
     * @covers ::readDatasets
     * @covers ::setIgnoreEmptyLines
     */
    public function testIgnoreEmptyLinesFalse()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines);
        $csvReader->loadString($csvContent);
        $csvReader->setIgnoreEmptyLines(false);
        $datasets = $csvReader->readDatasets();

        $this->assertEquals(6, count($datasets));
    }

    /**
     * @covers ::loadString
     * @covers ::readDatasetsCallback
     * @covers ::setIgnoreEmptyLines
     */
    public function testIgnoreEmptyLinesFalseWithEmptyLineAtTheEnd()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines) . "\n";
        $csvReader->loadString($csvContent);
        $csvReader->setIgnoreEmptyLines(false);
        $datasetsCount = $csvReader->readDatasetsCallback(function (array $data) {
        });

        $this->assertEquals(6, $datasetsCount);
    }

    /**
     * @covers ::loadString
     * @covers ::readDatasets
     * @covers ::setIgnoreEmptyLines
     */
    public function testReadDatasets()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines);
        $csvReader->loadString($csvContent);
        $datasets = $csvReader->readDatasets();

        $this->assertEquals(5, count($datasets));

        $firstRow = ['id', 'country', 'city', 'postal', 'street', 'house'];
        $this->assertEquals($firstRow, $datasets[1]);
    }

    /**
     * @covers ::loadString
     * @covers ::loadFormatString
     * @covers ::readDatasets
     * @covers ::setIgnoreEmptyLines
     */
    public function testReadDatasetsWithFixedColumnWidths()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->prnLines);
        $csvReader->loadString($csvContent);
        $csvReader->loadFormatString($this->prnFormat);
        $datasets = $csvReader->readDatasets();

        $this->assertEquals(5, count($datasets));

        $expectedFixedWidthFields = [3, 8, 13, 7, 10, 5];
        $this->assertEquals($expectedFixedWidthFields, $csvReader->FixedWidthFields);

        $firstRow = ['id ', 'country ', 'city         ', 'postal ', 'street    ', 'house'];
        $this->assertEquals($firstRow, $datasets[1]);
    }

    /**
     * @covers ::loadFormatFile
     */
    public function testLoadFormatFileThatDoesntExists()
    {
        $this->expectExceptionMessage(
            'Locr\Lib\CsvReader::loadFormatFile(string $filename, bool $detectAndSetHeaderFields = false): void' .
                ' => error opening the format-file (\'unknown.file\')'
        );

        $csvReader = new CsvReader();
        $csvReader->loadFormatFile('unknown.file');
    }

    /**
     * @covers ::loadFormatString
     */
    public function testLoadFormatStringWithEmptyContent()
    {
        $this->expectExceptionMessage(
            'Locr\Lib\CsvReader::loadFormatFile(string $filename, bool $detectAndSetHeaderFields = false): void' .
                ' => error parsing the format-file'
        );

        $csvReader = new CsvReader();
        $csvReader->loadFormatString('');
    }

    /**
     * @covers ::loadString
     * @covers ::loadFormatString
     * @covers ::readDatasets
     */
    public function testReadDatasetsWithFixedColumnWidthsNew()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->prnLines);
        $csvReader->loadString($csvContent);
        $csvReader->loadFormatString($this->prnNewFormat);
        $datasets = $csvReader->readDatasets();

        $this->assertEquals(5, count($datasets));

        $firstRow = ['id ', 'country ', 'city         ', 'postal ', 'street    ', 'house'];
        $this->assertEquals($firstRow, $datasets[1]);
    }

    /**
     * @covers ::__get
     * @covers ::loadString
     * @covers ::loadFormatString
     * @covers ::readDatasets
     * @covers ::setFirstLineIsHeader
     */
    public function testReadDatasetsWithFixedColumnWidthsNew2()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->prnLines);
        $csvReader->loadString($csvContent);
        $csvReader->loadFormatString($this->prnNewFormat, true);
        $detectedFieldNames = $csvReader->HeaderFields;
        $csvReader->setFirstLineIsHeader(true);
        $datasets = $csvReader->readDatasets();

        $this->assertEquals(4, count($datasets));

        $firstRow = [
            'id' => '1  ',
            'country' => 'DEU     ',
            'city' => 'Braunschweig ',
            'postal' => '38106  ',
            'street' => 'Bültenweg ',
            'house' => '73'
        ];
        $this->assertEquals($firstRow, $datasets[1]);

        $expectedFieldNames = ['id', 'country', 'city', 'postal', 'street', 'house'];
        $this->assertEquals($expectedFieldNames, $detectedFieldNames);
    }

    /**
     * @covers ::loadString
     * @covers ::readDatasetsCallback
     */
    public function testReadDatasetsCallback()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines);
        $csvReader->loadString($csvContent);
        $firstRow = ['id', 'country', 'city', 'postal', 'street', 'house'];
        $firstLineCallback = null;
        $datasetsCallbacked = $csvReader->readDatasetsCallback(
            function (array $row, int $lineNumber) use (&$firstLineCallback) {
                if ($lineNumber == 1) {
                    $firstLineCallback = $row;
                }
            }
        );

        $this->assertEquals(5, $datasetsCallbacked);

        $this->assertEquals($firstRow, $firstLineCallback);
    }

    /**
     * @covers ::loadString
     * @covers ::loadFormatString
     * @covers ::readDatasetsCallback
     */
    public function testReadDatasetsCallbackWithFixedColumnWidths()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->prnLines);
        $csvReader->loadString($csvContent);
        $csvReader->loadFormatString($this->prnFormat);
        $firstRow = ['id ', 'country ', 'city         ', 'postal ', 'street    ', 'house'];
        $firstLineCallback = null;
        $datasetsCallbacked = $csvReader->readDatasetsCallback(
            function (array $row, int $lineNumber) use (&$firstLineCallback) {
                if ($lineNumber == 1) {
                    $firstLineCallback = $row;
                }
            }
        );

        $this->assertEquals(5, $datasetsCallbacked);

        $this->assertEquals($firstRow, $firstLineCallback);
    }

    /**
     * @covers ::loadString
     * @covers ::readDatasets
     * @covers ::setFirstLineIsHeader
     */
    public function testReadDatasetsWithFirstLineIsHeader()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines);
        $csvReader->setFirstLineIsHeader(true);
        $csvReader->loadString($csvContent);
        $datasets = $csvReader->readDatasets();

        $this->assertEquals(4, count($datasets));

        $firstRow = [
            'id' => '1',
            'country' => 'DEU',
            'city' => 'Braunschweig',
            'postal' => '38106',
            'street' => 'Bültenweg',
            'house' => '73'
        ];
        $this->assertEquals($firstRow, $datasets[1]);
    }

    /**
     * @covers ::loadString
     * @covers ::loadFormatString
     * @covers ::readDatasets
     * @covers ::setFirstLineIsHeader
     */
    public function testReadDatasetsWithFirstLineIsHeaderWithFixedColumnWidths()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->prnLines);
        $csvReader->setFirstLineIsHeader(true);
        $csvReader->loadString($csvContent);
        $csvReader->loadFormatString($this->prnFormat);
        $datasets = $csvReader->readDatasets();

        $this->assertEquals(4, count($datasets));

        $firstRow = [
            'id ' => '1  ',
            'country ' => 'DEU     ',
            'city         ' => 'Braunschweig ',
            'postal ' => '38106  ',
            'street    ' => 'Bültenweg ',
            'house' => '73'
        ];
        $this->assertEquals($firstRow, $datasets[1]);
    }

    /**
     * @covers ::loadString
     * @covers ::readDatasets
     * @covers ::setFirstLineIsHeader
     */
    public function testReadDatasetsWithQuotedCell()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines);
        $csvReader->setFirstLineIsHeader(true);
        $csvReader->loadString($csvContent);
        $datasets = $csvReader->readDatasets();

        $this->assertEquals(4, count($datasets));

        $secondRow = [
            'id' => '2',
            'country' => 'DEU',
            'city' => 'Braunschweig',
            'postal' => '38106',
            'street' => 'Rebenring',
            'house' => '31'
        ];
        $this->assertEquals($secondRow, $datasets[2]);
    }

    /**
     * @covers ::loadString
     * @covers ::readDatasets
     * @covers ::setFirstLineIsHeader
     */
    public function testPadIncompleteRows()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines);
        $csvReader->setFirstLineIsHeader(true);
        $csvReader->loadString($csvContent);
        $datasets = $csvReader->readDatasets();

        $thirdRow = [
            'id' => '3',
            'country' => 'DEU',
            'city' => 'incomplete',
            'postal' => '38100',
            'street' => '',
            'house' => ''
        ];
        $this->assertEquals($thirdRow, $datasets[3]);
    }

    /**
     * @covers ::loadString
     * @covers ::loadFormatString
     * @covers ::readDatasets
     * @covers ::setFirstLineIsHeader
     */
    public function testPadIncompleteRowsWithFixedColumnWidths()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->prnLines);
        $csvReader->setFirstLineIsHeader(true);
        $csvReader->loadString($csvContent);
        $csvReader->loadFormatString($this->prnFormat);
        $datasets = $csvReader->readDatasets();

        $thirdRow = [
            'id ' => '3  ',
            'country ' => 'DEU     ',
            'city         ' => 'incomplete   ',
            'postal ' => '38100',
            'street    ' => '',
            'house' => ''
        ];
        $this->assertEquals($thirdRow, $datasets[3]);
    }

    /**
     * @covers ::loadString
     * @covers ::readDatasets
     * @covers ::setFirstLineIsHeader
     */
    public function testCutToFewColumns()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines);
        $csvReader->setFirstLineIsHeader(true);
        $csvReader->loadString($csvContent);
        $datasets = $csvReader->readDatasets();

        $thirdRow = [
            'id' => '4',
            'country' => 'DEU',
            'city' => 'overloaded',
            'postal' => '38100',
            'street' => 'a',
            'house' => 'b'
        ];
        $this->assertEquals($thirdRow, $datasets[4]);
    }

    /**
     * @covers ::loadString
     * @covers ::loadFormatString
     * @covers ::readDatasets
     * @covers ::setFirstLineIsHeader
     */
    public function testCutToFewColumnsWithFixedColumnWidths()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->prnLines);
        $csvReader->setFirstLineIsHeader(true);
        $csvReader->loadString($csvContent);
        $csvReader->loadFormatString($this->prnFormat);
        $datasets = $csvReader->readDatasets();

        $thirdRow = [
            'id ' => '4  ',
            'country ' => 'DEU     ',
            'city         ' => 'overloaded   ',
            'postal ' => '38100  ',
            'street    ' => 'a         ',
            'house' => 'b    '
        ];
        $this->assertEquals($thirdRow, $datasets[4]);
    }

    /**
     * @covers ::loadString
     * @covers ::readDatasets
     * @covers ::setHeaderFields
     * @covers ::setFirstLineIsHeader
     */
    public function testReadDatasetsWithCustomFieldNames()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines);
        $csvReader->setFirstLineIsHeader(true);
        $csvReader->loadString($csvContent);
        $csvReader->setHeaderFields(['id1', 'country2', '', 'postal4', '', 'house6']);
        $datasets = $csvReader->readDatasets();

        $this->assertEquals(4, count($datasets));

        $firstRow = [
            'id1' => '1',
            'country2' => 'DEU',
            2 => 'Braunschweig',
            'postal4' => '38106',
            4 => 'Bültenweg',
            'house6' => '73'
        ];
        $this->assertEquals($firstRow, $datasets[1]);
    }

    /**
     * @covers ::loadString
     * @covers ::readDatasets
     * @covers ::setHeaderFields
     */
    public function testReadDatasetsWithCustomFieldNamesAndNoHeadLine()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines);
        $csvReader->loadString($csvContent);
        $csvReader->setHeaderFields(['id1', 'country2', '', 'postal4', '', 'house6']);
        $datasets = $csvReader->readDatasets();

        $this->assertEquals(5, count($datasets));

        $firstRow = [
            'id1' => '1',
            'country2' => 'DEU',
            2 => 'Braunschweig',
            'postal4' => '38106',
            4 => 'Bültenweg',
            'house6' => '73'
        ];
        $this->assertEquals($firstRow, $datasets[2]);
    }

    /**
     * @covers ::loadString
     * @covers ::readDatasetsCallback
     * @covers ::setHeaderFields
     */
    public function testReadDatasetsCallbackWithCustomFieldNamesAndNoHeadLine()
    {
        $csvReader = new CsvReader();

        $csvContent = implode("\n", $this->csvLines);
        $csvReader->loadString($csvContent);
        $csvReader->setHeaderFields(['id1', 'country2', '', 'postal4', '', 'house6']);
        $foundDataset = null;
        $csvReader->readDatasetsCallback(function (array $dataset) use (&$foundDataset) {
            $foundDataset = $dataset;
        }, 1, 1);

        $firstRow = [
            'id1' => '1',
            'country2' => 'DEU',
            2 => 'Braunschweig',
            'postal4' => '38106',
            4 => 'Bültenweg',
            'house6' => '73'
        ];
        $this->assertEquals($firstRow, $foundDataset);
    }

    /**
     * @covers ::loadString
     * @covers ::readDatasets
     * @covers ::setFirstLineIsHeader
     * @covers ::setStripTags
     */
    public function testStripTags()
    {
        $csvReader = new CsvReader();
        $csvReader->loadString(implode("\n", $this->csvLinesWithTags));
        $csvReader->setFirstLineIsHeader(true);

        $this->assertEquals(false, $csvReader->StripTags);
        $csvReader->setStripTags(true);
        $this->assertEquals(true, $csvReader->StripTags);

        $datasets = $csvReader->readDatasets();
        $this->assertEquals(2, count($datasets));

        $dataset1 = [
            'id' => '1',
            'country' => 'DEU',
            'city' => 'Braunschweig',
            'postal' => '38106',
            'street' => 'Bültenweg',
            'house' => '73'
        ];
        $dataset2 = [
            'id' => '2',
            'country' => 'DEU',
            'city' => 'Braunschweig',
            'postal' => '38106',
            'street' => 'Rebenring',
            'house' => '31'
        ];
        $this->assertEquals($dataset1, $datasets[1]);
        $this->assertEquals($dataset2, $datasets[2]);
    }

    /**
     * @covers ::detectSeparator
     */
    public function testDetectSeparator()
    {
        $commaSeparator = CsvReader::detectSeparator('1,2,3,4,5,6');
        $semicolonSeparator = CsvReader::detectSeparator('1;2;3;4');
        $pipeSeparator = CsvReader::detectSeparator('1|2,3|4');
        $tabSeparator = CsvReader::detectSeparator('1	2	3	4');
        $this->assertEquals(',', $commaSeparator);
        $this->assertEquals(';', $semicolonSeparator);
        $this->assertEquals('|', $pipeSeparator);
        $this->assertEquals('	', $tabSeparator);
    }

    /**
     * @covers ::__get
     * @covers ::loadString
     * @covers ::readDatasets
     * @covers ::setFirstLineIsHeader
     */
    public function testCSVWithBOM()
    {
        $csvReader = new CsvReader();
        $csvReader->loadString(implode("\n", $this->csvLinesWithBOM));
        $this->assertEquals(3, $csvReader->BOMLength);
        $this->assertEquals('UTF-8', $csvReader->BOMEncoding);

        $csvReader->setFirstLineIsHeader(true);

        $datasets = $csvReader->readDatasets();
        $this->assertEquals(2, count($datasets));

        $dataset1 = [
            'id' => '1',
            'country' => 'DEU',
            'city' => 'Braunschweig',
            'postal' => '38106',
            'street' => 'Bültenweg',
            'house' => '73'
        ];
        $dataset2 = [
            'id' => '2',
            'country' => 'DEU',
            'city' => 'Braunschweig',
            'postal' => '38106',
            'street' => 'Rebenring',
            'house' => '31'
        ];
        $this->assertEquals($dataset1, $datasets[1]);
        $this->assertEquals($dataset2, $datasets[2]);
    }

    /**
     * @covers ::__destruct
     * @covers ::loadFile
     */
    public function testException()
    {
        $this->expectExceptionMessage(
            'Locr\Lib\CsvReader::loadFile(string $filename): void => invalid file-content (\'no_file.csv\')'
        );

        $csvReader = new CsvReader();
        $csvReader->loadFile('no_file.csv');
    }
}
