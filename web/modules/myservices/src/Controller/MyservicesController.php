<?php

declare(strict_types=1);

namespace Drupal\myservices\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;



class MyservicesController extends ControllerBase {

  protected  $currentUser;
  protected  $entityTypeManager;
  protected  $logger;


  public function __construct(AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  public function userInfo(): array {
    // 1. current_user - Get user name and ID
    $uid = $this->currentUser->id();
    $username = $this->currentUser->getDisplayName();

    // 2. entity_type.manager - Load full user entity
    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($uid);

    $mail = $user ? $user->getEmail() : 'N/A';

    return [
      '#type' => 'markup',
      '#markup' => $this->t('Hello @username! Your email is: @mail', [
        '@username' => $username,
        '@mail' => $mail,
      ]),
    ];
  }
}
