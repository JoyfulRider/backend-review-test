<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class SearchInput
{
    /**
     * @Assert\NotBlank()
     */
    public \DateTimeImmutable $date;

    /**
     * @Assert\NotBlank()
     */
    public string $keyword;
}
