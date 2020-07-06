<?php

namespace Drupal\login_with_salesforce\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserData;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class SalesforceController.
 *
 * @package Drupal\login_with_salesforce\Controller
 */
class SalesforceController extends ControllerBase {

  /**
   * The Http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserData
   */
  protected $userData;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * SalesforceController constructor.
   *
   * @param \GuzzleHttp\Client $http_client
   *   The htpp client.
   * @param \Drupal\user\UserData $user_data
   *   The user data.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(Client $http_client, UserData $user_data, LoggerChannelFactoryInterface $logger_factory, RequestStack $request_stack) {
    $this->httpClient = $http_client;
    $this->config = $this->configFactory->get('login_with_salesforce.settings');
    $this->userData = $user_data;
    $this->loggerFactory = $logger_factory->get('login_with_salesforce');
    $this->currentRequest = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('user.data'),
      $container->get('logger.factory'),
      $container->get('request_stack')
    );
  }

  /**
   * Callback method.
   */
  public function callback() {
    $code = $this->currentRequest->query->get('code');
    if (!$code) {
      return $this->redirect('user.login');
    }

    $token_data = $this->requestToken($code);
    if (!$token_data) {
      return $this->redirect('user.login');
    }

    $this->loginUser($token_data);
    return $this->redirect('<front>');
  }

  /**
   * Gets token from Salesforce.
   *
   * @param string $code
   *   The code.
   *
   * @return string|null
   *   The token.
   */
  protected function requestToken($code) {
    $login_url = $this->configFactory->get('login_url');

    try {
      $response = $this->httpClient->post($login_url . '/services/oauth2/token', [
        'form_params' => [
          'grant_type' => 'authorization_code',
          'client_id' => $this->configFactory->get('client_id'),
          'client_secret' => $this->configFactory->get('client_secret'),
          'code' => $code,
          'redirect_uri' => $this->configFactory->get('redirect_uri'),
        ],
      ]);

      return json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Request token error in ' . $e->getFile() . ' at line ' . $e->getLine() . '. Message: ' . $e->getMessage());
      return NULL;
    }
  }

  /**
   * Log in user.
   *
   * @param array $token_data
   *   The token data.
   */
  protected function loginUser(array $token_data) {
    $id = $token_data['id'];
    $access_token = $token_data['access_token'];
    $refresh_token = $token_data['refresh_token'];
    $issued_at = $token_data['issued_at'];

    $user_data = $this->getUserData($id, $access_token);
    $user_email = $user_data['email'];
    $user_name = $user_data['username'];

    /** @var \Drupal\user\Entity\User $user */
    $user = user_load_by_mail($user_email);

    if ($user) {
      $this->userData->set('login_with_salesforce', $user->id(), 'id', $id);
      $this->userData->set('login_with_salesforce', $user->id(), 'access_token', $access_token);
      $this->userData->set('login_with_salesforce', $user->id(), 'refresh_token', $refresh_token);
      $this->userData->set('login_with_salesforce', $user->id(), 'issued_at', $issued_at);
      user_login_finalize($user);
    }
    else {
      $user = User::create();
      $user->setPassword(rand());
      $user->enforceIsNew();
      $user->setEmail($user_email);
      $user->setUsername($user_name);
      $user->activate();
      $user->save();
      user_login_finalize($user);
    }
  }

  /**
   * Gets user data.
   *
   * @param string $url
   *   The url.
   * @param string $access_token
   *   The access token.
   *
   * @return mixed|null
   *   The user data.
   */
  protected function getUserData($url, $access_token) {
    try {
      $response = $this->httpClient->post($url, [
        'headers' => [
          'Authorization' => 'Bearer ' . $access_token,
        ],
      ]);

      return json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Get user data error in ' . $e->getFile() . ' at line ' . $e->getLine() . '. Message: ' . $e->getMessage());
      return NULL;
    }
  }

}
