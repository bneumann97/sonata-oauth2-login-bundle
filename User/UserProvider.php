<?php

namespace Exozet\Oauth2LoginBundle\User;

use Exozet\Oauth2LoginBundle\Checker\Email;
use Exozet\Oauth2LoginBundle\Google\Authorization;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Sonata\UserBundle\Entity\UserManager;
use Sonata\UserBundle\Model\UserManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements OAuthAwareUserProviderInterface, UserProviderInterface
{
    /**
     * @var UserManager
     */
    private $userManager;

    /**
     * @var Authorization
     */
    private $authorization;

    /**
     * @var Email
     */
    private $emailChecker;

    /**
     * @param UserManagerInterface $userManager
     * @param Email $emailChecker
     * @param Authorization $authorization
     */
    public function __construct(UserManagerInterface $userManager, Email $emailChecker, Authorization $authorization)
    {
        $this->userManager = $userManager;
        $this->emailChecker = $emailChecker;
        $this->authorization = $authorization;
    }

    /**
     * @inheritdoc()
     */
    public function loadUserByUsername($username)
    {
        return $this->userManager->findUserByUsernameOrEmail($username);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $token = $response->getOAuthToken()->getRawToken();

        if(!$this->emailChecker->isEmailValid($response->getEmail())) {
            $client = $this->authorization->getClient();
            $client->revokeToken($token);

            return null;
        }

        $user = $this->loadUserByUsername($response->getEmail());

        if(!$user) {
            $user = $this->userManager->create();
            $user->setUsername($response->getEmail());
            $user->setEmail($response->getEmail());
            $user->setFirstname($response->getFirstName());
            $user->setLastname($response->getLastName());
            $user->setPassword('');
            $user->setEnabled(true);
            $user->setRoles(['ROLE_SONATA_ADMIN']);

            $this->userManager->save($user);
        }

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    /**
     * {@inheritDoc}
     */
    public function supportsClass($class)
    {
        return $class === User::class;
    }
}