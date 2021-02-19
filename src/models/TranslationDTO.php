<?php

namespace Wowworks\TranslationGoogleSheet\models;

class TranslationDTO
{
    /**
     * @var string
     */
    private $key;

    /**
     * ['language' => translation]
     * @var array
     */
    private $translations;

    public function __construct(string $key, array $translations)
    {
        $this->key = $key;
        $this->translations = $translations;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $language
     * @return string
     */
    public function getTranslationByLanguage(string $language): string
    {
        return $this->translations[$language];
    }
}
