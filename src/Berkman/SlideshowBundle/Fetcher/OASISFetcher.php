<?php

namespace Berkman\SlideshowBundle\Fetcher;

use Berkman\SlideshowBundle\Entity;

class OASISFetcher implements FetcherInterface {

	/*
	 * id_1 = recordId
	 * id_3 = imageId
	 * id_4 = finding aid unit id
	 */

	const SEARCH_URL_PATTERN          = 'http://webservices.lib.harvard.edu/rest/hollis/search/mods/?curpage={page}&q=eadid:*+{keyword}&add_ref=612';
	const FINDING_AID_XML_URL_PATTERN = 'http://oasis.lib.harvard.edu/oasis/ead2002/schema/{finding-aid-id}';
	const PAGED_RESOURCES_URL_PATTERN = 'http://pds.lib.harvard.edu/pds/view/{paged-resource-id}?op=n&printThumbnails=true';

	const RECORD_URL_PATTERN    = 'http://oasis.lib.harvard.edu/oasis/deliver/deepLink?_collection=oasis&uniqueId={id-1}';
	const METADATA_URL_PATTERN = 'http://oasis.lib.harvard.edu/oasis/ead2002/schema/{id-1}';
	const IMAGE_URL_PATTERN     = 'http://ids.lib.harvard.edu/ids/view/{id-3}?width=2400&height=2400';
	const THUMBNAIL_URL_PATTERN = 'http://ids.lib.harvard.edu/ids/view/{id-3}?width=150&height=150&usethumb=y';

	const RESULTS_PER_PAGE = 25;

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
		$pdsIds = array();
		$totalResults = 0;
		$numResults = $endIndex - $startIndex + 1;
		$page = floor($startIndex / (self::RESULTS_PER_PAGE)) + 1;

		$searchUrl = str_replace(
			array('{keyword}', '{page}'),
			array($keyword, $page), 
			self::SEARCH_URL_PATTERN
		);

		$doc = new \DOMDocument();
		$xml = $this->fetchXml($searchUrl);
		if (!$xml) {
			return array('images' => $images, 'totalResults' => 0);
		}
		$doc->loadXML($xml);
		$totalResults = (int) $doc->getElementsByTagName('totalResults')->item(0)->textContent;
		if ($totalResults < $numResults) {
			#throw some Exception
		}
		$xpath = new \DOMXPath($doc);
		$noteNodes = $xpath->query('//note[@xlink:href]');
		foreach ($noteNodes as $noteNode) {
			$findingAidId = substr($noteNode->getAttribute('xlink:href'), -8);
			$findingAidUrl = str_replace(
				array('{finding-aid-id}'),
				array($findingAidId),
				self::FINDING_AID_XML_URL_PATTERN
			);
			$findingAidDoc = new \DOMDocument();
			$findingAidXml = $this->fetchXml($findingAidUrl);
			if (!$findingAidXml) {
				return array('images' => $images, 'totalResults' => 0);
			}
			$findingAidDoc->loadXML($findingAidXml);
			$hollisId = $findingAidDoc->getElementsByTagName('eadid')->item(0)->getAttribute('identifier');
			$imageLinkNodes = $findingAidDoc->getElementsByTagName('dao');
			foreach ($imageLinkNodes as $imageLinkNode) {
				$unitId = $imageLinkNode->parentNode->parentNode->parentNode->getElementsByTagName('unitid')->item(0)->textContent;
				if (count($images) == $numResults) {
					break 2;
				}
				$imageLink = $imageLinkNode->getAttribute('xlink:href');

				$curl = curl_init($imageLink);
				curl_setopt($curl, CURLOPT_HEADER, true);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				$response = curl_exec($curl);
				if (strpos($response, 'HTTP/1.1 303 See Other') !== false) {
					$resourceLink = '';
					if (strpos($response, 'Location: http://ids.') !== false) {
						preg_match('!Location: http://ids\.lib\.harvard\.edu/ids/view/(.*)\?.*\\r\\n!', $response, $resourceLink);
						$imageId = $resourceLink[1];
						$images[] = new Entity\Image($this->getRepo(), $findingAidId, $hollisId, $imageId, $unitId);
					}
					if (strpos($response, 'Location: http://pds.') !== false) {
						//preg_match('!Location: http://pds\.lib\.harvard\.edu/pds/view/(.*)\?.*\\r\\n!', $response, $resourceLink);
						//$pdsIds[] = $resourceLink[1];
					}
				}
			}
		}

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
			'Title' => './/ns:unittitle',
			'Creator' => '//ns:origination[@label="creator"]',
			'Date' => './/ns:unitdate',
			'Notes' => './/ns:note'
		);
		$metadataId = $image->getId2();
		$unitId = $image->getId4();

		$metadataUrl = $this->fillUrl(self::METADATA_URL_PATTERN, $image);
		$response = $this->fetchXml($metadataUrl);
		if (!$response) {
			return array();
		}
		$doc = new \DOMDocument();
		$doc->loadXML($response);
		$xpath = new \DOMXPath($doc);
		$xpath->registerNamespace('ns', 'urn:isbn:1-931666-22-9');
		$recordContainer = $xpath->query('//ns:unitid[.="'.$unitId.'"]')->item(0);
		if ($recordContainer) {
			$recordContainer = $recordContainer->parentNode->parentNode;
			foreach ($fields as $name => $query) {
				$node = $xpath->query($query, $recordContainer)->item(0);
				if ($node) {
					$metadata[$name] = preg_replace('/\s+/', ' ', $node->textContent);
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

	/**
	 * Fetch the XML from a given url
	 *
	 * @param string $url
	 * @return string @xml
	 */
	private function fetchXml($url)
	{
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		#curl_setopt($curl, CURLOPT_HTTPHEADER, array("Accept: application/json"));
		return curl_exec($curl);
	}

	/**
	 * Fill in the placeholders in a given URL pattern
	 *
	 * @param string $urlPattern
	 * @param Berkman\SlideshowBundle\Entity\Image
	 * @return string $url
	 */
	private function fillUrl($urlPattern, Entity\Image $image)
	{
		return str_replace(
			array('{id-1}', '{id-2}', '{id-3}', '{id-4}', '{id-5}', '{id-6}'),
			array($image->getId1(), $image->getId2(), $image->getId3(), $image->getId4(), $image->getId5(), $image->getId6()),
			$urlPattern
		);
	}
}
