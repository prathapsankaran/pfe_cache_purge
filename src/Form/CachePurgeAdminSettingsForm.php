<?php

namespace Drupal\pfe_cache_purge\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\smartsite_client\Client;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Component\Serialization\Json;

/**
 * Implements cachepurgeadminform.
 */
class CachePurgeAdminSettingsForm extends FormBase {

  /**
   * The smartsite client.
   *
   * @var \Drupal\smartsite_client\Client
   */
  protected $smartsiteClient;

  /**
   * Watchdog logger channel for captcha.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public function __construct(Client $smartsite_client, LoggerChannelInterface $logger, MessengerInterface $messenger) {
    $this->smartsiteClient = $smartsite_client;
    $this->logger = $logger;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('smartsite_client.client'),
      $container->get('logger.factory')->get('pfe_cache_purge'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cache_purge_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['purge_cache'] = [
      '#type' => 'details',
      '#title' => t('Purge Cache'),
      '#open' => TRUE,
    ];

    $form['purge_cache']['clear'] = [
      '#type' => 'submit',
      '#value' => t('Purge all cache'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Call to dashboard for cache purge.
    $payload = [
      'command' => 'cache-purge',
    ];

    $endpoint = '/ci/notification/site_environment';
    $response = \Drupal::service('smartsite_client.client')->call($endpoint, 'POST', $payload);

    if ($response) {
      $status_code = $response->getStatusCode();
      $stream_size = $response->getBody()->getSize();
      $data = Json::decode($response->getBody()->read($stream_size));
      if ($status_code == 200) {
        $this->messenger->addMessage($this->t('Triggered cache purge command on dashboard'));
        $this->logger->info('Triggered cache purge command on dashboard');
      }
      else {
        $this->messenger->addError($this->t('Failed to trigger cache purge %status and %response', ['%status' => $status_code, '%response' => $response]));
        $this->logger->error($data);
      }
    }
    else {
      $this->messenger->addError($this->t('Could not connect to the service.'));
      $this->logger->error('Could not connect to the service.');
    }

  }

}
