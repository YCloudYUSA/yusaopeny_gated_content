<?php

namespace Drupal\openy_gc_auth_example\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openy_gc_auth\GCUserAuthorizer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class VirtualYExampleLogin Form.
 *
 * @package Drupal\openy_gc_auth_example\Form
 */
class VirtualYExampleLoginForm extends FormBase {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $currentRequest;

  /**
   * The Gated Content User Authorizer.
   *
   * @var \Drupal\openy_gc_auth\GCUserAuthorizer
   */
  protected $gcUserAuthorizer;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    RequestStack $requestStack,
    GCUserAuthorizer $gcUserAuthorizer,
    ConfigFactoryInterface $config_factory
  ) {
    $this->currentRequest = $requestStack->getCurrentRequest();
    $this->gcUserAuthorizer = $gcUserAuthorizer;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('openy_gc_auth.user_authorizer'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openy_gc_auth_example_login_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $provider_config = $this->configFactory->get('openy_gc_auth.provider.dummy');
    $autosubmit = $provider_config->get('autosubmit');

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enter Virtual Y'),
      '#attached' => [
        'library' => [
          'openy_gc_auth_example/openy_gc_auth_example'
        ]
      ],
      '#attributes' => [
        'data-autosubmit' => $autosubmit,
      ]
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = 'dummy+' . $this->currentRequest->getClientIp() . '+' . rand(0, 10000);
    $email = $name . '@virtualy.org';
    // Authorize user (register, login, log, etc).
    $this->gcUserAuthorizer->authorizeUser($name, $email);
  }

}
