<?php

namespace AppBundle\Entity;

use Algolia\AlgoliaSearchBundle\Mapping\Annotation as Algolia;
use AppBundle\OAuth\Model\User as InMemoryOAuthUser;
use AppBundle\Collection\CitizenProjectMembershipCollection;
use AppBundle\Collection\CommitteeMembershipCollection;
use AppBundle\Entity\BoardMember\BoardMember;
use AppBundle\Exception\AdherentAlreadyEnabledException;
use AppBundle\Exception\AdherentException;
use AppBundle\Exception\AdherentTokenException;
use AppBundle\Geocoder\GeoPointInterface;
use AppBundle\Membership\ActivityPositions;
use AppBundle\Membership\MembershipInterface;
use AppBundle\Membership\MembershipRequest;
use AppBundle\Subscription\SubscriptionTypeEnum;
use AppBundle\ValueObject\Genders;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use libphonenumber\PhoneNumber;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\Encoder\EncoderAwareInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use JMS\Serializer\Annotation as JMS;

/**
 * @ORM\Table(name="adherents", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="adherents_uuid_unique", columns="uuid"),
 *     @ORM\UniqueConstraint(name="adherents_email_address_unique", columns="email_address")
 * })
 * @ORM\Entity(repositoryClass="AppBundle\Repository\AdherentRepository")
 *
 * @Algolia\Index(autoIndex=false)
 */
class Adherent implements UserInterface, GeoPointInterface, EncoderAwareInterface, MembershipInterface, ReferentTaggableEntity, \Serializable
{
    public const ENABLED = 'ENABLED';
    public const DISABLED = 'DISABLED';
    public const DISABLED_CITIZEN_PROJECT_EMAIL = -1;
    public const CITIZEN_PROJECT_EMAIL_DEFAULT_DISTANCE = 10;

    use EntityCrudTrait;
    use EntityIdentityTrait;
    use EntityPersonNameTrait;
    use EntityPostAddressTrait;
    use LazyCollectionTrait;

    /**
     * @ORM\Column(nullable=true)
     */
    private $password;

    /**
     * @ORM\Column(nullable=true)
     */
    private $oldPassword;

    /**
     * @ORM\Column(length=6, nullable=true)
     */
    private $gender;

    /**
     * @ORM\Column
     *
     * @JMS\Groups({"user_profile", "public"})
     * @JMS\SerializedName("emailAddress")
     */
    private $emailAddress;

    /**
     * @ORM\Column(type="phone_number", nullable=true)
     */
    private $phone;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $birthdate;

    /**
     * @ORM\Column(length=20, nullable=true)
     */
    private $position;

    /**
     * @ORM\Column(length=10, options={"default": "DISABLED"})
     */
    private $status;

    /**
     * @ORM\Column(type="datetime")
     */
    private $registeredAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $activatedAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Gedmo\Timestampable(on="update")
     */
    private $updatedAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $lastLoggedAt;

    /**
     * @ORM\Column(type="simple_array", nullable=true)
     */
    private $interests = [];

    /**
     * @var SubscriptionType[]|Collection
     *
     * @ORM\ManyToMany(targetEntity="SubscriptionType", cascade={"persist"})
     */
    private $subscriptionTypes;

    /**
     * @ORM\Column(type="integer", options={"default": 10})
     */
    private $citizenProjectCreationEmailSubscriptionRadius = self::CITIZEN_PROJECT_EMAIL_DEFAULT_DISTANCE;

    /**
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    private $legislativeCandidate;

    /**
     * @var ReferentManagedArea|null
     *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\ReferentManagedArea", cascade={"all"}, orphanRemoval=true)
     */
    private $managedArea;

    /**
     * @var CoordinatorManagedArea|null
     *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\CoordinatorManagedArea", cascade={"all"}, orphanRemoval=true)
     */
    private $coordinatorCitizenProjectArea;

    /**
     * @var CoordinatorManagedArea|null
     *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\CoordinatorManagedArea", cascade={"all"}, orphanRemoval=true)
     */
    private $coordinatorCommitteeArea;

    /**
     * @var ProcurationManagedArea|null
     *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\ProcurationManagedArea", mappedBy="adherent", cascade={"all"}, orphanRemoval=true)
     */
    private $procurationManagedArea;

