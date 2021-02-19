<?php

namespace Wowworks\TranslationGoogleSheet;

use Google_Service_Sheets;
use Google_Service_Sheets_BatchClearValuesRequest;
use Google_Service_Sheets_BatchClearValuesResponse;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Google_Service_Sheets_BatchUpdateValuesRequest;
use Google_Service_Sheets_BatchUpdateValuesResponse;
use Google_Service_Sheets_Request;
use Google_Service_Sheets_ValueRange;
use Wowworks\TranslationGoogleSheet\models\Sheet;
use Wowworks\TranslationGoogleSheet\Models\TranslationStringConverter;

class TranslationGoogleSheetService
{
    private const INDEX_HEADER_ROW = 0;

    /**
     * @var string[]
     */
    private $spreadSheetUrls = [];

    private $googleSheetService;
    private $configurationService;
    private $translationStringConverter;

    public function __construct(
        array $spreadSheetUrls,
        Google_Service_Sheets $googleSheetService,
        TranslationGoogleSheetConfigurationService $configurationService,
        TranslationStringConverter $translationStringConverter
    ) {
        $this->spreadSheetUrls = $spreadSheetUrls;
        $this->googleSheetService = $googleSheetService;
        $this->configurationService = $configurationService;
        $this->translationStringConverter = $translationStringConverter;
    }

    /**
     * @param string[] $pathsToTranslations
     */
    public function push(array $pathsToTranslations): void
    {
        $sheets = $this->configurationService->makeSheetListByPathsToTranslations($pathsToTranslations);

        foreach ($this->spreadSheetUrls as $url) {
            $spreadsheetId = $this->configurationService->getSpreadsheetIdFromUrl($url);
            $sheetTitleListFromGoogleSheet = $this->sendRequestAboutTitleListFromGoogleSheet($spreadsheetId);

            $sheetsNotExistInGoogleSheet = array_filter($sheets, function ($sheet) use ($sheetTitleListFromGoogleSheet) {
                return !in_array($sheet->getTitle(), $sheetTitleListFromGoogleSheet);
            });

            if (!empty($sheetsNotExistInGoogleSheet)) {
                $this->sendRequestForAddSheetsToGoogleSheet($spreadsheetId, $sheetsNotExistInGoogleSheet);
            }

            $this->sendRequestForClear($spreadsheetId, $sheets);
            $this->sendRequestForUpdate($spreadsheetId, $sheets);
        }
    }

    /**
     * @param string[] $pathsToTranslations
     */
    public function pull(array $pathsToTranslations): void
    {
        foreach ($this->spreadSheetUrls as $url) {
            $spreadsheetId = $this->configurationService->getSpreadsheetIdFromUrl($url);

            foreach ($pathsToTranslations as $pathToTranslationFile) {
                $sheetTitle = $this->configurationService->getSheetTitleFromPath($pathToTranslationFile);

                $translations = $this->getTranslationsFromGoogleSheet($spreadsheetId, $sheetTitle);

                if (!empty($translations)) {
                    $this->updateTranslationsInFile($pathToTranslationFile, $translations);
                }
            }
        }
    }

    /**
     * @param string $spreadsheetId
     * @param string $sheetTitle
     * @return array ['language' => ['key' => translation]]
     */
    private function getTranslationsFromGoogleSheet(string $spreadsheetId, string $sheetTitle): array
    {
        $values =  $this->sendRequestForGetTranslationsFromSheet($spreadsheetId, $sheetTitle);

        if (empty($values)) {
            return [];
        }

        $indicesHeaderRow = array_flip($values[self::INDEX_HEADER_ROW]);
        $languages = $this->configurationService->getLanguages();

        $translationsWithLanguages = [];
        foreach ($languages as $language) {
            $indexLanguage = $indicesHeaderRow[$language];
            $valuesWithoutHeaderRow = $this->removeHeaderRow($values);
            $filteredValuesWithoutHeaderRow = array_filter($valuesWithoutHeaderRow, function ($value) use ($indexLanguage) {
                return isset($value[$indexLanguage]);
            });

            $translationKeys = array_column($filteredValuesWithoutHeaderRow, $indicesHeaderRow['Key']);
            $translations = array_column($filteredValuesWithoutHeaderRow, $indexLanguage);

            $translationsWithLanguages[$language] = array_filter(array_combine($translationKeys, $translations));
        }

        return $translationsWithLanguages;
    }

