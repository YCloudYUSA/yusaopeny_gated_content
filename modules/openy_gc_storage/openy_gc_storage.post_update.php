<?php

/**
 * @file
 * Contains hook_post_update_NAME() implementations.
 */

/**
 * Helper function for migrate instructor fields.
 */
function _openy_gc_storage_migrate_instructors(&$sandbox, string $entity_type, string $legacyFieldName) {
  if (!isset($sandbox['max'])) {
    $sandbox['ids'] = \Drupal::entityQuery($entity_type)
      ->exists($legacyFieldName)
      ->execute();
    $sandbox['max'] = count($sandbox['ids']);
  }

  $ids = array_slice($sandbox['ids'], 0, 5);

  // Doublecheck that ids are int, not string.
  $ids = array_map(
    function ($value) {
      return (int) $value;
    },
    $ids
  );
  $entities = \Drupal::entityTypeManager()
    ->getStorage($entity_type)
    ->loadMultiple($ids);
  $notExisted = array_diff($ids, array_keys($entities));
  if (!empty($notExisted)) {
    $sandbox['ids'] = array_diff($sandbox['ids'], $notExisted);
  }
  $termStorage = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term');

  foreach ($entities as $entity) {
    $sandbox['ids'] = array_diff($sandbox['ids'], [$entity->id()]);
    $instructorNames = explode(',', $entity->{$legacyFieldName}->value);
    if (empty($instructorNames)) {
      continue;
    }
    $entity->field_gc_instructor_reference = [];
    foreach ($instructorNames as $instructorName) {
      $trimmedName = trim($instructorName);
      $instructorsIds = $termStorage->getQuery()
        ->condition('name', $trimmedName)
        ->range(0, 1)
        ->execute();
      if (!empty($instructorsIds)) {
        $term = $termStorage->loadMultiple($instructorsIds);
        $term = reset($term);
      }
      else {
        $term = $termStorage->create([
          'vid' => 'gc_instructor',
          'name' => $trimmedName,
        ]);
        $term->save();
      }
      $entity->field_gc_instructor_reference[] = ['target_id' => $term->id()];
    }
    $entity->save();
  }

  $sandbox['#finished'] = (count($sandbox['ids']) === 0) ? TRUE : count($sandbox['ids']) / $sandbox['max'];
  if ($sandbox['#finished']) {
    return t('Fields data were migrated for @count entities', ['@count' => $sandbox['max']]);
  }
}

/**
 * Update all existed Virtual Y Videos' with the instructor references.
 */
function openy_gc_storage_post_update_migrate_node_instructors(&$sandbox) {
  _openy_gc_storage_migrate_instructors($sandbox, 'node', 'field_gc_video_instructor');
}

/**
 * Update all existed Virtual Y eventseries' with the instructor references.
 */
function openy_gc_storage_post_update_migrate_eventseries_instructors(&$sandbox) {
  _openy_gc_storage_migrate_instructors($sandbox, 'eventseries', 'field_ls_host_name');
}

/**
 * Update all existed Virtual Y eventinstances' with the instructor references.
 */
function openy_gc_storage_post_update_migrate_eventinstance_instructors(&$sandbox) {
  _openy_gc_storage_migrate_instructors($sandbox, 'eventinstance', 'field_ls_host_name');
}

/**
 * Create terms for Virtual Y Duration.
 */
function openy_gc_storage_post_update_create_durations(&$sandbox) {
  $durations = [
    [
      'title' => '10 minutes or less',
      'low' => 1,
      'high' => 659,
    ],
    [
      'title' => '15 minutes',
      'low' => 660,
      'high' => 1080,
    ],
    [
      'title' => '20 minutes',
      'low' => 1081,
      'high' => 1319,
    ],
    [
      'title' => '30 minutes',
      'low' => 1320,
      'high' => 1919,
    ],
    [
      'title' => '45 minutes',
      'low' => 1920,
      'high' => 2819,
    ],
    [
      'title' => '60 minutes',
      'low' => 2820,
      'high' => 3899,
    ],
    [
      'title' => '90 minutes',
      'low' => 3900,
      'high' => 5699,
    ],
    [
      'title' => '90 minutes or more',
      'low' => 5700,
      'high' => 0,
    ],
    [
      'title' => 'Undefined',
      'low' => 0,
      'high' => 0,
    ],
  ];

  $term_storage = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term');

  foreach ($durations as $duration) {
    $term = $term_storage->create([
      'vid'                   => 'gc_duration',
      'name'                  => $duration['title'],
      'field_gc_duration_min' => $duration['low'],
      'field_gc_duration_max' => $duration['high'],
    ]);
    $term->save();
  }

  $sandbox['#finished'] = 1;
  return t('The Duration terms have been created.');
}

/**
 * Helper method to build duration references based on duration field value.
 */
