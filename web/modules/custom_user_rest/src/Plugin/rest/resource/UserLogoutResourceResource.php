<?php

namespace Drupal\custom_user_rest\Plugin\rest\resource;


use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a REST API to logout the current user with CSRF protection.
 *
 * @RestResource(
 *   id = "user_logout_rest_resource",
 *   label = @Translation("User Logout Resource"),
 *   uri_paths = {
 *     "create" = "/api/user/logout",
 *     "canonical" = "/api/user/logout",
 *   }
 * )
 */
class UserLogoutResourceResource extends ResourceBase {

  protected $currentUser;
  protected $sessionManager;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    SessionManagerInterface $session_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->sessionManager = $session_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('custom_logout_rest'),
      $container->get('current_user'),
      $container->get('session_manager')
    );
  }

  public function get() {
    if ($this->currentUser->isAuthenticated()) {
      user_logout();
      $this->sessionManager->destroy();
      $this->logger->info('User ID @uid has logged out via REST.', ['@uid' => $this->currentUser->id()]);
      return new ResourceResponse(['message' => 'Logged out successfully'], 200);
    }

    return new ResourceResponse(['message' => 'No active user session found.'], 401);
  }
}

