<?php

namespace Drupal\openy_gc_auth_reclique_sso\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Drupal\openy_gc_auth\Event\GCUserLoginEvent;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class GCAuthReCliqueSSOUserLoginSubscriber provides ReClique SSO login Subscriber.
 *
 * @package Drupal\openy_gc_auth_reclique_sso\EventSubscriber
 */
class GCAuthReCliqueSSOUserLoginSubscriber implements EventSubscriberInterface {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Config for openy_gc_auth_reclique_sso module.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $configRecliqueSSO;

  /**
   * Constructs a new GCAuthReCliqueSSOUserLoginSubscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
    $this->configRecliqueSSO = $configFactory->get('openy_gc_auth.provider.reclique_sso');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Static class constant => method on this class.
      GCUserLoginEvent::EVENT_NAME => 'onUserLogin',
    ];
  }

  /**
   * Subscribe to the GC user login event dispatched.
   *
   * @param \Drupal\openy_gc_auth\Event\GCUserLoginEvent $event
   *   Event object.
   */
  public function onUserLogin(GCUserLoginEvent $event) {

    if ($this->configFactory->get('openy_gc_auth.settings')->get('active_provider') == 'reclique_sso') {
      $permissions_mapping = $this->configRecliqueSSO->get('permissions_mapping');
      $require_active = $this->configRecliqueSSO->get('require_active');

      if ($event->account instanceof User && !empty($event->extraData)) {
        $account = $event->account;
        if (isset($event->extraData['member'])) {
          $account_roles = $account->getRoles();
          // Remove all virtual_y roles (in case if any changes in membership).
          foreach ($account_roles as $account_role) {
            if (strstr($account_role, 'virtual_y')) {
              $account->removeRole($account_role);
            }
          }
          $membership_field = $this->configRecliqueSSO->get('membership_field');
          $user_membership = $event->extraData['member']->{$membership_field};
          $active_roles = [];
          $permissions_mapping = explode(';', $permissions_mapping);
          foreach ($permissions_mapping as $mapping) {
            $role = explode(':', $mapping);
            // Compare mapping roles with user membership.
            if (!empty($role[0]) && !empty($role[1])) {
              $role_pattern = '/^(' . preg_replace('/[^\\\\](\*)/', '.*', $role[0]) . ')$/';
              // If we hit a match add that to the active roles.
              if (preg_match($role_pattern, $user_membership)) {
                $active_roles[] = $role[1];
              }
            }
          }
          if ($require_active && $user_membership && empty($active_roles)) {
            $active_roles = ['virtual_y'];
          }
          foreach ($active_roles as $role) {
            $account->addRole($role);
          }
          $account->save();
        }
      }
    }
  }

}
