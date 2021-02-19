<?php

namespace Wowworks\TranslationGoogleSheet\models;

class TranslationStringConverter
{
    const PATTERN_FIND_QUOTE = '/\"|\'/';
    const PATTERN_FIND_SINGLE_QUOTE = '/\'/';
    const PATTERN_FIND_HTML = '/<.+?>/';
    const PATTEN_FIND_CARRIAGE_RETURN = '/\n|\r/';

    const SPACE = ' ';
    const FOUR_SPACE = '    ';
    const COMMA = ',';

    public function convertToString($pathToTranslationFile, array $translations): string
    {
        $headerString = $this->getHeaderString($pathToTranslationFile);
        $translationsAsString = $headerString . PHP_EOL . PHP_EOL . 'return' . self::SPACE . '[' . PHP_EOL;

        foreach ($translations as $key => $translation) {
            $translationsAsString .= $this->convertKeyValueToString($key, $translation);
        }

        $translationsAsString .=  ']' . ';' . PHP_EOL;
        return $translationsAsString;
    }

    private function getHeaderString(string $pathToTranslationFile): string
    {
        $translationsAsString = file_get_contents($pathToTranslationFile);
        $headerString = explode('return', $translationsAsString)[0];
        return trim($headerString);
    }

    private function convertKeyValueToString(string $key, $translation): string
    {
        return self::FOUR_SPACE . (new Literal($key)) . self::SPACE . '=>'
            . self::SPACE . $this->normalize($translation) . self::COMMA . PHP_EOL;
    }

    private function normalize($translation)
    {
        if ($translation === null) {
            return 'null';
        } elseif ($translation === '') {
            return "''";
        }

        if ($this->isEmojiExists($translation)
            || $this->isCarriageReturnExists($translation)
            || $this->isSingleQuoteExists($translation)
        ) {
            return json_encode($translation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $quote = '\'';
            return "{$quote}{$translation}{$quote}";
        }
    }

    private function isSingleQuoteExists(string $translation): bool
    {
        return preg_match(self::PATTERN_FIND_SINGLE_QUOTE, $translation);
    }

    private function isCarriageReturnExists(string $translation): bool
    {
        return preg_match(self::PATTEN_FIND_CARRIAGE_RETURN, $translation);
    }

    private function isHtmlExists(string $translation): bool
    {
        return preg_match(self::PATTERN_FIND_HTML, $translation);
    }

    private function isEmojiExists(string $translations): bool
    {
        $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
        preg_match($regexEmoticons, $translations, $matches_emo);
        if (!empty($matches_emo[0])) {
            return true;
        }

        $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
        preg_match($regexSymbols, $translations, $matches_sym);
        if (!empty($matches_sym[0])) {
            return true;
        }

        $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
        preg_match($regexTransport, $translations, $matches_trans);
        if (!empty($matches_trans[0])) {
            return true;
        }

        $regexMisc = '/[\x{2600}-\x{26FF}]/u';
        preg_match($regexMisc, $translations, $matches_misc);
        if (!empty($matches_misc[0])) {
            return true;
        }

        $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
        preg_match($regexDingbats, $translations, $matches_bats);
        if (!empty($matches_bats[0])) {
            return true;
        }

        return false;
    }
}
