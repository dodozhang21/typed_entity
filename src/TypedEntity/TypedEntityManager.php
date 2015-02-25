<?php

/**
 * @file
 * Contains \Drupal\typed_entity\TypedEntity\TypedEntityManager.
 */

namespace Drupal\typed_entity\TypedEntity;

class TypedEntityManager implements TypedEntityManagerInterface {

  /**
   * {@inheritdoc}
   */
  public static function create($entity_type, $entity) {
    $class_name = static::getClass($entity_type, $entity);
    return new $class_name($entity_type, NULL, $entity);
  }

  /**
   * Helper function to get a class name given an entity type and an entity.
   *
   * @param string $entity_type
   *   The entity type
   * @param object $entity
   *   The entity object.
   *
   * @return string
   *   A valid class name if one exists. It defaults to TypedEntity.
   */
  public static function getClass($entity_type, $entity) {
    $classes = &drupal_static(__CLASS__ . '::' . __METHOD__);
    list( , , $bundle) = entity_extract_ids($entity_type, $entity);
    $cid = $entity_type . ':' . $bundle;

    if (isset($classes[$cid])) {
      return $classes[$cid];
    }

    $cached_classes = array();
    if ($cache = cache_get('typed_entity_classes', 'cache_bootstrap')) {
      $cached_classes = $cache->data;
    }

    $classes = array_merge($cached_classes, isset($classes) ? $classes : array());
    if (isset($classes[$cid])) {
      return $classes[$cid];
    }

    // The default class should always be TypedEntity. Assume that TypedEntity
    // is under the same namespace as TypedEntityManager.
    $classes[$cid] = '\\' . __NAMESPACE__ . '\\TypedEntity';
    $candidates = static::getClassNameCandidates($entity_type, $bundle);
    foreach ($candidates as $candidate) {
      if (class_exists($candidate)) {
        $classes[$cid] = $candidate;
        break;
      }
    }
    cache_set('typed_entity_classes', $classes, 'cache_bootstrap');

    return $classes[$cid];
  }

  /**
   * Helper function to get possible class names for a given entity type and bundle.
   *
   * If you want your entity types to be auto loaded then you need to place your
   * class in your custom module, under src/TypedEntity. The class needs to be
   * named Typed<EntityTypeCamelCase> or
   * Typed<EntityTypeCamelCase><BundleCamelCase>. For instance TypedNodeArticle.
   *
   * @param string $entity_type
   *   The type of the entity.
   * @param string $bundle
   *   The bundle of the entity.
   *
   * @return array
   *   An array of class name candidates.
   */
  protected static function getClassNameCandidates($entity_type, $bundle) {
    $names = array();
    $class_names = array();
    if (!empty($bundle)) {
      $class_names[] = 'Typed' . static::camelize($entity_type) . static::camelize($bundle);
    }
    // It is important to add the most specific first.
    $class_names[] = 'Typed' . static::camelize($entity_type);
    foreach (module_list() as $module_name) {
      foreach ($class_names as $class_name) {
        $names[] = '\\Drupal\\' . $module_name . '\\TypedEntity\\' . $class_name;
      }
    }
    return $names;
  }

  /**
   * Turns a string into camel case. From search_api_index to SearchApiIndex.
   *
   * @param string $input
   *   The input string.
   *
   * @return string
   *   The camelized string.
   */
  protected static function camelize($input) {
    $input = preg_replace('/-_/', ' ', $input);
    $input = ucwords($input);
    $parts = explode('_', $input);
    return implode('', $parts);
  }

}
