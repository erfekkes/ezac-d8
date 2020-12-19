<?php


namespace ezacLeden\src\Plugin\rest\resource;

use Drupal\ezacLeden\Model\EzacLid;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

  /**
   * Provides a resource for leden table reads
   *
   * @RestResource(
   *   id = "leden",
   *   label = @Translation("EZAC leden table"),
   *   uri_paths = {
   *     "canonical" = "/leden/{id}"
   *   }
   * )
   */
class EzacLedenResource extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * Returns a leden table record for the specified ID.
   *
   * @param int $id
   *   The ID of the leden record.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the leden record.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the leden record was not found.
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when no leden id was provided.
   */
  public function get($id = NULL) {
    if ($id) {
      $record = (new EzacLid)->read($id);
      if (!empty($record)) {
        return new ResourceResponse($record);
      }

      throw new NotFoundHttpException("Leden entry with ID '$id' was not found");
    }

    throw new BadRequestHttpException('No Leden ID was provided');
  }

}