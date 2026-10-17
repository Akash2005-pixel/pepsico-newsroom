<?php

declare(strict_types=1);

namespace Drupal\pepsico_api\Drush\Commands;

use Drupal\Component\Utility\Crypt;
use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Creates the demo OAuth2 client for the news API.
 *
 * The client secret is generated here, printed once and stored only as a
 * password hash on the Consumer entity. It is never written to config, state,
 * logs or files, so it cannot be shown again: rotate it instead.
 */
final class ApiClientCommands extends DrushCommands {

  use AutowireTrait;

  public const CLIENT_ID = 'news_demo';
  public const CLIENT_USER = 'api_client_news_demo';
  public const ROLE = 'api_consumer';
  public const SCOPE = 'news_read';
  public const GRANT_TYPE = 'client_credentials';

  /**
   * Access token lifetime in seconds.
   */
  private const TOKEN_LIFETIME = 300;

  /**
   * Random bytes in the secret (256 bits, 43 URL-safe characters).
   */
  private const SECRET_BYTES = 32;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Creates the demo OAuth client and prints its secret once.
   */
  #[CLI\Command(name: 'pepsico:api-client', aliases: ['pac'])]
  #[CLI\Option(name: 'rotate-secret', description: 'Issue a new secret for the existing client and revoke its tokens.')]
  #[CLI\Usage(name: 'drush pepsico:api-client', description: 'Create the demo client (fails if it already exists).')]
  #[CLI\Usage(name: 'drush pepsico:api-client --rotate-secret', description: 'Replace the secret; the old one stops working.')]
  public function apiClient(array $options = ['rotate-secret' => FALSE]): int {
    $consumer = $this->loadConsumer();
    $rotate = (bool) $options['rotate-secret'];

    if ($consumer && !$rotate) {
      $this->logger()->error(dt('The OAuth client "@id" already exists. Its secret cannot be shown again; use --rotate-secret to issue a new one.', ['@id' => self::CLIENT_ID]));
      return self::EXIT_FAILURE;
    }
    if (!$consumer && $rotate) {
      $this->logger()->error(dt('There is no OAuth client "@id" to rotate. Run the command without --rotate-secret first.', ['@id' => self::CLIENT_ID]));
      return self::EXIT_FAILURE;
    }

    $secret = Crypt::randomBytesBase64(self::SECRET_BYTES);

    if ($consumer) {
      // The password field type hashes the plain value on save.
      $consumer->set('secret', $secret)->save();
      $revoked = $this->deleteTokens($consumer);
      $this->io()->warning(sprintf('The previous secret no longer works. %d existing token(s) were revoked.', $revoked));
    }
    else {
      $this->createConsumer($this->createClientUser(), $secret);
    }

    $this->io()->writeln([
      '',
      sprintf('client_id:     %s', self::CLIENT_ID),
      sprintf('client_secret: %s', $secret),
      sprintf('grant_type:    %s', self::GRANT_TYPE),
      sprintf('scope:         %s', self::SCOPE),
      '',
    ]);
    $this->io()->caution('Copy the secret now. It is stored only as a hash and will not be shown again.');
    return self::EXIT_SUCCESS;
  }

  /**
   * Loads the demo consumer, if it exists.
   */
  private function loadConsumer(): ?ConsumerInterface {
    $consumers = $this->entityTypeManager->getStorage('consumer')
      ->loadByProperties(['client_id' => self::CLIENT_ID]);
    return $consumers ? reset($consumers) : NULL;
  }

  /**
   * Creates the account the client acts as.
   *
   * The account is active, because Simple OAuth revokes tokens of blocked
   * users, but it cannot sign in: it has no password (core rejects any login
   * against an empty hash) and no email address (no reset link can be sent).
   *
   * @throws \RuntimeException
   *   When an account with the same name already exists.
   */
  private function createClientUser(): UserInterface {
    $storage = $this->entityTypeManager->getStorage('user');
    if ($storage->loadByProperties(['name' => self::CLIENT_USER])) {
      throw new \RuntimeException(sprintf('The user "%s" already exists without an OAuth client. Review and delete it before running this command.', self::CLIENT_USER));
    }

    /** @var \Drupal\user\UserInterface $user */
    $user = $storage->create([
      'name' => self::CLIENT_USER,
      'mail' => NULL,
      'pass' => NULL,
      'status' => 1,
      'roles' => [self::ROLE],
    ]);
    $user->save();
    return $user;
  }

  /**
   * Creates the confidential client_credentials consumer.
   */
  private function createConsumer(UserInterface $user, string $secret): void {
    $this->entityTypeManager->getStorage('consumer')->create([
      'client_id' => self::CLIENT_ID,
      'label' => 'News API demo client',
      'description' => 'Fictional machine-to-machine client for the PepsiCo Newsroom demo.',
      'secret' => $secret,
      'confidential' => TRUE,
      'third_party' => FALSE,
      'is_default' => FALSE,
      'grant_types' => [self::GRANT_TYPE],
      'scopes' => [self::SCOPE],
      'user_id' => $user->id(),
      'access_token_expiration' => self::TOKEN_LIFETIME,
    ])->save();
  }

  /**
   * Deletes every access and refresh token issued to the consumer.
   *
   * @return int
   *   The number of deleted tokens.
   */
  private function deleteTokens(ConsumerInterface $consumer): int {
    $storage = $this->entityTypeManager->getStorage('oauth2_token');
    $tokens = $storage->loadByProperties(['client' => $consumer->id()]);
    $storage->delete($tokens);
    return count($tokens);
  }

}
