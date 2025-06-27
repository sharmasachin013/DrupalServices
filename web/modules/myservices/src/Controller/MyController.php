<?php

declare(strict_types=1);

namespace Drupal\myservices\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Returns responses for Myservices routes.
 */
final class MyController extends ControllerBase {

  protected $currentUser;

  // Constructor to receive current_user 
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

    // Static create() method for dependency injection
    public static function create(ContainerInterface $container) {
      return new static(
        $container->get('current_user')
      );
    }

  /**
   * Builds the response.
   */
  public function __invoke(): array {

    $uid = $this->currentUser->id();
    $name = $this->currentUser->getAccountName();
    $roles = $this->currentUser->getRoles();

    return [
      '#markup' => "Hello, $name (UID: $uid). Roles: " . implode(', ', $roles),
    ];

    return $build;
  }

}
