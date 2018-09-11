<?php

namespace AppBundle\Twig;

use AppBundle\CitizenProject\CitizenProjectPermissions;
use AppBundle\Entity\Adherent;
use AppBundle\Entity\CitizenProject;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class CitizenProjectRuntime
{
    private const COLOR_STATUS_PENDING = 'bullet--pending';
    private const COLOR_STATUS_ADMINISTRATOR = 'bullet--own';

    private $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function isAdministratorOf(CitizenProject $citizenProject): bool
    {
        return $this->authorizationChecker->isGranted(CitizenProjectPermissions::ADMINISTRATE, $citizenProject);
    }

    public function canFollowCitizenProject(CitizenProject $citizenProject): bool
    {
        return $this->authorizationChecker->isGranted(CitizenProjectPermissions::FOLLOW, $citizenProject);
    }

    public function canUnfollowCitizenProject(CitizenProject $citizenProject): bool
    {
        return $this->authorizationChecker->isGranted(CitizenProjectPermissions::UNFOLLOW, $citizenProject);
    }

    public function canSeeCitizenProject(CitizenProject $citizenProject): bool
    {
        return $this->authorizationChecker->isGranted(CitizenProjectPermissions::SHOW, $citizenProject);
    }

    public function canCommentCitizenProject(CitizenProject $citizenProject): bool
    {
        return $this->authorizationChecker->isGranted(CitizenProjectPermissions::COMMENT, $citizenProject);
    }

    public function canSeeCommentCitizenProject(CitizenProject $citizenProject): bool
    {
        return $this->authorizationChecker->isGranted(CitizenProjectPermissions::SHOW_COMMENT, $citizenProject);
    }

    public function getCitizenProjectColorStatus(Adherent $adherent, CitizenProject $citizenProject): string
    {
        if ($citizenProject->isPending()) {
            return self::COLOR_STATUS_PENDING;
        }

        if ($adherent->isAdministratorOf($citizenProject)) {
            return self::COLOR_STATUS_ADMINISTRATOR;
        }

        return '';
    }
}
