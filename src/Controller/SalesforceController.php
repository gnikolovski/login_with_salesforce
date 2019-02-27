<?php

namespace Drupal\login_with_salesforce\Controller;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserData;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config_factory;

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
   * SalesforceController constructor.
   *
   * @param \GuzzleHttp\Client $http_client
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   * @param \Drupal\user\UserData $user_data
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   */
  public function __construct(Client $http_client, ConfigFactory $config_factory, UserData $user_data, LoggerChannelFactoryInterface $logger_factory) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory->get('login_with_salesforce.settings');
    $this->userData = $user_data;
    $this->loggerFactory = $logger_factory->get('login_with_salesforce');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('config.factory'),
      $container->get('user.data'),
      $container->get('logger.factory')
    );
  }

  /**
   * Callback method.
   */
  public function callback() {
    $code = \Drupal::request()->query->get('code');
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
   * @param $code
   *
   * @return string|null
   */
  protected function requestToken($code) {
    $login_url = $this->configFactory->get('login_url');

    try {
      $response = $this->httpClient->post($login_url . '/services/oauth2/token', [
        'form_params' => [
          'grant_type' => 'password',
          'client_id' => $this->configFactory->get('client_id'),
          'client_secret' => $this->configFactory->get('client_secret'),
          'code' => $code,
          'redirect_uri' => $this->configFactory->get('redirect_uri'),
        ],
      ]);

      return json_decode($response->getBody()->getContents(), TRUE);
    } catch (\Exception $e) {
      $this->loggerFactory->error('Error in ' . $e->getFile() . ' at line ' . $e->getLine() . '. Message: ' . $e->getMessage());
      return NULL;
    }
  }

  /**
   * Log in user.
   *
   * @param $token_data
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function loginUser($token_data) {
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
   * @param $url
   * @param $access_token
   *
   * @return mixed|null
   */
  protected function getUserData($url, $access_token) {
    try {
      $response = $this->httpClient->post($url, [
        'headers' => [
          'Authorization' => 'Bearer ' . $access_token,
        ],
      ]);

      return json_decode($response->getBody()->getContents(), TRUE);
    } catch (\Exception $e) {
      $this->loggerFactory->error('Error in ' . $e->getFile() . ' at line ' . $e->getLine() . '. Message: ' . $e->getMessage());
      return NULL;
    }
  }

}
