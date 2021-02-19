<?php

namespace Wowworks\TranslationGoogleSheet;

use Wowworks\TranslationGoogleSheet\exceptions\TranslationGoogleSheetException;
use Wowworks\TranslationGoogleSheet\models\Sheet;
use Wowworks\TranslationGoogleSheet\models\TranslationDTO;
use yii\i18n\PhpMessageSource;

class TranslationGoogleSheetConfigurationService
{
    const PATTERN_FIND_LANGUAGE = '/[a-z]{2}_[A-Z]{2}/';
    const PATTERN_FIND_SPREADSHEET_ID = '/spreadsheets\/d\/([a-zA-Z0-9-_]+)/';

    const PHP_EXTENSION = '.php';

    const INDEX_NAME_TOKEN = 0;
    const INDEX_VALUE_TOKEN = 1;
    const INDEX_LINE_TOKEN = 2;

    /**
     * @var string[]
     */
    private $languages;

    /**
     * @var string
     */
    private $languageRu;

    public function __construct(array $languages, string $languageRu)
    {
        $this->languages = $languages;
        $this->languageRu = $languageRu;
    }

    public function getLanguages(): array
    {
        return $this->languages;
    }

    /**
     * @param mixed[] $translations
     * @return string[]
     */
    public function getAllPathsToTranslations(array $translations): array
    {
        $paths = [];
        foreach ($translations as $category => $translation) {
            if (!($translation instanceof PhpMessageSource) && !key_exists('basePath', $translation)) {
                continue;
            }
            $category = str_replace('*', '', $category);

            if ($translation instanceof PhpMessageSource) {
                if (!empty($translation->fileMap)) {
                    $pathToTranslation = str_replace('@', '', $translation->basePath) . "/{$this->languageRu}";
                    foreach ($translation->fileMap as $file) {
                        $paths[] = $pathToTranslation . '/' . $file;
                    }
                }
                continue;
            }

            $pathToTranslationWithoutCategory = str_replace('@', '', $translation['basePath']) . "/{$this->languageRu}";
            $pathToTranslationWithCategory = $pathToTranslationWithoutCategory . '/' . $category;

            if (is_dir($pathToTranslationWithCategory)) {
                foreach (array_diff(scandir($pathToTranslationWithCategory), ['..', '.']) as $file) {
                    $paths[] = $pathToTranslationWithCategory . '/' . $file;
                }
            } elseif (key_exists('fileMap', $translation)) {
                foreach ($translation['fileMap'] as $key => $file) {
                    $paths[] = $pathToTranslationWithoutCategory . '/' . $file;
                }
            } elseif (file_exists($pathToTranslationWithCategory . self::PHP_EXTENSION)) {
                $paths[] = $pathToTranslationWithCategory . self::PHP_EXTENSION;
            } else {
                throw new TranslationGoogleSheetException("Unable to locate message source for category {$category}.");
            }
        }
        return array_unique($paths);
    }

    public function replaceNameLanguageDirectoryInPath(string $pathToFile, string $nameLanguageDirectory): string
    {
        return preg_replace(self::PATTERN_FIND_LANGUAGE, $nameLanguageDirectory, $pathToFile);
    }

