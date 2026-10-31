<?php

namespace App\Services\Social;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

/**
 * Pinterest does not ship a driver with Socialite, so idexal Os carries one.
 * Pinterest authenticates the token exchange with HTTP Basic and its v5 user
 * endpoint returns a username and avatar but never an email address.
 */
class PinterestProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * @var list<string>
     */
    protected $scopes = ['user_accounts:read'];

    protected $scopeSeparator = ',';

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase('https://www.pinterest.com/oauth/', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl(): string
    {
        return 'https://api.pinterest.com/v5/oauth/token';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get('https://api.pinterest.com/v5/user_account', [
            RequestOptions::HEADERS => ['Authorization' => 'Bearer '.$token],
        ]);

        return json_decode($response->getBody(), true) ?? [];
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => (string) ($user['id'] ?? $user['username'] ?? ''),
            'nickname' => $user['username'] ?? null,
            'name' => $user['username'] ?? null,
            'email' => null,
            'avatar' => $user['profile_image'] ?? null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenResponse($code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::HEADERS => ['Accept' => 'application/json'],
            RequestOptions::AUTH => [$this->clientId, $this->clientSecret],
            RequestOptions::FORM_PARAMS => $this->getTokenFields($code),
        ]);

        return json_decode($response->getBody(), true) ?? [];
    }

    /**
     * Pinterest rejects credentials sent in the body; they are already in the
     * Authorization header.
     *
     * {@inheritdoc}
     */
    protected function getTokenFields($code): array
    {
        return Arr::except(parent::getTokenFields($code), ['client_id', 'client_secret']);
    }
}
