<?php

namespace Drupal\openy_gated_content\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Normalizes query parameters for JSON:API requests.
 *
 * Fixes the Symfony HttpKernel "non-scalar value" error that occurs when
 * page[limit] and page[offset] are sent as query parameters and Symfony
 * parses them into a nested array.
 *
 * @see https://www.drupal.org/project/jsonapi_search_api_facets/issues/3208534
 */
class JsonApiRequestNormalizer implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Run at very high priority, before any validation
      KernelEvents::REQUEST => ['normalizeQueryParams', 256],
    ];
  }

  /**
   * Normalize query parameters for JSON:API.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function normalizeQueryParams(RequestEvent $event) {
    $request = $event->getRequest();

    // Only process JSON:API requests
    if (strpos($request->getPathInfo(), '/jsonapi/') === FALSE) {
      return;
    }

    // Get all query parameters using the low-level method.
    $query = $request->query;
    $all_params = $query->all();

    // Check if 'page' exists as an array (from parsing page[limit] and page[offset]).
    if (isset($all_params['page']) && is_array($all_params['page'])) {
      $page_data = $all_params['page'];

      // Remove the problematic nested 'page' key.
      $query->remove('page');

      // Re-add the page limit and offset as individual scalar parameters.
      // This prevents Symfony from treating 'page' as a non-scalar value.
      if (isset($page_data['limit'])) {
        $query->set('page_limit', (int) $page_data['limit']);
      }
      if (isset($page_data['offset'])) {
        $query->set('page_offset', (int) $page_data['offset']);
      }

      // Update the actual query string for downstream use.
      $this->updateQueryString($request, $page_data);
    }
  }

  /**
   * Update the raw query string.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param array $page_data
   *   The original page query parameters.
   */
  private function updateQueryString($request, $page_data) {
   // Build new query string with page params in bracket notation.
   $parts = [];

   // Add all non-page query params.
   foreach ($request->query->all() as $key => $value) {
     if ($key !== 'page_limit' && $key !== 'page_offset') {
       if (is_array($value)) {
         // Handle nested arrays (filter, sort, etc.).
         foreach ($value as $k => $v) {
           if (is_array($v)) {
             foreach ($v as $kk => $vv) {
               $parts[] = urlencode($key) . '[' . urlencode($k) . '][' . urlencode($kk) . ']=' . urlencode($vv);
             }
           } else {
             $parts[] = urlencode($key) . '[' . urlencode($k) . ']=' . urlencode($v);
           }
         }
       } else {
         $parts[] = urlencode($key) . '=' . urlencode($value);
       }
     }
   }

   // Add all original page params in bracket notation that JSON:API expects.
   foreach ($page_data as $key => $value) {
     // Preserve existing behavior of casting limit/offset to integers.
     if ($key === 'limit' || $key === 'offset') {
       $value = (int) $value;
     }
     $parts[] = 'page[' . urlencode($key) . ']=' . urlencode($value);
   }

   // Update the server query string.
   $query_string = implode('&', $parts);
   $request->server->set('QUERY_STRING', $query_string);
  }

}

