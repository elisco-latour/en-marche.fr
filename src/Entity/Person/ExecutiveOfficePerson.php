<?php

namespace AppBundle\Entity\Person;

use Algolia\AlgoliaSearchBundle\Mapping\Annotation as Algolia;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 * @ORM\Table(
 *     name="person_executive_office",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="executive_office_person_uuid_unique", columns="uuid"),
 *         @ORM\UniqueConstraint(name="executive_office_person_slug_unique", columns="slug")
 *     }
 * )
 *
 * @Algolia\Index(autoIndex=false)
 */
class ExecutiveOfficePerson extends AbstractPerson
{
    /**
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    private $executiveOfficer = false;

    /**
     * @ORM\Column(type="text")
     */
    private $job;

    public function __construct(UuidInterface $uuid = null)
    {
        parent::__construct($uuid);
    }

    public function setExecutiveOfficer(bool $executiveOfficer): void
    {
        $this->executiveOfficer = $executiveOfficer;
    }

    public function isExecutiveOfficer(): bool
    {
        return $this->executiveOfficer;
    }

    public function getJob(): ?string
    {
        return $this->job;
    }

    public function setJob(string $job): void
    {
        $this->job = $job;
    }
}