    /**
     * @param string $pathToTranslationFile
     * @param mixed[] $newTranslations ['language' => ['key' => translation]]
     */
    private function updateTranslationsInFile(string $pathToTranslationFile, array $newTranslations): void
    {
        foreach ($this->configurationService->getLanguages() as $language) {
            $path = $this->configurationService->replaceNameLanguageDirectoryInPath(
                $pathToTranslationFile,
                $language
            );
            $oldTranslationsWithConvertedKeys = $this->configurationService->getTranslationsWithConvertedKeysFromFile($path);
            $updatedTranslations = array_merge($oldTranslationsWithConvertedKeys, $newTranslations[$language]);

            $translationsAsString = $this->translationStringConverter->convertToString(
                $pathToTranslationFile,
                $updatedTranslations
            );

            file_put_contents($path, $translationsAsString);
        }
    }

    /**
     * @param string $spreadSheetId
     * @return string[]
     */
    private function sendRequestAboutTitleListFromGoogleSheet(string $spreadSheetId): array
    {
        $spreadsheet = $this->googleSheetService->spreadsheets->get($spreadSheetId);
        $sheetTitleList = [];

        foreach ($spreadsheet->getSheets() as $sheet) {
            $sheetTitleList[] = $sheet->getProperties()->getTitle();
        }

        return $sheetTitleList;
    }

    /**
     * @param string $spreadSheetId
     * @param  Sheet[] $sheets
     */
    private function sendRequestForAddSheetsToGoogleSheet(string $spreadSheetId, array $sheets): void
    {
        $requests = [];
        foreach ($sheets as $sheet) {
            $requests[] = new Google_Service_Sheets_Request([
                'addSheet' => [
                    'properties' => [
                        'title' => $sheet->getTitle()
                    ]
                ]
            ]);
        }

        $batchUpdateSpreadsheetRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => $requests
        ]);

        $this->googleSheetService->spreadsheets->batchUpdate($spreadSheetId, $batchUpdateSpreadsheetRequest);
    }

    /**
     * @param string $spreadsheetId
     * @param Sheet[] $sheets
     */
    private function sendRequestForClear(string $spreadsheetId, array $sheets): Google_Service_Sheets_BatchClearValuesResponse
    {
        $ranges = [];
        foreach ($sheets as $sheet) {
            $ranges[] = $sheet->getTitle();
        }

        $requestBody = new Google_Service_Sheets_BatchClearValuesRequest([
            'ranges' => $ranges
        ]);

        return $this->googleSheetService->spreadsheets_values->batchClear($spreadsheetId, $requestBody);
    }

    /**
     * @param string $spreadsheetId
     * @param Sheet[] $sheets
     * @return Google_Service_Sheets_BatchUpdateValuesResponse
     */
    private function sendRequestForUpdate(string $spreadsheetId, array $sheets): Google_Service_Sheets_BatchUpdateValuesResponse
    {
        $dataForRequest = [];
        foreach ($sheets as $sheet) {
            $dataForRequest[] = $this->prepareDataForUpdateRequest($sheet);
        }

        $body = new Google_Service_Sheets_BatchUpdateValuesRequest([
            'valueInputOption' => 'RAW',
            'data' => $dataForRequest
        ]);

        return $this->googleSheetService->spreadsheets_values->batchUpdate($spreadsheetId, $body);
    }

    private function sendRequestForGetTranslationsFromSheet(string $spreadsheetId, string $sheetTitle)
    {
        $response = $this->googleSheetService->spreadsheets_values->get($spreadsheetId, "{$sheetTitle}");
        return $response->getValues();
    }

    /**
     * @param string $sheetTitle
     * @param Sheet $sheet
     * @return Google_Service_Sheets_ValueRange
     */
    private function prepareDataForUpdateRequest(Sheet $sheet): Google_Service_Sheets_ValueRange
    {
        $headerRow = $this->getHeaderRow();
        $values = [];

        while ($sheet->getPosition() < $sheet->count()) {
            $row = [];

            if ($sheet->isEmpty()) {
                break;
            }
            if (!$this->existHeader($values)) {
                $values[] = $headerRow;
                continue;
            }

            $translationDTO = $sheet->current();
            $row[] = $translationDTO->getKey();
            foreach ($this->configurationService->getLanguages() as $language) {
                $row[]= $translationDTO->getTranslationByLanguage($language);
            }

            $values[] = $row;
            $sheet->next();
        }

        $sheet->rewind();

        return new Google_Service_Sheets_ValueRange([
            'range' => $sheet->getTitle(),
            'values' => $values
        ]);
    }

    private function getHeaderRow(): array
    {
        $languages = $this->configurationService->getLanguages();
        $headerRow[] = 'Key';
        foreach ($languages as $language) {
            $headerRow[] = $language;
        }

        return $headerRow;
    }

    private function existHeader(array $values): bool
    {
        return count($values) > 0;
    }

    private function removeHeaderRow(array $values): array
    {
        $processedValues = $values;
        unset($processedValues[self::INDEX_HEADER_ROW]);
        return $processedValues;
    }
}
