<?php

namespace Drupal\openy_gated_content\Plugin\Block;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Drupal\link\LinkItemInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\openy_system\EntityBrowserFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Virtual Y App' block.
 *
 * @Block(
 *   id = "virtual_y_app",
 *   admin_label = @Translation("Virtual Y App"),
 *   category = @Translation("Virtual Y")
 * )
 */
class VirtualYApp extends BlockBase implements ContainerFactoryPluginInterface {

  use EntityBrowserFormTrait;

  /**
   * Image style to be used for background of the virtual y app headline.
   */
  const BACKGROUND_IMAGE_STYLE = 'virtual_y_paragraph_headline';

  /**
   * Gated Content config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $gatedContentConfig;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module handler interface.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The module extensions list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * Constructs a Block object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The Gated content config.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extensions list.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ImmutableConfig $config,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    ModuleExtensionList $module_extension_list,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->gatedContentConfig  = $config;
    $this->entityTypeManager   = $entity_type_manager;
    $this->moduleHandler       = $module_handler;
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')->get('openy_gated_content.settings'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('extension.list.module')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:openy_gated_content.settings',
      'config:openy_gc_auth.settings',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'title' => 'Virtual YMCA',
      'description' => [
        'value' => "<p>Find the newest Y classes and programs</p>\r\n\r\n<p><a class=\"btn btn-primary\" href=\"#/live-stream\"><span class=\"text\">Live Streams</span></a>&nbsp; <a class=\"btn btn-primary\" href=\"#/categories/video\"><span class=\"text\">Videos</span></a></p>\r\n",
        'format' => 'full_html',
      ],
      'background_image' => NULL,
      'link' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $conf = $this->getConfiguration();

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Headline Title'),
      '#default_value' => $conf['title'],
    ];

    $form['description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Headline Description'),
      '#format' => $conf['description']['format'] ?? '',
      '#default_value' => $conf['description']['value'] ?? '',
      '#rows' => 5,
      '#attributes' => ['class' => ['js-text-full', 'text-full']],
    ];

    // Entity Browser element for background image.
    $form['background_image'] = $this->getEntityBrowserForm(
      'images_library',
      $conf['background_image'],
      1,
      'preview'
    );

    // Convert the wrapping container to a details element.
    $form['background_image']['#type'] = 'details';
    $form['background_image']['#title'] = $this->t('Background image');
    $form['background_image']['#open'] = TRUE;

    $form['link'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Headline Link'),
      '#element_validate' => [
        [LinkWidget::class, 'validateTitleElement'],
        [LinkWidget::class, 'validateTitleNoLink'],
      ],
    ];

    $form['link']['uri'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#attributes' => [
        'data-autocomplete-first-character-blacklist' => '/#?',
      ],
      '#description' => $this->t('Start typing the title of a piece of content to select it. You can also enter an internal path such as %add-node or an external URL such as %url. Enter %front to link to the front page. Enter %nolink to display link text only. Enter %button to display keyboard-accessible link text only.', [
        '%front' => '<front>',
        '%add-node' => '/node/add',
        '%url' => 'http://example.com',
        '%nolink' => '<nolink>',
        '%button' => '<button>',
      ]),
      '#process_default_value' => FALSE,
      '#title' => $this->t('URL'),
      // The current field value could have been entered by a different user.
      // However, if it is inaccessible to the current user, do not display it
      // to them.
      '#default_value' => !empty($conf['link']['uri'])
        ? $this->getUriAsDisplayableString($conf['link']['uri'])
        : NULL,
      '#element_validate' => [[LinkWidget::class, 'validateUriElement']],
      '#maxlength' => 2048,
      '#link_type' => LinkItemInterface::LINK_GENERIC,
    ];

    $form['link']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#default_value' => $conf['link']['title'] ?? '',
      '#maxlength' => 255,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['title'] = $form_state->getValue('title');
    $this->configuration['description'] = $form_state->getValue('description');
    $this->configuration['background_image'] = $this->getEntityBrowserValue($form_state, 'background_image');
    $this->configuration['link'] = $form_state->getValue('link');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $block_config = $this->getConfiguration();

    $app_config = $this->gatedContentConfig->getOriginal();
    // Give ability for 3rd party modules to alter data for the js app.
    $this->moduleHandler->alter('virtual_y_app_settings', $app_config);

    $background_image = '';
    if (!empty($block_config['background_image']) && $media_image = static::loadEntityBrowserEntity($block_config['background_image'])) {
      $image_style = $this->entityTypeManager->getStorage('image_style')
        ->load(self::BACKGROUND_IMAGE_STYLE);
      $background_image = $image_style->buildUrl($media_image->field_media_image->entity->uri->value);
    }

    if (empty($background_image)) {
      $file = 'base://' . $this->moduleExtensionList->getPath('openy_gated_content') . '/assets/img/paragraph-headline.png';
      $background_image = Url::fromUri($file)->setAbsolute()->toString();
    }

    $headline = [
      'title' => $block_config['title'],
      'description' => $block_config['description']['value'],
      'backgroundImage' => $background_image,
    ];

    if (!empty($block_config['link']['title'])
      && !empty($block_config['link']['uri'])) {
      $headline['linkText'] = $block_config['link']['title'];
      $headline['linkUrl'] = Url::fromUri($block_config['link']['uri'])->toString();
    }

    return [
      '#theme' => 'gated_content_app',
      '#app_settings' => json_encode($app_config),
      '#headline' => json_encode($headline),
    ];
  }

  /**
   * Get an entity label if it was selected before.
   *
   * @param $uri
   *   A URI string.
   *
   * @return string
   *   A label of the entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getUriAsDisplayableString($uri) {
    $scheme = parse_url($uri, PHP_URL_SCHEME);
    // By default, the displayable string is the URI.
    $displayable_string = $uri;
    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uri_reference = explode(':', $uri, 2)[1];
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }
      $displayable_string = $uri_reference;
    }
    elseif ($scheme === 'entity') {
      [$entity_type, $entity_id] = explode('/', substr($uri, 7), 2);
      // Show the 'entity:' URI as the entity autocomplete would.
      if ($entity_type == 'node' && $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id)) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }
    elseif ($scheme === 'route') {
      $displayable_string = ltrim($displayable_string, 'route:');
    }
    return $displayable_string;
  }
}
