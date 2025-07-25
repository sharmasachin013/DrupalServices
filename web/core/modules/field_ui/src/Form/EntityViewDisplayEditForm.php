<?php

namespace Drupal\field_ui\Form;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\PluginSettingsInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\field_ui\FieldUI;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\field\FieldLabelOptionsTrait;

/**
 * Edit form for the EntityViewDisplay entity type.
 *
 * @internal
 */
class EntityViewDisplayEditForm extends EntityDisplayFormBase {
  use FieldLabelOptionsTrait;
  /**
   * {@inheritdoc}
   */
  protected $displayContext = 'view';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.field.field_type'),
      $container->get('plugin.manager.field.formatter'),
      $container->get('entity_display.repository'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function buildFieldRow(FieldDefinitionInterface $field_definition, array $form, FormStateInterface $form_state) {
    $field_row = parent::buildFieldRow($field_definition, $form, $form_state);

    $field_name = $field_definition->getName();
    $display_options = $this->entity->getComponent($field_name);

    // Insert the label column.
    $label = [
      'label' => [
        '#type' => 'select',
        '#title' => $this->t('Label display for @title', ['@title' => $field_definition->getLabel()]),
        '#title_display' => 'invisible',
        '#options' => $this->getFieldLabelOptions(),
        '#default_value' => $display_options ? $display_options['label'] : 'above',
      ],
    ];

    $label_position = array_search('plugin', array_keys($field_row));
    $field_row = array_slice($field_row, 0, $label_position, TRUE) + $label + array_slice($field_row, $label_position, count($field_row) - 1, TRUE);

    // Update the (invisible) title of the 'plugin' column.
    $field_row['plugin']['#title'] = $this->t('Formatter for @title', ['@title' => $field_definition->getLabel()]);
    if (!empty($field_row['plugin']['settings_edit_form']) && ($plugin = $this->entity->getRenderer($field_name))) {
      $plugin_type_info = $plugin->getPluginDefinition();
      $field_row['plugin']['settings_edit_form']['label']['#markup'] = $this->t('Format settings:') . ' <span class="plugin-name">' . $plugin_type_info['label'] . '</span>';
    }

    return $field_row;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildExtraFieldRow($field_id, $extra_field) {
    $extra_field_row = parent::buildExtraFieldRow($field_id, $extra_field);

    // Insert an empty placeholder for the label column.
    $label = [
      'empty_cell' => [
        '#markup' => '&nbsp;',
      ],
    ];
    $label_position = array_search('plugin', array_keys($extra_field_row));
    $extra_field_row = array_slice($extra_field_row, 0, $label_position, TRUE) + $label + array_slice($extra_field_row, $label_position, count($extra_field_row) - 1, TRUE);

    return $extra_field_row;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityDisplay($entity_type_id, $bundle, $mode) {
    return $this->entityDisplayRepository->getViewDisplay($entity_type_id, $bundle, $mode);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultPlugin($field_type) {
    return $this->fieldTypes[$field_type]['default_formatter'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDisplayModes() {
    return $this->entityDisplayRepository->getViewModes($this->entity->getTargetEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  protected function getDisplayModeOptions() {
    return $this->entityDisplayRepository->getViewModeOptions($this->entity->getTargetEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  protected function getDisplayModesLink() {
    return [
      '#type' => 'link',
      '#title' => $this->t('Manage view modes'),
      '#url' => Url::fromRoute('entity.entity_view_mode.collection'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getTableHeader() {
    return [
      $this->t('Field'),
      [
        'data' => $this->t('Machine name'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM, 'machine-name'],
      ],
      $this->t('Weight'),
      $this->t('Parent'),
      $this->t('Region'),
      $this->t('Label'),
      ['data' => $this->t('Format'), 'colspan' => 3],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getOverviewUrl($mode) {
    $entity_type = $this->entityTypeManager->getDefinition($this->entity->getTargetEntityTypeId());
    return Url::fromRoute('entity.entity_view_display.' . $this->entity->getTargetEntityTypeId() . '.view_mode', [
      'view_mode_name' => $mode,
    ] + FieldUI::getRouteBundleParameter($entity_type, $this->entity->getTargetBundle()));
  }

  /**
   * {@inheritdoc}
   */
  protected function thirdPartySettingsForm(PluginSettingsInterface $plugin, FieldDefinitionInterface $field_definition, array $form, FormStateInterface $form_state) {
    $settings_form = [];
    // Invoke hook_field_formatter_third_party_settings_form(), keying resulting
    // subforms by module name.
    $this->moduleHandler->invokeAllWith(
      'field_formatter_third_party_settings_form',
      function (callable $hook, string $module) use (&$settings_form, &$plugin, &$field_definition, &$form, &$form_state) {
        $settings_form[$module] = ($settings_form[$module] ?? []) + ($hook(
          $plugin,
          $field_definition,
          $this->entity->getMode(),
          $form,
          $form_state,
        )) ?? [];
      }
    );
    return $settings_form;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterSettingsSummary(array &$summary, PluginSettingsInterface $plugin, FieldDefinitionInterface $field_definition) {
    $context = [
      'formatter' => $plugin,
      'field_definition' => $field_definition,
      'view_mode' => $this->entity->getMode(),
    ];
    $this->moduleHandler->alter('field_formatter_settings_summary', $summary, $context);
  }

}
