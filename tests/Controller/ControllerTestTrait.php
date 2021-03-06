<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\Adherent;
use AppBundle\Entity\EventCategory;
use AppBundle\Entity\ReferentTag;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Tests\AppBundle\TestHelperTrait;

/**
 * @method static assertSame($expected, $actual, $message = '')
 */
trait ControllerTestTrait
{
    use TestHelperTrait;

    protected $hosts = [];

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var EntityManagerInterface
     */
    protected $manager;

    public function assertResponseStatusCode(int $statusCode, Response $response, string $message = '')
    {
        $this->assertSame($statusCode, $response->getStatusCode(), $message);
    }

    public function assertClientIsRedirectedTo(string $path, Client $client, bool $withSchemes = false, bool $permanent = false)
    {
        $response = $client->getResponse();

        $this->assertResponseStatusCode($permanent ? Response::HTTP_MOVED_PERMANENTLY : Response::HTTP_FOUND, $response);
        $this->assertSame(
            $withSchemes ? $client->getRequest()->getSchemeAndHttpHost().$path : $path,
            $response->headers->get('location')
        );
    }

    public function logout(Client $client): void
    {
        $session = $client->getContainer()->get('session');

        $client->getCookieJar()->clear();
        $session->set('_security_main_context', null);
        $session->save();
    }

    public function authenticateAsAdherent(Client $client, string $emailAddress): void
    {
        if (!$user = $this->getAdherentRepository()->findOneBy(['emailAddress' => $emailAddress])) {
            throw new \Exception(sprintf('Adherent %s not found', $emailAddress));
        }

        $this->authenticate($client, $user);
    }

    public function authenticateAsAdmin(Client $client, string $email = 'admin@en-marche-dev.fr'): void
    {
        if (!$user = $this->getAdministratorRepository()->loadUserByUsername($email)) {
            throw new \Exception(sprintf('Admin %s not found', $email));
        }

        $this->authenticate($client, $user);
    }

    protected function seeDefaultCitizenProjectImage(): bool
    {
        try {
            $styleText = $this->client->getCrawler()->filter('.citizen-project--bkg')->attr('style');

            return "background-image:url('/assets/images/citizen_projects/default.png')" === $styleText;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    protected function getFirstPrefixForm(Form $form): ?string
    {
        foreach ($form->all() as $field) {
            preg_match('/^(.*)\[.*\]$/', $field->getName(), $matches);
            if ($matches) {
                return $matches[1];
            }
        }

        return null;
    }

    protected function seeFlashMessage(Crawler $crawler, ?string $message = null): bool
    {
        $flash = $crawler->filter('.notice-flashes');

        if ($message) {
            $this->assertSame($message, trim($flash->text()));
        }

        return 1 === count($flash);
    }

    protected function appendCollectionFormPrototype(\DOMElement $collection, string $newIndex = '0', string $prototypeName = '__name__'): void
    {
        $prototypeHTML = $collection->getAttribute('data-prototype');
        $prototypeHTML = str_replace($prototypeName, $newIndex, $prototypeHTML);
        $prototypeFragment = new \DOMDocument();
        $prototypeFragment->loadHTML($prototypeHTML);
        foreach ($prototypeFragment->getElementsByTagName('body')->item(0)->childNodes as $prototypeNode) {
            $collection->appendChild($collection->ownerDocument->importNode($prototypeNode, true));
        }
    }

    protected function assertSeeCommitteeTimelineMessage(Crawler $crawler, int $position, string $author, string $role, string $text)
    {
        $message = $crawler->filter('.committee__timeline__message')->eq($position);

        $this->assertContains($author, $message->filter('h3')->text());
        $this->assertSame($role, $message->filter('h3 span')->text());
        $this->assertContains($text, $message->filter('div')->first()->text());
    }

    protected function assertHavePublishedMessage(string $queue, string $msgBody): void
    {
        $messages = array_filter(
            $this->getMessages($queue),
            function ($message) use ($msgBody) { return $msgBody === $message->getBody(); }
        );

        self::assertEquals(1, count($messages), 'Expected message not found.');
    }

    private function getMessages(string $queue): array
    {
        $channel = $this->container->get('old_sound_rabbit_mq.connection.default')->channel();
        $messages = [];

        /** @var AMQPMessage $message */
        while ($message = $channel->basic_get($queue)) {
            $messages[] = $message;
            $channel->basic_ack($message->get('delivery_tag'));
        }

        return $messages;
    }

    private function authenticate(Client $client, UserInterface $user): void
    {
        $session = $client->getContainer()->get('session');

        $token = new UsernamePasswordToken($user, null, 'main_context', $user->getRoles());
        $session->set('_security_main_context', serialize($token));
        $session->save();

        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }

    private function getEventCategoryIdForName(string $categoryName): int
    {
        return $this->manager->getRepository(EventCategory::class)->findOneBy(['name' => $categoryName])->getId();
    }

    private static function assertAdherentHasReferentTag(Adherent $adherent, string $code): void
    {
        $referentTag = $adherent
            ->getReferentTags()
            ->filter(function (ReferentTag $referentTag) use ($code) {
                return $code === $referentTag->getCode();
            })
            ->first()
        ;

        self::assertInstanceOf(ReferentTag::class, $referentTag);
        self::assertSame($referentTag->getCode(), $code);
    }

    protected function init(array $fixtures = [], string $host = 'app')
    {
        $this->container = $this->getContainer();
        $this->manager = $this->container->get('doctrine.orm.entity_manager');

        $this->manager->getConnection()->executeUpdate('SET foreign_key_checks = 0;');
        $this->loadFixtures($fixtures, null, 'doctrine', ORMPurger::PURGE_MODE_TRUNCATE);
        $this->manager->getConnection()->executeUpdate('SET foreign_key_checks = 1;');

        $this->hosts = [
            'scheme' => $this->container->getParameter('router.request_context.scheme'),
            'app' => $this->container->getParameter('app_host'),
            'amp' => $this->container->getParameter('amp_host'),
            'legislatives' => $this->container->getParameter('legislatives_host'),
        ];

        $this->client = $this->makeClient(false, ['HTTP_HOST' => $this->hosts[$host]]);
    }

    protected function kill()
    {
        $this->client = null;
        $this->manager = null;
        $this->adherents = null;
        $this->hosts = [];

        if ($this->container) {
            $this->cleanupContainer($this->container);
            $this->container = null;
        }

        foreach ($this->containers as $container) {
            $this->cleanupContainer($container);
        }
    }
}
