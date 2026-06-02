<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Fixtures;

use JMS\Serializer\Annotation as Serializer;

class Model
{
    #[Serializer\Type('string')]
    #[Serializer\Groups(['api'])]
    public ?string $apiString = null;

    #[Serializer\Type('string')]
    #[Serializer\Groups(['details'])]
    public ?string $detailString = null;

    public $unAnnotated;

    #[Serializer\Type(Nested::class)]
    public ?Nested $nestedField = null;

    #[Serializer\Type('DateTime')]
    public ?\DateTime $date = null;

    #[Serializer\Type("DateTime<'Y-m-d'>")]
    public ?\DateTime $dateWithFormat = null;

    #[Serializer\Type("DateTime<'Y-m-d', '', 'd/m/Y'>")]
    public ?\DateTime $dateWithOneDeserializationFormat = null;

    #[Serializer\Type("DateTime<'Y-m-d', '', ['m/d/Y', 'Y-m-d']>")]
    public ?\DateTime $dateWithMultipleDeserializationFormats = null;

    #[Serializer\Type("DateTime<'Y-m-d', '+0600', '!d/m/Y'>")]
    public ?\DateTime $dateWithTimezone = null;

    #[Serializer\Type('DateTimeImmutable')]
    public ?\DateTimeImmutable $dateImmutable = null;

    #[Serializer\Type('DateTimeImmutable')]
    #[Serializer\Accessor(getter: 'getDateImmutablePrivate', setter: 'setDateImmutablePrivate')]
    private ?\DateTimeImmutable $dateImmutablePrivate = null;

    public function getDateImmutablePrivate(): ?\DateTimeImmutable
    {
        return $this->dateImmutablePrivate;
    }

    public function setDateImmutablePrivate(?\DateTimeImmutable $dateImmutablePrivate): void
    {
        $this->dateImmutablePrivate = $dateImmutablePrivate;
    }
}
