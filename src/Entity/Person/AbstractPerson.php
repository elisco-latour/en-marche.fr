<?php

namespace AppBundle\Entity\Person;

use Doctrine\ORM\Mapping as ORM;
use AppBundle\Entity\EntityIdentityTrait;
use AppBundle\Entity\EntityPersonNameTrait;
use AppBundle\Entity\EntityTimestampableTrait;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\MappedSuperclass
 */
abstract class AbstractPerson
{
    use EntityIdentityTrait;
    use EntityPersonNameTrait;
    use EntityTimestampableTrait;

    /**
     * @ORM\Column
     *
     * @Gedmo\Slug(fields={"firstName", "lastName"})
     */
    protected $slug;

    /**
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    protected $published = false;

    /**
     * @ORM\Column(nullable=true)
     *
     * @Assert\Length(max=255)
     */
    protected $description;

    /**
     * @ORM\Column(length=800, nullable=true)
     *
     * @Assert\Length(min=5, max=800)
     */
    protected $content;

    /**
     * @ORM\Column(nullable=true)
     *
     * @Assert\Length(max=255)
     */
    protected $facebookProfile;

    /**
     * @ORM\Column(nullable=true)
     *
     * @Assert\Length(max=255)
     */
    protected $twitterProfile;

    /**
     * @ORM\Column(nullable=true)
     *
     * @Assert\Length(max=255)
     */
    protected $instagramProfile;

    /**
     * @ORM\Column(nullable=true)
     *
     * @Assert\Length(max=255)
     */
    protected $linkedInProfile;

    public function __construct(UuidInterface $uuid = null)
    {
        $this->uuid = $uuid ?: Uuid::uuid4();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setPublished(bool $published): void
    {
        $this->published = $published;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function getFacebookProfile(): ?string
    {
        return $this->facebookProfile;
    }

    public function setFacebookProfile(string $facebookProfile): void
    {
        $this->facebookProfile = $facebookProfile;
    }

    public function getTwitterProfile(): ?string
    {
        return $this->twitterProfile;
    }

    public function setTwitterProfile(string $twitterProfile): void
    {
        $this->twitterProfile = $twitterProfile;
    }

    public function getInstagramProfile(): ?string
    {
        return $this->instagramProfile;
    }

    public function setInstagramProfile(string $instagramProfile): void
    {
        $this->instagramProfile = $instagramProfile;
    }

    public function getLinkedInProfile(): ?string
    {
        return $this->linkedInProfile;
    }

    public function setLinkedInProfile(string $linkedInProfile): void
    {
        $this->linkedInProfile = $linkedInProfile;
    }
}