    /**
     * @param string $pathToTranslations
     * @return string[]
     */
    public function getTranslationsWithConvertedKeysFromFile(string $pathToTranslations): array
    {
        if (!file_exists($pathToTranslations)) {
            throw new TranslationGoogleSheetException("Translation not found by path {$pathToTranslations}");
        }

        $tokens = token_get_all(file_get_contents($pathToTranslations));
        $convertedKeys = [];
        $tokensCount = count($tokens) - 1;

        for ($i = 0; $i <= $tokensCount; $i++) {
            $token = $tokens[$i];
            $isDoubleColonToken = $token[self::INDEX_NAME_TOKEN] === T_DOUBLE_COLON;
            if (is_array($token) && $isDoubleColonToken) {
                $previousIndex = $i - 1;
                $nextIndex = $i + 1;

                $tokenClass = $tokens[$previousIndex];
                $tokenDoubleColon = $token;
                $tokenConst = $tokens[$nextIndex];

                $tokenSpace = $tokens[$nextIndex + 1];
                $tokenDoubleArrow = $tokens[$nextIndex + 2];

                $isTokenSpace = is_array($tokenSpace) && $tokenSpace[self::INDEX_NAME_TOKEN] === T_WHITESPACE;
                $isTokenDoubleArrow = is_array($tokenDoubleArrow) && $tokenDoubleArrow[self::INDEX_NAME_TOKEN] === T_DOUBLE_ARROW;
                if (!($isTokenSpace && $isTokenDoubleArrow)) {
                    throw new TranslationGoogleSheetException("File {$pathToTranslations} exists errors on line:" . $tokenConst[self::INDEX_LINE_TOKEN] . '.'
                        . 'After key: "ClassName::Const" must be one whitespace and char: "=>"');
                }

                $convertedKey = $tokenClass[self::INDEX_VALUE_TOKEN] . $tokenDoubleColon[self::INDEX_VALUE_TOKEN] . $tokenConst[self::INDEX_VALUE_TOKEN];
                $convertedKeys[] = $convertedKey;
            }
        }

        $translations = require($pathToTranslations);
        $translations = is_array($translations) ? $translations : [];
        if (count($convertedKeys) != count($translations)) {
            throw new TranslationGoogleSheetException("File by path {$pathToTranslations} exist errors or null");
        }

         return array_combine($convertedKeys, $translations);
    }

    public function getSpreadsheetIdFromUrl(string $url): string
    {
        preg_match(self::PATTERN_FIND_SPREADSHEET_ID, $url, $matches);

        if (!isset($matches[1])) {
            throw new TranslationGoogleSheetException('Url not exists in config');
        }

        $spreadsheetId = $matches[1];
        return $spreadsheetId;
    }

    public function getSheetTitleFromPath(string $pathToTranslations): string
    {
        $language = $this->getLanguageFromPath($pathToTranslations);
        return str_replace([self::PHP_EXTENSION, $language], ['', '<language>'], $pathToTranslations);
    }

    /**
     * @param string[] $pathsToTranslations
     * @return Sheet[]
     */
    public function makeSheetListByPathsToTranslations(array $pathsToTranslations): array
    {
        return array_map(function ($pathToTranslations) {
             return $this->makeSheetByPathToTranslation($pathToTranslations);
        }, $pathsToTranslations);
    }

    private function getLanguageFromPath(string $pathToTranslations): string
    {
        preg_match(self::PATTERN_FIND_LANGUAGE, $pathToTranslations, $matches);
        $language = $matches[0];
        return $language;
    }

    private function makeSheetByPathToTranslation(string $pathToTranslations): Sheet
    {
        $sheetTitle = $this->getSheetTitleFromPath($pathToTranslations);
        $sheet = new Sheet($sheetTitle);
        $convertedTranslationsKeysRussianLanguage = $this->getConvertedTranslationKeysRussianLanguage($pathToTranslations);
        $translationsOnAllLanguagesWithConvertedKeys = $this->getTranslationsFromFileAndNeighborLanguageDirectory($pathToTranslations);

        foreach ($convertedTranslationsKeysRussianLanguage as $convertedKey) {
            $translation = [];
            foreach ($this->getLanguages() as $language) {
                $translation[$language] = $translationsOnAllLanguagesWithConvertedKeys[$language][$convertedKey] ?? '';
            }

            $translationsDTO = new TranslationDTO($convertedKey, $translation);
            $sheet->push($translationsDTO);
        }

        return $sheet;
    }

    private function getConvertedTranslationKeysRussianLanguage(string $pathToTranslations): array
    {
        $pathToTranslationsRussianLanguage = $this->replaceNameLanguageDirectoryInPath($pathToTranslations, $this->languageRu);
        $translationsRussianLanguageWithConvertedKeys = $this->getTranslationsWithConvertedKeysFromFile($pathToTranslationsRussianLanguage);
        return array_keys($translationsRussianLanguageWithConvertedKeys);
    }

    private function getTranslationsFromFileAndNeighborLanguageDirectory(string $pathToTranslations): array
    {
        $languages = $this->getLanguages();

        $translations = [];
        foreach ($languages as $language) {
            $pathWithReplacedNameLanguageDirectory = $this->replaceNameLanguageDirectoryInPath($pathToTranslations, $language);
            $translations[$language] = $this->getTranslationsWithConvertedKeysFromFile($pathWithReplacedNameLanguageDirectory);
        }

        return $translations;
    }
}
