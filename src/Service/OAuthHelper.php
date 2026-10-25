<?php
namespace App\Service;

use App\CSPro\CSProOAuthPdo;
use \OAuth2\Server;
use \OAuth2\GrantType\UserCredentials;
use \OAuth2\GrantType\RefreshToken;
use Psr\Log\LoggerInterface;


class OAuthHelper extends Server {

    private $storage;

    // Reuse the request's shared PdoHelper connection instead of opening a second one:
    // halves the MySQL connections per request (matters on shared hosting max_user_connections).
    public function __construct(PdoHelper $pdo) {

        $this->storage = new CSProOAuthPdo($pdo);
        parent::__construct($this->storage);
        // Add the "User Credentials" grant type
        $this->addGrantType(new \OAuth2\GrantType\UserCredentials($this->storage));

        // the refresh token grant request will have a "refresh_token" field
        // with a new refresh token on each request
        $grantType = new \OAuth2\GrantType\RefreshToken($this->storage, ['always_issue_new_refresh_token' => true, 'refresh_token_lifetime' => 2_419_200]);
        // add the grant type to your OAuth server
        $this->addGrantType($grantType);
    }

}
