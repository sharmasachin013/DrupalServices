<?php

namespace Drupal\myservices\Controller;

use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Controller\ControllerBase;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AliasController extends ControllerBase {

  protected $aliasManager;

  public function __construct(AliasManagerInterface $alias_manager) {
    $this->aliasManager = $alias_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path_alias.manager')
    );
  }

  public function getPathFromAlias($alias) {
    $alias = '/' . ltrim($alias, '/'); // Ensure it starts with a slash
    $internal_path = $this->aliasManager->getPathByAlias($alias);

    return new Response("System path for alias '$alias' is: $internal_path");
  }
}
