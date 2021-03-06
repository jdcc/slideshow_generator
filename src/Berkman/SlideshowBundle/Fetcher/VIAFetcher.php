<?php

namespace Berkman\SlideshowBundle\Fetcher;

use Berkman\SlideshowBundle\Entity;

class VIAFetcher extends Fetcher implements FetcherInterface {

	/*
	 * id_1 = recordId
	 * id_2 = componentId
	 * id_3 = metadataId
	 * id_4 = metadataSubId
	 * id_5 = imageId
	 * id_6 = thumbnailId
	 */

	const SEARCH_URL_PATTERN    = 'http://webservices.lib.harvard.edu/rest/hollis/search/dc/?curpage={page}&q=material-id:matPhoto+{keyword}';
	const RECORD_URL_PATTERN    = 'http://via.lib.harvard.edu:80/via/deliver/deepLinkItem?recordId={id-1}&componentId={id-2}';
	const METADATA_URL_PATTERN  = 'http://webservices.lib.harvard.edu/rest/mods/via/{id-3}';
	const IMAGE_URL_PATTERN     = 'http://nrs.harvard.edu/urn-3:{id-5}';
	const THUMBNAIL_URL_PATTERN = 'http://nrs.harvard.edu/urn-3:{id-6}';

	const RESULTS_PER_PAGE      = 25;

	/**
	 * @var Berkman\SlideshowBundle\Entity\Repo $repo
	 */
	private $repo;

	/**
	 * Construct the fetcher and associate with repo
	 *
	 * @param Berkman\SlideshowBundle\Entity\Repo $repo
	 */
	public function __construct(Entity\Repo $repo)
	{
		$this->repo = $repo;
	}

	/**
	 * Get the repository associated with this fetcher
	 *
	 * @return Berkman\SlideshowBundle\Entity\Repo $repo
	 */
	public function getRepo()
	{
		return $this->repo;
	}

	/**
	 * Get search results
	 *
	 * @param string $keyword
	 * @param int $startIndex
	 * @param int $endIndex
	 * @return array An array of the form array('images' => $images, 'totalResults' => $totalResults)
	 */
	public function getSearchResults($keyword, $startIndex, $endIndex)
	{
		$images = array();
		$totalResults = 0;
		$numResults = $endIndex - $startIndex + 1;
		$page = floor($startIndex / (self::RESULTS_PER_PAGE)) + 1;

#		while (count($images) < $numResults) {
			$searchUrl = str_replace(
				array('{keyword}', '{page}'),
				array($keyword, $page), 
				self::SEARCH_URL_PATTERN
			);

			$xpath = $this->fetchXpath($searchUrl);
			$totalResults = (int) $xpath->document->getElementsByTagName('totalResults')->item(0)->textContent;
			if ($totalResults < $numResults) {
				$numResults = $totalResults;
			}
			$nodeList = $xpath->document->getElementsByTagName('item');
			foreach ($nodeList as $image) {
				if (count($images) == $numResults) {
					//break;
				}
				$recordId = $image->getAttribute('hollisid');
				$metadataId = $recordId;
				$componentId = null;
				$metadataSubId = null;
				$imageId = null;
				$thumbnailId = null;

				// Search for sub-images (wtf).
				$numberOfImages = $image->getElementsByTagName('numberofimages')->item(0);
				if ($numberOfImages && $numberOfImages->textContent > 1) {
					$imageXpath = $this->fetchXpath('http://webservices.lib.harvard.edu/rest/mods/via/'.$recordId);
					$imageXpath->registerNamespace('mods', 'http://www.loc.gov/mods/v3');
					$constituents = $imageXpath->query("//mods:relatedItem[@type='constituent']");
					foreach ($constituents as $constituent) {
						if (count($images) == $numResults) {
							//break;
						}
						$fullImage = $imageXpath->query(".//mods:location/mods:url[@displayLabel='Full Image'][@note='unrestricted']", $constituent)->item(0);
						if ($fullImage) {
							$componentId = substr($fullImage->textContent, strpos($fullImage->textContent, ':', 5) + 1);
							$imageId = $componentId;
							$thumbnail = $imageXpath->query(".//mods:location/mods:url[@displayLabel='Thumbnail']", $constituent)->item(0);
							if ($thumbnail) {
								$thumbnailId = substr($thumbnail->textContent, strpos($thumbnail->textContent, ':', 5) + 1).'?height=150&width=150';
							}
							$recordIdentifier = $imageXpath->query('.//mods:recordIdentifier', $constituent)->item(0);
							$metadataSubId = $recordIdentifier->textContent;
							$images[] = new Entity\Image($this->getRepo(), $recordId, $componentId, $metadataId, $metadataSubId, $imageId, $thumbnailId);
						}
					}
				} 
				// Add images without sub-images
				elseif ($numberOfImages && $numberOfImages->textContent == 1) {
					$thumbnail = $image->getElementsByTagName('thumbnail')->item(0);
					if ($thumbnail) {
						$thumbnailUrl = $thumbnail->textContent;
						$thumbnailId = substr($thumbnailUrl, strpos($thumbnailUrl, ':', 5) + 1);
					}
					$fullImage = $image->getElementsByTagName('fullimage')->item(0);
					if ($fullImage) {
						$fullImageUrl = $fullImage->textContent;
						$componentId = substr($fullImageUrl, strpos($fullImageUrl, ':', 5) + 1);
						$imageId = $componentId;
						$images[] = new Entity\Image($this->getRepo(), $recordId, $componentId, $metadataId, $metadataSubId, $imageId, $thumbnailId);
					}
				}
			}

			$page++;
#		}

		return array('images' => $images, 'totalResults' => $totalResults);
	}

