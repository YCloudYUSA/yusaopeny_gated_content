<?php

namespace Drupal\openy_gc_auth_smart_rec\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openy_gc_auth\GCUserAuthorizer;
use Drupal\openy_smart_rec\YmcaSmartRecAmiliaApiClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Virtual Y Smart Rec (Amilia) login form.
 *
 * Authenticates users by validating their email address against the configured
 * Amilia membership network via YmcaSmartRecAmiliaApiClient::getPersonsByEmail().
 *
 * @package Drupal\openy_gc_auth_smart_rec\Form
 */
class VirtualYSmartRecLoginForm extends FormBase {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $currentRequest;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * The Gated Content User Authorizer.
   *
   * @var \Drupal\openy_gc_auth\GCUserAuthorizer
   */
  protected $gcUserAuthorizer;

  /**
   * The Amilia API client.
   *
   * @var \Drupal\openy_smart_rec\YmcaSmartRecAmiliaApiClient
   */
  protected $smartRecApiClient;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Form manager from the simple_recaptcha module (optional).
   *
   * NULL when simple_recaptcha is not installed.
   *
   * @var \Drupal\simple_recaptcha\SimpleReCaptchaFormManager|null
   */
  protected $reCaptchaFormManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    RequestStack $requestStack,
    ConfigFactoryInterface $config_factory,
    FloodInterface $flood,
    GCUserAuthorizer $gcUserAuthorizer,
    YmcaSmartRecAmiliaApiClient $smartRecApiClient,
    ModuleHandlerInterface $moduleHandler,
    $reCaptchaFormManager = NULL
  ) {
    $this->currentRequest = $requestStack->getCurrentRequest();
    $this->configFactory = $config_factory;
    $this->flood = $flood;
    $this->gcUserAuthorizer = $gcUserAuthorizer;
    $this->smartRecApiClient = $smartRecApiClient;
    $this->moduleHandler = $moduleHandler;
    $this->reCaptchaFormManager = $reCaptchaFormManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('flood'),
      $container->get('openy_gc_auth.user_authorizer'),
      $container->get('openy_smart_rec.smart_rec_api_client'),
      $container->get('module_handler'),
      $container->has('simple_recaptcha.form_manager') ? $container->get('simple_recaptcha.form_manager') : NULL
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openy_gc_auth_smart_rec_login_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $provider_config = $this->configFactory->get('openy_gc_auth.provider.smart_rec');

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#description' => $provider_config->get('verification_text'),
      '#required' => TRUE,
    ];

    if ($provider_config->get('enable_captcha')) {
      if ($this->reCaptchaFormManager !== NULL) {
        // simple_recaptcha is installed — add reCAPTCHA checkbox.
        $this->reCaptchaFormManager->addReCaptchaCheckbox($form, $this->getFormId());
      }
      elseif ($this->moduleHandler->moduleExists('captcha')) {
        // captcha module is installed — use the configured default challenge.
        $form['captcha'] = [
          '#type' => 'captcha',
          '#captcha_type' => 'default',
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enter Virtual Y'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $flood_config = $this->configFactory->get('user.flood');
    if (!$this->flood->isAllowed('openy_gc_auth_smart_rec.login', $flood_config->get('user_limit'), $flood_config->get('user_window'))) {
      $form_state->setErrorByName(
        'email',
        $this->t('Too many login attempts from your IP address. Please try again later or contact the site administrator.')
      );
      return;
    }

    $email = trim($form_state->getValue('email'));
    $provider_config = $this->configFactory->get('openy_gc_auth.provider.smart_rec');
    $persons = $this->smartRecApiClient->getPersonsByEmail($email);

    if ($persons === FALSE) {
      $this->flood->register('openy_gc_auth_smart_rec.login', $flood_config->get('user_window'));
      $form_state->setErrorByName('email', $provider_config->get('not_member_text'));
    }
    else {
      // Store the first person record for use in submitForm().
      $form_state->set('smart_rec_person', reset($persons));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $email = trim($form_state->getValue('email'));
    $person = $form_state->get('smart_rec_person');

    $name = isset($person['FirstName'], $person['LastName'])
      ? trim($person['FirstName'] . ' ' . $person['LastName'])
      : $email;

    $this->gcUserAuthorizer->authorizeUser($name, $email);
  }

}
