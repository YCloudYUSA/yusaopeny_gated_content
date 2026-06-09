<?php

namespace Drupal\openy_gc_auth_reclique_sso\Plugin\GCIdentityProvider;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;
use Drupal\openy_gated_content\GCUserService;
use Drupal\openy_gc_auth\GCIdentityProviderPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Identity provider plugin.
 *
 * @GCIdentityProvider(
 *   id="reclique_sso",
 *   label = @Translation("Reclique SSO OAuth2 provider"),
 *   config="openy_gc_auth.provider.reclique_sso"
 * )
 */
class RecliqueSSO extends GCIdentityProviderPluginBase {

  use DependencySerializationTrait;
  use MessengerTrait;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config,
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $requestStack,
    FormBuilderInterface $form_builder,
    GCUserService $gc_user_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config, $entity_type_manager, $form_builder, $gc_user_service);
    $this->request = $requestStack->getCurrentRequest();
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
      $container->get('request_stack'),
      $container->get('form_builder'),
      $container->get('openy_gated_content.user_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'authorization_server' => 'https://[association_slug].recliquecore.com',
      'client_id' => '',
      'client_secret' => '',
      'error_access_denied' => 'That user does not have access to Virtual Y.',
      'error_accompanying_message' => 'Please contact us if you have any questions.',
      'error_invalid' => 'Something went wrong.',
      'error_not_found' => 'That user was not found.',
      'login_mode' => 'present_login_button',
      'membership_field' => 'PackageName',
      'permissions_mapping' => '',
      'require_active' => 1
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['#tree'] = TRUE;

    $form['permissions_mapping'] = [
      '#title' => $this->t('Permissions mapping'),
      '#type' => 'details',
      '#open' => TRUE,
      '#prefix' => '<div id="permissions-mapping-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['permissions_mapping']['require_active'] = [
      '#title' => $this->t('Require "Active" status'),
      '#description' => $this->t("Set to TRUE if users are required to have an Active status.<br/>
        If TRUE, all Active users will receive <em>at least</em> basic Virtual Y access.<br/>
        If FALSE no users will have access unless their membership matches a mapping below."),
      '#type' => 'checkbox',
      '#default_value' => $config['require_active'],
    ];

    $form['permissions_mapping']['membership_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Membership field'),
      '#default_value' => $config['membership_field'],
      '#description' => $this->t('The field in the ReClique "member" object to check for the values below.'),
    ];

    $permissions_mapping = explode(';', $config['permissions_mapping']);
    if (!$form_state->has('permissions_mapping_items_count')) {
      $form_state->set('permissions_mapping_items_count', count($permissions_mapping));
    }
    $permissions_mapping_items = $form_state->get('permissions_mapping_items_count');
    $roles = $this->gcUserService->getRoles();
    for ($i = 0; $i < $permissions_mapping_items; $i++) {
      $role = isset($permissions_mapping[$i]) ? explode(':', $permissions_mapping[$i]) : '';
      $form['permissions_mapping'][$i]['permissions_mapping_reclique_role'] = [
        '#title' => $this->t('ReClique membership'),
        '#type' => 'textfield',
        '#default_value' => isset($role[0]) ? $role[0] : '',
        '#size' => 30,
        '#prefix' => '<div class="container-inline">',
      ];

      $form['permissions_mapping'][$i]['permissions_mapping_role'] = [
        '#title' => $this->t('Virtual Y role'),
        '#type' => 'select',
        '#options' => ['' => $this->t('None')] + $roles,
        '#default_value' => isset($role[1]) ? $role[1] : '',
        '#suffix' => '</div>',
      ];
    }

    $form['permissions_mapping']['description'] = [
      '#markup' => $this->t("Set member roles to map to Virtual Y roles. <br/>
        Use <code>*</code> as a wildcard, use <code>\*</code> to search for <code>*</code> itself."),
    ];

    $form['permissions_mapping']['actions'] = [
      '#type' => 'actions',
    ];

    $form['permissions_mapping']['actions']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more'),
      '#submit' => [[get_class($this), 'addOne']],
      '#ajax' => [
        'callback' => [get_class($this), 'addmoreCallback'],
        'wrapper' => 'permissions-mapping-fieldset-wrapper',
      ],
    ];

    $form['authorization_server'] = [
      '#type' => 'url',
      '#title' => $this->t('Authorization server'),
      '#default_value' => $config['authorization_server'],
      '#description' => $this->t('It is most likely "https://[association_slug].recliquecore.com", where association_slug should be provided from ReClique.'),
    ];

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Id'),
      '#default_value' => $config['client_id'],
      '#description' => $this->t('Your ReClique client id.'),
    ];

    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#default_value' => $config['client_secret'],
      '#description' => $this->t('Your ReClique client secret.'),
    ];

    $form['error_not_found'] = [
      '#title' => $this->t('Message for "not found" errors.'),
      '#description' => $this->t('Message displayed when the user is not found in ReClique.'),
      '#type' => 'textfield',
      '#default_value' => $config['error_not_found'],
      '#required' => FALSE,
    ];

    $form['error_access_denied'] = [
      '#title' => $this->t('Message for "access denied" errors.'),
      '#description' => $this->t('Message displayed when the user does not have privileges to Virtual Y.'),
      '#type' => 'textfield',
      '#default_value' => $config['error_access_denied'],
      '#required' => FALSE,
    ];

    $form['error_invalid'] = [
      '#title' => $this->t('Message for all other errors.'),
      '#description' => $this->t('Message displayed for any other errors communicating with ReClique.'),
      '#type' => 'textfield',
      '#default_value' => $config['error_invalid'],
      '#required' => FALSE,
    ];

    $form['error_accompanying_message'] = [
      '#title' => $this->t('Authentication error message'),
      '#description' => $this->t('Additional help displayed after all login failures.'),
      '#type' => 'textfield',
      '#default_value' => $config['error_accompanying_message'],
      '#required' => FALSE,
    ];

    $form['login_mode'] = [
      '#title' => $this->t('Login mode'),
      '#description' => $this->t('When "Redirect immediately" mode used, it is not possible to redirect user after login to his initially requested VY route.'),
      '#type' => 'radios',
      '#default_value' => $config['login_mode'],
      '#required' => TRUE,
      '#options' => [
        'present_login_button' => $this->t('Present login button'),
        'redirect_immediately' => $this->t('Redirect immediately'),
      ],
    ];

    return $form;
  }

  /**
   * Add more item.
   *
   * @param array $form
   *   Form data.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public static function addOne(array &$form, FormStateInterface $form_state) {
    $permissions_mapping_items = $form_state->get('permissions_mapping_items_count');
    $add_button = $permissions_mapping_items + 1;
    $form_state->set('permissions_mapping_items_count', $add_button);
    $form_state->setRebuild();
  }

  /**
   * Add more callback.
   *
   * @param array $form
   *   Form data.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return mixed
   *   Return array with data.
   */
  public static function addmoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['settings']['permissions_mapping'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $this->configuration['authorization_server'] = $form_state->getValue('settings')['authorization_server'];
      $this->configuration['client_id'] = $form_state->getValue('settings')['client_id'];
      $this->configuration['client_secret'] = $form_state->getValue('settings')['client_secret'];
      $this->configuration['error_not_found'] = $form_state->getValue('settings')['error_not_found'];
      $this->configuration['error_access_denied'] = $form_state->getValue('settings')['error_access_denied'];
      $this->configuration['error_invalid'] = $form_state->getValue('settings')['error_invalid'];
      $this->configuration['error_accompanying_message'] = $form_state->getValue('settings')['error_accompanying_message'];
      $this->configuration['login_mode'] = $form_state->getValue('settings')['login_mode'];
      foreach ($form_state->getValue('settings')['permissions_mapping'] as $mapping) {
        if (!empty($mapping['permissions_mapping_reclique_role'])) {
          $permissions_mapping[] = $mapping['permissions_mapping_reclique_role'] . ':' . $mapping['permissions_mapping_role'];
        }
      }
      $this->configuration['permissions_mapping'] = !empty($permissions_mapping) ? implode(';', $permissions_mapping) : '';
      $this->configuration['require_active'] = $form_state->getValue('settings')['permissions_mapping']['require_active'];
      $this->configuration['membership_field'] = $form_state->getValue('settings')['permissions_mapping']['membership_field'];
      parent::submitConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLoginForm() {
    if ($this->request->query->has('error')) {
      return $this->formBuilder->getForm('Drupal\openy_gc_auth_reclique_sso\Form\TryAgainForm');
    }

    if ($this->configuration['login_mode'] === 'present_login_button') {
      return $this->formBuilder->getForm('Drupal\openy_gc_auth_reclique_sso\Form\ContinueWithRecliqueLoginForm');
    }

    // Forcing no-cache at redirect headers.
    $headers = [
      'Cache-Control' => 'no-cache',
    ];
    $response = new RedirectResponse(
      Url::fromRoute('openy_gc_auth_reclique_sso.authenticate_redirect')
        ->toString(),
      302,
      $headers
    );

    return $response->send();
  }

}