function _openy_gc_storage_build_duration_references(&$sandbox) {
  if (!isset($sandbox['sandbox']['ids'])) {
    $sandbox['sandbox']['ids'] = \Drupal::entityQuery('node')
      ->condition('type', 'gc_video')
      ->sort('nid')
      ->execute();

    // By default we'll set reference to 'Undefined' duration term.
    $sandbox['sandbox']['default_duation_term'] = '';

    // Collect all duration terms, sorted by minimum duration value.
    $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $query = $taxonomy_storage->getQuery();
    $query->condition('vid', 'gc_duration')
      ->sort('field_gc_duration_min', 'ASC');
    $duration_terms_ids = $query->execute();

    $duration_terms = $taxonomy_storage->loadMultiple($duration_terms_ids);
    /**
     * @var int $duration_id
     * @var \Drupal\taxonomy\TermInterface $duration_term
     */
    foreach ($duration_terms as $duration_id => $duration_term) {
      $min = $duration_term->get('field_gc_duration_min')->getString();
      $max = $duration_term->get('field_gc_duration_max')->getString();
      if ($min === '0' && $max === '0') {
        $sandbox['sandbox']['default_duration_term'] = $duration_id;
        continue;
      }

      $sandbox['sandbox']['durations'][$duration_id] = [
        'min' => $min,
        'max' => $max,
      ];
    }

    $sandbox['sandbox']['max'] = count($sandbox['sandbox']['ids']);
    $sandbox['sandbox']['processed'] = 0;
  }

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  for ($i = 0; $i < 5; $i++) {
    if (empty($sandbox['sandbox']['ids'])) {
      break;
    }
    $duration_term_tid = $sandbox['sandbox']['default_duration_term'];

    $current_nid = array_shift($sandbox['sandbox']['ids']);
    $node = $node_storage->load($current_nid);
    /** @var \Drupal\node\NodeInterface $node */
    $duration_field = $node->get('field_gc_video_duration');
    // Let's find id of the duration term that has minimal value less than the
    // duration of the given video & has maximum value bigger than or equal to
    // the duration of the given video.
    // If term has '0' as a maximum range, then we assign it to the given node
    // provided the minimum range is satisfiable.
    if (!$duration_field->isEmpty()) {
      $duration_value = $duration_field->getString();
      foreach ($sandbox['sandbox']['durations'] as $duration_id => $duration_range) {
        if (
          $duration_range['min'] < $duration_value
          && (
            $duration_range['max'] === '0'
            || $duration_range['max'] >= $duration_value
          )
        ) {
          $duration_term_tid = $duration_id;
          break;
        }
      }
    }

    if (!empty($duration_term_tid)) {
      $node->set('field_gc_duration_reference', $duration_term_tid)->save();
    }

    $sandbox['sandbox']['processed']++;
    $sandbox['results']['processed'] = $sandbox['sandbox']['processed'];
  }

  $sandbox['#finished'] = empty($sandbox['sandbox']['ids']) ? 1 : $sandbox['sandbox']['processed'] / $sandbox['sandbox']['max'];

  $sandbox['finished'] = $sandbox['#finished'];
  $sandbox['message'] = t(
    'Processed @current videos out of total @total',
    [
      '@current' => $sandbox['sandbox']['processed'],
      '@total' => $sandbox['sandbox']['max'],
    ]
  );
}

/**
 * Add appropriate duration references to all existing 'Virtual Y Video' nodes.
 */
function openy_gc_storage_post_update_migrate_node_durations(&$sandbox) {
  _openy_gc_storage_build_duration_references($sandbox);
}

/**
 * Migrate entity_browser fields to media_library widget.
 *
 * This migrates all entity_browser_entity_reference widgets that use
 * images_library or videos_library to the core media_library_widget.
 */
function openy_gc_storage_post_update_migrate_entity_browser_to_media_library() {
  $form_displays = [
    'node.gc_video.default' => ['field_gc_video_media' => 'video', 'field_gc_video_image' => 'image'],
    'node.vy_blog_post.default' => ['field_vy_blog_image' => 'image'],
    'eventinstance.live_stream.default' => ['field_ls_image' => 'image'],
    'eventseries.live_stream.default' => ['field_ls_image' => 'image'],
    'eventinstance.virtual_meeting.default' => ['field_ls_image' => 'image'],
    'eventseries.virtual_meeting.default' => ['field_ls_image' => 'image'],
    'taxonomy_term.gc_category.default' => ['field_gc_category_media' => 'image'],
    'taxonomy_term.gc_instructor.default' => ['field_gc_instructor_photo' => 'image'],
  ];

  $storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');
  $migrated = [];
  $skipped = [];

  foreach ($form_displays as $display_id => $fields) {
    $form_display = $storage->load($display_id);

    if (!$form_display) {
      $skipped[] = $display_id . ' (not found)';
      continue;
    }

    $display_changed = FALSE;

    foreach ($fields as $field_name => $media_type) {
      $component = $form_display->getComponent($field_name);

      if (!$component) {
        continue;
      }

      // Only migrate if still using entity_browser.
      if ($component['type'] === 'entity_browser_entity_reference') {
        $component['type'] = 'media_library_widget';
        $component['settings'] = ['media_types' => [$media_type]];

        // Remove entity_browser specific settings.
        unset($component['settings']['entity_browser']);
        unset($component['settings']['field_widget_display']);
        unset($component['settings']['field_widget_edit']);
        unset($component['settings']['field_widget_remove']);
        unset($component['settings']['selection_mode']);
        unset($component['settings']['field_widget_display_settings']);
        unset($component['settings']['field_widget_replace']);
        unset($component['settings']['open']);

        $form_display->setComponent($field_name, $component);
        $display_changed = TRUE;
        $migrated[] = $display_id . ' -> ' . $field_name;
      }
    }

    if ($display_changed) {
      // Recalculate dependencies after changing widget type.
      $form_display->calculateDependencies();
      $form_display->save();
    }
  }

  $message = '';
  if (!empty($migrated)) {
    $message .= t('Successfully migrated @count fields: @list', [
      '@count' => count($migrated),
      '@list' => implode(', ', $migrated),
    ]);
  }
  if (!empty($skipped)) {
    $message .= ' ' . t('Skipped: @list', ['@list' => implode(', ', $skipped)]);
  }
  if (empty($migrated) && empty($skipped)) {
    $message = t('All fields are already using media_library widget, no migration needed.');
  }

  return $message;
}
