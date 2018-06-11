<?php
namespace EventFarm\Restforce;

use EventFarm\Restforce\Rest\GuzzleRestClient;
use EventFarm\Restforce\Rest\OAuthAccessToken;
use EventFarm\Restforce\Rest\OAuthRestClient;
use EventFarm\Restforce\Rest\OAuthRestClientException;
use EventFarm\Restforce\Rest\RestClientInterface;
use EventFarm\Restforce\Rest\SalesforceRestClient;
use Psr\Http\Message\ResponseInterface;

class Restforce implements RestforceInterface
{
    public const USER_INFO_ENDPOINT = 'RESOURCE_OWNER';

    private const DEFAULT_API_VERSION = 'v38.0';

    /** @var string */
    private $clientId;
    /** @var string */
    private $clientSecret;
    /** @var null|string */
    private $username;
    /** @var null|string */
    private $password;
    /** @var OAuthAccessToken|null */
    private $accessToken;
    /** @var string */
    private $apiVersion;
    /** @var OAuthRestClient|null */
    private $oAuthRestClient;
    /** @var string */
    private $authUrl;

    public function __construct(
        string $clientId,
        string $clientSecret,
        ?OAuthAccessToken $accessToken = null,
        ?string $username = null,
        ?string $password = null,
        ?string $apiVersion = null
    ) {
        if ($accessToken === null && $username === null && $password === null) {
            throw RestforceException::minimumRequiredFieldsNotMet();
        }

        if ($apiVersion === null) {
            $apiVersion = self::DEFAULT_API_VERSION;
        }

        $this->apiVersion = $apiVersion;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->accessToken = $accessToken;
        $this->username = $username;
        $this->password = $password;
    }

    public function create(string $sobjectType, array $data): ResponseInterface
    {
        $uri = 'sobjects/' . $sobjectType;

        return $this->getOAuthRestClient()->postJson($uri, $data);
    }

    public function update(string $sobjectType, string $sobjectId, array $data): ResponseInterface
    {
        $uri = 'sobjects/' . $sobjectType . '/' . $sobjectId;

        return $this->getOAuthRestClient()->patchJson($uri, $data);
    }

    public function describe(string $sobject): ResponseInterface
    {
        $uri = 'sobjects/' . $sobject . '/describe';

        return $this->getOAuthRestClient()->get($uri);
    }

    public function find(string $sobjectType, string $sobjectId, array $fields = []): ResponseInterface
    {
        $uri = 'sobjects/' . $sobjectType . '/' . $sobjectId;

        $queryParams = [];

        if (!empty($fields)) {
            $fieldsString = implode(',', $fields);
            $queryParams = ['fields' => $fieldsString];
        }

        return $this->getOAuthRestClient()->get($uri, $queryParams);
    }

    public function limits(): ResponseInterface
    {
        return $this->getOAuthRestClient()->get('/limits');
    }

    public function getNext(string $url): ResponseInterface
    {
        return $this->getOAuthRestClient()->get($url);
    }

    public function query(string $queryString): ResponseInterface
    {
        return $this->getOAuthRestClient()->get('query', [
            'q' => $queryString,
        ]);
    }
    public function userInfo(): ResponseInterface
    {
        return $this->getOAuthRestClient()->get(self::USER_INFO_ENDPOINT);
    }
    public function setUrl(string $url) : self
    {
        $this->authUrl = $url;
        return $this;
    }
    private function getAuthUrl() :string
    {
        if($this->authUrl === null)
        {
            throw new OAuthRestClientException('No Login URL set.');
        }
        return $this->authUrl;
    }

    private function setOAuthRestClient(oAuthRestClient $client = null) : self
    {
        if($client === null)
        {
            $this->oAuthRestClient = new OAuthRestClient(
                new SalesforceRestClient(
                    new GuzzleRestClient('https://na1.salesforce.com'),
                    $this->apiVersion
                ),
                new GuzzleRestClient($this->getAuthUrl()),
                $this->clientId,
                $this->clientSecret,
                $this->username,
                $this->password,
                $this->accessToken
            );
        }else{
            $this->oAuthRestClient = $client;
        }

        return $this;
    }
    private function getOAuthRestClient(): RestClientInterface
    {
        if ($this->oAuthRestClient === null) {
           $this->setOAuthRestClient();
        }

        return $this->oAuthRestClient;
    }
}
