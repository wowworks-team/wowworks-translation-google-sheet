<?php

namespace Wowworks\TranslationGoogleSheet\models;

class Sheet
{
    /**
     * @var int
     */
    private $position = 0;

    /**
     * @var string
     */
    private $title;

    /**
     * @var TranslationDTO[]
     */
    private $translationDTOList = [];

    public function __construct(string $title, $translationDTOList = [])
    {
        $this->title = $title;
        if (!empty($translationDTOList)) {
            foreach ($translationDTOList as $translationDTO) {
                $this->$translationDTOList[] = $translationDTO;
            }
        }
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function current(): ?TranslationDTO
    {
        return $this->translationDTOList[$this->position] ?? null;
    }

    public function next()
    {
        $this->position++;
    }

    public function push(TranslationDTO $value): void
    {
        array_push($this->translationDTOList, $value);
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function count(): int
    {
        return count($this->translationDTOList);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }
}