    /**
     * @var BoardMember|null
     *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\BoardMember\BoardMember", mappedBy="adherent", cascade={"all"}, orphanRemoval=true)
     */
    private $boardMember;

    /**
     * @var CommitteeMembership[]|Collection
     *
     * @ORM\OneToMany(targetEntity="CommitteeMembership", mappedBy="adherent", cascade={"remove"})
     */
    private $memberships;

    /**
     * @var CitizenProjectMembership[]|Collection
     *
     * @ORM\OneToMany(targetEntity="CitizenProjectMembership", mappedBy="adherent", cascade={"remove"})
     */
    private $citizenProjectMemberships;

    /**
     * @var CommitteeFeedItem[]|Collection|iterable
     *
     * @ORM\OneToMany(targetEntity="CommitteeFeedItem", mappedBy="author", cascade={"remove"})
     */
    private $committeeFeedItems;

    /**
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\AdherentTag")
     */
    private $tags;

    /**
     * @var Collection|ReferentTag[]
     *
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\ReferentTag")
     */
    private $referentTags;

    /**
     * @var District|null
     *
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\District", mappedBy="adherent", cascade={"persist"})
     */
    private $managedDistrict;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private $adherent = false;

    /**
     * @var InMemoryOAuthUser|null
     */
    private $oAuthUser;

    /**
     * @var string[]
     */
    private $roles = [];

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    public $localHostEmailsSubscription = false;

    /**
     * @var string[]
     *
     * @ORM\Column(type="simple_array", nullable=true)
     */
    public $emailsSubscriptions;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    public $comMobile;

    /**
     * Activation token was already sent after 48h of registration
     *
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private $remindSent = false;

    public function __construct(
        UuidInterface $uuid,
        string $emailAddress,
        string $password,
        ?string $gender,
        string $firstName,
        string $lastName,
        ?\DateTime $birthDate,
        ?string $position,
        PostAddress $postAddress,
        PhoneNumber $phone = null,
        string $status = self::DISABLED,
        string $registeredAt = 'now',
        ?array $tags = [],
        ?array $referentTags = []
    ) {
        $this->uuid = $uuid;
        $this->password = $password;
        $this->gender = $gender;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->emailAddress = $emailAddress;
        $this->birthdate = $birthDate;
        $this->position = $position;
        $this->postAddress = $postAddress;
        $this->phone = $phone;
        $this->status = $status;
        $this->legislativeCandidate = false;
        $this->registeredAt = new \DateTime($registeredAt);
        $this->memberships = new ArrayCollection();
        $this->citizenProjectMemberships = new ArrayCollection();
        $this->tags = new ArrayCollection($tags);
        $this->referentTags = new ArrayCollection($referentTags);
        $this->subscriptionTypes = new ArrayCollection();
    }

    public static function createUuid(string $email): UuidInterface
    {
        return Uuid::uuid5(Uuid::NAMESPACE_OID, $email);
    }

    /**
     * @JMS\VirtualProperty
     * @JMS\SerializedName("uuid"),
     * @JMS\Groups({"user_profile", "public"})
     */
    public function getUuidAsString(): string
    {
        return $this->getUuid()->toString();
    }

    /**
     * @JMS\VirtualProperty
     * @JMS\SerializedName("subscriptionExternalIds"),
     * @JMS\Groups({"public"})
     */
    public function getSubscriptionExternalIds(): array
    {
        return array_values(array_filter(array_map(function (SubscriptionType $subscription) {
            return $subscription->getExternalId();
        }, $this->getSubscriptionTypes())));
    }

