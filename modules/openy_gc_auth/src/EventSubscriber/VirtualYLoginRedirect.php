<?php

namespace Drupal\openy_gc_auth\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\openy_gc_auth\GCAuthManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\KernelEvent;

/**
 * Class VirtualYLogin Redirect.
 */
class VirtualYLoginRedirect implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * Current user object.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * ConfigFactory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Messenger service instance.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * Auth manager.
   *
   * @var \Drupal\openy_gc_auth\GCAuthManager
   */
  protected $authManager;

  /**
   * Constructs a new VirtualYLoginRedirect.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   Messenger service.
   * @param \Drupal\openy_gc_auth\GCAuthManager $authManager
   *   Auth manager.
   */
  public function __construct(
    RouteMatchInterface $current_route_match,
    AccountProxyInterface $current_user,
    ConfigFactoryInterface $configFactory,
    Messenger $messenger,
    GCAuthManager $authManager
  ) {
    $this->currentRouteMatch = $current_route_match;
    $this->currentUser = $current_user;
    $this->configFactory = $configFactory;
    $this->messenger = $messenger;
    $this->authManager = $authManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['kernel.response'] = ['checkForRedirect'];

    return $events;
  }

  /**
   * A method to be called whenever a kernel.response event is dispatched.
   *
   * @param \Symfony\Component\HttpKernel\Event\KernelEvent $event
   *   The event triggered by the response.
   */
  public function checkForRedirect(KernelEvent $event) {
    $route_name = $this->currentRouteMatch->getRouteName();
    $config = $this->configFactory->get('openy_gated_content.settings');

    switch ($route_name) {
      case 'entity.node.canonical':
        /** @var \Drupal\node\NodeInterface $node */
        $node = $this->currentRouteMatch->getParameter('node');
        $currentUser = $this->currentUser;

        if (
          $currentUser->isAnonymous()
          && $this->authManager->gatedContentExists($node)
        ) {
          if (!empty($config->get('virtual_y_login_url'))) {
            $event->setResponse(new RedirectResponse($config->get('virtual_y_login_url')));
          }
          else {
            $link = Link::createFromRoute($this->t('Setup Virtual Y'),
              'openy_gated_content.settings',
              [],
              [
                'attributes' => [
                  'target' => '_blank',
                ],
              ]
            )->toString();
            $this->messenger->addError($this->t('Virtual Landing Pages are not configured. @link', [
              '@link' => $link,
            ]));
          }
        }

        if (
          $currentUser->isAuthenticated()
          && $this->authManager->gatedContentLoginExists($node)
        ) {

          if (
            $node->hasField('layout_builder__layout')
            // Check if current user has either administrator or virtual_ymca_editor
            // role.
            && count(array_intersect(
              $currentUser->getRoles(),
              ['administrator', 'virtual_ymca_editor']
            )) >= 1
          ) {
            // We don't want to redirect the user from page with Virtual Y Login
            // if he uses Layout Builder, since he has to be able to change the
            // Layout via local tasks tabs.
            return;
          }

          if (!empty($config->get('virtual_y_url'))) {
            $event->setResponse(new RedirectResponse($config->get('virtual_y_url')));
          }
          else {
            $link = Link::createFromRoute($this->t('Setup Virtual Y'),
              'openy_gated_content.settings',
              [],
              [
                'attributes' => [
                  'target' => '_blank',
                ],
              ]
            )->toString();
            $this->messenger->addError($this->t('Virtual Landing Pages are not configured. @link', [
              '@link' => $link,
            ]));
          }
        }
    }
  }

}
