<?php

namespace Drupal\openy_gc_auth_smart_rec\Plugin\GCIdentityProvider;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openy_gc_auth\GCIdentityProviderPluginBase;
use Drupal\openy_gated_content\GCUserService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Smart Rec (Amilia) identity provider plugin.
 *
 * @GCIdentityProvider(
 *   id="smart_rec",
 *   label = @Translation("Smart Rec (Amilia) provider"),
 *   config="openy_gc_auth.provider.smart_rec"
 * )
 */
class SmartRec extends GCIdentityProviderPluginBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config,
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
    GCUserService $gc_user_service,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config, $entity_type_manager, $form_builder, $gc_user_service);
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('openy_gated_content.user_service'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'enable_captcha' => FALSE,
      'verification_text' => 'Please enter the email address associated with your YMCA membership.',
      'not_member_text' => 'We could not find a membership record for that email address. Please verify your email or contact the YMCA for assistance.',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['verification_text'] = [
      '#title' => $this->t('Verification text'),
      '#description' => $this->t('Instructional text shown above the email field on the login form.'),
      '#type' => 'textfield',
      '#default_value' => $config['verification_text'],
      '#required' => TRUE,
    ];

    $form['not_member_text'] = [
      '#title' => $this->t('Not a member message'),
      '#description' => $this->t('Error message shown when no membership record is found for the submitted email address.'),
      '#type' => 'textarea',
      '#default_value' => $config['not_member_text'],
      '#required' => TRUE,
    ];

    if ($this->moduleHandler->moduleExists('simple_recaptcha')) {
      $captcha_description = $this->t('Enable CAPTCHA on the login form. <strong>simple_recaptcha</strong> module detected — configure keys at <a href="/admin/config/services/simple-recaptcha">reCAPTCHA settings</a>.');
    }
    elseif ($this->moduleHandler->moduleExists('captcha')) {
      $captcha_description = $this->t('Enable CAPTCHA on the login form. <strong>captcha</strong> module detected — the default challenge type will be used per <a href="/admin/config/people/captcha">CAPTCHA settings</a>.');
    }
    else {
      $captcha_description = $this->t('Enable CAPTCHA on the login form. No CAPTCHA module (simple_recaptcha or captcha) is currently installed — this setting will have no effect until one is enabled.');
    }

    $form['enable_captcha'] = [
      '#title' => $this->t('Enable CAPTCHA'),
      '#type' => 'checkbox',
      '#default_value' => $config['enable_captcha'],
      '#description' => $captcha_description,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $this->configuration['verification_text'] = $form_state->getValue('verification_text');
      $this->configuration['not_member_text'] = $form_state->getValue('not_member_text');
      $this->configuration['enable_captcha'] = $form_state->getValue('enable_captcha');
      parent::submitConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLoginForm() {
    return $this->formBuilder->getForm('Drupal\openy_gc_auth_smart_rec\Form\VirtualYSmartRecLoginForm');
  }

}