    public function getRoles()
    {
        $roles = ['ROLE_USER'];

        if ($this->isAdherent()) {
            $roles[] = 'ROLE_ADHERENT';
        }

        if ($this->isReferent()) {
            $roles[] = 'ROLE_REFERENT';
        }

        if ($this->isDeputy()) {
            $roles[] = 'ROLE_DEPUTY';
        }

        if ($this->isCoordinator()) {
            $roles[] = 'ROLE_COORDINATOR';
        }

        if ($this->isCoordinatorCitizenProjectSector()) {
            $roles[] = 'ROLE_COORDINATOR_CITIZEN_PROJECT';
        }

        if ($this->isCoordinatorCommitteeSector()) {
            $roles[] = 'ROLE_COORDINATOR_COMMITTEE';
        }

        if ($this->isHost()) {
            $roles[] = 'ROLE_HOST';
        }

        if ($this->isSupervisor()) {
            $roles[] = 'ROLE_SUPERVISOR';
        }

        if ($this->isProcurationManager()) {
            $roles[] = 'ROLE_PROCURATION_MANAGER';
        }

        if ($this->legislativeCandidate) {
            $roles[] = 'ROLE_LEGISLATIVE_CANDIDATE';
        }

        if ($this->isBoardMember()) {
            $roles[] = 'ROLE_BOARD_MEMBER';
        }

        if ($this->isCitizenProjectAdministrator()) {
            $roles[] = 'ROLE_CITIZEN_PROJECT_ADMINISTRATOR';
        }

        return array_merge($roles, $this->roles);
    }

    public function addRoles(array $roles): void
    {
        foreach ($roles as $role) {
            $this->roles[] = $role;
        }
    }

    public function getType(): string
    {
        if ($this->isReferent()) {
            return 'REFERENT';
        }

        if ($this->isHost()) {
            return 'HOST';
        }

        return 'ADHERENT';
    }

    public function hasAdvancedPrivileges(): bool
    {
        return $this->isReferent()
            || $this->isCoordinator()
            || $this->isProcurationManager()
            || $this->isHost()
            || $this->isCitizenProjectAdministrator()
            || $this->isBoardMember()
            || $this->isDeputy()
        ;
    }

    public function getPassword()
    {
        return !$this->password ? $this->oldPassword : $this->password;
    }

    public function hasLegacyPassword(): bool
    {
        return null !== $this->oldPassword;
    }

    public function getEncoderName(): ?string
    {
        if ($this->hasLegacyPassword()) {
            return 'legacy_encoder';
        }

        return null;
    }

    public function getSalt()
    {
    }

    public function getUsername()
    {
        return $this->emailAddress;
    }

