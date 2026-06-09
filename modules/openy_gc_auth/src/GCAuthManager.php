<?php

namespace Drupal\openy_gc_auth;

use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\node\NodeInterface;

/**
 * Class GCAuthManager for common methods.
 */
class GCAuthManager {

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * GCAuthManager constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Check if provided paragraph exists on the node.
   */
  public function checkIfParagraphAtNode(NodeInterface $node, $paragraph_id) {
    $connection = Database::getConnection();

    $result = $connection->select('paragraphs_item_field_data', 'pd')
      ->fields('pd', ['id'])
      ->condition('pd.parent_id', $node->id())
      ->condition('pd.type', $paragraph_id)
      ->countQuery()
      ->execute()
      ->fetchCol();
    return reset($result);
  }

  /**
   * Check if the block exists in the layout.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node to check the block for.
   * @param string $block_id
   *   The id of the block to check.
   *
   * @return bool
   *   TRUE if the block found. FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function checkIfLayoutBlockExists(NodeInterface $node, string $block_id):bool {
    // The layout builder is not used, the block is not going to be found.
    if (!$this->moduleHandler->moduleExists('layout_builder')) {
      return FALSE;
    }

    // Given content type doesn't have layout builder enabled for it.
    if (!$node->hasField('layout_builder__layout')) {
      return FALSE;
    }

    $sections = $node->get('layout_builder__layout')->getValue();
    foreach ($sections as $delta => $section) {
      /** @var \Drupal\layout_builder\Section $section */
      $section = $section['section'];

      foreach ($section->getComponents() as $component) {
        if (strpos($component->getPluginId(), $block_id) !== false) {
          return TRUE;
        }
      }
    }

    // If the code got to this point, the block wasn't found.
    return FALSE;
  }

  /**
   * Check if "Gated Content" Paragraph or Block is present in the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node to check the paragraph/block for.
   *
   * @return bool
   *   TRUE if paragraph or block with "gated_content" id exists.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function gatedContentExists(NodeInterface $node):bool {
    $result = $this->checkIfParagraphAtNode($node, 'gated_content');

    // Paragraph is present. No need to check for block.
    if ($result) {
      return TRUE;
    }

    return $this->checkIfLayoutBlockExists($node, 'virtual_y_app');
  }

  /**
   * Check if "Gated Content Login" Paragraph or Block is present in the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node to check the paragraph/block for.
   *
   * @return bool
   *   TRUE if paragraph/block with id "gated_content_login" exists.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function gatedContentLoginExists(NodeInterface $node):bool {
    $result = $this->checkIfParagraphAtNode($node, 'gated_content_login');

    // Paragraph is present. No need to check for block.
    if ($result) {
      return TRUE;
    }

    return $this->checkIfLayoutBlockExists($node, 'virtual_y_login');
  }

}