	/**
	 * Get the metadata for a given image
	 *
	 * @param Berkman\SlideshowBundle\Entity\Image $image
	 * @return array An associative array where the key is the metadata field name and value is the value
	 */
	public function getMetadata(Entity\Image $image)
	{
		$metadata = array();
		$fields = array(
			'Title' => './/mods:title',
			'Creator' => './/mods:namePart[1]',
			'Date' => './/mods:dateCreated[last()]',
			//'Usage Restrictions' => './/mods:accessCondition',
			'Notes' => './mods:note'
		);
		$metadataId = $image->getId3();
		if ($image->getId4()) {
			$metadataId = $image->getId4();
		}
		$metadataUrl = $this->fillUrl(self::METADATA_URL_PATTERN, $image);
		$xpath = $this->fetchXpath($metadataUrl);
		$xpath->registerNamespace('mods', 'http://www.loc.gov/mods/v3');
		$recordIdent = $xpath->query("//mods:recordIdentifier[.='".$metadataId."']")->item(0);
		if ($recordIdent) {
			$recordContainer = $recordIdent->parentNode->parentNode;
			
			foreach ($fields as $name => $query) {
				$node = $xpath->query($query, $recordContainer)->item(0);
				if ($node) {
					$metadata[$name] = $node->textContent;
				}
			}
		}

		return $metadata;
	}

	/**
	 * Get the full image url for a given image object
	 *
	 * @param Berkman\SlideshowBundle\Entity\Image @image
	 * @return string $imageUrl
	 */
	public function getImageUrl(Entity\Image $image)
	{
		return $this->fillUrl(self::IMAGE_URL_PATTERN, $image);
	}

	/**
	 * Get the thumbnail url for a given image object
	 *
	 * @param Berkman\SlideshowBundle\Entity\Image @image
	 * @return string $thumbnailUrl
	 */
	public function getThumbnailUrl(Entity\Image $image)
	{
		return $this->fillUrl(self::THUMBNAIL_URL_PATTERN, $image);
	}

	/**
	 * Get the authoritative record url for a given image object
	 *
	 * @param Berkman\SlideshowBundle\Entity\Image $image
	 * @return string $recordUrl
	 */
	public function getRecordUrl(Entity\Image $image)
	{
		return $this->fillUrl(self::RECORD_URL_PATTERN, $image);
	}	
}
