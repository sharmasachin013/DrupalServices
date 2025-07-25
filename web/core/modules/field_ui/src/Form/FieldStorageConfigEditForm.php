<?php

namespace Drupal\field_ui\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a form for the "field storage" edit page.
 *
 * @internal
 */
class FieldStorageConfigEditForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\field\FieldStorageConfigInterface
   */
  protected $entity;

  /**
   * FieldStorageConfigEditForm constructor.
   *
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typedDataManager
   *   The typed data manager.
   */
  public function __construct(
    protected TypedDataManagerInterface $typedDataManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('typed_data_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    // The URL of this entity form contains only the ID of the field_config
    // but we are actually editing a field_storage_config entity.
    $field_config = FieldConfig::load($route_match->getRawParameter('field_config'));
    if (!$field_config) {
      throw new NotFoundHttpException();
    }

    return $field_config->getFieldStorageDefinition();
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\field\FieldConfigInterface|string $field_config
   *   The ID of the field config whose field storage config is being edited.
   */
  public function buildForm(array $form, FormStateInterface $form_state, FieldConfigInterface|string|null $field_config = NULL) {
    if ($field_config) {
      $field = $field_config;
      if (is_string($field)) {
        $field = FieldConfig::load($field_config);
      }
      $form_state->set('field_config', $field);

      $form_state->set('entity_type_id', $field->getTargetEntityTypeId());
      $form_state->set('bundle', $field->getTargetBundle());
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $field_label = $form_state->get('field_config')->label();
    $form['#prefix'] = '<p>' . $this->t('These settings apply to the %field field everywhere it is used. Some also impact the way that data is stored and cannot be changed once data has been created.', ['%field' => $field_label]) . '</p>';

    // Add the cardinality sub-form.
    $form['cardinality_container'] = $this->getCardinalityForm();

    // Add settings provided by the field module. The field module is
    // responsible for not returning settings that cannot be changed if
    // the field already has data.
    $form['settings'] = [
      '#weight' => -10,
      '#tree' => TRUE,
    ];
    // Create an arbitrary entity object, so that we can have an instantiated
    // FieldItem.
    $ids = (object) [
      'entity_type' => $form_state->get('entity_type_id'),
      'bundle' => $form_state->get('bundle'),
      'entity_id' => NULL,
    ];
    $entity = _field_create_entity_from_ids($ids);
    if (!$this->entity->isNew()) {
      $items = $entity->get($this->entity->getName());
    }
    else {
      $field_config = $form_state->get('field_config');
      $items = $this->typedDataManager->create($field_config, name: $this->entity->getName(), parent: EntityAdapter::createFromEntity($entity));
    }
    $item = $items->first() ?: $items->appendItem();
    $form['settings'] += $item->storageSettingsForm($form, $form_state, $this->entity->hasData());

    return $form;
  }

  /**
   * Builds the cardinality form.
   *
   * @return array
   *   The cardinality form render array.
   */
  protected function getCardinalityForm() {
    $form = [
      // Reset #parents so the additional container does not appear.
      '#parents' => [],
      '#type' => 'fieldset',
      '#title' => $this->t('Allowed number of values'),
      '#attributes' => [
        'class' => [
          'container-inline',
          'fieldgroup',
          'form-composite',
        ],
      ],
    ];

    if ($enforced_cardinality = $this->getEnforcedCardinality()) {
      if ($enforced_cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
        $markup = $this->t("This field cardinality is set to unlimited and cannot be configured.");
      }
      else {
        $markup = $this->t("This field cardinality is set to @cardinality and cannot be configured.", ['@cardinality' => $enforced_cardinality]);
      }
      $form['cardinality'] = ['#markup' => $markup];
    }
    else {
      $form['#element_validate'][] = [$this, 'validateCardinality'];
      $cardinality = $this->entity->getCardinality();
      $form['cardinality'] = [
        '#type' => 'select',
        '#title' => $this->t('Allowed number of values'),
        '#title_display' => 'invisible',
        '#options' => [
          'number' => $this->t('Limited'),
          FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED => $this->t('Unlimited'),
        ],
        '#default_value' => ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 'number',
      ];
      $form['cardinality_number'] = [
        '#type' => 'number',
        '#default_value' => $cardinality != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED ? $cardinality : 1,
        '#min' => 1,
        '#title' => $this->t('Limit'),
        '#title_display' => 'invisible',
        '#size' => 2,
        '#states' => [
          'visible' => [
            ':input[name="field_storage[subform][cardinality]"]' => ['value' => 'number'],
          ],
          'disabled' => [
            ':input[id="field_storage[subform][cardinality]"]' => ['value' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED],
          ],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * Validates the cardinality.
   *
   * @param array $element
   *   The cardinality form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateCardinality(array &$element, FormStateInterface $form_state) {
    $field_storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($this->entity->getTargetEntityTypeId());

    $cardinality = $form_state->getValue([
      ...$element['#parents'],
      'cardinality',
    ]);
    $cardinality_number = $form_state->getValue([
      ...$element['#parents'],
      'cardinality_number',
    ]);

    // Validate field cardinality.
    if ($cardinality === 'number' && !$cardinality_number) {
      $form_state->setError($element['cardinality_number'], $this->t('Number of values is required.'));
    }
    // If a specific cardinality is used, validate that there are no entities
    // with a higher delta.
    elseif (!$this->entity->isNew() && isset($field_storage_definitions[$this->entity->getName()]) && $cardinality != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {

      // Get a count of entities that have a value in a delta higher than the
      // one selected. Deltas start with 0, so the selected value does not
      // need to be incremented.
      $entities_with_higher_delta = \Drupal::entityQuery($this->entity->getTargetEntityTypeId())
        ->accessCheck(FALSE)
        ->condition($this->entity->getName() . '.%delta', $cardinality_number)
        ->count()
        ->execute();
      if ($entities_with_higher_delta) {
        $form_state->setError($element['cardinality_number'], $this->formatPlural($entities_with_higher_delta, 'There is @count entity with @delta or more values in this field, so the allowed number of values cannot be set to @allowed.', 'There are @count entities with @delta or more values in this field, so the allowed number of values cannot be set to @allowed.', ['@delta' => $cardinality_number + 1, '@allowed' => $cardinality_number]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    // Save field cardinality.
    if (!$this->getEnforcedCardinality() && $form_state->getValue('cardinality') === 'number' && $form_state->getValue('cardinality_number')) {
      $form_state->setValue('cardinality', (int) $form_state->getValue('cardinality_number'));
    }

    return parent::buildEntity($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
  }

  /**
   * Returns the cardinality enforced by the field type.
   *
   * Some field types choose to enforce a fixed cardinality. This method
   * returns that cardinality or NULL if no cardinality has been enforced.
   *
   * @return int|null
   *   The enforced cardinality as an integer, or NULL if no cardinality is
   *   enforced.
   */
  protected function getEnforcedCardinality() {
    /** @var \Drupal\Core\Field\FieldTypePluginManager $field_type_manager */
    $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
    $definition = $field_type_manager->getDefinition($this->entity->getType());
    return $definition['cardinality'] ?? NULL;
  }

}