    public function eraseCredentials()
    {
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function getPhone(): ?PhoneNumber
    {
        return $this->phone;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function isForeignResident(): bool
    {
        return 'FR' !== strtoupper($this->getCountry());
    }

    public function isFemale(): bool
    {
        return Genders::FEMALE === $this->gender;
    }

    public function getBirthdate(): ?\DateTime
    {
        return $this->birthdate;
    }

    public function getAge(): ?int
    {
        return $this->birthdate ? $this->birthdate->diff(new \DateTime())->y : null;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(string $position): void
    {
        if (!ActivityPositions::exists($position)) {
            throw new \InvalidArgumentException(sprintf('Invalid position "%s", known positions are "%s".', $position, implode('", "', ActivityPositions::ALL)));
        }

        $this->position = $position;
    }

    public function isEnabled(): bool
    {
        return self::ENABLED === $this->status;
    }

    public function getActivatedAt(): ?\DateTime
    {
        return $this->activatedAt;
    }

    public function changePassword(string $newPassword): void
    {
        $this->password = $newPassword;
    }

    public function getEmailsSubscriptions(): array
    {
        return array_values(array_map(function (SubscriptionType $type) {
            return $type->getCode();
        }, $this->subscriptionTypes->toArray()));
    }

    /**
     * @return SubscriptionType[]
     */
    public function getSubscriptionTypes(): array
    {
        return $this->subscriptionTypes->toArray();
    }

    public function hasSubscriptionType(string $code): bool
    {
        return $this->subscriptionTypes->exists(function (int $index, SubscriptionType $type) use ($code) {
            return $type->getCode() === $code;
        });
    }

    public function hasSubscribedLocalHostEmails(): bool
    {
        return $this->hasSubscriptionType(SubscriptionTypeEnum::LOCAL_HOST_EMAIL);
    }

    /**
     * Activates the Adherent account with the provided activation token.
     *
     * @throws AdherentException
     * @throws AdherentTokenException
     */
    public function activate(AdherentActivationToken $token, string $timestamp = 'now'): void
    {
        if ($this->activatedAt) {
            throw new AdherentAlreadyEnabledException($this->uuid);
        }

        $token->consume($this);

        $this->status = self::ENABLED;
        $this->activatedAt = new \DateTime($timestamp);
    }

    /**
     * Resets the Adherent password using a reset pasword token.
     *
     * @throws \InvalidArgumentException
     * @throws AdherentException
     * @throws AdherentTokenException
     */
    public function resetPassword(AdherentResetPasswordToken $token): void
    {
        if (!$newPassword = $token->getNewPassword()) {
            throw new \InvalidArgumentException('Token must have a new password.');
        }

        $token->consume($this);

        $this->clearOldPassword();
        $this->password = $newPassword;
    }

    public function changeEmail(AdherentChangeEmailToken $token): void
    {
        if (!$token->getEmail()) {
            throw new \InvalidArgumentException('Token must have a new email.');
        }

        $token->consume($this);

        $this->emailAddress = $token->getEmail();
    }

    public function clearOldPassword(): void
    {
        $this->oldPassword = null;
    }

    public function migratePassword(string $newEncodedPassword): void
    {
        $this->password = $newEncodedPassword;
    }

    /**
     * Records the adherent last login date and time.
     *
     * @param string|int $timestamp a valid date representation as a string or integer
     */
    public function recordLastLoginTime($timestamp = 'now'): void
    {
        $this->lastLoggedAt = new \DateTime($timestamp);
    }

    /**
     * Returns the last login date and time of this adherent.
     */
    public function getLastLoggedAt(): ?\DateTime
    {
        return $this->lastLoggedAt;
    }

    public function getInterests(): array
    {
        return $this->interests;
    }

    public function getInterestsAsJson(): string
    {
        return \GuzzleHttp\json_encode($this->interests, JSON_PRETTY_PRINT);
    }

    public function setInterests(array $interests): void
    {
        $this->interests = $interests;
    }

    public function updateMembership(MembershipRequest $membership, PostAddress $postAddress): void
    {
        $this->gender = $membership->gender;
        $this->firstName = $membership->firstName;
        $this->lastName = $membership->lastName;
        $this->birthdate = $membership->getBirthdate();
        $this->position = $membership->position;
        $this->phone = $membership->getPhone();
        $this->emailAddress = $membership->getEmailAddress();

        if (!$this->postAddress->equals($postAddress)) {
            $this->postAddress = $postAddress;
        }
    }

    /**
     * Joins a committee as a SUPERVISOR privileged person.
     */
    public function superviseCommittee(Committee $committee, string $subscriptionDate = 'now'): CommitteeMembership
    {
        return $this->joinCommittee($committee, CommitteeMembership::COMMITTEE_SUPERVISOR, $subscriptionDate);
    }

    /**
     * Joins a committee as a HOST privileged person.
     */
    public function hostCommittee(Committee $committee, string $subscriptionDate = 'now'): CommitteeMembership
    {
        return $this->joinCommittee($committee, CommitteeMembership::COMMITTEE_HOST, $subscriptionDate);
    }

    /**
     * Joins a committee as a simple FOLLOWER privileged person.
     */
    public function followCommittee(Committee $committee, string $subscriptionDate = 'now'): CommitteeMembership
    {
        return $this->joinCommittee($committee, CommitteeMembership::COMMITTEE_FOLLOWER, $subscriptionDate);
    }

    private function joinCommittee(Committee $committee, string $privilege, string $subscriptionDate): CommitteeMembership
    {
        $committee->incrementMembersCount();

        return CommitteeMembership::createForAdherent($committee, $this, $privilege, $subscriptionDate);
    }

    /**
     * Joins a citizen project as a ADMINISTRATOR privileged person.
     */
    public function administrateCitizenProject(CitizenProject $citizenProject, string $subscriptionDate = 'now'): CitizenProjectMembership
    {
        return $this->joinCitizenProject($citizenProject, CitizenProjectMembership::CITIZEN_PROJECT_ADMINISTRATOR, $subscriptionDate);
    }

    /**
     * Joins a citizen project as a simple FOLLOWER privileged person.
     */
    public function followCitizenProject(CitizenProject $citizenProject, string $subscriptionDate = 'now'): CitizenProjectMembership
    {
        return $this->joinCitizenProject($citizenProject, CitizenProjectMembership::CITIZEN_PROJECT_FOLLOWER, $subscriptionDate);
    }

    private function joinCitizenProject(CitizenProject $citizenProject, string $privilege, string $subscriptionDate): CitizenProjectMembership
    {
        $citizenProject->incrementMembersCount();

        $memberShip = CitizenProjectMembership::createForAdherent($citizenProject, $this, $privilege, $subscriptionDate);

        $this->citizenProjectMemberships->add($memberShip);

        return $memberShip;
    }

    public function getPostAddress(): PostAddress
    {
        return $this->postAddress;
    }

    /**
     * Returns whether or not the current adherent is the same as the given one.
     */
    public function equals(self $other): bool
    {
        return $this->uuid->equals($other->getUuid());
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getRegisteredAt(): ?\DateTime
    {
        return $this->registeredAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function getManagedArea(): ?ReferentManagedArea
    {
        return $this->managedArea;
    }

    public function setManagedArea(ReferentManagedArea $managedArea): void
    {
        $this->managedArea = $managedArea;
    }

    /**
     * @JMS\VirtualProperty
     * @JMS\SerializedName("managedAreaTagCodes"),
     * @JMS\Groups({"referent"})
     *
     * @return string[]
     */
    public function getManagedAreaTagCodes(): array
    {
        return $this->getManagedArea()
            ? $this->getManagedArea()->getReferentTagCodes()
            : []
        ;
    }

    public function getProcurationManagedArea(): ?ProcurationManagedArea
    {
        return $this->procurationManagedArea;
    }

    public function setProcurationManagedArea(ProcurationManagedArea $procurationManagedArea = null): void
    {
        $this->procurationManagedArea = $procurationManagedArea;
    }

    public function getBoardMember(): ?BoardMember
    {
        return $this->boardMember;
    }

    public function setBoardMember(string $area, iterable $roles): void
    {
        if (!$this->boardMember) {
            $this->boardMember = new BoardMember();
            $this->boardMember->setAdherent($this);
        }

        $this->boardMember->setArea($area);
        $this->boardMember->setRoles($roles);
    }

    public function isBoardMember(): bool
    {
        return $this->boardMember instanceof BoardMember
            && !empty($this->boardMember->getArea()) && !empty($this->boardMember->getRoles());
    }

    public function revokeBoardMember(): void
    {
        if (!$this->boardMember) {
            return;
        }

        $this->boardMember->revoke();
        $this->boardMember = null;
    }

    public function setReferent(array $tags, string $markerLatitude = null, string $markerLongitude = null): void
    {
        $this->managedArea = new ReferentManagedArea($tags, $markerLatitude, $markerLongitude);
    }

    public function isReferent(): bool
    {
        return $this->managedArea instanceof ReferentManagedArea
            && !$this->managedArea->getTags()->isEmpty();
    }

    public function revokeReferent(): void
    {
        $this->managedArea = null;
    }

    public function getManagedAreaMarkerLatitude(): ?string
    {
        if (!$this->managedArea) {
            return '';
        }

        return $this->managedArea->getMarkerLatitude();
    }

    public function getManagedAreaMarkerLongitude(): ?string
    {
        if (!$this->managedArea) {
            return '';
        }

        return $this->managedArea->getMarkerLongitude();
    }

    public function isProcurationManager(): bool
    {
        return $this->procurationManagedArea instanceof ProcurationManagedArea && !empty($this->procurationManagedArea->getCodes());
    }

    public function canBeProxy(): bool
    {
        return $this->isReferent() || $this->isProcurationManager();
    }

    public function getProcurationManagedAreaCodesAsString(): ?string
    {
        if (!$this->procurationManagedArea) {
            return '';
        }

        return $this->procurationManagedArea->getCodesAsString();
    }

    public function setProcurationManagedAreaCodesAsString(string $codes = null): void
    {
        if (!$this->procurationManagedArea) {
            $this->procurationManagedArea = new ProcurationManagedArea();
            $this->procurationManagedArea->setAdherent($this);
        }

        $this->procurationManagedArea->setCodesAsString($codes);
    }

    public function isCoordinator(): bool
    {
        return $this->isCoordinatorCommitteeSector() || $this->isCoordinatorCitizenProjectSector();
    }

    public function isCoordinatorCitizenProjectSector(): bool
    {
        return $this->coordinatorCitizenProjectArea && $this->coordinatorCitizenProjectArea->getCodes();
    }

    public function isCoordinatorCommitteeSector(): bool
    {
        return $this->coordinatorCommitteeArea && $this->coordinatorCommitteeArea->getCodes();
    }

    public function getMemberships(): CommitteeMembershipCollection
    {
        if (!$this->memberships instanceof CommitteeMembershipCollection) {
            $this->memberships = new CommitteeMembershipCollection($this->memberships->toArray());
        }

        return $this->memberships;
    }

    public function hasLoadedMemberships(): bool
    {
        return $this->isCollectionLoaded($this->memberships);
    }

    public function getMembershipFor(Committee $committee): ?CommitteeMembership
    {
        foreach ($this->memberships as $membership) {
            if ($membership->matches($this, $committee)) {
                return $membership;
            }
        }

        return null;
    }

    public function getCitizenProjectMemberships(): CitizenProjectMembershipCollection
    {
        if (!$this->citizenProjectMemberships instanceof CitizenProjectMembershipCollection) {
            $this->citizenProjectMemberships = new CitizenProjectMembershipCollection($this->citizenProjectMemberships->toArray());
        }

        return $this->citizenProjectMemberships;
    }

    public function hasLoadedCitizenProjectMemberships(): bool
    {
        return $this->isCollectionLoaded($this->citizenProjectMemberships);
    }

    public function getCitizenProjectMembershipFor(CitizenProject $citizenProject): ?CitizenProjectMembership
    {
        foreach ($this->citizenProjectMemberships as $citizenProjectMembership) {
            if ($citizenProjectMembership->matches($this, $citizenProject)) {
                return $citizenProjectMembership;
            }
        }

        return null;
    }

    public function isBasicAdherent(): bool
    {
        return $this->isAdherent() && !$this->isHost() && !$this->isReferent() && !$this->isBoardMember() && !$this->isDeputy();
    }

    public function isHost(): bool
    {
        return $this->getMemberships()->countCommitteeHostMemberships() >= 1;
    }

    public function isHostOnly(): bool
    {
        return $this->getMemberships()->getCommitteeHostMemberships(CommitteeMembershipCollection::EXCLUDE_SUPERVISORS)->count() >= 1;
    }

    public function isHostOf(Committee $committee): bool
    {
        if (!$membership = $this->getMembershipFor($committee)) {
            return false;
        }

        return $membership->canHostCommittee();
    }

    public function isCitizenProjectAdministrator(): bool
    {
        return $this->getCitizenProjectMemberships()->countCitizenProjectAdministratorMemberships() >= 1;
    }

    public function isAdministratorOf(CitizenProject $citizenProject): bool
    {
        if (!$membership = $this->getCitizenProjectMembershipFor($citizenProject)) {
            return false;
        }

        return $membership->canAdministrateCitizenProject();
    }

    public function isSupervisor(): bool
    {
        return $this->getMemberships()->countCommitteeSupervisorMemberships() >= 1;
    }

    public function isSupervisorOf(Committee $committee): bool
    {
        if (!$membership = $this->getMembershipFor($committee)) {
            return false;
        }

        return $membership->isSupervisor();
    }

    public function isLegislativeCandidate(): bool
    {
        return $this->legislativeCandidate;
    }

    public function setLegislativeCandidate(bool $candidate): void
    {
        $this->legislativeCandidate = $candidate;
    }

    public function allowNotification(): ?bool
    {
        return $this->allowNotification;
    }

    public function addSubscriptionType(SubscriptionType $type): void
    {
        if (!$this->subscriptionTypes->contains($type)) {
            $this->subscriptionTypes->add($type);
        }
    }

    public function removeSubscriptionType(SubscriptionType $type): void
    {
        $this->subscriptionTypes->removeElement($type);
    }

    public function removeSubscriptionTypeByCode(string $code): void
    {
        foreach ($this->subscriptionTypes as $type) {
            if ($code === $type->getCode()) {
                $this->removeSubscriptionType($type);
            }
        }
    }

    public function setSubscriptionTypes(array $subscriptionTypes)
    {
        foreach ($subscriptionTypes as $type) {
            $this->addSubscriptionType($type);
        }
    }

    public function getCommitteeFeedItems(): iterable
    {
        return $this->committeeFeedItems;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function setTags(iterable $tags): void
    {
        foreach ($tags as $tag) {
            $this->addTag($tag);
        }
    }

    public function addTag(AdherentTag $adherentTag): void
    {
        if (!$this->tags->contains($adherentTag)) {
            $this->tags->add($adherentTag);
        }
    }

    public function removeTag(AdherentTag $adherentTag): void
    {
        $this->tags->removeElement($adherentTag);
    }

    /**
     * @return Collection|ReferentTag[]
     */
    public function getReferentTags(): Collection
    {
        return $this->referentTags;
    }

    public function addReferentTag(ReferentTag $referentTag): void
    {
        if (!$this->referentTags->contains($referentTag)) {
            $this->referentTags->add($referentTag);
        }
    }

    public function removeReferentTag(ReferentTag $referentTag): void
    {
        $this->referentTags->remove($referentTag);
    }

    public function clearReferentTags(): void
    {
        $this->referentTags->clear();
    }

    public function getCitizenProjectCreationEmailSubscriptionRadius(): int
    {
        return $this->citizenProjectCreationEmailSubscriptionRadius;
    }

    public function setCitizenProjectCreationEmailSubscriptionRadius(int $citizenProjectCreationEmailSubscriptionRadius): void
    {
        $this->citizenProjectCreationEmailSubscriptionRadius = $citizenProjectCreationEmailSubscriptionRadius;
    }

    public function hasCitizenProjectCreationEmailSubscription(): bool
    {
        return $this->hasSubscriptionType(SubscriptionTypeEnum::CITIZEN_PROJECT_CREATION_EMAIL);
    }

    public function getCoordinatorCitizenProjectArea(): ?CoordinatorManagedArea
    {
        return $this->coordinatorCitizenProjectArea;
    }

    public function setCoordinatorCitizenProjectArea(?CoordinatorManagedArea $coordinatorCitizenProjectArea): void
    {
        $this->coordinatorCitizenProjectArea = $coordinatorCitizenProjectArea;
    }

    public function getCoordinatorCommitteeArea(): ?CoordinatorManagedArea
    {
        return $this->coordinatorCommitteeArea;
    }

    public function setCoordinatorCommitteeArea(?CoordinatorManagedArea $coordinatorCommitteeArea): void
    {
        $this->coordinatorCommitteeArea = $coordinatorCommitteeArea;
    }

    public function getManagedDistrict(): ?District
    {
        return $this->managedDistrict;
    }

    public function setManagedDistrict(?District $district): void
    {
        if ($district && $district->getAdherent() && !$this->equals($district->getAdherent())) {
            throw new \InvalidArgumentException('District is already managed by another deputy.');
        }

        if ($this->managedDistrict) {
            $this->managedDistrict->setAdherent(null);
        }

        $this->managedDistrict = $district;
        if ($district) {
            $this->managedDistrict->setAdherent($this);
        }
    }

    public function isDeputy(): bool
    {
        return $this->managedDistrict instanceof District;
    }

    public function isAdherent(): bool
    {
        return $this->adherent;
    }

    public function isUser(): bool
    {
        return !$this->isAdherent();
    }

    public function join(): void
    {
        $this->adherent = true;
    }

    public function getOAuthUser(): InMemoryOAuthUser
    {
        if (!$this->oAuthUser) {
            $this->oAuthUser = new InMemoryOAuthUser($this->uuid);
        }

        return $this->oAuthUser;
    }

    public function serialize()
    {
        return serialize([
            $this->id,
            $this->emailAddress,
            $this->password,
        ]);
    }

    public function unserialize($serialized)
    {
        list($this->id, $this->emailAddress, $this->password) = unserialize($serialized);
    }

    public function setRemindSent(bool $remindSent): void
    {
        $this->remindSent = $remindSent;
    }
}
